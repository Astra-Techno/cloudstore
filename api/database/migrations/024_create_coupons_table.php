<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE coupons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage, fixed, free_delivery',
            discount_value INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units or basis points (percentage * 100)',
            min_order_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            max_discount_amount INT UNSIGNED DEFAULT NULL COMMENT 'cap for percentage discounts, minor units',
            usage_limit INT UNSIGNED DEFAULT NULL COMMENT 'total uses allowed, NULL = unlimited',
            per_customer_limit INT UNSIGNED NOT NULL DEFAULT 1,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            starts_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            applies_to VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all, category, product',
            applies_to_ids JSON DEFAULT NULL COMMENT 'category or product UUIDs',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_coupons_tenant_code (tenant_id, code),
            INDEX idx_coupons_active (tenant_id, is_active, starts_at, expires_at),
            CONSTRAINT fk_coupons_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE coupon_usage (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            coupon_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            discount_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_coupon_usage_coupon (coupon_id),
            INDEX idx_coupon_usage_customer (coupon_id, customer_id),
            CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            CONSTRAINT fk_coupon_usage_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS coupon_usage",
        "DROP TABLE IF EXISTS coupons",
    ],
];
