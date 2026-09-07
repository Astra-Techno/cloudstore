<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repository;

use App\Core\Database\Connection;

final class AddonRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    // Addon Groups

    public function findGroupsByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM addon_groups WHERE tenant_id = ? AND status = 'active' ORDER BY sort_order ASC",
            [$tenantId]
        );
    }

    public function findGroupById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM addon_groups WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function updateGroup(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'is_required', 'min_selections', 'max_selections', 'sort_order', 'status'], true)) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return;
        }

        $values[] = $id;

        $this->db->execute(
            "UPDATE addon_groups SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
    }

    public function findItemById(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM addon_items WHERE id = ?", [$id]);
    }

    public function deleteItem(int $itemId, int $groupId): bool
    {
        $affected = $this->db->execute(
            "DELETE FROM addon_items WHERE id = ? AND group_id = ?",
            [$itemId, $groupId]
        );

        return $affected > 0;
    }

    public function createGroup(array $data): int
    {
        $this->db->execute(
            "INSERT INTO addon_groups (uuid, tenant_id, name, is_required, min_selections, max_selections, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['tenant_id'],
                $data['name'],
                $data['is_required'] ?? 0,
                $data['min_selections'] ?? 0,
                $data['max_selections'] ?? 5,
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'active',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    // Addon Items

    public function findItemsByGroup(int $groupId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM addon_items WHERE group_id = ? AND status = 'active' ORDER BY sort_order ASC",
            [$groupId]
        );
    }

    public function createItem(array $data): int
    {
        $this->db->execute(
            "INSERT INTO addon_items (uuid, group_id, name, price, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['group_id'],
                $data['name'],
                $data['price'],
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'active',
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    // Product-Addon linking

    public function attachGroupToProduct(int $productId, int $addonGroupId): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO product_addon_groups (product_id, addon_group_id) VALUES (?, ?)",
            [$productId, $addonGroupId]
        );
    }

    public function detachGroupFromProduct(int $productId, int $addonGroupId): void
    {
        $this->db->execute(
            "DELETE FROM product_addon_groups WHERE product_id = ? AND addon_group_id = ?",
            [$productId, $addonGroupId]
        );
    }

    public function getGroupsForProduct(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT ag.* FROM addon_groups ag
             JOIN product_addon_groups pag ON pag.addon_group_id = ag.id
             WHERE pag.product_id = ? AND ag.status = 'active'
             ORDER BY ag.sort_order ASC",
            [$productId]
        );
    }

    public function getGroupsWithItemsForProduct(int $productId): array
    {
        $groups = $this->getGroupsForProduct($productId);

        foreach ($groups as &$group) {
            $group['items'] = $this->findItemsByGroup((int) $group['id']);
        }

        return $groups;
    }
}
