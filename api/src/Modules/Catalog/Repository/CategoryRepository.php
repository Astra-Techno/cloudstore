<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repository;

use App\Core\Database\Connection;

final class CategoryRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM categories WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$id, $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM categories WHERE uuid = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$uuid, $tenantId]
        );
    }

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM categories WHERE slug = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$slug, $tenantId]
        );
    }

    public function findAll(int $tenantId, ?string $status = 'active'): array
    {
        $sql = "SELECT * FROM categories WHERE tenant_id = ? AND deleted_at IS NULL";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY sort_order ASC, name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO categories (uuid, tenant_id, name, slug, description, image_url, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['image_url'] ?? null,
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'active',
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
            "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $values
        );
    }

    public function softDelete(int $id, int $tenantId): void
    {
        $this->db->execute(
            "UPDATE categories SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function delete(int $id, int $tenantId): void
    {
        $this->db->execute("DELETE FROM categories WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }

    public function getProductCount(int $categoryId, int $tenantId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM products WHERE category_id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$categoryId, $tenantId]
        );

        return (int) ($row['cnt'] ?? 0);
    }
}
