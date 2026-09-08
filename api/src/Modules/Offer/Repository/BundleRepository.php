<?php

declare(strict_types=1);

namespace App\Modules\Offer\Repository;

use App\Core\Database\Connection;

final class BundleRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO bundles (uuid, tenant_id, name, slug, description, bundle_price, original_price,
             image_url, is_active, starts_at, expires_at, sort_order)
             VALUES (:uuid, :tenant_id, :name, :slug, :description, :bundle_price, :original_price,
             :image_url, :is_active, :starts_at, :expires_at, :sort_order)',
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

        $sql = 'UPDATE bundles SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant_id';
        return $this->db->execute($sql, $params) > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'DELETE FROM bundles WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $tenantId]
        ) > 0;
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM bundles WHERE uuid = :uuid AND tenant_id = :tenant_id',
            ['uuid' => $uuid, 'tenant_id' => $tenantId]
        );
    }

    public function listByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM bundles WHERE tenant_id = :tenant_id ORDER BY sort_order ASC, created_at DESC LIMIT :limit OFFSET :offset',
            ['tenant_id' => $tenantId, 'limit' => $limit, 'offset' => $offset]
        );
    }

    public function countByTenant(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) as cnt FROM bundles WHERE tenant_id = :tenant_id',
            ['tenant_id' => $tenantId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getActiveBundles(int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT b.*, GROUP_CONCAT(bi.product_id) as product_ids
             FROM bundles b
             LEFT JOIN bundle_items bi ON bi.bundle_id = b.id
             WHERE b.tenant_id = :tenant_id
               AND b.is_active = 1
               AND (b.starts_at IS NULL OR b.starts_at <= NOW())
               AND (b.expires_at IS NULL OR b.expires_at >= NOW())
             GROUP BY b.id
             ORDER BY b.sort_order ASC',
            ['tenant_id' => $tenantId]
        );
    }

    public function addItem(int $bundleId, int $productId, ?int $variantId, int $quantity): int
    {
        $this->db->execute(
            'INSERT INTO bundle_items (bundle_id, product_id, variant_id, quantity)
             VALUES (:bundle_id, :product_id, :variant_id, :quantity)',
            ['bundle_id' => $bundleId, 'product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity]
        );
        return (int) $this->db->lastInsertId();
    }

    public function removeItem(int $itemId, int $bundleId): bool
    {
        return $this->db->execute(
            'DELETE FROM bundle_items WHERE id = :id AND bundle_id = :bundle_id',
            ['id' => $itemId, 'bundle_id' => $bundleId]
        ) > 0;
    }

    public function getItems(int $bundleId): array
    {
        return $this->db->fetchAll(
            'SELECT bi.*, p.name as product_name, p.uuid as product_uuid, p.base_price, p.sale_price,
                    pv.name as variant_name, pv.price as variant_price
             FROM bundle_items bi
             JOIN products p ON p.id = bi.product_id
             LEFT JOIN product_variants pv ON pv.id = bi.variant_id
             WHERE bi.bundle_id = :bundle_id
             ORDER BY bi.id ASC',
            ['bundle_id' => $bundleId]
        );
    }
}
