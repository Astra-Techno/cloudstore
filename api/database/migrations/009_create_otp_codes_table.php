<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE otp_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            phone VARCHAR(20) NOT NULL,
            code_hash VARCHAR(64) NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'login',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
            verified_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_tenant_phone (tenant_id, phone),
            INDEX idx_otp_expires (expires_at),
            CONSTRAINT fk_otp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS otp_codes",
];
