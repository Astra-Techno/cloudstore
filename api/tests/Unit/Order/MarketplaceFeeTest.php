<?php
declare(strict_types=1);

namespace Tests\Unit\Order;

use App\Modules\Order\Domain\MarketplaceFee;
use PHPUnit\Framework\TestCase;

final class MarketplaceFeeTest extends TestCase
{
    public function testMinimumAndPercentageInPaise(): void
    {
        foreach ([[0, 500], [20000, 500], [50000, 500], [50100, 501], [100000, 1000], [100050, 1001]] as [$amount, $fee]) {
            self::assertSame($fee, MarketplaceFee::calculate($amount));
        }
    }

    public function testNegativeValuesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MarketplaceFee::calculate(-1);
    }
}
