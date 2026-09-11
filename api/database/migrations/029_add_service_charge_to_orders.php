<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE orders ADD COLUMN service_charge INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units' AFTER delivery_fee",
    ],
    'down' => [
        "ALTER TABLE orders DROP COLUMN service_charge",
    ],
];
