<?php

declare(strict_types=1);

namespace App\Modules\Offer\Service;

use App\Modules\Offer\Repository\PromotionRepository;

final class PromotionEngine
{
    public function __construct(private readonly PromotionRepository $repo)
    {
    }

    /**
     * Evaluate all active promotions against a cart and return applicable discounts.
     *
     * @return array{promotions: array, total_discount: int, free_delivery: bool}
     */
    public function evaluate(int $tenantId, int $subtotal, array $cartItems, int $deliveryFee = 0): array
    {
        $promotions = $this->repo->getActivePromotions($tenantId);

        $appliedPromotions = [];
        $totalDiscount = 0;
        $freeDelivery = false;

        foreach ($promotions as $promo) {
            $result = $this->evaluatePromotion($promo, $subtotal, $cartItems, $deliveryFee);

            if ($result === null) {
                continue;
            }

            if ($result['type'] === 'free_delivery') {
                $freeDelivery = true;
            } else {
                $totalDiscount += $result['discount'];
            }

            $appliedPromotions[] = [
                'promotion_id' => $promo['id'],
                'uuid' => $promo['uuid'],
                'title' => $promo['title'],
                'promotion_type' => $promo['promotion_type'],
                'discount' => $result['discount'],
                'type' => $result['type'],
                'description' => $result['description'],
            ];

            // Non-stackable promotion stops evaluation
            if (!$promo['is_stackable']) {
                break;
            }
        }

        // Discount cannot exceed subtotal
        if ($totalDiscount > $subtotal) {
            $totalDiscount = $subtotal;
        }

        return [
            'promotions' => $appliedPromotions,
            'total_discount' => $totalDiscount,
            'free_delivery' => $freeDelivery,
        ];
    }

    private function evaluatePromotion(array $promo, int $subtotal, array $cartItems, int $deliveryFee): ?array
    {
        if ($subtotal < (int) $promo['min_order_amount']) {
            return null;
        }

        $rules = json_decode($promo['rules_json'] ?? '{}', true) ?: [];

        return match ($promo['promotion_type']) {
            'order_discount' => $this->evaluateOrderDiscount($promo, $subtotal),
            'category_discount' => $this->evaluateCategoryDiscount($promo, $rules, $cartItems),
            'buy_x_get_y' => $this->evaluateBuyXGetY($promo, $rules, $cartItems),
            'free_delivery' => $this->evaluateFreeDelivery($promo, $subtotal, $deliveryFee),
            'flash_sale' => $this->evaluateOrderDiscount($promo, $subtotal), // same as order_discount
            default => null,
        };
    }

    private function evaluateOrderDiscount(array $promo, int $subtotal): array
    {
        $discount = $this->calcDiscount($promo, $subtotal);
        return [
            'discount' => $discount,
            'type' => 'order_discount',
            'description' => $promo['title'],
        ];
    }

    private function evaluateCategoryDiscount(array $promo, array $rules, array $cartItems): ?array
    {
        $categoryIds = $rules['category_ids'] ?? [];
        if (empty($categoryIds)) {
            return null;
        }

        $applicableAmount = 0;
        foreach ($cartItems as $item) {
            if (in_array($item['category_id'] ?? $item['category_uuid'] ?? '', $categoryIds, true)) {
                $applicableAmount += (int) ($item['line_total'] ?? 0);
            }
        }

        if ($applicableAmount <= 0) {
            return null;
        }

        $discount = $this->calcDiscount($promo, $applicableAmount);
        return [
            'discount' => $discount,
            'type' => 'category_discount',
            'description' => $promo['title'],
        ];
    }

    private function evaluateBuyXGetY(array $promo, array $rules, array $cartItems): ?array
    {
        $buyQty = $rules['buy_quantity'] ?? 0;
        $getQty = $rules['get_quantity'] ?? 0;
        $productIds = $rules['product_ids'] ?? [];

        if ($buyQty <= 0 || $getQty <= 0) {
            return null;
        }

        $totalQty = 0;
        $cheapestPrice = PHP_INT_MAX;

        foreach ($cartItems as $item) {
            $matchId = $item['product_uuid'] ?? $item['product_id'] ?? '';
            if (!empty($productIds) && !in_array($matchId, $productIds, true)) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 1);
            $totalQty += $qty;
            $unitPrice = (int) ($item['unit_price'] ?? 0);
            if ($unitPrice < $cheapestPrice) {
                $cheapestPrice = $unitPrice;
            }
        }

        if ($totalQty < $buyQty + $getQty) {
            return null;
        }

        // Free items = floor(totalQty / (buy+get)) * get_qty, priced at cheapest
        $sets = intdiv($totalQty, $buyQty + $getQty);
        $freeItems = $sets * $getQty;
        $discount = $freeItems * $cheapestPrice;

        return [
            'discount' => $discount,
            'type' => 'buy_x_get_y',
            'description' => "{$promo['title']} (Buy {$buyQty} Get {$getQty})",
        ];
    }

    private function evaluateFreeDelivery(array $promo, int $subtotal, int $deliveryFee): ?array
    {
        if ($deliveryFee <= 0) {
            return null;
        }

        return [
            'discount' => $deliveryFee,
            'type' => 'free_delivery',
            'description' => $promo['title'],
        ];
    }

    private function calcDiscount(array $promo, int $amount): int
    {
        $discount = match ($promo['discount_type']) {
            'percentage' => (int) floor($amount * (int) $promo['discount_value'] / 10000),
            'fixed' => min((int) $promo['discount_value'], $amount),
            default => 0,
        };

        if ($promo['max_discount_amount'] !== null && $discount > (int) $promo['max_discount_amount']) {
            $discount = (int) $promo['max_discount_amount'];
        }

        return $discount;
    }
}
