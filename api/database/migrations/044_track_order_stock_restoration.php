<?php

declare(strict_types=1);

return [
    'up' => 'ALTER TABLE orders ADD COLUMN stock_restored_at TIMESTAMP NULL DEFAULT NULL AFTER cancelled_at',
    'down' => 'ALTER TABLE orders DROP COLUMN stock_restored_at',
];
