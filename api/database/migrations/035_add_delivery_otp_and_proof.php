<?php

return [
    "ALTER TABLE driver_assignments ADD COLUMN delivery_otp CHAR(4) NULL AFTER status",
    "ALTER TABLE driver_assignments ADD COLUMN proof_photo_url VARCHAR(500) NULL AFTER delivery_otp",
    "ALTER TABLE driver_assignments ADD COLUMN delivered_at TIMESTAMP NULL AFTER proof_photo_url",
    "ALTER TABLE driver_assignments ADD COLUMN earnings INT UNSIGNED DEFAULT 0 COMMENT 'minor units' AFTER delivered_at",
];
