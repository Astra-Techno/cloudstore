<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS favourites (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fav (tenant_id, customer_id, product_id),
            KEY idx_customer (tenant_id, customer_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ],
    'down' => [
        "DROP TABLE IF EXISTS favourites",
    ],
];
