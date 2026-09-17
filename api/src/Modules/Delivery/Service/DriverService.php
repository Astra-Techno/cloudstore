<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Service;

use App\Core\Database\Connection;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Notification\Service\NotificationService;

final class DriverService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DriverRepository $driverRepo,
        private readonly DriverAssignmentRepository $assignmentRepo,
        private readonly OrderRepository $orderRepo,
        private readonly ?NotificationService $notificationService = null,
    ) {
    }

    /**
     * Assign a driver to an order (admin action).
     */
    public function assignDriver(int $tenantId, int $orderId, int $driverId): array
    {
        $order = $this->orderRepo->findById($orderId, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        if ($order['order_type'] !== 'delivery') {
            return ['error' => 'Order is not a delivery order.', 'code' => 'NOT_DELIVERY'];
        }

        if (!in_array($order['status'], [OrderStatus::CONFIRMED, OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
            return ['error' => 'A driver can only be assigned to an active delivery order.', 'code' => 'ORDER_NOT_READY_FOR_ASSIGNMENT'];
        }

        $existingAssignment = $this->assignmentRepo->findByOrderId($orderId);
        if ($existingAssignment !== null) {
            return ['error' => 'This order already has an active driver assignment.', 'code' => 'DRIVER_ALREADY_ASSIGNED'];
        }

        $driver = $this->driverRepo->findById($driverId);
        if ($driver === null || (int) $driver['tenant_id'] !== $tenantId) {
            return ['error' => 'Driver not found.', 'code' => 'DRIVER_NOT_FOUND'];
        }
        if ($driver['availability'] === 'offline') {
            return ['error' => 'This driver is offline.', 'code' => 'DRIVER_OFFLINE'];
        }

        $assignmentId = $this->assignmentRepo->create([
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'tenant_id' => $tenantId,
        ]);

        return [
            'assignment_id' => $assignmentId,
            'driver' => [
                'id' => $driver['uuid'],
                'name' => $driver['name'],
                'phone' => $driver['phone'],
                'vehicle_type' => $driver['vehicle_type'],
                'vehicle_number' => $driver['vehicle_number'],
            ],
        ];
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
            'picked_up' => ['delivered'],
        ];

        $allowed = $validTransitions[$assignment['status']] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return ['error' => 'Invalid status transition.', 'code' => 'INVALID_TRANSITION'];
        }

        $result = $this->db->transaction(function () use ($assignment, $assignmentId, $newStatus) {
            $orderId = (int) $assignment['order_id'];
            $tenantId = (int) $assignment['tenant_id'];
            $order = $this->orderRepo->findById($orderId, $tenantId);

            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }

            if ($newStatus === 'picked_up' && !in_array($order['status'], [OrderStatus::READY, OrderStatus::PREPARING, OrderStatus::ACCEPTED, OrderStatus::CONFIRMED], true)) {
                return ['error' => 'The order must be accepted or prepared before pickup.', 'code' => 'ORDER_NOT_READY'];
            }

            if ($newStatus === 'picked_up' && $assignment['status'] === 'assigned') {
                $this->assignmentRepo->updateStatus($assignmentId, 'accepted');
            }

            $this->assignmentRepo->updateStatus($assignmentId, $newStatus);

            // Generate delivery OTP when driver picks up the order
            if ($newStatus === 'picked_up') {
                $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                $this->assignmentRepo->setDeliveryOtp($assignmentId, $otp);
            }

            // Map driver assignment status to order status
            $orderStatusMap = [
                'accepted' => OrderStatus::ACCEPTED,
                'picked_up' => OrderStatus::OUT_FOR_DELIVERY,
                'delivered' => OrderStatus::DELIVERED,
                'cancelled' => null,
            ];

            $newOrderStatus = $orderStatusMap[$newStatus] ?? null;

            if ($newStatus === 'picked_up' && !OrderStatus::canTransition($order['status'], $newOrderStatus)) {
                $this->advanceDeliveryOrderToOutForDelivery($orderId, $tenantId, $order, (int) $assignment['driver_id']);
            } elseif ($newOrderStatus !== null && OrderStatus::canTransition($order['status'], $newOrderStatus)) {
                $timestampField = match ($newOrderStatus) {
                    OrderStatus::OUT_FOR_DELIVERY => 'picked_up_at',
                    OrderStatus::DELIVERED => 'delivered_at',
                    default => null,
                };

                $this->orderRepo->updateStatus($orderId, $tenantId, $newOrderStatus, $timestampField);
                $this->orderRepo->addStatusHistory(
                    $orderId, $order['status'], $newOrderStatus,
                    'driver', (int) $assignment['driver_id']
                );
            }

            return ['status' => $newStatus, 'order_id' => $orderId];
        });

        if (!isset($result['error']) && in_array($newStatus, ['picked_up', 'delivered'], true)) {
            $order = $this->orderRepo->findById((int) $result['order_id'], (int) $assignment['tenant_id']);
            if ($order !== null) {
                $this->notifyCustomerOrderStatus((int) $assignment['tenant_id'], $order, $newStatus === 'picked_up'
                    ? OrderStatus::OUT_FOR_DELIVERY
                    : OrderStatus::DELIVERED);
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
        $assignment = $this->assignmentRepo->findById($assignmentId);
        if ($assignment === null || (int) $assignment['driver_id'] !== $driverId) {
            return ['error' => 'Assignment not found.', 'code' => 'ASSIGNMENT_NOT_FOUND'];
        }

        if ($assignment['status'] !== 'picked_up') {
            return ['error' => 'Delivery can only be verified after pickup.', 'code' => 'INVALID_STATUS'];
        }

        if ($assignment['delivery_otp'] === null) {
            return ['error' => 'No delivery OTP set for this assignment.', 'code' => 'NO_OTP'];
        }

        if ($assignment['delivery_otp'] !== $otp) {
            return ['error' => 'Invalid OTP.', 'code' => 'INVALID_OTP'];
        }

        $result = $this->db->transaction(function () use ($assignment, $assignmentId) {
            $orderId = (int) $assignment['order_id'];
            $tenantId = (int) $assignment['tenant_id'];
            $order = $this->orderRepo->findById($orderId, $tenantId);

            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }

            // Calculate earnings from delivery fee
            $earnings = (int) ($order['delivery_fee'] ?? 0);

            $this->assignmentRepo->markDelivered($assignmentId, $earnings);

            // Update order status to delivered
            if ($order !== null && OrderStatus::canTransition($order['status'], OrderStatus::DELIVERED)) {
                $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::DELIVERED, 'delivered_at');
                $this->orderRepo->addStatusHistory(
                    $orderId, $order['status'], OrderStatus::DELIVERED,
                    'driver', (int) $assignment['driver_id']
                );
            }

            return [
                'status' => 'delivered',
                'earnings' => $earnings,
                'order_id' => $orderId,
            ];
        });

        if (!isset($result['error'])) {
            $order = $this->orderRepo->findById((int) $result['order_id'], (int) $assignment['tenant_id']);
            if ($order !== null) {
                $this->notifyCustomerOrderStatus((int) $assignment['tenant_id'], $order, OrderStatus::DELIVERED);
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
             WHERE tenant_id = ? AND availability = 'available' AND deleted_at IS NULL
             ORDER BY last_location_at DESC",
            [$tenantId]
        );
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
