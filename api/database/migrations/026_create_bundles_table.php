<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE bundles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            bundle_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'special combo price, minor units',
            original_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'sum of individual prices, minor units',
            image_url VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            starts_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_bundles_tenant_slug (tenant_id, slug),
            INDEX idx_bundles_active (tenant_id, is_active, sort_order),
            CONSTRAINT fk_bundles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE bundle_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bundle_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED DEFAULT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bundle_items_bundle (bundle_id),
            CONSTRAINT fk_bundle_items_bundle FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE,
            CONSTRAINT fk_bundle_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS bundle_items",
        "DROP TABLE IF EXISTS bundles",
    ],
];
