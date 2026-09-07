<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE addon_groups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            min_selections INT UNSIGNED NOT NULL DEFAULT 0,
            max_selections INT UNSIGNED NOT NULL DEFAULT 5,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_addon_groups_tenant (tenant_id, status),
            CONSTRAINT fk_addon_groups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE addon_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            group_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units (paise)',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_addon_items_group (group_id, status, sort_order),
            CONSTRAINT fk_addon_items_group FOREIGN KEY (group_id) REFERENCES addon_groups(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE product_addon_groups (
            product_id BIGINT UNSIGNED NOT NULL,
            addon_group_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (product_id, addon_group_id),
            CONSTRAINT fk_pag_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_pag_addon_group FOREIGN KEY (addon_group_id) REFERENCES addon_groups(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS product_addon_groups",
        "DROP TABLE IF EXISTS addon_items",
        "DROP TABLE IF EXISTS addon_groups",
    ],
];
