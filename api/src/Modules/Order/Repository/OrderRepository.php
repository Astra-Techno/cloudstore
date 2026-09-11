<?php

declare(strict_types=1);

namespace App\Modules\Order\Repository;

use App\Core\Database\Connection;

final class OrderRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM orders WHERE uuid = ? AND tenant_id = ?",
            [$uuid, $tenantId]
        );
    }

    public function findByOrderNumber(string $orderNumber, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM orders WHERE order_number = ? AND tenant_id = ?",
            [$orderNumber, $tenantId]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO orders (uuid, order_number, tenant_id, customer_id, address_id, status, order_type,
             subtotal, delivery_fee, service_charge, tax_amount, discount_amount, total,
             coupon_code, payment_method, payment_status, notes, address_snapshot, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['order_number'], $data['tenant_id'], $data['customer_id'],
                $data['address_id'] ?? null, $data['status'] ?? 'pending_payment',
                $data['order_type'] ?? 'delivery',
                $data['subtotal'], $data['delivery_fee'] ?? 0,
                $data['service_charge'] ?? 0, $data['tax_amount'] ?? 0,
                $data['discount_amount'] ?? 0, $data['total'],
                $data['coupon_code'] ?? null, $data['payment_method'] ?? null,
                $data['payment_status'] ?? 'pending', $data['notes'] ?? null,
                $data['address_snapshot'], $data['scheduled_at'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function addItem(array $data): int
    {
        $this->db->execute(
            "INSERT INTO order_items (order_id, product_id, variant_id, product_snapshot, variant_snapshot, addons_snapshot,
             quantity, unit_price, addons_price, line_total, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['order_id'], $data['product_id'] ?? null, $data['variant_id'] ?? null,
                $data['product_snapshot'], $data['variant_snapshot'] ?? null, $data['addons_snapshot'] ?? null,
                $data['quantity'], $data['unit_price'], $data['addons_price'] ?? 0,
                $data['line_total'], $data['notes'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status, ?string $timestampField = null): void
    {
        $sql = "UPDATE orders SET status = ?";
        $params = [$status];

        if ($timestampField !== null) {
            $sql .= ", {$timestampField} = NOW()";
        }

        $sql .= " WHERE id = ? AND tenant_id = ?";
        $params[] = $id;
        $params[] = $tenantId;

        $this->db->execute($sql, $params);
    }

    public function addStatusHistory(int $orderId, ?string $from, string $to, ?string $actorType = null, ?int $actorId = null, ?string $notes = null): void
    {
        $this->db->execute(
            "INSERT INTO order_status_history (order_id, from_status, to_status, actor_type, actor_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$orderId, $from, $to, $actorType, $actorId, $notes]
        );
    }

    public function getItems(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC",
            [$orderId]
        );
    }

    public function getStatusHistory(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC",
            [$orderId]
        );
    }

    public function findByCustomer(int $customerId, int $tenantId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM orders WHERE customer_id = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$customerId, $tenantId, $limit, $offset]
        );
    }

    public function findByTenant(int $tenantId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT o.*, c.name as customer_name, c.phone as customer_phone
                FROM orders o JOIN customers c ON c.id = o.customer_id
                WHERE o.tenant_id = ?";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function generateOrderNumber(int $tenantId): string
    {
        $date = date('Ymd');
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM orders WHERE tenant_id = ? AND DATE(created_at) = CURDATE()",
            [$tenantId]
        );
        $seq = ((int) ($row['cnt'] ?? 0)) + 1;

        return sprintf('ORD-%s-%06d', $date, $seq);
    }

    public function countsByStatus(int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) as count FROM orders WHERE tenant_id = ? GROUP BY status",
            [$tenantId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        return $result;
    }

    public function findByTenantWithItems(int $tenantId, int $limit = 200): array
    {
        $orders = $this->db->fetchAll(
            "SELECT o.*, c.name as customer_name, c.phone as customer_phone
             FROM orders o JOIN customers c ON c.id = o.customer_id
             WHERE o.tenant_id = ?
             ORDER BY o.created_at DESC LIMIT ?",
            [$tenantId, $limit]
        );

        $orderIds = array_column($orders, 'id');
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $items = $this->db->fetchAll(
            "SELECT * FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id ASC",
            $orderIds
        );

        $itemsByOrder = [];
        foreach ($items as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }

        return $orders;
    }

    public function countByTenant(int $tenantId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM orders WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $row = $this->db->fetchOne($sql, $params);

        return (int) ($row['cnt'] ?? 0);
    }
}
