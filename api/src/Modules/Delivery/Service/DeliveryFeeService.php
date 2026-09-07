<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Service;

use App\Modules\Delivery\Repository\DeliveryZoneRepository;

final class DeliveryFeeService
{
    public function __construct(
        private readonly DeliveryZoneRepository $zoneRepo,
    ) {
    }

    /**
     * Calculate delivery fee for a given tenant, distance, and subtotal.
     * Returns fee in minor units, or null if not serviceable.
     */
    public function calculate(int $tenantId, float $distanceKm, int $subtotal): ?int
    {
        $zone = $this->zoneRepo->findZoneForDistance($tenantId, $distanceKm);

        if ($zone === null) {
            return null; // not serviceable
        }

        $fee = (int) $zone['fee'];

        // Free delivery above threshold
        if ($zone['min_order_free_delivery'] !== null && $subtotal >= (int) $zone['min_order_free_delivery']) {
            return 0;
        }

        return $fee;
    }

    /**
     * Calculate distance between two lat/lng points in km (Haversine).
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
