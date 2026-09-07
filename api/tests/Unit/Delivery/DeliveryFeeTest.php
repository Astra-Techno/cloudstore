<?php

declare(strict_types=1);

namespace Tests\Unit\Delivery;

use App\Modules\Delivery\Service\DeliveryFeeService;
use PHPUnit\Framework\TestCase;

final class DeliveryFeeTest extends TestCase
{
    public function testHaversineDistanceSamePointIsZero(): void
    {
        $distance = DeliveryFeeService::haversineDistance(10.0, 78.0, 10.0, 78.0);

        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }

    public function testHaversineDistanceKnownPoints(): void
    {
        // Chennai (13.0827, 80.2707) to Madurai (9.9252, 78.1198) ≈ 422 km
        $distance = DeliveryFeeService::haversineDistance(13.0827, 80.2707, 9.9252, 78.1198);

        $this->assertEqualsWithDelta(422.0, $distance, 5.0);
    }

    public function testHaversineDistanceShortDistance(): void
    {
        // ~1.1 km apart
        $distance = DeliveryFeeService::haversineDistance(10.0, 78.0, 10.01, 78.0);

        $this->assertGreaterThan(0.5, $distance);
        $this->assertLessThan(2.0, $distance);
    }
}
