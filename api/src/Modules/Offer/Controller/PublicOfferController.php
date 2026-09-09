<?php

declare(strict_types=1);

namespace App\Modules\Offer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Offer\Repository\BundleRepository;
use App\Modules\Offer\Repository\PromotionRepository;
use App\Modules\Offer\Service\CouponService;
use App\Modules\Offer\Service\DiscountCalculator;
use App\Modules\Cart\Repository\CartRepository;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Tenant\Domain\TenantContext;

final class PublicOfferController
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly DiscountCalculator $discountCalc,
        private readonly PromotionRepository $promotionRepo,
        private readonly BundleRepository $bundleRepo,
        private readonly CartRepository $cartRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    /**
     * GET /offers — list all active offers (promotions + bundles) for the tenant.
     */
    public function activeOffers(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();

        $promotions = $this->promotionRepo->getActivePromotions($tenantId);
        $bundles = $this->bundleRepo->getActiveBundles($tenantId);

        // Clean up for public display
        $promos = array_map(fn($p) => [
            'uuid' => $p['uuid'],
            'title' => $p['title'],
            'description' => $p['description'],
            'promotion_type' => $p['promotion_type'],
            'discount_type' => $p['discount_type'],
            'discount_value' => (int) $p['discount_value'],
            'min_order_amount' => (int) $p['min_order_amount'],
            'starts_at' => $p['starts_at'],
            'expires_at' => $p['expires_at'],
        ], $promotions);

        $bndls = array_map(fn($b) => [
            'uuid' => $b['uuid'],
            'name' => $b['name'],
            'description' => $b['description'],
            'bundle_price' => (int) $b['bundle_price'],
            'original_price' => (int) $b['original_price'],
            'savings' => (int) $b['original_price'] - (int) $b['bundle_price'],
            'image_url' => $b['image_url'],
            'starts_at' => $b['starts_at'],
            'expires_at' => $b['expires_at'],
        ], $bundles);

        return Response::success([
            'promotions' => array_values($promos),
            'bundles' => array_values($bndls),
        ]);
    }

    /**
     * POST /customer/cart/apply-coupon — validate and apply a coupon to current cart.
     */
    public function applyCoupon(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $data = $request->json();
        $code = trim($data['code'] ?? '');
        if ($code === '') {
            return Response::error('Coupon code is required.', 'MISSING_CODE', 400);
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];

        // Get cart subtotal
        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::error('No active cart.', 'CART_EMPTY', 400);
        }

        $items = $this->cartRepo->getItems((int) $cart['id']);
        $subtotal = 0;
        $cartItems = [];
        foreach ($items as $item) {
            $unitPrice = (int) ($item['unit_price'] ?? $item['base_price'] ?? 0);
            $addonsPrice = (int) ($item['addons_price'] ?? 0);
            $qty = (int) $item['quantity'];
            $lineTotal = ($unitPrice + $addonsPrice) * $qty;
            $subtotal += $lineTotal;
            $cartItems[] = array_merge($item, ['line_total' => $lineTotal]);
        }

        $result = $this->couponService->validate($tenantId, $code, $customerId, $subtotal, $cartItems);

        if (isset($result['error'])) {
            $status = match ($result['code']) {
                'INVALID_CODE' => 404,
                'MIN_ORDER_NOT_MET' => 422,
                default => 400,
            };
            return Response::error($result['error'], $result['code'], $status);
        }

        return Response::success([
            'coupon' => [
                'code' => $result['coupon']['code'],
                'title' => $result['coupon']['title'],
                'discount_type' => $result['coupon']['discount_type'],
            ],
            'discount_amount' => $result['discount_amount'],
            'subtotal' => $subtotal,
            'new_subtotal' => $subtotal - $result['discount_amount'],
        ]);
    }

    /**
     * GET /customer/cart/discounts — preview all applicable discounts for current cart.
     */
    public function cartDiscounts(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];

        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::success([
                'coupon_discount' => 0,
                'promotion_discount' => 0,
                'total_discount' => 0,
                'free_delivery' => false,
                'applied_promotions' => [],
            ]);
        }

        $items = $this->cartRepo->getItems((int) $cart['id']);
        $subtotal = 0;
        $cartItems = [];
        foreach ($items as $item) {
            $unitPrice = (int) ($item['unit_price'] ?? $item['base_price'] ?? 0);
            $addonsPrice = (int) ($item['addons_price'] ?? 0);
            $qty = (int) $item['quantity'];
            $lineTotal = ($unitPrice + $addonsPrice) * $qty;
            $subtotal += $lineTotal;
            $cartItems[] = array_merge($item, ['line_total' => $lineTotal]);
        }

        $couponCode = $request->input('coupon_code');
        $result = $this->discountCalc->calculate($tenantId, $subtotal, $cartItems, 0, $couponCode, $customerId);

        return Response::success($result);
    }
}
