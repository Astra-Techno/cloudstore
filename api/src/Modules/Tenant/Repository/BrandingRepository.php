<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Repository;

use App\Core\Database\Connection;

final class BrandingRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByTenant(int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM tenant_branding WHERE tenant_id = ?",
            [$tenantId]
        );
    }

    public function upsert(int $tenantId, array $data): void
    {
        $existing = $this->findByTenant($tenantId);

        if ($existing) {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }

            $values[] = $tenantId;

            $this->db->execute(
                "UPDATE tenant_branding SET " . implode(', ', $fields) . " WHERE tenant_id = ?",
                $values
            );
        } else {
            $data['tenant_id'] = $tenantId;
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));

            $this->db->execute(
                "INSERT INTO tenant_branding ({$columns}) VALUES ({$placeholders})",
                array_values($data)
            );
        }
    }
}
