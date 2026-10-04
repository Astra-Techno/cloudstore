<?php
declare(strict_types=1);

return [
    'up' => [
        // Holiday / exception dates per session
        "CREATE TABLE meal_session_exceptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meal_session_id BIGINT UNSIGNED NOT NULL,
            exception_date DATE NOT NULL,
            action ENUM('closed','extended_cutoff') NOT NULL DEFAULT 'closed',
            cutoff_override TIME NULL DEFAULT NULL,
            reason VARCHAR(255) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_session_exception (meal_session_id, exception_date),
            CONSTRAINT fk_mse_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Session-specific delivery zone overrides
        "ALTER TABLE meal_sessions ADD COLUMN delivery_fee_override INT UNSIGNED NULL DEFAULT NULL AFTER max_orders",
        "ALTER TABLE meal_sessions ADD COLUMN min_order_amount INT UNSIGNED NULL DEFAULT NULL AFTER delivery_fee_override",
        "ALTER TABLE meal_sessions ADD COLUMN paused TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled",
        "ALTER TABLE meal_sessions ADD COLUMN cutoff_extended_until DATETIME NULL DEFAULT NULL AFTER paused",

        // Session-specific product overrides (prep time, image, availability)
        "ALTER TABLE meal_session_products ADD COLUMN prep_minutes SMALLINT UNSIGNED NULL DEFAULT NULL AFTER quantity_limit",
        "ALTER TABLE meal_session_products ADD COLUMN available TINYINT(1) NOT NULL DEFAULT 1 AFTER prep_minutes",

        // Customer favourite sessions for reminders
        "CREATE TABLE customer_favourite_sessions (
            customer_id BIGINT UNSIGNED NOT NULL,
            meal_session_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (customer_id, meal_session_id),
            CONSTRAINT fk_cfs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_cfs_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS customer_favourite_sessions",
        "DROP TABLE IF EXISTS meal_session_exceptions",
        "ALTER TABLE meal_sessions DROP COLUMN delivery_fee_override",
        "ALTER TABLE meal_sessions DROP COLUMN min_order_amount",
        "ALTER TABLE meal_sessions DROP COLUMN paused",
        "ALTER TABLE meal_sessions DROP COLUMN cutoff_extended_until",
        "ALTER TABLE meal_session_products DROP COLUMN prep_minutes",
        "ALTER TABLE meal_session_products DROP COLUMN available",
    ],
];
