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

    private const ALLOWED_TIMESTAMP_FIELDS = [
        'confirmed_at', 'preparing_at', 'ready_at', 'picked_up_at',
        'delivered_at', 'completed_at', 'cancelled_at',
    ];

    public function updateStatus(int $id, int $tenantId, string $status, ?string $timestampField = null): void
    {
        $sql = "UPDATE orders SET status = ?";
        $params = [$status];

        if ($timestampField !== null && in_array($timestampField, self::ALLOWED_TIMESTAMP_FIELDS, true)) {
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

    public function findByTenantAndStatuses(int $tenantId, array $statuses): array
    {
        if (empty($statuses)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM orders WHERE tenant_id = ? AND status IN ({$placeholders}) ORDER BY created_at ASC",
            array_merge([$tenantId], $statuses)
        );
    }

    /**
     * Get the previous order's item product IDs for each customer (repeat order detection).
     * @return array<int, string>  customer_id => comma-separated product_ids from prev order
     */
    public function getPreviousOrderItemsHash(int $tenantId, array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }
        $result = [];
        foreach ($customerIds as $cid) {
            $prevOrder = $this->db->fetchOne(
                "SELECT id FROM orders WHERE tenant_id = ? AND customer_id = ?
                 ORDER BY created_at DESC LIMIT 1 OFFSET 1",
                [$tenantId, $cid]
            );
            if (!$prevOrder) continue;
            $items = $this->db->fetchAll(
                "SELECT product_id, variant_id, quantity FROM order_items
                 WHERE order_id = ? ORDER BY product_id, variant_id",
                [(int) $prevOrder['id']]
            );
            $result[$cid] = implode(',', array_map(
                fn($i) => $i['product_id'] . ':' . ($i['variant_id'] ?? '0') . ':' . $i['quantity'],
                $items
            ));
        }
        return $result;
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
            "SELECT o.*, c.name as customer_name, c.phone as customer_phone,
                    dt.name AS dining_table_name, ds.id AS dining_session_id, ds.access_code AS dining_access_code
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             LEFT JOIN dining_orders dord ON dord.order_id = o.id
             LEFT JOIN dining_sessions ds ON ds.id = dord.session_id
             LEFT JOIN dining_tables dt ON dt.id = ds.table_id
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

    public function getDiningInfo(int $orderId): ?array
    {
        return $this->db->fetchOne(
            "SELECT dt.name AS table_name, ds.access_code, ds.opened_at, ds.closed_at
             FROM dining_orders dord
             JOIN dining_sessions ds ON ds.id = dord.session_id
             JOIN dining_tables dt ON dt.id = ds.table_id
             WHERE dord.order_id = ?",
            [$orderId]
        );
    }

    /**
     * Count completed orders per customer (for "returning customer" badge).
     * @return array<int, int>  customer_id => order_count
     */
    public function countOrdersByCustomers(int $tenantId, array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT customer_id, COUNT(*) as cnt FROM orders
             WHERE tenant_id = ? AND customer_id IN ({$placeholders})
             GROUP BY customer_id",
            array_merge([$tenantId], $customerIds)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['customer_id']] = (int) $row['cnt'];
        }
        return $result;
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

    public function getLatestOrderId(int $tenantId): ?int
    {
        $row = $this->db->fetchOne(
            "SELECT MAX(id) as max_id FROM orders WHERE tenant_id = ?",
            [$tenantId]
        );

        return $row['max_id'] !== null ? (int) $row['max_id'] : null;
    }

    public function getOrdersSince(int $tenantId, int $afterId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, order_number, status, total, order_type, created_at
             FROM orders
             WHERE tenant_id = ? AND id > ?
             ORDER BY id ASC
             LIMIT ?",
            [$tenantId, $afterId, $limit]
        );
    }
}
