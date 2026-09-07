<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Repository;

use App\Core\Database\Connection;

final class DriverAssignmentRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO driver_assignments (order_id, driver_id, tenant_id, status)
             VALUES (?, ?, ?, ?)",
            [
                $data['order_id'], $data['driver_id'], $data['tenant_id'],
                $data['status'] ?? 'assigned',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function findByOrderId(int $orderId): ?array
    {
        return $this->db->fetchOne(
            "SELECT da.*, d.name as driver_name, d.phone as driver_phone,
                    d.vehicle_type, d.vehicle_number,
                    d.last_location_lat, d.last_location_lng
             FROM driver_assignments da
             JOIN drivers d ON d.id = da.driver_id
             WHERE da.order_id = ? AND da.status != 'cancelled'
             ORDER BY da.created_at DESC LIMIT 1",
            [$orderId]
        );
    }

    public function findActiveByDriver(int $driverId): array
    {
        return $this->db->fetchAll(
            "SELECT da.*, o.order_number, o.status as order_status, o.order_type,
                    o.total, o.address_snapshot
             FROM driver_assignments da
             JOIN orders o ON o.id = da.order_id
             WHERE da.driver_id = ? AND da.status IN ('assigned', 'accepted', 'picked_up')
             ORDER BY da.created_at DESC",
            [$driverId]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $timestampField = match ($status) {
            'accepted' => 'accepted_at',
            'picked_up' => 'picked_up_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };

        $sql = "UPDATE driver_assignments SET status = ?";
        $params = [$status];

        if ($timestampField !== null) {
            $sql .= ", {$timestampField} = NOW()";
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $this->db->execute($sql, $params);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM driver_assignments WHERE id = ?",
            [$id]
        );
    }
}
