<?php
declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS dining_tables (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            token CHAR(64) NOT NULL UNIQUE,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (tenant_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS dining_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            table_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            access_code CHAR(6) NOT NULL,
            opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL,
            INDEX (table_id, closed_at),
            FOREIGN KEY (table_id) REFERENCES dining_tables(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS dining_orders (
            order_id BIGINT UNSIGNED PRIMARY KEY,
            session_id BIGINT UNSIGNED NOT NULL,
            request_key CHAR(36) NOT NULL,
            receipt_token CHAR(64) NOT NULL UNIQUE,
            UNIQUE KEY dining_request (session_id, request_key),
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (session_id) REFERENCES dining_sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => ['DROP TABLE dining_orders', 'DROP TABLE dining_sessions', 'DROP TABLE dining_tables'],
];
