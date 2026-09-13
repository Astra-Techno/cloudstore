<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE delivery_zones ADD COLUMN pincodes JSON NULL AFTER max_distance_km",
        "ALTER TABLE delivery_zones ADD COLUMN zone_type ENUM('distance','pincode') DEFAULT 'distance' AFTER name",
    ],
    'down' => [
        "ALTER TABLE delivery_zones DROP COLUMN pincodes",
        "ALTER TABLE delivery_zones DROP COLUMN zone_type",
    ],
];
