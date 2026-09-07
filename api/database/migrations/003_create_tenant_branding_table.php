<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE tenant_branding (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
            primary_color VARCHAR(7) NOT NULL DEFAULT '#2563EB',
            secondary_color VARCHAR(7) NOT NULL DEFAULT '#1E40AF',
            accent_color VARCHAR(7) DEFAULT NULL,
            font VARCHAR(100) DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            favicon_url VARCHAR(500) DEFAULT NULL,
            splash_image_url VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_tenant_branding_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS tenant_branding",
];
