<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final class PricingCalculator
{
    /**
     * Get the effective price for a product/variant in minor units.
     */
    public static function getEffectivePrice(array $product, ?array $variant = null): int
    {
        if ($variant !== null) {
            return (int) $variant['price'];
        }

        $salePrice = $product['sale_price'] ?? null;
        if ($salePrice !== null && (int) $salePrice > 0) {
            return (int) $salePrice;
        }

        return (int) $product['base_price'];
    }

    /**
     * Calculate price for weight-based products.
     * $weightGrams: customer-selected weight in grams
     * base_price is per-kg in minor units.
     */
    public static function calculateWeightPrice(int $pricePerKg, int $weightGrams): int
    {
        return (int) round($pricePerKg * $weightGrams / 1000);
    }
}
