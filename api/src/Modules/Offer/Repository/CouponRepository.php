<?php

declare(strict_types=1);

namespace App\Modules\Offer\Repository;

use App\Core\Database\Connection;

final class CouponRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO coupons (uuid, tenant_id, code, title, description, discount_type, discount_value,
             min_order_amount, max_discount_amount, usage_limit, per_customer_limit, starts_at, expires_at,
             is_active, applies_to, applies_to_ids)
             VALUES (:uuid, :tenant_id, :code, :title, :description, :discount_type, :discount_value,
             :min_order_amount, :max_discount_amount, :usage_limit, :per_customer_limit, :starts_at, :expires_at,
             :is_active, :applies_to, :applies_to_ids)',
            $data
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id, 'tenant_id' => $tenantId];
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE coupons SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant_id';
        return $this->db->execute($sql, $params) > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'DELETE FROM coupons WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $tenantId]
        ) > 0;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM coupons WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM coupons WHERE uuid = :uuid AND tenant_id = :tenant_id',
            ['uuid' => $uuid, 'tenant_id' => $tenantId]
        );
    }

    public function findByCode(string $code, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM coupons WHERE code = :code AND tenant_id = :tenant_id',
            ['code' => $code, 'tenant_id' => $tenantId]
        );
    }

    public function listByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM coupons WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['tenant_id' => $tenantId, 'limit' => $limit, 'offset' => $offset]
        );
    }

    public function countByTenant(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) as cnt FROM coupons WHERE tenant_id = :tenant_id',
            ['tenant_id' => $tenantId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getCustomerUsageCount(int $couponId, int $customerId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) as cnt FROM coupon_usage WHERE coupon_id = :coupon_id AND customer_id = :customer_id',
            ['coupon_id' => $couponId, 'customer_id' => $customerId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function recordUsage(int $couponId, int $customerId, int $orderId, int $discountAmount): void
    {
        $this->db->execute(
            'INSERT INTO coupon_usage (coupon_id, customer_id, order_id, discount_amount)
             VALUES (:coupon_id, :customer_id, :order_id, :discount_amount)',
            ['coupon_id' => $couponId, 'customer_id' => $customerId, 'order_id' => $orderId, 'discount_amount' => $discountAmount]
        );

        $this->db->execute(
            'UPDATE coupons SET used_count = used_count + 1 WHERE id = :id',
            ['id' => $couponId]
        );
    }

    public function incrementUsedCount(int $couponId): void
    {
        $this->db->execute(
            'UPDATE coupons SET used_count = used_count + 1 WHERE id = :id',
            ['id' => $couponId]
        );
    }
}
