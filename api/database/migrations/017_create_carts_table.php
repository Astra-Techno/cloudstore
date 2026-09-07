<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE carts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(64) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_carts_tenant_customer (tenant_id, customer_id),
            INDEX idx_carts_session (session_id),
            CONSTRAINT fk_carts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE cart_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cart_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED DEFAULT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'server-calculated, minor units',
            addons_json JSON DEFAULT NULL,
            addons_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cart_items_cart (cart_id),
            CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
            CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS cart_items",
        "DROP TABLE IF EXISTS carts",
    ],
];
