<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repository;

use App\Core\Database\Connection;

final class AddressRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id, int $customerId, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM addresses WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            [$id, $customerId, $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $customerId, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM addresses WHERE uuid = ? AND customer_id = ? AND tenant_id = ?",
            [$uuid, $customerId, $tenantId]
        );
    }

    public function findByCustomer(int $customerId, int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM addresses WHERE customer_id = ? AND tenant_id = ? ORDER BY is_default DESC, created_at DESC",
            [$customerId, $tenantId]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone,
             address_line_1, address_line_2, landmark, city, state, postal_code,
             latitude, longitude, delivery_instructions, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['customer_id'], $data['tenant_id'],
                $data['label'] ?? null, $data['recipient_name'] ?? null, $data['phone'] ?? null,
                $data['address_line_1'], $data['address_line_2'] ?? null,
                $data['landmark'] ?? null, $data['city'] ?? null, $data['state'] ?? null,
                $data['postal_code'] ?? null, $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['delivery_instructions'] ?? null, $data['is_default'] ?? 0,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $customerId, int $tenantId, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;
        $values[] = $customerId;
        $values[] = $tenantId;

        $this->db->execute(
            "UPDATE addresses SET " . implode(', ', $fields) . " WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            $values
        );
    }

    public function delete(int $id, int $customerId, int $tenantId): void
    {
        $this->db->execute(
            "DELETE FROM addresses WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            [$id, $customerId, $tenantId]
        );
    }

    public function clearDefault(int $customerId, int $tenantId): void
    {
        $this->db->execute(
            "UPDATE addresses SET is_default = 0 WHERE customer_id = ? AND tenant_id = ?",
            [$customerId, $tenantId]
        );
    }
}
