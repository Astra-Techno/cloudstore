<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repository;

use App\Core\Database\Connection;

final class ReviewRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Fetch reviews for a product, including the customer's first name.
     */
    public function findByProduct(int $tenantId, int $productId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT r.uuid, r.rating, r.review_text, r.status, r.created_at,
                    cu.first_name as customer_name
             FROM reviews r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.tenant_id = ? AND r.product_id = ? AND r.status = 'approved'
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $productId, $limit, $offset]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO reviews (uuid, tenant_id, customer_id, product_id, order_id, rating, review_text, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['customer_id'],
                $data['product_id'],
                $data['order_id'] ?? null,
                $data['rating'],
                $data['review_text'] ?? null,
                $data['status'] ?? 'approved',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array{avg_rating: float, count: int}|null
     */
    public function getAverageRating(int $tenantId, int $productId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as count
             FROM reviews
             WHERE tenant_id = ? AND product_id = ? AND status = 'approved'",
            [$tenantId, $productId]
        );

        if ($row === null || (int) $row['count'] === 0) {
            return null;
        }

        return [
            'avg_rating' => (float) $row['avg_rating'],
            'count' => (int) $row['count'],
        ];
    }

    public function findByCustomerAndProduct(int $tenantId, int $customerId, int $productId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM reviews WHERE tenant_id = ? AND customer_id = ? AND product_id = ?",
            [$tenantId, $customerId, $productId]
        );
    }
}
