<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Database\Connection;
use App\Modules\Delivery\Repository\DeliveryZoneRepository;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use Ramsey\Uuid\Uuid;

final class AdminSettingsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly DeliveryZoneRepository $zoneRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly OrderRepository $orderRepo,
        private readonly TenantRepository $tenantRepo,
        private readonly BrandingRepository $brandingRepo,
    ) {
    }

    // --- Delivery Zones ---

    public function listZones(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $zones = $this->zoneRepo->findAllByTenant($tenantId);

        return Response::success($zones);
    }

    public function createZone(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'min_distance_km' => ['required'],
            'max_distance_km' => ['required'],
            'fee' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $id = $this->zoneRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'min_distance_km' => (float) $data['min_distance_km'],
            'max_distance_km' => (float) $data['max_distance_km'],
            'fee' => (int) $data['fee'],
            'min_order_free_delivery' => isset($data['min_order_free_delivery']) ? (int) $data['min_order_free_delivery'] : null,
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $zone = $this->zoneRepo->findById($id, $tenantId);

        return Response::success($zone, status: 201);
    }

    public function updateZone(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $zone = $this->zoneRepo->findById((int) $params['zoneId'], $tenantId);
        if ($zone === null) {
            return Response::notFound('Delivery zone not found.');
        }

        $this->zoneRepo->update((int) $params['zoneId'], $tenantId, $data);
        $updated = $this->zoneRepo->findById((int) $params['zoneId'], $tenantId);

        return Response::success($updated);
    }

    public function deleteZone(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $deleted = $this->zoneRepo->delete((int) $params['zoneId'], $tenantId);
        if (!$deleted) {
            return Response::notFound('Delivery zone not found.');
        }

        return Response::success(['deleted' => true]);
    }

    // --- Customers ---

    public function listCustomers(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $page = (int) ($request->input('page', 1));
        $perPage = min((int) ($request->input('per_page', 50)), 100);
        $search = $request->input('search');
        $offset = ($page - 1) * $perPage;

        $customers = $this->customerRepo->findAllByTenant($tenantId, $perPage, $offset, $search);
        $total = $this->customerRepo->countByTenant($tenantId, $search);

        // Attach order stats per customer
        foreach ($customers as &$c) {
            $stats = $this->db->fetchOne(
                "SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
                 FROM orders WHERE customer_id = ? AND tenant_id = ? AND status != 'cancelled'",
                [(int) $c['id'], $tenantId]
            );
            $c['order_count'] = (int) ($stats['order_count'] ?? 0);
            $c['total_spent'] = (int) ($stats['total_spent'] ?? 0);
        }

        return Response::success($customers, [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage) ?: 1,
        ]);
    }

    public function getCustomer(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $customer = $this->customerRepo->findByUuid($params['uuid'], $tenantId);
        if ($customer === null) {
            return Response::notFound('Customer not found.');
        }

        // Get order stats
        $stats = $this->db->fetchOne(
            "SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
             FROM orders WHERE customer_id = ? AND tenant_id = ? AND status != 'cancelled'",
            [(int) $customer['id'], $tenantId]
        );
        $customer['order_count'] = (int) ($stats['order_count'] ?? 0);
        $customer['total_spent'] = (int) ($stats['total_spent'] ?? 0);

        // Get recent orders
        $orders = $this->db->fetchAll(
            "SELECT uuid, order_number, status, total, payment_method, payment_status, created_at
             FROM orders WHERE customer_id = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT 20",
            [(int) $customer['id'], $tenantId]
        );
        $customer['recent_orders'] = $orders;

        return Response::success($customer);
    }

    // --- Store Settings ---

    public function getSettings(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $tenant = $this->tenantRepo->findById($tenantId);
        $branding = $this->brandingRepo->findByTenant($tenantId);

        // Get store hours from tenant metadata
        $tenantRow = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $metadata = [];
        if (!empty($tenantRow['configuration'])) {
            $metadata = json_decode($tenantRow['configuration'], true) ?: [];
        }

        return Response::success([
            'store' => [
                'name' => $tenant?->name ?? '',
                'slug' => $tenant?->slug ?? '',
                'business_type' => $tenant?->businessType ?? '',
                'status' => $tenant?->status ?? 'active',
            ],
            'branding' => $branding,
            'business_hours' => $metadata['business_hours'] ?? $this->defaultBusinessHours(),
            'preparation_time_default' => $metadata['preparation_time_default'] ?? 30,
            'min_order_amount' => $metadata['min_order_amount'] ?? 0,
            'tax_rate' => $metadata['tax_rate'] ?? 0,
            'delivery_enabled' => $metadata['delivery_enabled'] ?? true,
            'pickup_enabled' => $metadata['pickup_enabled'] ?? true,
            'payment_methods' => $metadata['payment_methods'] ?? ['cod'],
        ]);
    }

    public function updateSettings(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $tenantRow = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $metadata = [];
        if (!empty($tenantRow['configuration'])) {
            $metadata = json_decode($tenantRow['configuration'], true) ?: [];
        }

        // Update metadata fields
        $settableKeys = ['business_hours', 'preparation_time_default', 'min_order_amount', 'tax_rate', 'delivery_enabled', 'pickup_enabled', 'payment_methods'];
        foreach ($settableKeys as $key) {
            if (array_key_exists($key, $data)) {
                $metadata[$key] = $data[$key];
            }
        }

        $this->db->execute(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($metadata), $tenantId]
        );

        // Update branding if provided
        if (isset($data['branding'])) {
            $brandingData = $data['branding'];
            $this->db->execute(
                "UPDATE tenant_branding SET primary_color = ?, logo_url = ?, tagline = ? WHERE tenant_id = ?",
                [
                    $brandingData['primary_color'] ?? '#2563eb',
                    $brandingData['logo_url'] ?? null,
                    $brandingData['tagline'] ?? null,
                    $tenantId,
                ]
            );
        }

        return $this->getSettings($request, $params);
    }

    // --- Store Live Toggle ---

    public function toggleLive(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $tenant = $this->tenantRepo->findById($tenantId);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $newStatus = ($data['live'] ?? false) ? 'active' : 'draft';

        $this->db->execute(
            "UPDATE tenants SET status = ? WHERE id = ?",
            [$newStatus, $tenantId]
        );

        return Response::success(['status' => $newStatus, 'live' => $newStatus === 'active']);
    }

    // --- Dashboard Enhanced ---

    public function dashboardEnhanced(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        // Basic stats
        $today = date('Y-m-d');
        $todayStats = $this->db->fetchOne(
            "SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as revenue
             FROM orders WHERE tenant_id = ? AND DATE(created_at) = ? AND status != 'cancelled'",
            [$tenantId, $today]
        );

        // Weekly revenue (last 7 days)
        $weeklyRevenue = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue
             FROM orders WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'cancelled'
             GROUP BY DATE(created_at) ORDER BY date ASC",
            [$tenantId]
        );

        // Top products (last 30 days)
        $topProducts = $this->db->fetchAll(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.name')) as product_name,
                    SUM(oi.quantity) as total_qty, SUM(oi.line_total) as total_revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.tenant_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND o.status != 'cancelled'
             GROUP BY product_name ORDER BY total_qty DESC LIMIT 5",
            [$tenantId]
        );

        // Average order value
        $avgOrder = $this->db->fetchOne(
            "SELECT AVG(total) as avg_value FROM orders WHERE tenant_id = ? AND status != 'cancelled' AND total > 0",
            [$tenantId]
        );

        // Customer count
        $customerCount = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM customers WHERE tenant_id = ?",
            [$tenantId]
        );

        // Order status breakdown
        $statusBreakdown = $this->db->fetchAll(
            "SELECT status, COUNT(*) as count FROM orders WHERE tenant_id = ? AND status NOT IN ('delivered', 'cancelled')
             GROUP BY status ORDER BY count DESC",
            [$tenantId]
        );

        $activeOrders = [];
        foreach ($statusBreakdown as $row) {
            $activeOrders[$row['status']] = (int) $row['count'];
        }

        // Low stock products
        $lowStock = $this->db->fetchAll(
            "SELECT uuid, name, stock_quantity FROM products
             WHERE tenant_id = ? AND stock_mode = 'limited_stock' AND stock_quantity <= 10 AND deleted_at IS NULL
             ORDER BY stock_quantity ASC LIMIT 10",
            [$tenantId]
        );

        return Response::success([
            'today_orders' => (int) ($todayStats['order_count'] ?? 0),
            'today_revenue' => (int) ($todayStats['revenue'] ?? 0),
            'avg_order_value' => (int) ($avgOrder['avg_value'] ?? 0),
            'total_customers' => (int) ($customerCount['cnt'] ?? 0),
            'active_orders' => $activeOrders,
            'weekly_revenue' => $weeklyRevenue,
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
        ]);
    }

    private function defaultBusinessHours(): array
    {
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = [
                'open' => true,
                'start' => '09:00',
                'end' => '22:00',
            ];
        }

        return $hours;
    }
}
