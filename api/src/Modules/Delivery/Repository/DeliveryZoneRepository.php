<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Repository;

use App\Core\Database\Connection;

final class DeliveryZoneRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM delivery_zones WHERE tenant_id = ? AND status = 'active' ORDER BY sort_order ASC",
            [$tenantId]
        );
    }

    public function findZoneForDistance(int $tenantId, float $distanceKm): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM delivery_zones
             WHERE tenant_id = ? AND status = 'active'
             AND min_distance_km <= ? AND max_distance_km >= ?
             ORDER BY sort_order ASC LIMIT 1",
            [$tenantId, $distanceKm, $distanceKm]
        );
    }

    public function findAllByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM delivery_zones WHERE tenant_id = ? ORDER BY sort_order ASC",
            [$tenantId]
        );
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM delivery_zones WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO delivery_zones (uuid, tenant_id, name, min_distance_km, max_distance_km, fee, min_order_free_delivery, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['tenant_id'], $data['name'],
                $data['min_distance_km'], $data['max_distance_km'],
                $data['fee'], $data['min_order_free_delivery'] ?? null,
                $data['status'] ?? 'active', $data['sort_order'] ?? 0,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'min_distance_km', 'max_distance_km', 'fee', 'min_order_free_delivery', 'status', 'sort_order'], true)) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return;
        }

        $values[] = $id;
        $values[] = $tenantId;

        $this->db->execute(
            "UPDATE delivery_zones SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $values
        );
    }

    public function delete(int $id, int $tenantId): bool
    {
        $affected = $this->db->execute(
            "DELETE FROM delivery_zones WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return $affected > 0;
    }
}
