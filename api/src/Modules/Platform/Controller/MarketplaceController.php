<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controller;

use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Catalog\Service\CatalogService;
use App\Modules\Delivery\Service\DeliveryFeeService;

/** Public discovery API for the CloudMarket customer app. */
final class MarketplaceController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CatalogService $catalogService,
        private readonly DeliveryFeeService $deliveryFeeService,
    ) {
    }

    public function stores(Request $request, array $params): Response
    {
        $search = trim((string) $request->input('q', ''));
        $type = trim((string) $request->input('business_type', ''));
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $hasLocation = is_numeric($latitude) && is_numeric($longitude);
        $sql = "SELECT t.id AS tenant_id, t.uuid, t.name, t.slug, t.business_type, t.address, t.contact_phone, t.configuration,
                       b.logo_url, b.primary_color,
                       COUNT(p.id) AS product_count
                FROM tenants t
                LEFT JOIN tenant_branding b ON b.tenant_id = t.id
                LEFT JOIN products p ON p.tenant_id = t.id AND p.status = 'active' AND p.deleted_at IS NULL
                WHERE t.status = 'active' AND t.commercial_plan = 'marketplace' AND t.marketplace_status = 'active'";
        $values = [];
        if ($search !== '') {
            $sql .= " AND (t.name LIKE ? OR t.business_type LIKE ? OR t.address LIKE ?)";
            $values = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }
        if ($type !== '') {
            $sql .= ' AND t.business_type = ?';
            $values[] = $type;
        }
        $sql .= ' GROUP BY t.id ORDER BY t.marketplace_sort_order ASC, t.name ASC';
        $stores = $this->db->fetchAll($sql, $values);
        $result = [];
        foreach ($stores as $store) {
            $config = json_decode((string) ($store['configuration'] ?? ''), true) ?: [];
            $location = $config['delivery'] ?? [];
            if ($hasLocation) {
                if (!is_numeric($location['latitude'] ?? null) || !is_numeric($location['longitude'] ?? null)) continue;
                $distance = DeliveryFeeService::haversineDistance((float) $location['latitude'], (float) $location['longitude'], (float) $latitude, (float) $longitude);
                if ($this->deliveryFeeService->calculate((int) $store['tenant_id'], $distance, 0) === null) continue;
                $store['distance_km'] = round($distance, 1);
            }
            unset($store['tenant_id'], $store['configuration']);
            $result[] = $store;
        }
        return Response::success($result);
    }

    public function store(Request $request, array $params): Response
    {
        $store = $this->findActiveStore($params['slug']);
        if ($store === null) return Response::notFound('Store not found.');
        return Response::success($store);
    }

    public function catalog(Request $request, array $params): Response
    {
        $store = $this->findActiveStore($params['slug']);
        if ($store === null) return Response::notFound('Store not found.');
        $store['catalog'] = $this->catalogService->getPublicCatalog((int) $store['tenant_id']);
        unset($store['tenant_id']);
        return Response::success($store);
    }

    private function findActiveStore(string $slug): ?array
    {
        return $this->db->fetchOne(
            "SELECT t.id AS tenant_id, t.uuid, t.name, t.slug, t.business_type, t.address, t.contact_phone,
                    b.logo_url, b.primary_color
             FROM tenants t LEFT JOIN tenant_branding b ON b.tenant_id = t.id
             WHERE t.slug = ? AND t.status = 'active' AND t.commercial_plan = 'marketplace'
               AND t.marketplace_status = 'active' AND t.deleted_at IS NULL",
            [$slug],
        );
    }
}
