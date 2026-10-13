<?php
declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE meal_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            ordering_mode ENUM('preorder','instant','both') NOT NULL DEFAULT 'preorder',
            weekdays JSON NOT NULL,
            service_start TIME NOT NULL,
            service_end TIME NOT NULL,
            opens_day_offset SMALLINT NOT NULL DEFAULT -1,
            opens_at TIME NOT NULL DEFAULT '18:00:00',
            cutoff_day_offset SMALLINT NOT NULL DEFAULT 0,
            cutoff_at TIME NOT NULL,
            max_orders INT UNSIGNED DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_meal_sessions_tenant (tenant_id, enabled, sort_order),
            CONSTRAINT fk_meal_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE meal_session_products (
            meal_session_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            price_override INT UNSIGNED DEFAULT NULL,
            quantity_limit INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (meal_session_id, product_id),
            CONSTRAINT fk_msp_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE,
            CONSTRAINT fk_msp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE orders ADD COLUMN meal_session_id BIGINT UNSIGNED NULL AFTER scheduled_at",
        "ALTER TABLE orders ADD COLUMN service_date DATE NULL AFTER meal_session_id",
        "ALTER TABLE orders ADD INDEX idx_orders_meal_session (tenant_id, meal_session_id, service_date, status)",
        "ALTER TABLE orders ADD CONSTRAINT fk_orders_meal_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE SET NULL",
    ],
    'down' => [
        "ALTER TABLE orders DROP FOREIGN KEY fk_orders_meal_session",
        "ALTER TABLE orders DROP INDEX idx_orders_meal_session",
        "ALTER TABLE orders DROP COLUMN service_date",
        "ALTER TABLE orders DROP COLUMN meal_session_id",
        "DROP TABLE IF EXISTS meal_session_products",
        "DROP TABLE IF EXISTS meal_sessions",
    ],
];
