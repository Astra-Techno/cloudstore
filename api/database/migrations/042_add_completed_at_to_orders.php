<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE orders ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER cancelled_at",
    ],
    'down' => [
        "ALTER TABLE orders DROP COLUMN completed_at",
    ],
];
