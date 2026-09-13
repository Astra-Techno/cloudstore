<?php

return [
    "ALTER TABLE delivery_zones ADD COLUMN pincodes JSON NULL AFTER max_distance_km",
    "ALTER TABLE delivery_zones ADD COLUMN zone_type ENUM('distance','pincode') DEFAULT 'distance' AFTER name",
];
