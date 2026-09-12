<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE tenants
            ADD COLUMN commercial_plan VARCHAR(30) NOT NULL DEFAULT 'branded' AFTER business_type,
            ADD COLUMN marketplace_status VARCHAR(20) NOT NULL DEFAULT 'hidden' AFTER commercial_plan,
            ADD COLUMN marketplace_sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER marketplace_status",
        "CREATE TABLE platform_fee_ledger (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            gross_order_value INT UNSIGNED NOT NULL,
            fee_amount INT UNSIGNED NOT NULL,
            fee_rule VARCHAR(50) NOT NULL DEFAULT 'one_percent_capped_at_500_paise',
            status VARCHAR(20) NOT NULL DEFAULT 'accrued',
            settled_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_platform_fee_order (order_id),
            INDEX idx_platform_fee_tenant_status (tenant_id, status, created_at),
            CONSTRAINT fk_platform_fee_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            CONSTRAINT fk_platform_fee_order FOREIGN KEY (order_id) REFERENCES orders(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS platform_fee_ledger",
        "ALTER TABLE tenants DROP COLUMN marketplace_sort_order, DROP COLUMN marketplace_status, DROP COLUMN commercial_plan",
    ],
];
