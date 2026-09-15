<?php

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            subject VARCHAR(180) NOT NULL,
            category ENUM('order','delivery','refund','account','other') NOT NULL DEFAULT 'other',
            status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
            priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
            last_message_at TIMESTAMP NULL DEFAULT NULL,
            resolved_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_support_customer (tenant_id, customer_id, created_at),
            KEY idx_support_status (tenant_id, status, last_message_at),
            CONSTRAINT fk_support_ticket_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            CONSTRAINT fk_support_ticket_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_support_ticket_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS support_ticket_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            ticket_id BIGINT UNSIGNED NOT NULL,
            sender_type ENUM('customer','admin') NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_support_messages_ticket (ticket_id, created_at),
            CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS support_ticket_messages',
        'DROP TABLE IF EXISTS support_tickets',
    ],
];
