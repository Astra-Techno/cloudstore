<?php
declare(strict_types=1);

namespace App\Modules\Order\Domain;

final class MarketplaceFee
{
    public const RULE = 'one_percent_minimum_500_paise';

    /** Amounts are integer paise. Apply only to eligible completed marketplace orders. */
    public static function calculate(int $total): int
    {
        if ($total < 0) throw new \InvalidArgumentException('Order value cannot be negative.');
        return max(500, (int) round($total / 100));
    }
}
