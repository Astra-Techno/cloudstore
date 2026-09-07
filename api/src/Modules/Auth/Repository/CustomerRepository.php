<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Core\Database\Connection;

final class CustomerRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
    }

    public function findByPhone(int $tenantId, string $phone): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE tenant_id = ? AND phone = ? AND deleted_at IS NULL",
            [$tenantId, $phone]
        );
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE tenant_id = ? AND email = ? AND deleted_at IS NULL",
            [$tenantId, $email]
        );
    }

    public function findByUuid(string $uuid, ?int $tenantId = null): ?array
    {
        if ($tenantId !== null) {
            return $this->db->fetchOne(
                "SELECT * FROM customers WHERE uuid = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1",
                [$uuid, $tenantId]
            );
        }

        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE uuid = ? AND deleted_at IS NULL LIMIT 1",
            [$uuid]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO customers (uuid, tenant_id, name, phone, email, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['name'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['password_hash'] ?? null,
                $data['status'] ?? 'active',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute("UPDATE customers SET last_login_at = NOW() WHERE id = ?", [$id]);
    }

    public function findByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, name, phone, email, status, last_login_at, created_at
             FROM customers WHERE tenant_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        );
    }

    public function findAllByTenant(int $tenantId, int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $sql = "SELECT id, uuid, name, phone, email, status, last_login_at, created_at
                FROM customers WHERE tenant_id = ? AND deleted_at IS NULL";
        $params = [$tenantId];

        if ($search !== null && $search !== '') {
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function countByTenant(int $tenantId, ?string $search = null): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM customers WHERE tenant_id = ? AND deleted_at IS NULL";
        $params = [$tenantId];

        if ($search !== null && $search !== '') {
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $row = $this->db->fetchOne($sql, $params);

        return (int) ($row['cnt'] ?? 0);
    }
}
