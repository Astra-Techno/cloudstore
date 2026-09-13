<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repository;

use App\Core\Database\Connection;

final class AuditLogRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function log(
        int $tenantId,
        ?int $adminId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ip,
    ): void {
        $this->db->execute(
            "INSERT INTO audit_log (tenant_id, admin_id, action, entity_type, entity_id, old_values, new_values, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $adminId,
                $action,
                $entityType,
                $entityId,
                $oldValues !== null ? json_encode($oldValues) : null,
                $newValues !== null ? json_encode($newValues) : null,
                $ip,
            ]
        );
    }

    public function findByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT al.*, a.name as admin_name
             FROM audit_log al
             LEFT JOIN admins a ON a.id = al.admin_id
             WHERE al.tenant_id = ?
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        );
    }
}
