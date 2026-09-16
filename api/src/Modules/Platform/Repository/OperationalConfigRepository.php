<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repository;

use App\Core\Database\Connection;

final class OperationalConfigRepository
{
    public function __construct(private readonly Connection $db) {}

    public function find(string $key, int $tenantId = 0): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM operational_configs WHERE tenant_id = ? AND config_key = ?',
            [$tenantId, $key],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function findForScope(int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT config_key, encrypted_value, is_secret, updated_at FROM operational_configs WHERE tenant_id = ? ORDER BY config_key',
            [$tenantId],
        );
    }

    public function upsert(string $key, string $encryptedValue, bool $isSecret, int $adminId, int $tenantId = 0): void
    {
        $this->db->execute(
            'INSERT INTO operational_configs (tenant_id, config_key, encrypted_value, is_secret, updated_by_admin_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE encrypted_value = VALUES(encrypted_value), is_secret = VALUES(is_secret), updated_by_admin_id = VALUES(updated_by_admin_id)',
            [$tenantId, $key, $encryptedValue, $isSecret ? 1 : 0, $adminId],
        );
    }
}
