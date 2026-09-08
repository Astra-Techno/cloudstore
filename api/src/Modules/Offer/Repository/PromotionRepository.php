<?php

declare(strict_types=1);

namespace App\Modules\Offer\Repository;

use App\Core\Database\Connection;

final class PromotionRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO promotions (uuid, tenant_id, title, description, promotion_type, discount_type,
             discount_value, max_discount_amount, min_order_amount, rules_json, priority, is_stackable,
             usage_limit, starts_at, expires_at, is_active)
             VALUES (:uuid, :tenant_id, :title, :description, :promotion_type, :discount_type,
             :discount_value, :max_discount_amount, :min_order_amount, :rules_json, :priority, :is_stackable,
             :usage_limit, :starts_at, :expires_at, :is_active)',
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

        $sql = 'UPDATE promotions SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant_id';
        return $this->db->execute($sql, $params) > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'DELETE FROM promotions WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $tenantId]
        ) > 0;
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM promotions WHERE uuid = :uuid AND tenant_id = :tenant_id',
            ['uuid' => $uuid, 'tenant_id' => $tenantId]
        );
    }

    public function listByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM promotions WHERE tenant_id = :tenant_id ORDER BY priority DESC, created_at DESC LIMIT :limit OFFSET :offset',
            ['tenant_id' => $tenantId, 'limit' => $limit, 'offset' => $offset]
        );
    }

    public function countByTenant(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) as cnt FROM promotions WHERE tenant_id = :tenant_id',
            ['tenant_id' => $tenantId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getActivePromotions(int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM promotions
             WHERE tenant_id = :tenant_id
               AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (expires_at IS NULL OR expires_at >= NOW())
               AND (usage_limit IS NULL OR used_count < usage_limit)
             ORDER BY priority DESC',
            ['tenant_id' => $tenantId]
        );
    }

    public function incrementUsedCount(int $promotionId): void
    {
        $this->db->execute(
            'UPDATE promotions SET used_count = used_count + 1 WHERE id = :id',
            ['id' => $promotionId]
        );
    }
}
