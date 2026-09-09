<?php

declare(strict_types=1);

namespace App\Modules\Offer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Database\Connection;
use App\Modules\Offer\Repository\CouponRepository;
use App\Modules\Offer\Repository\PromotionRepository;
use App\Modules\Offer\Repository\BundleRepository;
use App\Modules\Offer\Service\CouponService;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class AdminOfferController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CouponRepository $couponRepo,
        private readonly CouponService $couponService,
        private readonly PromotionRepository $promotionRepo,
        private readonly BundleRepository $bundleRepo,
    ) {
    }

    // ── Coupons ──

    public function listCoupons(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $page = max(1, (int) ($request->input('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->input('per_page') ?? 20)));
        $offset = ($page - 1) * $perPage;

        $coupons = $this->couponRepo->listByTenant($tenantId, $perPage, $offset);
        $total = $this->couponRepo->countByTenant($tenantId);

        return Response::success($coupons, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function getCoupon(Request $request, array $params): Response
    {
        $coupon = $this->couponRepo->findByUuid($params['uuid'], TenantContext::id());
        if ($coupon === null) {
            return Response::notFound('Coupon not found.');
        }
        return Response::success($coupon);
    }

    public function createCoupon(Request $request, array $params): Response
    {
        $data = $request->json();
        $validator = new Validator();

        if (!$validator->validate($data, [
            'code' => ['required', 'string', 'min:3', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed,free_delivery'],
            'discount_value' => ['required', 'integer', 'min:0'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $result = $this->couponService->create(TenantContext::id(), $data);

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 422);
        }

        return Response::success($result, status: 201);
    }

    public function updateCoupon(Request $request, array $params): Response
    {
        $data = $request->json();
        $result = $this->couponService->update(TenantContext::id(), $params['uuid'], $data);

        if (isset($result['error'])) {
            $status = $result['code'] === 'NOT_FOUND' ? 404 : 422;
            return Response::error($result['error'], $result['code'], $status);
        }

        return Response::success($result);
    }

    public function deleteCoupon(Request $request, array $params): Response
    {
        $result = $this->couponService->delete(TenantContext::id(), $params['uuid']);

        if (isset($result['error'])) {
            return Response::notFound($result['error']);
        }

        return Response::success(['deleted' => true]);
    }

    // ── Promotions ──

    public function listPromotions(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $page = max(1, (int) ($request->input('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->input('per_page') ?? 20)));
        $offset = ($page - 1) * $perPage;

        $promotions = $this->promotionRepo->listByTenant($tenantId, $perPage, $offset);
        $total = $this->promotionRepo->countByTenant($tenantId);

        // Decode rules_json for each promotion
        foreach ($promotions as &$p) {
            $p['rules'] = json_decode($p['rules_json'] ?? '{}', true);
        }

        return Response::success($promotions, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function getPromotion(Request $request, array $params): Response
    {
        $promo = $this->promotionRepo->findByUuid($params['uuid'], TenantContext::id());
        if ($promo === null) {
            return Response::notFound('Promotion not found.');
        }
        $promo['rules'] = json_decode($promo['rules_json'] ?? '{}', true);
        return Response::success($promo);
    }

    public function createPromotion(Request $request, array $params): Response
    {
        $data = $request->json();
        $validator = new Validator();

        if (!$validator->validate($data, [
            'title' => ['required', 'string', 'max:255'],
            'promotion_type' => ['required', 'string', 'in:buy_x_get_y,category_discount,order_discount,free_delivery,flash_sale'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['required', 'integer', 'min:0'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $tenantId = TenantContext::id();
        $id = $this->promotionRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'promotion_type' => $data['promotion_type'],
            'discount_type' => $data['discount_type'],
            'discount_value' => (int) $data['discount_value'],
            'max_discount_amount' => isset($data['max_discount_amount']) ? (int) $data['max_discount_amount'] : null,
            'min_order_amount' => (int) ($data['min_order_amount'] ?? 0),
            'rules_json' => json_encode($data['rules'] ?? []),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_stackable' => (int) ($data['is_stackable'] ?? 0),
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
        ]);

        $row = $this->db->fetchOne(
            'SELECT uuid FROM promotions WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $tenantId]
        );
        $promo = $this->promotionRepo->findByUuid($row['uuid'], $tenantId);
        $promo['rules'] = json_decode($promo['rules_json'] ?? '{}', true);

        return Response::success($promo, status: 201);
    }

    public function updatePromotion(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $promo = $this->promotionRepo->findByUuid($params['uuid'], $tenantId);
        if ($promo === null) {
            return Response::notFound('Promotion not found.');
        }

        $data = $request->json();
        $fields = [];
        $allowed = ['title', 'description', 'promotion_type', 'discount_type', 'discount_value',
                     'max_discount_amount', 'min_order_amount', 'priority', 'is_stackable',
                     'usage_limit', 'starts_at', 'expires_at', 'is_active'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (in_array($key, ['discount_value', 'max_discount_amount', 'min_order_amount', 'priority', 'usage_limit', 'is_stackable', 'is_active'], true)) {
                    $value = $value !== null ? (int) $value : null;
                }
                $fields[$key] = $value;
            }
        }

        if (array_key_exists('rules', $data)) {
            $fields['rules_json'] = json_encode($data['rules']);
        }

        $this->promotionRepo->update((int) $promo['id'], $tenantId, $fields);

        $updated = $this->promotionRepo->findByUuid($params['uuid'], $tenantId);
        $updated['rules'] = json_decode($updated['rules_json'] ?? '{}', true);
        return Response::success($updated);
    }

    public function deletePromotion(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $promo = $this->promotionRepo->findByUuid($params['uuid'], $tenantId);
        if ($promo === null) {
            return Response::notFound('Promotion not found.');
        }

        $this->promotionRepo->delete((int) $promo['id'], $tenantId);
        return Response::success(['deleted' => true]);
    }

    // ── Bundles ──

    public function listBundles(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $page = max(1, (int) ($request->input('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->input('per_page') ?? 20)));
        $offset = ($page - 1) * $perPage;

        $bundles = $this->bundleRepo->listByTenant($tenantId, $perPage, $offset);
        $total = $this->bundleRepo->countByTenant($tenantId);

        // Attach items to each bundle
        foreach ($bundles as &$bundle) {
            $bundle['items'] = $this->bundleRepo->getItems((int) $bundle['id']);
        }

        return Response::success($bundles, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function getBundle(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $bundle = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        if ($bundle === null) {
            return Response::notFound('Bundle not found.');
        }
        $bundle['items'] = $this->bundleRepo->getItems((int) $bundle['id']);
        return Response::success($bundle);
    }

    public function createBundle(Request $request, array $params): Response
    {
        $data = $request->json();
        $validator = new Validator();

        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'max:255'],
            'bundle_price' => ['required', 'integer', 'min:0'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $tenantId = TenantContext::id();
        $slug = $this->generateSlug($data['name']);
        $uuid = Uuid::uuid4()->toString();

        $bundleId = $this->bundleRepo->create([
            'uuid' => $uuid,
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'bundle_price' => (int) $data['bundle_price'],
            'original_price' => (int) ($data['original_price'] ?? 0),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $bundle = $this->bundleRepo->findByUuid($uuid, $tenantId);
        $bundle['items'] = [];
        return Response::success($bundle, status: 201);
    }

    public function updateBundle(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $bundle = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        if ($bundle === null) {
            return Response::notFound('Bundle not found.');
        }

        $data = $request->json();
        $fields = [];
        $allowed = ['name', 'description', 'bundle_price', 'original_price', 'image_url',
                     'is_active', 'starts_at', 'expires_at', 'sort_order'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (in_array($key, ['bundle_price', 'original_price', 'is_active', 'sort_order'], true)) {
                    $value = $value !== null ? (int) $value : null;
                }
                $fields[$key] = $value;
            }
        }

        if (isset($data['name']) && $data['name'] !== $bundle['name']) {
            $fields['slug'] = $this->generateSlug($data['name']);
        }

        $this->bundleRepo->update((int) $bundle['id'], $tenantId, $fields);

        $updated = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        $updated['items'] = $this->bundleRepo->getItems((int) $updated['id']);
        return Response::success($updated);
    }

    public function deleteBundle(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $bundle = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        if ($bundle === null) {
            return Response::notFound('Bundle not found.');
        }

        $this->bundleRepo->delete((int) $bundle['id'], $tenantId);
        return Response::success(['deleted' => true]);
    }

    public function addBundleItem(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $bundle = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        if ($bundle === null) {
            return Response::notFound('Bundle not found.');
        }

        $data = $request->json();
        $validator = new Validator();
        if (!$validator->validate($data, [
            'product_id' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $this->bundleRepo->addItem(
            (int) $bundle['id'],
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) ($data['quantity'] ?? 1),
        );

        $updated = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        $updated['items'] = $this->bundleRepo->getItems((int) $updated['id']);
        return Response::success($updated, status: 201);
    }

    public function removeBundleItem(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $bundle = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        if ($bundle === null) {
            return Response::notFound('Bundle not found.');
        }

        $this->bundleRepo->removeItem((int) $params['itemId'], (int) $bundle['id']);

        $updated = $this->bundleRepo->findByUuid($params['uuid'], $tenantId);
        $updated['items'] = $this->bundleRepo->getItems((int) $updated['id']);
        return Response::success($updated);
    }

    // ── Helpers ──

    private function generateSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
    }

}
