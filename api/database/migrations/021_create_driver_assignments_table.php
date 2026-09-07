<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE driver_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            driver_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'assigned' COMMENT 'assigned, accepted, picked_up, delivered, cancelled',
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            accepted_at TIMESTAMP NULL DEFAULT NULL,
            picked_up_at TIMESTAMP NULL DEFAULT NULL,
            delivered_at TIMESTAMP NULL DEFAULT NULL,
            cancelled_at TIMESTAMP NULL DEFAULT NULL,
            cancel_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_driver_assignments_order (order_id),
            INDEX idx_driver_assignments_driver (driver_id, status),
            INDEX idx_driver_assignments_tenant (tenant_id),
            CONSTRAINT fk_da_order FOREIGN KEY (order_id) REFERENCES orders(id),
            CONSTRAINT fk_da_driver FOREIGN KEY (driver_id) REFERENCES drivers(id),
            CONSTRAINT fk_da_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS driver_assignments",
    ],
];
