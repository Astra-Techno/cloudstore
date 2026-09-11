<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Core\Database\Connection;

final class DriverRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM drivers WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
    }

    public function findByPhone(int $tenantId, string $phone): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM drivers WHERE tenant_id = ? AND phone = ? AND deleted_at IS NULL",
            [$tenantId, $phone]
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM drivers WHERE uuid = ? AND deleted_at IS NULL",
            [$uuid]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO drivers (uuid, tenant_id, name, phone, email, password_hash, vehicle_type, vehicle_number, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['name'],
                $data['phone'],
                $data['email'] ?? null,
                $data['password_hash'],
                $data['vehicle_type'] ?? null,
                $data['vehicle_number'] ?? null,
                $data['status'] ?? 'offline',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateAvailability(int $id, string $availability): void
    {
        $this->db->execute(
            "UPDATE drivers SET availability = ? WHERE id = ?",
            [$availability, $id]
        );
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute("UPDATE drivers SET last_login_at = NOW() WHERE id = ?", [$id]);
    }

    public function findByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, name, phone, email, vehicle_type, vehicle_number, status, availability, last_login_at, created_at
             FROM drivers WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$tenantId]
        );
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $this->db->execute(
            "UPDATE drivers SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
    }

    public function delete(int $id, int $tenantId): void
    {
        $this->db->execute(
            "UPDATE drivers SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function countByTenant(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM drivers WHERE tenant_id = ? AND deleted_at IS NULL",
            [$tenantId]
        );

        return (int) ($row['cnt'] ?? 0);
    }
}
