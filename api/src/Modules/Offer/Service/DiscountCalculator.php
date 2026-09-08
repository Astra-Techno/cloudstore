<?php

declare(strict_types=1);

namespace App\Modules\Offer\Service;

/**
 * Orchestrates all discount sources (coupons, promotions, bundles) into a single calculation result.
 * Used by the cart summary and checkout flow.
 */
final class DiscountCalculator
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly PromotionEngine $promotionEngine,
    ) {
    }

    /**
     * Calculate all applicable discounts for a cart.
     *
     * @return array{
     *   coupon_discount: int,
     *   promotion_discount: int,
     *   total_discount: int,
     *   free_delivery: bool,
     *   applied_coupon: ?array,
     *   applied_promotions: array,
     *   delivery_fee_discount: int,
     * }
     */
    public function calculate(
        int $tenantId,
        int $subtotal,
        array $cartItems,
        int $deliveryFee = 0,
        ?string $couponCode = null,
        ?int $customerId = null,
    ): array {
        $result = [
            'coupon_discount' => 0,
            'promotion_discount' => 0,
            'total_discount' => 0,
            'free_delivery' => false,
            'applied_coupon' => null,
            'applied_promotions' => [],
            'delivery_fee_discount' => 0,
        ];

        // 1. Auto-applied promotions
        $promoResult = $this->promotionEngine->evaluate($tenantId, $subtotal, $cartItems, $deliveryFee);
        $result['promotion_discount'] = $promoResult['total_discount'];
        $result['applied_promotions'] = $promoResult['promotions'];

        if ($promoResult['free_delivery']) {
            $result['free_delivery'] = true;
            $result['delivery_fee_discount'] = $deliveryFee;
        }

        // 2. Coupon (manual code entry)
        if ($couponCode !== null && $customerId !== null) {
            $couponResult = $this->couponService->validate($tenantId, $couponCode, $customerId, $subtotal, $cartItems);

            if (!isset($couponResult['error'])) {
                $result['coupon_discount'] = $couponResult['discount_amount'];
                $result['applied_coupon'] = [
                    'code' => $couponResult['coupon']['code'],
                    'title' => $couponResult['coupon']['title'],
                    'discount_type' => $couponResult['coupon']['discount_type'],
                    'discount_amount' => $couponResult['discount_amount'],
                ];

                // Free delivery coupon
                if ($couponResult['coupon']['discount_type'] === 'free_delivery') {
                    $result['free_delivery'] = true;
                    $result['delivery_fee_discount'] = $deliveryFee;
                }
            }
        }

        // Total discount = promotions + coupon (capped at subtotal)
        $result['total_discount'] = min(
            $result['promotion_discount'] + $result['coupon_discount'],
            $subtotal
        );

        return $result;
    }
}
