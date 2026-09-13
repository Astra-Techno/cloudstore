<?php

return [
    "ALTER TABLE customers ADD COLUMN fcm_token VARCHAR(255) NULL AFTER email",
    "ALTER TABLE drivers ADD COLUMN fcm_token VARCHAR(255) NULL AFTER email",
];
