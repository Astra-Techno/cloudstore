<?php

declare(strict_types=1);

// Note: delivered_at may already exist from an earlier manual change.
// Using procedures to conditionally add columns.
return [
    'up' => [
        "CREATE PROCEDURE _add_col_035a() BEGIN IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='driver_assignments' AND COLUMN_NAME='delivery_otp') THEN ALTER TABLE driver_assignments ADD COLUMN delivery_otp CHAR(4) NULL AFTER status; END IF; END",
        "CALL _add_col_035a()",
        "DROP PROCEDURE IF EXISTS _add_col_035a",

        "CREATE PROCEDURE _add_col_035b() BEGIN IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='driver_assignments' AND COLUMN_NAME='proof_photo_url') THEN ALTER TABLE driver_assignments ADD COLUMN proof_photo_url VARCHAR(500) NULL AFTER delivery_otp; END IF; END",
        "CALL _add_col_035b()",
        "DROP PROCEDURE IF EXISTS _add_col_035b",

        "CREATE PROCEDURE _add_col_035c() BEGIN IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='driver_assignments' AND COLUMN_NAME='delivered_at') THEN ALTER TABLE driver_assignments ADD COLUMN delivered_at TIMESTAMP NULL AFTER proof_photo_url; END IF; END",
        "CALL _add_col_035c()",
        "DROP PROCEDURE IF EXISTS _add_col_035c",

        "CREATE PROCEDURE _add_col_035d() BEGIN IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='driver_assignments' AND COLUMN_NAME='earnings') THEN ALTER TABLE driver_assignments ADD COLUMN earnings INT UNSIGNED DEFAULT 0 AFTER delivered_at; END IF; END",
        "CALL _add_col_035d()",
        "DROP PROCEDURE IF EXISTS _add_col_035d",
    ],
    'down' => [
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS delivery_otp",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS proof_photo_url",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS delivered_at",
        "ALTER TABLE driver_assignments DROP COLUMN IF EXISTS earnings",
    ],
];
