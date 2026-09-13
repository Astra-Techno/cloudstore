<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repository;

use App\Core\Database\Connection;

final class FavouriteRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /** @return int[] product IDs */
    public function findByCustomer(int $tenantId, int $customerId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT product_id FROM favourites WHERE tenant_id = ? AND customer_id = ? ORDER BY created_at DESC",
            [$tenantId, $customerId]
        );

        return array_map(fn(array $r) => (int) $r['product_id'], $rows);
    }

    public function add(int $tenantId, int $customerId, int $productId): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO favourites (tenant_id, customer_id, product_id) VALUES (?, ?, ?)",
            [$tenantId, $customerId, $productId]
        );
    }

    public function remove(int $tenantId, int $customerId, int $productId): void
    {
        $this->db->execute(
            "DELETE FROM favourites WHERE tenant_id = ? AND customer_id = ? AND product_id = ?",
            [$tenantId, $customerId, $productId]
        );
    }

    public function isFavourite(int $tenantId, int $customerId, int $productId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM favourites WHERE tenant_id = ? AND customer_id = ? AND product_id = ?",
            [$tenantId, $customerId, $productId]
        );

        return $row !== null;
    }

    /**
     * Return full product rows for a customer's favourites (with category info).
     */
    public function findProductsByCustomer(int $tenantId, int $customerId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM favourites f
             JOIN products p ON p.id = f.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE f.tenant_id = ? AND f.customer_id = ?
             ORDER BY f.created_at DESC",
            [$tenantId, $customerId]
        );
    }
}
