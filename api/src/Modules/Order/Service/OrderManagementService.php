<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Delivery\Service\DriverService;
use App\Modules\Notification\Service\NotificationService;
use Ramsey\Uuid\Uuid;

final class OrderManagementService
{
    /** Maximum minutes after order creation during which customer can cancel. */
    private const CANCEL_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly Connection $db,
        private readonly OrderRepository $orderRepo,
        private readonly ?DriverService $driverService = null,
        private readonly ?NotificationService $notificationService = null,
    ) {
    }

    /**
     * Admin transitions an order to a new status.
     */
    public function updateStatus(int $tenantId, int $orderId, string $newStatus, string $actorType, int $actorId, ?string $notes = null): array
    {
        $order = $this->orderRepo->findById($orderId, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        if (!OrderStatus::canTransition($order['status'], $newStatus)) {
            return [
                'error' => "Cannot transition from '{$order['status']}' to '{$newStatus}'.",
                'code' => 'INVALID_TRANSITION',
                'allowed' => OrderStatus::getAllowedTransitions($order['status']),
            ];
        }

        // Keep the two fulfilment journeys separate. A pickup customer must see
        // "ready for pickup", while delivery orders progress through a driver.
        if ($order['order_type'] === 'pickup' && in_array($newStatus, [OrderStatus::READY, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED], true)) {
            return ['error' => 'Pickup orders must use the pickup status flow.', 'code' => 'PICKUP_STATUS_MISMATCH'];
        }
        if ($order['order_type'] === 'delivery' && in_array($newStatus, [OrderStatus::READY_FOR_PICKUP, OrderStatus::PICKED_UP], true)) {
            return ['error' => 'Delivery orders must use the delivery status flow.', 'code' => 'DELIVERY_STATUS_MISMATCH'];
        }

        $timestampField = match ($newStatus) {
            OrderStatus::ACCEPTED => 'accepted_at',
            OrderStatus::PREPARING => 'preparing_at',
            OrderStatus::READY, OrderStatus::READY_FOR_PICKUP => 'ready_at',
            OrderStatus::OUT_FOR_DELIVERY => null,
            OrderStatus::DELIVERED => 'delivered_at',
            OrderStatus::PICKED_UP => 'picked_up_at',
            OrderStatus::CANCELLED => 'cancelled_at',
            default => null,
        };

        $this->orderRepo->updateStatus($orderId, $tenantId, $newStatus, $timestampField);
        $this->orderRepo->addStatusHistory($orderId, $order['status'], $newStatus, $actorType, $actorId, $notes);

        // Generate delivery OTP when order goes out for delivery
        if ($newStatus === OrderStatus::OUT_FOR_DELIVERY && $this->driverService !== null) {
            $this->driverService->generateDeliveryOtp($orderId);
        }

        if ($newStatus === OrderStatus::CANCELLED && !empty($notes)) {
            $this->db->execute("UPDATE orders SET cancel_reason = ? WHERE id = ?", [$notes, $orderId]);
        }

        // Marketplace commission is earned only after a merchant completes a
        // delivery or pickup. Branded merchants never receive a platform fee.
        if (in_array($newStatus, [OrderStatus::DELIVERED, OrderStatus::PICKED_UP], true)) {
            $tenant = $this->db->fetchOne('SELECT commercial_plan FROM tenants WHERE id = ?', [$tenantId]);
            if ($tenant !== null && ($tenant['commercial_plan'] ?? 'branded') === 'marketplace') {
                $fee = min((int) round((int) $order['total'] * 0.01), 500);
                $this->db->execute(
                    'INSERT IGNORE INTO platform_fee_ledger (uuid, tenant_id, order_id, gross_order_value, fee_amount)
                     VALUES (?, ?, ?, ?, ?)',
                    [Uuid::uuid4()->toString(), $tenantId, $orderId, (int) $order['total'], $fee],
                );
            }
        }

        // The order detail screen polls for this update, while the notification
        // lets the customer know even when they are not currently viewing it.
        $this->notifyCustomerOrderStatus($tenantId, $order, $newStatus);

        return [
            'order_id' => $orderId,
            'previous_status' => $order['status'],
            'new_status' => $newStatus,
        ];
    }

    /** @param array<string, mixed> $order */
    private function notifyCustomerOrderStatus(int $tenantId, array $order, string $status): void
    {
        if (empty($order['customer_id'])) {
            return;
        }

        $customerId = (int) $order['customer_id'];
        $orderNumber = (string) $order['order_number'];

        // In-app + push notification (NotificationService handles both)
        try {
            $this->notificationService?->notifyOrderStatus($tenantId, $customerId, $orderNumber, $status);
        } catch (\Throwable) {
        }
    }

    /**
     * Customer cancels their own order (only if pending or confirmed).
     */
    public function cancelOrder(int $tenantId, int $orderId, int $customerId, ?string $reason = null): array
    {
        $order = $this->orderRepo->findById($orderId, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        if ((int) $order['customer_id'] !== $customerId) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        $allowedStatuses = [OrderStatus::PENDING_PAYMENT, OrderStatus::CONFIRMED];
        if (!in_array($order['status'], $allowedStatuses, true)) {
            return [
                'error' => 'Order can only be cancelled when status is pending or confirmed.',
                'code' => 'CANCEL_NOT_ALLOWED',
            ];
        }

        // Enforce cancellation time window
        $createdAt = strtotime($order['created_at']);
        if ($createdAt !== false) {
            $minutesSinceOrder = (time() - $createdAt) / 60;
            if ($minutesSinceOrder > self::CANCEL_WINDOW_MINUTES) {
                return [
                    'error' => 'Cancellation window has expired. Orders can only be cancelled within ' . self::CANCEL_WINDOW_MINUTES . ' minutes of placement.',
                    'code' => 'CANCEL_WINDOW_EXPIRED',
                ];
            }
        }

        $newStatus = OrderStatus::CANCELLED;
        $this->orderRepo->updateStatus($orderId, $tenantId, $newStatus, 'cancelled_at');
        $this->orderRepo->addStatusHistory($orderId, $order['status'], $newStatus, 'customer', $customerId, $reason);

        if ($reason !== null && $reason !== '') {
            $this->db->execute("UPDATE orders SET cancel_reason = ? WHERE id = ?", [$reason, $orderId]);
        }

        return $this->orderRepo->findById($orderId, $tenantId) ?? ['error' => 'Order not found after cancellation.', 'code' => 'ORDER_NOT_FOUND'];
    }

    /**
     * Get dashboard stats for a tenant.
     */
    public function getDashboardStats(int $tenantId): array
    {
        $today = date('Y-m-d');

        $todayOrders = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue
             FROM orders WHERE tenant_id = ? AND DATE(created_at) = ?",
            [$tenantId, $today]
        );

        $statusCounts = $this->db->fetchAll(
            "SELECT status, COUNT(*) as cnt FROM orders
             WHERE tenant_id = ? AND status NOT IN ('delivered', 'picked_up', 'cancelled', 'rejected', 'refunded')
             GROUP BY status",
            [$tenantId]
        );

        $activeOrders = [];
        foreach ($statusCounts as $row) {
            $activeOrders[$row['status']] = (int) $row['cnt'];
        }

        $totalCustomers = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT customer_id) as cnt FROM orders WHERE tenant_id = ?",
            [$tenantId]
        );

        return [
            'today_orders' => (int) ($todayOrders['cnt'] ?? 0),
            'today_revenue' => (int) ($todayOrders['revenue'] ?? 0),
            'active_orders' => $activeOrders,
            'total_customers' => (int) ($totalCustomers['cnt'] ?? 0),
        ];
    }
}
