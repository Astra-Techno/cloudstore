<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Service;

use App\Core\Database\Connection;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;

final class DriverService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DriverRepository $driverRepo,
        private readonly DriverAssignmentRepository $assignmentRepo,
        private readonly OrderRepository $orderRepo,
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

        if (!in_array($order['status'], [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
            return ['error' => 'A driver can only be assigned after the order is accepted.', 'code' => 'ORDER_NOT_READY_FOR_ASSIGNMENT'];
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
            'assigned' => ['accepted', 'cancelled'],
            'accepted' => ['picked_up', 'cancelled'],
            'picked_up' => ['delivered'],
        ];

        $allowed = $validTransitions[$assignment['status']] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return ['error' => 'Invalid status transition.', 'code' => 'INVALID_TRANSITION'];
        }

        return $this->db->transaction(function () use ($assignment, $assignmentId, $newStatus) {
            $orderId = (int) $assignment['order_id'];
            $tenantId = (int) $assignment['tenant_id'];
            $order = $this->orderRepo->findById($orderId, $tenantId);

            if ($newStatus === 'picked_up' && $order['status'] !== OrderStatus::READY) {
                return ['error' => 'The order is not ready for pickup.', 'code' => 'ORDER_NOT_READY'];
            }

            $this->assignmentRepo->updateStatus($assignmentId, $newStatus);

            // Map driver assignment status to order status
            $orderStatusMap = [
                'accepted' => null, // order stays as-is when driver accepts
                'picked_up' => OrderStatus::OUT_FOR_DELIVERY,
                'delivered' => OrderStatus::DELIVERED,
                'cancelled' => null,
            ];

            $newOrderStatus = $orderStatusMap[$newStatus] ?? null;

            if ($newOrderStatus !== null && OrderStatus::canTransition($order['status'], $newOrderStatus)) {
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
    }

    /**
     * Toggle driver availability.
     */
    public function setAvailability(int $driverId, string $availability): void
    {
        $this->driverRepo->updateAvailability($driverId, $availability);
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
}
