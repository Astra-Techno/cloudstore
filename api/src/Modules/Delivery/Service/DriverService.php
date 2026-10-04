<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Service;

use App\Core\Database\Connection;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Order\Service\MarketplaceFeeAccrualService;
use App\Modules\Notification\Service\NotificationService;

final class DriverService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DriverRepository $driverRepo,
        private readonly DriverAssignmentRepository $assignmentRepo,
        private readonly OrderRepository $orderRepo,
        private readonly ?NotificationService $notificationService = null,
        private readonly ?MarketplaceFeeAccrualService $marketplaceFeeAccrual = null,
    ) {
    }

    /**
     * Assign a driver to an order (admin action).
     */
    public function assignDriver(int $tenantId, int $orderId, int $driverId): array
    {
        $result = $this->db->transaction(function () use ($tenantId, $orderId, $driverId) {
            // Lock both resources to prevent two admins assigning the same
            // order or driver concurrently.
            $order = $this->db->fetchOne(
                'SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE',
                [$orderId, $tenantId],
            );
            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }
            if ($order['order_type'] !== 'delivery') {
                return ['error' => 'Order is not a delivery order.', 'code' => 'NOT_DELIVERY'];
            }
            if (!in_array($order['status'], [OrderStatus::CONFIRMED, OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
                return ['error' => 'A driver can only be assigned to an active delivery order.', 'code' => 'ORDER_NOT_READY_FOR_ASSIGNMENT'];
            }
            if ($this->assignmentRepo->findByOrderId($orderId) !== null) {
                return ['error' => 'This order already has an active driver assignment.', 'code' => 'DRIVER_ALREADY_ASSIGNED'];
            }

            $driver = $this->db->fetchOne(
                'SELECT * FROM drivers WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
                [$driverId],
            );
            if ($driver === null || (int) $driver['tenant_id'] !== $tenantId) {
                return ['error' => 'Driver not found.', 'code' => 'DRIVER_NOT_FOUND'];
            }
            if (($driver['status'] ?? 'active') !== 'active') {
                return ['error' => 'This driver account is not active.', 'code' => 'DRIVER_INACTIVE'];
            }
            if (($driver['availability'] ?? 'offline') !== 'available') {
                return ['error' => 'This driver is not available.', 'code' => 'DRIVER_UNAVAILABLE'];
            }

            $activeAssignment = $this->db->fetchOne(
                "SELECT id FROM driver_assignments
                 WHERE driver_id = ? AND status IN ('assigned', 'accepted', 'picked_up')
                 LIMIT 1",
                [$driverId],
            );
            if ($activeAssignment !== null) {
                return ['error' => 'This driver already has an active delivery.', 'code' => 'DRIVER_BUSY'];
            }

            $assignmentId = $this->assignmentRepo->create([
                'order_id' => $orderId,
                'driver_id' => $driverId,
                'tenant_id' => $tenantId,
            ]);

            return [
                'assignment_id' => $assignmentId,
                'order_number' => (string) $order['order_number'],
                'driver' => [
                    'id' => $driver['uuid'],
                    'name' => $driver['name'],
                    'phone' => $driver['phone'],
                    'vehicle_type' => $driver['vehicle_type'],
                    'vehicle_number' => $driver['vehicle_number'],
                ],
            ];
        });

        // External notification delivery must happen after the transaction has
        // committed so a temporary FCM failure can never roll back assignment.
        if (!isset($result['error']) && $this->notificationService !== null) {
            try {
                $this->notificationService->notifyDriverAssignment(
                    $tenantId,
                    $driverId,
                    (string) $result['order_number'],
                );
            } catch (\Throwable) {
                // Assignment is already committed. Notification storage or
                // FCM outages must not make the admin retry the assignment.
            }
            unset($result['order_number']);
        }

        return $result;
    }

    /**
     * Driver updates their location.
     */
    public function updateLocation(int $driverId, float $lat, float $lng): void
    {
        $this->db->execute(
            "UPDATE drivers SET last_location_lat = ?, last_location_lng = ?, last_location_at = NOW() WHERE id = ?",
            [$lat, $lng, $driverId]
        );
    }

    /**
     * Driver updates assignment status (accept, pickup, deliver).
     */
    public function updateAssignmentStatus(int $driverId, int $assignmentId, string $newStatus): array
    {
        $assignment = $this->assignmentRepo->findById($assignmentId);
        if ($assignment === null || (int) $assignment['driver_id'] !== $driverId) {
            return ['error' => 'Assignment not found.', 'code' => 'ASSIGNMENT_NOT_FOUND'];
        }

        $validTransitions = [
            'assigned' => ['accepted', 'picked_up', 'cancelled'],
            'accepted' => ['picked_up', 'cancelled'],
            // Completion is intentionally excluded: delivery requires the
            // customer's OTP through verifyDeliveryOtp().
            'picked_up' => [],
        ];

        $allowed = $validTransitions[$assignment['status']] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return ['error' => 'Invalid status transition.', 'code' => 'INVALID_TRANSITION'];
        }

        $result = $this->db->transaction(function () use ($driverId, $assignmentId, $newStatus) {
            $lockedAssignment = $this->db->fetchOne(
                'SELECT * FROM driver_assignments WHERE id = ? FOR UPDATE',
                [$assignmentId],
            );
            if ($lockedAssignment === null || (int) $lockedAssignment['driver_id'] !== $driverId) {
                return ['error' => 'Assignment not found.', 'code' => 'ASSIGNMENT_NOT_FOUND'];
            }

            $lockedTransitions = [
                'assigned' => ['accepted', 'picked_up', 'cancelled'],
                'accepted' => ['picked_up', 'cancelled'],
                'picked_up' => [],
            ];
            if (!in_array($newStatus, $lockedTransitions[$lockedAssignment['status']] ?? [], true)) {
                return ['error' => 'Assignment was already updated. Refresh and try again.', 'code' => 'INVALID_TRANSITION'];
            }

            $orderId = (int) $lockedAssignment['order_id'];
            $tenantId = (int) $lockedAssignment['tenant_id'];
            $order = $this->db->fetchOne(
                'SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE',
                [$orderId, $tenantId],
            );

            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }

            if ($newStatus === 'picked_up' && !in_array($order['status'], [OrderStatus::READY, OrderStatus::PREPARING, OrderStatus::ACCEPTED, OrderStatus::CONFIRMED], true)) {
                return ['error' => 'The order must be accepted or prepared before pickup.', 'code' => 'ORDER_NOT_READY'];
            }

            if ($newStatus === 'picked_up' && $lockedAssignment['status'] === 'assigned') {
                $this->assignmentRepo->updateStatus($assignmentId, 'accepted');
            }

            $this->assignmentRepo->updateStatus($assignmentId, $newStatus);

            // Generate delivery OTP when driver picks up the order
            if ($newStatus === 'picked_up') {
                $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                $this->assignmentRepo->setDeliveryOtp($assignmentId, $otp);
            }

            // Map driver assignment status to order status.
            // When driver marks "picked_up", the target order status depends
            // on the current order status: READY_FOR_PICKUP → PICKED_UP,
            // otherwise advance through to OUT_FOR_DELIVERY.
            $newOrderStatus = match ($newStatus) {
                'accepted' => OrderStatus::ACCEPTED,
                'picked_up' => $order['status'] === OrderStatus::READY_FOR_PICKUP
                    ? OrderStatus::PICKED_UP
                    : OrderStatus::OUT_FOR_DELIVERY,
                default => null,
            };

            if ($newStatus === 'picked_up' && $newOrderStatus === OrderStatus::OUT_FOR_DELIVERY
                && !OrderStatus::canTransition($order['status'], $newOrderStatus)) {
                $this->advanceDeliveryOrderToOutForDelivery($orderId, $tenantId, $order, (int) $lockedAssignment['driver_id']);
            } elseif ($newOrderStatus !== null && OrderStatus::canTransition($order['status'], $newOrderStatus)) {
                $timestampField = match ($newOrderStatus) {
                    OrderStatus::OUT_FOR_DELIVERY => 'picked_up_at',
                    default => null,
                };

                $this->orderRepo->updateStatus($orderId, $tenantId, $newOrderStatus, $timestampField);
                $this->orderRepo->addStatusHistory(
                    $orderId, $order['status'], $newOrderStatus,
                    'driver', (int) $lockedAssignment['driver_id']
                );
            }

            return ['status' => $newStatus, 'order_id' => $orderId, 'tenant_id' => $tenantId];
        });

        if (!isset($result['error']) && $newStatus === 'picked_up') {
            $order = $this->orderRepo->findById((int) $result['order_id'], (int) $result['tenant_id']);
            if ($order !== null) {
                $this->notifyCustomerOrderStatus((int) $result['tenant_id'], $order, OrderStatus::OUT_FOR_DELIVERY);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $order */
    private function advanceDeliveryOrderToOutForDelivery(int $orderId, int $tenantId, array $order, int $driverId): void
    {
        $current = (string) $order['status'];
        $steps = match ($current) {
            OrderStatus::CONFIRMED => [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::OUT_FOR_DELIVERY],
            OrderStatus::ACCEPTED => [OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::OUT_FOR_DELIVERY],
            OrderStatus::PREPARING => [OrderStatus::READY, OrderStatus::OUT_FOR_DELIVERY],
            OrderStatus::READY => [OrderStatus::OUT_FOR_DELIVERY],
            default => [],
        };

        foreach ($steps as $next) {
            if (!OrderStatus::canTransition($current, $next)) {
                return;
            }

            $timestampField = match ($next) {
                OrderStatus::PREPARING => 'preparing_at',
                OrderStatus::READY => 'ready_at',
                OrderStatus::OUT_FOR_DELIVERY => 'picked_up_at',
                default => null,
            };

            $this->orderRepo->updateStatus($orderId, $tenantId, $next, $timestampField);
            $this->orderRepo->addStatusHistory($orderId, $current, $next, 'driver', $driverId);
            $current = $next;
        }
    }

    /**
     * Toggle driver availability.
     */
    public function setAvailability(int $driverId, string $availability): void
    {
        $this->driverRepo->updateAvailability($driverId, $availability);
    }

    /**
     * Generate a 4-digit delivery OTP for an assignment.
     * Called when order transitions to 'out_for_delivery'.
     */
    public function generateDeliveryOtp(int $orderId): void
    {
        $assignment = $this->assignmentRepo->findByOrderId($orderId);
        if ($assignment === null) {
            return;
        }

        $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $this->assignmentRepo->setDeliveryOtp((int) $assignment['id'], $otp);
    }

    /**
     * Verify delivery OTP and mark assignment as delivered.
     */
    public function verifyDeliveryOtp(int $driverId, int $assignmentId, string $otp): array
    {
        $result = $this->db->transaction(function () use ($driverId, $assignmentId, $otp) {
            // Lock the assignment so two concurrent OTP submissions cannot
            // complete the same delivery twice.
            $assignment = $this->db->fetchOne(
                'SELECT * FROM driver_assignments WHERE id = ? FOR UPDATE',
                [$assignmentId],
            );
            if ($assignment === null || (int) $assignment['driver_id'] !== $driverId) {
                return ['error' => 'Assignment not found.', 'code' => 'ASSIGNMENT_NOT_FOUND'];
            }
            if ($assignment['status'] !== 'picked_up') {
                return ['error' => 'Delivery can only be verified after pickup.', 'code' => 'INVALID_STATUS'];
            }
            if ($assignment['delivery_otp'] === null) {
                return ['error' => 'No delivery OTP set for this assignment.', 'code' => 'NO_OTP'];
            }
            if (!hash_equals((string) $assignment['delivery_otp'], $otp)) {
                return ['error' => 'Invalid OTP.', 'code' => 'INVALID_OTP'];
            }

            $orderId = (int) $assignment['order_id'];
            $tenantId = (int) $assignment['tenant_id'];
            $order = $this->orderRepo->findById($orderId, $tenantId);

            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }

            if (!OrderStatus::canTransition((string) $order['status'], OrderStatus::DELIVERED)) {
                return ['error' => 'Order is not ready to be completed.', 'code' => 'INVALID_ORDER_STATUS'];
            }

            // Calculate earnings from delivery fee
            $earnings = (int) ($order['delivery_fee'] ?? 0);

            $this->assignmentRepo->markDelivered($assignmentId, $earnings);

            // Update order status to delivered
            $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::DELIVERED, 'delivered_at');
            $this->orderRepo->addStatusHistory(
                $orderId, $order['status'], OrderStatus::DELIVERED,
                'driver', (int) $assignment['driver_id']
            );

            $this->marketplaceFeeAccrual?->accrue($tenantId, $orderId, (int) $order['total']);

            return [
                'status' => 'delivered',
                'earnings' => $earnings,
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
            ];
        });

        if (!isset($result['error'])) {
            $order = $this->orderRepo->findById((int) $result['order_id'], (int) $result['tenant_id']);
            if ($order !== null) {
                $this->notifyCustomerOrderStatus((int) $result['tenant_id'], $order, OrderStatus::DELIVERED);
            }
        }

        return $result;
    }

    /**
     * Get driver earnings summary.
     */
    public function getEarnings(int $driverId): array
    {
        return $this->assignmentRepo->getEarnings($driverId);
    }

    /**
     * Get available drivers for a tenant.
     */
    public function getAvailableDrivers(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, name, phone, vehicle_type, vehicle_number,
                    last_location_lat, last_location_lng, last_location_at
             FROM drivers
             WHERE tenant_id = ?
               AND status = 'active'
               AND availability = 'available'
               AND deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM driver_assignments da
                   WHERE da.driver_id = drivers.id
                     AND da.status IN ('assigned', 'accepted', 'picked_up')
               )
             ORDER BY last_location_at DESC",
            [$tenantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function getAssignmentForAdmin(int $orderId): ?array
    {
        $assignment = $this->assignmentRepo->findByOrderId($orderId);
        if ($assignment === null) {
            return null;
        }

        return [
            'status' => $assignment['status'],
            'driver_name' => $assignment['driver_name'] ?? null,
            'driver_phone' => $assignment['driver_phone'] ?? null,
            'vehicle_type' => $assignment['vehicle_type'] ?? null,
            'vehicle_number' => $assignment['vehicle_number'] ?? null,
            'assigned_at' => $assignment['assigned_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $order */
    private function notifyCustomerOrderStatus(int $tenantId, array $order, string $status): void
    {
        if ($this->notificationService === null || empty($order['customer_id'])) {
            return;
        }

        try {
            $this->notificationService->notifyOrderStatus(
                $tenantId,
                (int) $order['customer_id'],
                (string) $order['order_number'],
                $status,
            );
        } catch (\Throwable) {
            // Delivery state must not be rolled back because notification storage failed.
        }
    }
}
