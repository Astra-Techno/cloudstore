<?php

declare(strict_types=1);

// Uses a callable to conditionally add columns (some may already exist from manual changes).
return [
    'up' => function (PDO $pdo): void {
        $columns = [
            ['delivery_otp', 'CHAR(4) NULL', 'status'],
            ['proof_photo_url', 'VARCHAR(500) NULL', 'delivery_otp'],
            ['delivered_at', 'TIMESTAMP NULL', 'proof_photo_url'],
            ['earnings', 'INT UNSIGNED DEFAULT 0', 'delivered_at'],
        ];

        foreach ($columns as [$col, $type, $after]) {
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'driver_assignments'
                   AND COLUMN_NAME = :col"
            );
            $check->execute(['col' => $col]);
            if ((int) $check->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE driver_assignments ADD COLUMN {$col} {$type} AFTER {$after}");
            }
        }
    },
    'down' => [
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS delivery_otp",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS proof_photo_url",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS delivered_at",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS earnings",
    ],
];
