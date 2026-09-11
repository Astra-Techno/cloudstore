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
            'primary_color' => '#2563EB',
            'secondary_color' => '#1E40AF',
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

        return [
            'tenant' => $tenant->toPublicArray(),
            'branding' => [
                'primary_color' => $branding['primary_color'] ?? '#2563EB',
                'secondary_color' => $branding['secondary_color'] ?? '#1E40AF',
                'accent_color' => $branding['accent_color'] ?? null,
                'font' => $branding['font'] ?? null,
                'logo_url' => $branding['logo_url'] ?? null,
                'favicon_url' => $branding['favicon_url'] ?? null,
            ],
            'features' => $capabilities,
            'charges' => [
                'delivery_charge_fixed' => (int) ($tenant->configuration['delivery_charge_fixed'] ?? 0),
                'service_charge_percent' => (float) ($tenant->configuration['service_charge_percent'] ?? 0),
            ],
            'localization' => [
                'currency' => $tenant->currency,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ],
        ];
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
