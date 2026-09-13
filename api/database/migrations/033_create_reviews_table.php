<?php

return [
    "CREATE TABLE IF NOT EXISTS reviews (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        uuid CHAR(36) NOT NULL UNIQUE,
        tenant_id BIGINT UNSIGNED NOT NULL,
        customer_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT UNSIGNED NULL,
        rating TINYINT UNSIGNED NOT NULL COMMENT '1-5',
        review_text TEXT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'approved',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_product (tenant_id, product_id),
        KEY idx_customer (tenant_id, customer_id),
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
