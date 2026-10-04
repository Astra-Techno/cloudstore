<?php
declare(strict_types=1);

/**
 * Sync tenant contact_email with the tenant_owner admin's email
 * so the platform UI shows the correct login email everywhere.
 */
return [
    'up' => function (PDO $pdo): void {
        $pdo->exec(
            "UPDATE tenants t
             JOIN admins a ON a.tenant_id = t.id AND a.role = 'tenant_owner' AND a.deleted_at IS NULL
             SET t.contact_email = a.email
             WHERE t.deleted_at IS NULL"
        );
    },
    'down' => [],
];
