<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            admin_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tenant_action (tenant_id, action),
            KEY idx_entity (tenant_id, entity_type, entity_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        "DROP TABLE IF EXISTS audit_log",
    ],
];
