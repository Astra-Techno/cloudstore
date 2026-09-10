<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE app_builds ADD COLUMN file_path VARCHAR(500) DEFAULT NULL COMMENT 'local file path to APK/AAB' AFTER download_url",
    ],
    'down' => [
        "ALTER TABLE app_builds DROP COLUMN file_path",
    ],
];
