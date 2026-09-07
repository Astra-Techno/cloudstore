<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE rate_limits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL COMMENT 'IP or user:id',
            endpoint VARCHAR(100) NOT NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 1,
            window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            UNIQUE KEY uk_rate_limits (identifier, endpoint),
            INDEX idx_rate_limits_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS rate_limits",
    ],
];
