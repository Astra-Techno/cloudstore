<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Domain\PricingCalculator;
use PHPUnit\Framework\TestCase;

final class PricingTest extends TestCase
{
    public function testFixedPriceUsesBasePrice(): void
    {
        $product = ['base_price' => 18000, 'sale_price' => null];
        $this->assertSame(18000, PricingCalculator::getEffectivePrice($product));
    }

    public function testSalePriceOverridesBase(): void
    {
        $product = ['base_price' => 18000, 'sale_price' => 15000];
        $this->assertSame(15000, PricingCalculator::getEffectivePrice($product));
    }

    public function testVariantPriceOverridesProduct(): void
    {
        $product = ['base_price' => 18000, 'sale_price' => null];
        $variant = ['price' => 28000];
        $this->assertSame(28000, PricingCalculator::getEffectivePrice($product, $variant));
    }

    public function testWeightPriceCalculation(): void
    {
        // ₹720/kg = 72000 paise/kg
        $pricePerKg = 72000;

        // 500g = ₹360 = 36000 paise
        $this->assertSame(36000, PricingCalculator::calculateWeightPrice($pricePerKg, 500));

        // 750g = ₹540 = 54000 paise
        $this->assertSame(54000, PricingCalculator::calculateWeightPrice($pricePerKg, 750));

        // 1000g = ₹720 = 72000 paise
        $this->assertSame(72000, PricingCalculator::calculateWeightPrice($pricePerKg, 1000));
    }

    public function testWeightPriceNeverUsesFloat(): void
    {
        $result = PricingCalculator::calculateWeightPrice(72000, 333); // 333g
        $this->assertIsInt($result);
        // 72000 * 333 / 1000 = 23976
        $this->assertSame(23976, $result);
    }

    public function testZeroSalePriceUsesBasePrice(): void
    {
        $product = ['base_price' => 18000, 'sale_price' => 0];
        $this->assertSame(18000, PricingCalculator::getEffectivePrice($product));
    }

    public function testPriceStoredAsIntegerMinorUnits(): void
    {
        // ₹100.50 = 10050 paise
        $priceInPaise = 10050;
        $this->assertIsInt($priceInPaise);

        $rupees = $priceInPaise / 100;
        $this->assertSame(100.5, $rupees);
    }

    public function testVariantPriceIgnoresSalePrice(): void
    {
        // When variant is selected, variant price takes precedence over sale_price
        $product = ['base_price' => 18000, 'sale_price' => 12000];
        $variant = ['price' => 28000];
        $this->assertSame(28000, PricingCalculator::getEffectivePrice($product, $variant));
    }

    public function testWeightPriceForSmallQuantity(): void
    {
        // 100g of ₹720/kg = ₹72 = 7200 paise
        $this->assertSame(7200, PricingCalculator::calculateWeightPrice(72000, 100));
    }
}
