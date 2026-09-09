<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE app_builds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            platform ENUM('android', 'ios') NOT NULL DEFAULT 'android',
            app_mode ENUM('customer', 'driver') NOT NULL DEFAULT 'customer',
            build_type VARCHAR(50) NOT NULL DEFAULT 'apk' COMMENT 'apk, appbundle, both, ad-hoc, app-store',
            status ENUM('pending', 'queued', 'building', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            app_name VARCHAR(255) DEFAULT NULL,
            app_id VARCHAR(255) DEFAULT NULL COMMENT 'android applicationId or iOS bundleId',
            github_run_id VARCHAR(100) DEFAULT NULL,
            github_run_url VARCHAR(500) DEFAULT NULL,
            download_url VARCHAR(500) DEFAULT NULL,
            share_token VARCHAR(64) DEFAULT NULL UNIQUE COMMENT 'public share link token',
            file_size BIGINT UNSIGNED DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            triggered_by BIGINT UNSIGNED DEFAULT NULL COMMENT 'admin who triggered',
            expires_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_builds_tenant (tenant_id, platform, status),
            INDEX idx_builds_share (share_token),
            CONSTRAINT fk_builds_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS app_builds",
    ],
];
