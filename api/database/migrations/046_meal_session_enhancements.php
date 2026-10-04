<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        // Ensure prerequisite table exists (migration 045 may have been recorded
        // in the migrations table without the actual DDL succeeding on production).
        $exists = (bool) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meal_sessions'"
        )->fetchColumn();

        if (!$exists) {
            $pdo->exec("CREATE TABLE meal_sessions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $mspExists = (bool) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meal_session_products'"
        )->fetchColumn();

        if (!$mspExists) {
            $pdo->exec("CREATE TABLE meal_session_products (
                meal_session_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                price_override INT UNSIGNED DEFAULT NULL,
                quantity_limit INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (meal_session_id, product_id),
                CONSTRAINT fk_msp_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_msp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Ensure orders table has meal session columns
        $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'meal_session_id'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN meal_session_id BIGINT UNSIGNED NULL AFTER scheduled_at");
            $pdo->exec("ALTER TABLE orders ADD COLUMN service_date DATE NULL AFTER meal_session_id");
            $pdo->exec("ALTER TABLE orders ADD INDEX idx_orders_meal_session (tenant_id, meal_session_id, service_date, status)");
            $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_orders_meal_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE SET NULL");
        }

        // --- Actual 046 enhancements below ---

        // Holiday / exception dates per session
        $pdo->exec("CREATE TABLE IF NOT EXISTS meal_session_exceptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meal_session_id BIGINT UNSIGNED NOT NULL,
            exception_date DATE NOT NULL,
            action ENUM('closed','extended_cutoff') NOT NULL DEFAULT 'closed',
            cutoff_override TIME NULL DEFAULT NULL,
            reason VARCHAR(255) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_session_exception (meal_session_id, exception_date),
            CONSTRAINT fk_mse_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Add columns to meal_sessions (skip if already present)
        $addCols = [
            ['delivery_fee_override', "ADD COLUMN delivery_fee_override INT UNSIGNED NULL DEFAULT NULL AFTER max_orders"],
            ['min_order_amount', "ADD COLUMN min_order_amount INT UNSIGNED NULL DEFAULT NULL AFTER delivery_fee_override"],
            ['paused', "ADD COLUMN paused TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled"],
            ['cutoff_extended_until', "ADD COLUMN cutoff_extended_until DATETIME NULL DEFAULT NULL AFTER paused"],
        ];
        foreach ($addCols as [$col, $ddl]) {
            $found = $pdo->query("SHOW COLUMNS FROM meal_sessions LIKE '{$col}'")->fetchAll();
            if (empty($found)) {
                $pdo->exec("ALTER TABLE meal_sessions {$ddl}");
            }
        }

        // Add columns to meal_session_products
        $mspCols = [
            ['prep_minutes', "ADD COLUMN prep_minutes SMALLINT UNSIGNED NULL DEFAULT NULL AFTER quantity_limit"],
            ['available', "ADD COLUMN available TINYINT(1) NOT NULL DEFAULT 1 AFTER prep_minutes"],
        ];
        foreach ($mspCols as [$col, $ddl]) {
            $found = $pdo->query("SHOW COLUMNS FROM meal_session_products LIKE '{$col}'")->fetchAll();
            if (empty($found)) {
                $pdo->exec("ALTER TABLE meal_session_products {$ddl}");
            }
        }

        // Customer favourite sessions
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_favourite_sessions (
            customer_id BIGINT UNSIGNED NOT NULL,
            meal_session_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (customer_id, meal_session_id),
            CONSTRAINT fk_cfs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_cfs_session FOREIGN KEY (meal_session_id) REFERENCES meal_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => [
        "DROP TABLE IF EXISTS customer_favourite_sessions",
        "DROP TABLE IF EXISTS meal_session_exceptions",
        "ALTER TABLE meal_sessions DROP COLUMN IF EXISTS delivery_fee_override",
        "ALTER TABLE meal_sessions DROP COLUMN IF EXISTS min_order_amount",
        "ALTER TABLE meal_sessions DROP COLUMN IF EXISTS paused",
        "ALTER TABLE meal_sessions DROP COLUMN IF EXISTS cutoff_extended_until",
        "ALTER TABLE meal_session_products DROP COLUMN IF EXISTS prep_minutes",
        "ALTER TABLE meal_session_products DROP COLUMN IF EXISTS available",
    ],
];
