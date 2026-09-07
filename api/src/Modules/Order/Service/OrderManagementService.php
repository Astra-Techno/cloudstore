<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;

final class OrderManagementService
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderRepository $orderRepo,
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

        if ($newStatus === OrderStatus::CANCELLED && !empty($notes)) {
            $this->db->execute("UPDATE orders SET cancel_reason = ? WHERE id = ?", [$notes, $orderId]);
        }

        return [
            'order_id' => $orderId,
            'previous_status' => $order['status'],
            'new_status' => $newStatus,
        ];
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
