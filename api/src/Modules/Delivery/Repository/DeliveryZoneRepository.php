<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Repository;

use App\Core\Database\Connection;

final class DeliveryZoneRepository
{
    /**
     * Some already-live stores were created before migration 037 added pincode
     * zones. Keep distance delivery working while a deployment catches their
     * schema up; this avoids turning a missing optional feature into a 500.
     */
    private ?bool $supportsPincodeZones = null;

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
        if (!$this->hasPincodeZoneColumns()) {
            return $this->db->fetchOne(
                "SELECT * FROM delivery_zones
                 WHERE tenant_id = ? AND status = 'active'
                 AND min_distance_km <= ? AND max_distance_km >= ?
                 ORDER BY sort_order ASC LIMIT 1",
                [$tenantId, $distanceKm, $distanceKm]
            );
        }

        return $this->db->fetchOne(
            "SELECT * FROM delivery_zones
             WHERE tenant_id = ? AND status = 'active'
             AND (zone_type = 'distance' OR zone_type IS NULL)
             AND min_distance_km <= ? AND max_distance_km >= ?
             ORDER BY sort_order ASC LIMIT 1",
            [$tenantId, $distanceKm, $distanceKm]
        );
    }

    public function findZoneForPincode(int $tenantId, string $pincode): ?array
    {
        if (!$this->hasPincodeZoneColumns()) {
            return null;
        }

        $zones = $this->db->fetchAll(
            "SELECT * FROM delivery_zones
             WHERE tenant_id = ? AND status = 'active'
             AND zone_type = 'pincode' AND pincodes IS NOT NULL
             ORDER BY sort_order ASC",
            [$tenantId]
        );

        foreach ($zones as $zone) {
            $pincodes = json_decode($zone['pincodes'] ?? '[]', true);
            if (is_array($pincodes) && in_array($pincode, $pincodes, true)) {
                return $zone;
            }
        }

        return null;
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
        $pincodes = isset($data['pincodes']) ? json_encode($data['pincodes']) : null;

        if (!$this->hasPincodeZoneColumns()) {
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

        $this->db->execute(
            "INSERT INTO delivery_zones (uuid, tenant_id, name, zone_type, min_distance_km, max_distance_km, pincodes, fee, min_order_free_delivery, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['tenant_id'], $data['name'],
                $data['zone_type'] ?? 'distance',
                $data['min_distance_km'], $data['max_distance_km'],
                $pincodes,
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
        $supportsPincodeZones = $this->hasPincodeZoneColumns();

        // Encode pincodes array to JSON before setting
        if ($supportsPincodeZones && array_key_exists('pincodes', $data)) {
            $fields[] = "pincodes = ?";
            $values[] = $data['pincodes'] !== null ? json_encode($data['pincodes']) : null;
            unset($data['pincodes']);
        }

        foreach ($data as $key => $value) {
            $allowed = ['name', 'min_distance_km', 'max_distance_km', 'fee', 'min_order_free_delivery', 'status', 'sort_order'];
            if ($supportsPincodeZones) {
                $allowed[] = 'zone_type';
            }

            if (in_array($key, $allowed, true)) {
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

    private function hasPincodeZoneColumns(): bool
    {
        if ($this->supportsPincodeZones !== null) {
            return $this->supportsPincodeZones;
        }

        try {
            $this->supportsPincodeZones = $this->db->fetchOne(
                "SHOW COLUMNS FROM delivery_zones LIKE 'zone_type'"
            ) !== null;
        } catch (\Throwable) {
            // A normal distance-zone schema is enough for checkout. Do not
            // leak a database schema error into the customer application.
            $this->supportsPincodeZones = false;
        }

        return $this->supportsPincodeZones;
    }
}
