<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE tenants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            business_type VARCHAR(50) NOT NULL DEFAULT 'other',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Kolkata',
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            locale VARCHAR(10) NOT NULL DEFAULT 'en-IN',
            contact_phone VARCHAR(20) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            logo_media_id BIGINT UNSIGNED DEFAULT NULL,
            favicon_media_id BIGINT UNSIGNED DEFAULT NULL,
            configuration JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_tenants_slug (slug),
            INDEX idx_tenants_status (status),
            INDEX idx_tenants_business_type (business_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS tenants",
];
