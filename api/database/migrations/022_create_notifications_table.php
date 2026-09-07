<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            recipient_type VARCHAR(20) NOT NULL COMMENT 'customer, admin, driver',
            recipient_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'in_app' COMMENT 'in_app, sms, push, email',
            type VARCHAR(50) NOT NULL COMMENT 'order_confirmed, order_ready, etc',
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            data JSON DEFAULT NULL,
            read_at TIMESTAMP NULL DEFAULT NULL,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            failed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_recipient (recipient_type, recipient_id, read_at),
            INDEX idx_notifications_tenant (tenant_id, created_at),
            INDEX idx_notifications_type (type),
            CONSTRAINT fk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS notifications",
    ],
];
