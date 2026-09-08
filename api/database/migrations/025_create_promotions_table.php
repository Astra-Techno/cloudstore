<?php

declare(strict_types=1);

return [
    'up' => "CREATE TABLE promotions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            promotion_type VARCHAR(30) NOT NULL COMMENT 'buy_x_get_y, category_discount, order_discount, free_delivery, flash_sale',
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage, fixed',
            discount_value INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units or basis points',
            max_discount_amount INT UNSIGNED DEFAULT NULL COMMENT 'cap for percentage, minor units',
            min_order_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            rules_json JSON NOT NULL COMMENT 'type-specific rules',
            priority INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'higher = applied first',
            is_stackable TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'can combine with coupons',
            usage_limit INT UNSIGNED DEFAULT NULL,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            starts_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_promotions_active (tenant_id, is_active, priority DESC),
            INDEX idx_promotions_dates (tenant_id, starts_at, expires_at),
            CONSTRAINT fk_promotions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'down' => "DROP TABLE IF EXISTS promotions",
];
