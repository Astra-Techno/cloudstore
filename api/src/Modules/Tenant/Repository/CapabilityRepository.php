<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Repository;

use App\Core\Database\Connection;

final class CapabilityRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function getForTenant(int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT capability, enabled FROM tenant_capabilities WHERE tenant_id = ?",
            [$tenantId]
        );

        $capabilities = [];
        foreach ($rows as $row) {
            $capabilities[$row['capability']] = (bool) $row['enabled'];
        }

        return $capabilities;
    }

    public function set(int $tenantId, string $capability, bool $enabled): void
    {
        $this->db->execute(
            "INSERT INTO tenant_capabilities (tenant_id, capability, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)",
            [$tenantId, $capability, $enabled ? 1 : 0]
        );
    }

    public function setMany(int $tenantId, array $capabilities): void
    {
        foreach ($capabilities as $capability => $enabled) {
            $this->set($tenantId, $capability, $enabled);
        }
    }
}
