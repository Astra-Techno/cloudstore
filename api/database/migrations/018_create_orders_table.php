<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            order_number VARCHAR(30) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            address_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
            order_type VARCHAR(20) NOT NULL DEFAULT 'delivery',
            subtotal INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            delivery_fee INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            tax_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            discount_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            total INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            coupon_code VARCHAR(50) DEFAULT NULL,
            payment_method VARCHAR(30) DEFAULT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            paid_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            address_snapshot JSON NOT NULL,
            scheduled_at TIMESTAMP NULL DEFAULT NULL,
            accepted_at TIMESTAMP NULL DEFAULT NULL,
            preparing_at TIMESTAMP NULL DEFAULT NULL,
            ready_at TIMESTAMP NULL DEFAULT NULL,
            picked_up_at TIMESTAMP NULL DEFAULT NULL,
            delivered_at TIMESTAMP NULL DEFAULT NULL,
            cancelled_at TIMESTAMP NULL DEFAULT NULL,
            cancel_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_orders_tenant_status (tenant_id, status, created_at),
            INDEX idx_orders_customer (customer_id, created_at),
            INDEX idx_orders_number (order_number),
            INDEX idx_orders_tenant_date (tenant_id, created_at),
            CONSTRAINT fk_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED DEFAULT NULL,
            variant_id BIGINT UNSIGNED DEFAULT NULL,
            product_snapshot JSON NOT NULL,
            variant_snapshot JSON DEFAULT NULL,
            addons_snapshot JSON DEFAULT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            addons_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            line_total INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_items_order (order_id),
            CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE order_status_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            from_status VARCHAR(30) DEFAULT NULL,
            to_status VARCHAR(30) NOT NULL,
            actor_type VARCHAR(20) DEFAULT NULL,
            actor_id BIGINT UNSIGNED DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_history (order_id, created_at),
            CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS order_status_history",
        "DROP TABLE IF EXISTS order_items",
        "DROP TABLE IF EXISTS orders",
    ],
];
