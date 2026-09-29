<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;

final class InventoryRestorationService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** Restore limited stock once when an order is cancelled or rejected. */
    public function restore(int $tenantId, int $orderId): bool
    {
        return $this->db->transaction(function () use ($tenantId, $orderId): bool {
            $claimed = $this->db->execute(
                'UPDATE orders SET stock_restored_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND stock_restored_at IS NULL',
                [$orderId, $tenantId]
            );
            if ($claimed !== 1) {
                return false;
            }

            $items = $this->db->fetchAll(
                'SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?',
                [$orderId]
            );
            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                if ($quantity <= 0) {
                    continue;
                }
                if ($item['variant_id'] !== null) {
                    $this->db->execute(
                        "UPDATE product_variants v
                         JOIN products p ON p.id = v.product_id
                         SET v.stock_quantity = COALESCE(v.stock_quantity, 0) + ?
                         WHERE v.id = ? AND p.tenant_id = ? AND v.stock_mode = 'limited_stock'",
                        [$quantity, (int) $item['variant_id'], $tenantId]
                    );
                } elseif ($item['product_id'] !== null) {
                    $this->db->execute(
                        "UPDATE products SET stock_quantity = COALESCE(stock_quantity, 0) + ?
                         WHERE id = ? AND tenant_id = ? AND stock_mode = 'limited_stock'",
                        [$quantity, (int) $item['product_id'], $tenantId]
                    );
                }
            }
            return true;
        });
    }
}
