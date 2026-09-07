<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            short_description VARCHAR(500) DEFAULT NULL,
            product_type VARCHAR(30) NOT NULL DEFAULT 'simple',
            base_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units (paise)',
            sale_price INT UNSIGNED DEFAULT NULL COMMENT 'minor units (paise)',
            pricing_mode VARCHAR(20) NOT NULL DEFAULT 'fixed',
            unit VARCHAR(20) NOT NULL DEFAULT 'piece',
            tax_class VARCHAR(50) DEFAULT NULL,
            stock_mode VARCHAR(20) NOT NULL DEFAULT 'unlimited',
            stock_quantity INT DEFAULT NULL,
            min_quantity INT UNSIGNED NOT NULL DEFAULT 1,
            max_quantity INT UNSIGNED NOT NULL DEFAULT 50,
            preparation_time_minutes INT UNSIGNED DEFAULT NULL,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            metadata_json JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uk_products_slug_tenant (slug, tenant_id),
            INDEX idx_products_tenant_status (tenant_id, status),
            INDEX idx_products_category (category_id, status, sort_order),
            INDEX idx_products_tenant_featured (tenant_id, is_featured, status),
            CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS products",
];
