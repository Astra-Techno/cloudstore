<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use App\Modules\Delivery\Repository\DeliveryZoneRepository;
use App\Modules\Tenant\Repository\TenantRepository;
use Ramsey\Uuid\Uuid;

final class DeliveryZoneSeeder
{
    public function run(Connection $db): void
    {
        $tenantRepo = new TenantRepository($db);
        $zoneRepo = new DeliveryZoneRepository($db);

        $tenants = [
            'jeyam-mutton' => [
                ['name' => 'Nearby (0-3 km)', 'min' => 0, 'max' => 3, 'fee' => 3000, 'free_above' => 50000],
                ['name' => 'Mid-range (3-7 km)', 'min' => 3, 'max' => 7, 'fee' => 5000, 'free_above' => 100000],
                ['name' => 'Far (7-12 km)', 'min' => 7, 'max' => 12, 'fee' => 8000, 'free_above' => null],
            ],
            'hotel-abc' => [
                ['name' => 'Nearby (0-5 km)', 'min' => 0, 'max' => 5, 'fee' => 2000, 'free_above' => 30000],
                ['name' => 'Mid-range (5-10 km)', 'min' => 5, 'max' => 10, 'fee' => 4000, 'free_above' => 60000],
            ],
            'amma-home-kitchen' => [
                ['name' => 'Nearby (0-4 km)', 'min' => 0, 'max' => 4, 'fee' => 2500, 'free_above' => 40000],
                ['name' => 'Extended (4-8 km)', 'min' => 4, 'max' => 8, 'fee' => 5000, 'free_above' => null],
            ],
        ];

        foreach ($tenants as $slug => $zones) {
            $tenant = $tenantRepo->findBySlug($slug);
            if ($tenant === null) {
                echo "  Tenant '{$slug}' not found, skipping.\n";
                continue;
            }

            foreach ($zones as $order => $zone) {
                $zoneRepo->create([
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_id' => $tenant->id,
                    'name' => $zone['name'],
                    'min_distance_km' => $zone['min'],
                    'max_distance_km' => $zone['max'],
                    'fee' => $zone['fee'],
                    'min_order_free_delivery' => $zone['free_above'],
                    'sort_order' => $order,
                ]);
            }

            echo "  Seeded " . count($zones) . " delivery zones for {$slug}\n";
        }
    }
}
