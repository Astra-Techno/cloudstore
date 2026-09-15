<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Service;

use App\Modules\Tenant\Domain\Tenant;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use App\Modules\Tenant\Repository\CapabilityRepository;
use Ramsey\Uuid\Uuid;

final class TenantService
{
    public function __construct(
        private readonly TenantRepository $tenantRepo,
        private readonly BrandingRepository $brandingRepo,
        private readonly CapabilityRepository $capabilityRepo,
    ) {
    }

    public function create(array $data): Tenant
    {
        $data['uuid'] = Uuid::uuid4()->toString();

        if (!isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        $tenant = $this->tenantRepo->create($data);

        // Set default capabilities based on business type
        $defaults = $this->getDefaultCapabilities($tenant->businessType);
        $this->capabilityRepo->setMany($tenant->id, $defaults);

        // Create default branding
        $this->brandingRepo->upsert($tenant->id, [
            'primary_color' => '#E23744',
            'secondary_color' => '#B91C2C',
        ]);

        return $tenant;
    }

    public function getBootstrapData(int $tenantId): array
    {
        $tenant = $this->tenantRepo->findById($tenantId);

        if ($tenant === null) {
            throw new \RuntimeException('Tenant not found.');
        }

        $branding = $this->brandingRepo->findByTenant($tenantId);
        $capabilities = $this->capabilityRepo->getForTenant($tenantId);
        $ordering = $this->getOrderingStatus($tenant->configuration, $tenant->timezone);

        return [
            'tenant' => $tenant->toPublicArray(),
            'branding' => [
                'primary_color' => $branding['primary_color'] ?? '#E23744',
                'secondary_color' => $branding['secondary_color'] ?? '#1E40AF',
                'accent_color' => $branding['accent_color'] ?? null,
                'font' => $branding['font'] ?? null,
                'logo_url' => $branding['logo_url'] ?? null,
                'tagline' => $tenant->configuration['branding_tagline'] ?? null,
                'favicon_url' => $branding['favicon_url'] ?? null,
            ],
            'features' => $capabilities,
            'charges' => [
                'delivery_charge_fixed' => (int) ($tenant->configuration['delivery_charge_fixed'] ?? 0),
                'service_charge_percent' => (float) ($tenant->configuration['service_charge_percent'] ?? 0),
                'min_order_amount' => (int) ($tenant->configuration['min_order_amount'] ?? 0),
                'tax_rate' => (float) ($tenant->configuration['tax_rate'] ?? 0),
            ],
            'fulfilment' => [
                'delivery_enabled' => (bool) ($tenant->configuration['delivery_enabled'] ?? true),
                'pickup_enabled' => (bool) ($tenant->configuration['pickup_enabled'] ?? true),
            ],
            // The mobile app uses this to disable Add/Checkout controls before
            // a customer can build a cart that the server must reject.
            'ordering' => $ordering,
            'payment_methods' => $tenant->configuration['payment_methods'] ?? ['cod'],
            'localization' => [
                'currency' => $tenant->currency,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ],
        ];
    }

    /** @return array{is_open: bool, message: string} */
    private function getOrderingStatus(array $configuration, string $timezone): array
    {
        $hours = $configuration['business_hours'] ?? null;
        if (!is_array($hours) || $hours === []) {
            return ['is_open' => true, 'message' => 'Accepting orders'];
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        } catch (\Exception) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        }

        $dayKeys = [strtolower($now->format('l')), strtolower(substr($now->format('l'), 0, 3))];
        $schedule = null;
        foreach ($dayKeys as $day) {
            if (isset($hours[$day]) && is_array($hours[$day])) {
                $schedule = $hours[$day];
                break;
            }
        }
        if ($schedule === null) {
            return ['is_open' => true, 'message' => 'Accepting orders'];
        }
        if (($schedule['open'] ?? true) === false) {
            return ['is_open' => false, 'message' => 'Orders are unavailable today'];
        }

        $opensAt = $schedule['open_time'] ?? $schedule['start'] ?? null;
        $closesAt = $schedule['close_time'] ?? $schedule['end'] ?? null;
        if (!is_string($opensAt) || !is_string($closesAt)) {
            return ['is_open' => true, 'message' => 'Accepting orders'];
        }

        $current = $now->format('H:i');
        $isOpen = $opensAt <= $closesAt
            ? $current >= $opensAt && $current <= $closesAt
            : $current >= $opensAt || $current <= $closesAt;

        return $isOpen
            ? ['is_open' => true, 'message' => 'Accepting orders until ' . $closesAt]
            : ['is_open' => false, 'message' => 'Currently closed. Orders open at ' . $opensAt];
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    private function getDefaultCapabilities(string $businessType): array
    {
        $base = [
            'catalog' => true,
            'cart' => true,
            'checkout' => true,
            'delivery' => true,
            'pickup' => true,
            'cash_on_delivery' => true,
            'online_payment' => true,
            'notifications' => true,
        ];

        return match ($businessType) {
            'meat_shop', 'fish_shop' => array_merge($base, [
                'weight_products' => true,
                'variants' => true,
            ]),
            'restaurant', 'hotel' => array_merge($base, [
                'variants' => true,
                'addons' => true,
                'combos' => true,
                'reviews' => true,
            ]),
            'home_kitchen' => array_merge($base, [
                'scheduled_order' => true,
                'limited_quantity' => true,
            ]),
            'bakery', 'sweet_shop' => array_merge($base, [
                'variants' => true,
                'weight_products' => true,
                'combos' => true,
            ]),
            default => $base,
        };
    }
}
