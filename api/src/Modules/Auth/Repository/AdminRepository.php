<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Core\Database\Connection;

final class AdminRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM admins WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
    }

    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        if ($tenantId !== null) {
            return $this->db->fetchOne(
                "SELECT * FROM admins WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL",
                [$email, $tenantId]
            );
        }

        // Find any admin by email (tenant or platform)
        return $this->db->fetchOne(
            "SELECT * FROM admins WHERE email = ? AND deleted_at IS NULL LIMIT 1",
            [$email]
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM admins WHERE uuid = ? AND deleted_at IS NULL",
            [$uuid]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO admins (uuid, tenant_id, name, email, password_hash, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'] ?? null,
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['role'] ?? 'staff',
                $data['status'] ?? 'active',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute("UPDATE admins SET last_login_at = NOW() WHERE id = ?", [$id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->execute("UPDATE admins SET password_hash = ? WHERE id = ?", [$passwordHash, $id]);
    }

    public function getPermissions(int $adminId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT permission FROM admin_permissions WHERE admin_id = ?",
            [$adminId]
        );

        return array_column($rows, 'permission');
    }

    public function setPermissions(int $adminId, array $permissions): void
    {
        $this->db->execute("DELETE FROM admin_permissions WHERE admin_id = ?", [$adminId]);

        foreach ($permissions as $permission) {
            $this->db->execute(
                "INSERT INTO admin_permissions (admin_id, permission) VALUES (?, ?)",
                [$adminId, $permission]
            );
        }
    }

    public function findByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, name, email, role, status, last_login_at, created_at
             FROM admins WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$tenantId]
        );
    }
}
