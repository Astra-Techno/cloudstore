<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            gateway VARCHAR(30) NOT NULL COMMENT 'razorpay, stripe, manual',
            gateway_order_id VARCHAR(100) DEFAULT NULL,
            gateway_payment_id VARCHAR(100) DEFAULT NULL,
            gateway_signature VARCHAR(255) DEFAULT NULL,
            amount INT UNSIGNED NOT NULL COMMENT 'minor units',
            currency VARCHAR(3) NOT NULL DEFAULT 'INR',
            status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, paid, failed, refunded',
            failure_reason TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            paid_at TIMESTAMP NULL DEFAULT NULL,
            refunded_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_payments_order (order_id),
            INDEX idx_payments_tenant (tenant_id, status),
            INDEX idx_payments_gateway (gateway_order_id),
            CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id),
            CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE refunds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            payment_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            amount INT UNSIGNED NOT NULL COMMENT 'minor units',
            reason TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
            gateway_refund_id VARCHAR(100) DEFAULT NULL,
            initiated_by_type VARCHAR(20) DEFAULT NULL,
            initiated_by_id BIGINT UNSIGNED DEFAULT NULL,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_refunds_payment (payment_id),
            INDEX idx_refunds_order (order_id),
            CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
            CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS refunds",
        "DROP TABLE IF EXISTS payments",
    ],
];
