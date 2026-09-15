<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Repository;

use App\Core\Database\Connection;

final class TenantIntegrationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function find(int $tenantId, string $key): ?array
    {
        return $this->db->fetchOne('SELECT * FROM tenant_integrations WHERE tenant_id = ? AND integration_key = ?', [$tenantId, $key]);
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(int $tenantId): array
    {
        return $this->db->fetchAll('SELECT integration_key, encrypted_value, enabled, updated_at FROM tenant_integrations WHERE tenant_id = ?', [$tenantId]);
    }

    public function upsert(int $tenantId, string $key, ?string $encryptedValue, bool $enabled, int $adminId): void
    {
        $this->db->execute(
            'INSERT INTO tenant_integrations (tenant_id, integration_key, encrypted_value, enabled, updated_by_admin_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE encrypted_value = COALESCE(VALUES(encrypted_value), encrypted_value), enabled = VALUES(enabled), updated_by_admin_id = VALUES(updated_by_admin_id)',
            [$tenantId, $key, $encryptedValue, $enabled ? 1 : 0, $adminId],
        );
    }
}
