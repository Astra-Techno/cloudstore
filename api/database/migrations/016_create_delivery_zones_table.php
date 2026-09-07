<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE delivery_zones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            min_distance_km DECIMAL(6,2) NOT NULL DEFAULT 0,
            max_distance_km DECIMAL(6,2) NOT NULL,
            fee INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            min_order_free_delivery INT UNSIGNED DEFAULT NULL COMMENT 'minor units, free delivery above this',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_delivery_zones_tenant (tenant_id, status),
            CONSTRAINT fk_delivery_zones_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS delivery_zones",
];
