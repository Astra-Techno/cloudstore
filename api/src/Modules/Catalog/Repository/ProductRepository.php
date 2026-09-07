<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repository;

use App\Core\Database\Connection;

final class ProductRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL",
            [$id, $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.uuid = ? AND p.tenant_id = ? AND p.deleted_at IS NULL",
            [$uuid, $tenantId]
        );
    }

    public function findByCategory(int $categoryId, int $tenantId, ?string $status = 'active'): array
    {
        $sql = "SELECT * FROM products WHERE category_id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $params = [$categoryId, $tenantId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY sort_order ASC, name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findAll(int $tenantId, int $limit = 50, int $offset = 0, ?string $status = null, ?string $search = null): array
    {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.tenant_id = ? AND p.deleted_at IS NULL";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.short_description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY p.sort_order ASC, p.name ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function count(int $tenantId, ?string $status = null, ?string $search = null): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM products WHERE tenant_id = ? AND deleted_at IS NULL";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (name LIKE ? OR short_description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $row = $this->db->fetchOne($sql, $params);

        return (int) ($row['cnt'] ?? 0);
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO products (uuid, tenant_id, category_id, name, slug, description, short_description,
             product_type, base_price, sale_price, pricing_mode, unit, tax_class,
             stock_mode, stock_quantity, min_quantity, max_quantity, preparation_time_minutes,
             is_featured, status, sort_order, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['short_description'] ?? null,
                $data['product_type'] ?? 'simple',
                $data['base_price'] ?? 0,
                $data['sale_price'] ?? null,
                $data['pricing_mode'] ?? 'fixed',
                $data['unit'] ?? 'piece',
                $data['tax_class'] ?? null,
                $data['stock_mode'] ?? 'unlimited',
                $data['stock_quantity'] ?? null,
                $data['min_quantity'] ?? 1,
                $data['max_quantity'] ?? 50,
                $data['preparation_time_minutes'] ?? null,
                $data['is_featured'] ?? 0,
                $data['status'] ?? 'active',
                $data['sort_order'] ?? 0,
                isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;
        $values[] = $tenantId;

        $this->db->execute(
            "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $values
        );
    }

    public function findByCategoryId(int $categoryId, int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT id, uuid, name FROM products WHERE category_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1",
            [$categoryId, $tenantId]
        );
    }

    public function delete(int $id, int $tenantId): void
    {
        $this->db->execute("DELETE FROM products WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }

    public function softDelete(int $id, int $tenantId): void
    {
        $this->db->execute(
            "UPDATE products SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function updateStock(int $id, int $tenantId, int $quantity): void
    {
        $this->db->execute(
            "UPDATE products SET stock_quantity = ? WHERE id = ? AND tenant_id = ?",
            [$quantity, $id, $tenantId]
        );
    }

    public function decrementStock(int $id, int $tenantId, int $amount): bool
    {
        $affected = $this->db->execute(
            "UPDATE products SET stock_quantity = stock_quantity - ?
             WHERE id = ? AND tenant_id = ? AND stock_mode = 'limited_stock' AND stock_quantity >= ?",
            [$amount, $id, $tenantId, $amount]
        );

        return $affected > 0;
    }
}
