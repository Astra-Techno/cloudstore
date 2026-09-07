<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE idempotency_keys (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED DEFAULT NULL,
            idempotency_key VARCHAR(64) NOT NULL,
            request_hash VARCHAR(64) NOT NULL,
            response_body JSON NOT NULL,
            response_status INT UNSIGNED NOT NULL DEFAULT 200,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_idempotency (tenant_id, idempotency_key),
            INDEX idx_idempotency_expires (expires_at),
            CONSTRAINT fk_idempotency_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS idempotency_keys",
];
