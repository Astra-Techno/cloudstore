<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE app_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            token_prefix VARCHAR(10) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at TIMESTAMP NULL DEFAULT NULL,
            last_used_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_app_tokens_tenant (tenant_id),
            INDEX idx_app_tokens_prefix (token_prefix),
            INDEX idx_app_tokens_status (status),
            CONSTRAINT fk_app_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS app_tokens",
];
