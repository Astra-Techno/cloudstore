<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE product_variants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            product_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units (paise)',
            compare_price INT UNSIGNED DEFAULT NULL COMMENT 'strikethrough price',
            weight_grams INT UNSIGNED DEFAULT NULL COMMENT 'for weight-based products',
            stock_mode VARCHAR(20) NOT NULL DEFAULT 'unlimited',
            stock_quantity INT DEFAULT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_variants_product (product_id, status, sort_order),
            CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS product_variants",
];
