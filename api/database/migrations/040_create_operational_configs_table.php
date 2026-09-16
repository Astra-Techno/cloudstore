<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS operational_configs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 is the platform-wide default',
            config_key VARCHAR(100) NOT NULL,
            encrypted_value TEXT NULL,
            is_secret TINYINT(1) NOT NULL DEFAULT 1,
            updated_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_operational_config_scope (tenant_id, config_key),
            KEY idx_operational_config_key (config_key),
            CONSTRAINT fk_operational_configs_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => ['DROP TABLE IF EXISTS operational_configs'],
];
