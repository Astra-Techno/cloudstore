<?php

declare(strict_types=1);

namespace App\Modules\Cart\Repository;

use App\Core\Database\Connection;

final class CartRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findActiveByCustomer(int $customerId, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM carts WHERE customer_id = ? AND tenant_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            [$customerId, $tenantId]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO carts (uuid, tenant_id, customer_id, session_id, status)
             VALUES (?, ?, ?, ?, ?)",
            [$data['uuid'], $data['tenant_id'], $data['customer_id'] ?? null, $data['session_id'] ?? null, 'active']
        );

        return (int) $this->db->lastInsertId();
    }

    public function getItems(int $cartId): array
    {
        return $this->db->fetchAll(
            "SELECT ci.*, p.name as product_name, p.slug as product_slug, p.base_price, p.sale_price,
                    p.pricing_mode, p.unit, p.status as product_status, p.stock_mode, p.stock_quantity,
                    p.tenant_id,
                    pv.name as variant_name, pv.price as variant_price, pv.status as variant_status,
                    pv.stock_mode as variant_stock_mode, pv.stock_quantity as variant_stock_quantity
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_variants pv ON pv.id = ci.variant_id
             WHERE ci.cart_id = ?
             ORDER BY ci.created_at ASC",
            [$cartId]
        );
    }

    public function addItem(array $data): int
    {
        // Check if same product+variant already in cart
        $existing = $this->db->fetchOne(
            "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))",
            [$data['cart_id'], $data['product_id'], $data['variant_id'] ?? null, $data['variant_id'] ?? null]
        );

        if ($existing) {
            $newQty = (int) $existing['quantity'] + ($data['quantity'] ?? 1);
            $this->db->execute(
                "UPDATE cart_items SET quantity = ?, unit_price = ?, addons_json = ?, addons_price = ? WHERE id = ?",
                [$newQty, $data['unit_price'], $data['addons_json'] ?? null, $data['addons_price'] ?? 0, $existing['id']]
            );

            return (int) $existing['id'];
        }

        $this->db->execute(
            "INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price, addons_json, addons_price, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['cart_id'], $data['product_id'], $data['variant_id'] ?? null,
                $data['quantity'] ?? 1, $data['unit_price'],
                $data['addons_json'] ?? null, $data['addons_price'] ?? 0, $data['notes'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateItemQuantity(int $itemId, int $cartId, int $quantity): void
    {
        $this->db->execute(
            "UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?",
            [$quantity, $itemId, $cartId]
        );
    }

    public function removeItem(int $itemId, int $cartId): void
    {
        $this->db->execute(
            "DELETE FROM cart_items WHERE id = ? AND cart_id = ?",
            [$itemId, $cartId]
        );
    }

    public function clearCart(int $cartId): void
    {
        $this->db->execute("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
    }

    public function markCompleted(int $cartId): void
    {
        $this->db->execute("UPDATE carts SET status = 'completed' WHERE id = ?", [$cartId]);
    }
}
