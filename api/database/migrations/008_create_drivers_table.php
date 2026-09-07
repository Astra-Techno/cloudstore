<?php

declare(strict_types=1);

return [
    'up' => "
        CREATE TABLE drivers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            vehicle_type VARCHAR(50) DEFAULT NULL,
            vehicle_number VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'offline',
            availability VARCHAR(20) NOT NULL DEFAULT 'offline',
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            last_location_lat DECIMAL(10, 8) DEFAULT NULL,
            last_location_lng DECIMAL(11, 8) DEFAULT NULL,
            last_location_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uk_drivers_phone_tenant (phone, tenant_id),
            INDEX idx_drivers_tenant (tenant_id),
            INDEX idx_drivers_status (status),
            INDEX idx_drivers_availability (availability),
            CONSTRAINT fk_drivers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'down' => "DROP TABLE IF EXISTS drivers",
];
