<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repository;

use App\Core\Database\Connection;

final class VariantRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByProduct(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM product_variants WHERE product_id = ? AND status = 'active' ORDER BY sort_order ASC",
            [$productId]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM product_variants WHERE id = ?",
            [$id]
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM product_variants WHERE uuid = ?",
            [$uuid]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO product_variants (uuid, product_id, name, sku, price, compare_price, weight_grams, stock_mode, stock_quantity, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['product_id'],
                $data['name'],
                $data['sku'] ?? null,
                $data['price'],
                $data['compare_price'] ?? null,
                $data['weight_grams'] ?? null,
                $data['stock_mode'] ?? 'unlimited',
                $data['stock_quantity'] ?? null,
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'active',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $this->db->execute(
            "UPDATE product_variants SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM product_variants WHERE id = ?", [$id]);
    }

    public function decrementStock(int $id, int $amount): bool
    {
        $affected = $this->db->execute(
            "UPDATE product_variants SET stock_quantity = stock_quantity - ?
             WHERE id = ? AND stock_mode = 'limited_stock' AND stock_quantity >= ?",
            [$amount, $id, $amount]
        );

        return $affected > 0;
    }
}
