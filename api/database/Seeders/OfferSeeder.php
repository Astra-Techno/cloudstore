<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use Ramsey\Uuid\Uuid;

final class OfferSeeder
{
    public function run(Connection $db): void
    {
        echo "\nSeeding offers:\n";

        // Get tenants
        $tenants = $db->fetchAll('SELECT id, slug, name FROM tenants ORDER BY id');

        foreach ($tenants as $tenant) {
            $this->seedForTenant($db, (int) $tenant['id'], $tenant['slug'], $tenant['name']);
        }
    }

    private function seedForTenant(Connection $db, int $tenantId, string $slug, string $name): void
    {
        // ── Coupons ──
        $coupons = [
            [
                'code' => 'WELCOME10',
                'title' => '10% Off First Order',
                'description' => 'Get 10% off on your first order. New customers only!',
                'discount_type' => 'percentage',
                'discount_value' => 1000, // 10.00% in basis points
                'min_order_amount' => 20000, // Rs 200
                'max_discount_amount' => 10000, // Rs 100 cap
                'per_customer_limit' => 1,
                'usage_limit' => null,
            ],
            [
                'code' => 'FLAT50',
                'title' => 'Flat Rs 50 Off',
                'description' => 'Get flat Rs 50 off on orders above Rs 300.',
                'discount_type' => 'fixed',
                'discount_value' => 5000, // Rs 50
                'min_order_amount' => 30000, // Rs 300
                'max_discount_amount' => null,
                'per_customer_limit' => 3,
                'usage_limit' => 100,
            ],
            [
                'code' => 'FREEDELIVERY',
                'title' => 'Free Delivery',
                'description' => 'Free delivery on orders above Rs 500.',
                'discount_type' => 'free_delivery',
                'discount_value' => 0,
                'min_order_amount' => 50000, // Rs 500
                'max_discount_amount' => null,
                'per_customer_limit' => 5,
                'usage_limit' => null,
            ],
        ];

        foreach ($coupons as $c) {
            $db->execute(
                'INSERT INTO coupons (uuid, tenant_id, code, title, description, discount_type, discount_value,
                 min_order_amount, max_discount_amount, per_customer_limit, usage_limit, is_active, applies_to)
                 VALUES (:uuid, :tenant_id, :code, :title, :description, :discount_type, :discount_value,
                 :min_order_amount, :max_discount_amount, :per_customer_limit, :usage_limit, 1, :applies_to)',
                [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_id' => $tenantId,
                    'code' => $c['code'],
                    'title' => $c['title'],
                    'description' => $c['description'],
                    'discount_type' => $c['discount_type'],
                    'discount_value' => $c['discount_value'],
                    'min_order_amount' => $c['min_order_amount'],
                    'max_discount_amount' => $c['max_discount_amount'],
                    'per_customer_limit' => $c['per_customer_limit'],
                    'usage_limit' => $c['usage_limit'],
                    'applies_to' => 'all',
                ]
            );
        }

        // ── Promotions ──
        $promotions = [
            [
                'title' => 'Weekend Special: 15% Off',
                'description' => 'Auto-applied 15% discount on all orders above Rs 400 on weekends.',
                'promotion_type' => 'order_discount',
                'discount_type' => 'percentage',
                'discount_value' => 1500, // 15%
                'max_discount_amount' => 15000, // Rs 150 cap
                'min_order_amount' => 40000, // Rs 400
                'rules' => [],
                'priority' => 10,
                'is_stackable' => 1,
            ],
            [
                'title' => 'Free Delivery on Rs 600+',
                'description' => 'Free delivery automatically applied on orders above Rs 600.',
                'promotion_type' => 'free_delivery',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'max_discount_amount' => null,
                'min_order_amount' => 60000, // Rs 600
                'rules' => [],
                'priority' => 5,
                'is_stackable' => 1,
            ],
        ];

        foreach ($promotions as $p) {
            $db->execute(
                'INSERT INTO promotions (uuid, tenant_id, title, description, promotion_type, discount_type,
                 discount_value, max_discount_amount, min_order_amount, rules_json, priority, is_stackable, is_active)
                 VALUES (:uuid, :tenant_id, :title, :description, :promotion_type, :discount_type,
                 :discount_value, :max_discount_amount, :min_order_amount, :rules_json, :priority, :is_stackable, 1)',
                [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_id' => $tenantId,
                    'title' => $p['title'],
                    'description' => $p['description'],
                    'promotion_type' => $p['promotion_type'],
                    'discount_type' => $p['discount_type'],
                    'discount_value' => $p['discount_value'],
                    'max_discount_amount' => $p['max_discount_amount'],
                    'min_order_amount' => $p['min_order_amount'],
                    'rules_json' => json_encode($p['rules']),
                    'priority' => $p['priority'],
                    'is_stackable' => $p['is_stackable'],
                ]
            );
        }

        // ── Bundles ──
        $products = $db->fetchAll(
            'SELECT id, name, base_price, sale_price FROM products WHERE tenant_id = :tid AND status = :status ORDER BY RAND() LIMIT 4',
            ['tid' => $tenantId, 'status' => 'active']
        );

        if (count($products) >= 3) {
            $bundleProducts = array_slice($products, 0, 3);
            $originalPrice = array_sum(array_map(fn($p) => (int) ($p['sale_price'] ?? $p['base_price']), $bundleProducts));
            $bundlePrice = (int) floor($originalPrice * 0.85); // 15% savings

            $db->execute(
                'INSERT INTO bundles (uuid, tenant_id, name, slug, description, bundle_price, original_price, is_active, sort_order)
                 VALUES (:uuid, :tenant_id, :name, :slug, :description, :bundle_price, :original_price, 1, 0)',
                [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_id' => $tenantId,
                    'name' => 'Combo Meal Deal',
                    'slug' => 'combo-meal-deal',
                    'description' => 'Save 15% with our special combo! Includes 3 items.',
                    'bundle_price' => $bundlePrice,
                    'original_price' => $originalPrice,
                ]
            );
            $bundleId = (int) $db->lastInsertId();

            foreach ($bundleProducts as $bp) {
                $db->execute(
                    'INSERT INTO bundle_items (bundle_id, product_id, quantity) VALUES (:bundle_id, :product_id, 1)',
                    ['bundle_id' => $bundleId, 'product_id' => (int) $bp['id']]
                );
            }
        }

        $couponCount = count($coupons);
        $promoCount = count($promotions);
        $bundleCount = count($products) >= 3 ? 1 : 0;
        echo "  Seeded {$name}: {$couponCount} coupons, {$promoCount} promotions, {$bundleCount} bundle\n";
    }
}
