<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE customers ADD COLUMN fcm_token VARCHAR(255) NULL AFTER email",
        "ALTER TABLE drivers ADD COLUMN fcm_token VARCHAR(255) NULL AFTER email",
    ],
    'down' => [
        "ALTER TABLE customers DROP COLUMN fcm_token",
        "ALTER TABLE drivers DROP COLUMN fcm_token",
    ],
];
