<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use App\Modules\Tenant\Repository\CapabilityRepository;
use App\Modules\Tenant\Service\TenantService;
use App\Modules\Tenant\Service\AppTokenService;
use App\Modules\Tenant\Repository\AppTokenRepository;

final class TenantSeeder
{
    public function run(Connection $db): array
    {
        $tenantRepo = new TenantRepository($db);
        $brandingRepo = new BrandingRepository($db);
        $capabilityRepo = new CapabilityRepository($db);
        $tenantService = new TenantService($tenantRepo, $brandingRepo, $capabilityRepo);
        $tokenService = new AppTokenService(new AppTokenRepository($db));

        $results = [];

        // Tenant A — Jeyam Mutton (Meat Shop)
        $tenantA = $tenantService->create([
            'name' => 'Jeyam Mutton',
            'slug' => 'jeyam-mutton',
            'business_type' => 'meat_shop',
            'status' => 'active',
            'contact_phone' => '+919876543210',
            'contact_email' => 'jeyam@example.com',
            'address' => '123 Main Street, Chennai',
            'configuration' => ['delivery' => ['latitude' => 13.0827, 'longitude' => 80.2707]],
        ]);

        $brandingRepo->upsert($tenantA->id, [
            'primary_color' => '#DC2626',
            'secondary_color' => '#991B1B',
            'font' => 'Inter',
        ]);

        $tokenA = $tokenService->generate($tenantA->id);
        $results[] = [
            'tenant' => $tenantA->name,
            'slug' => $tenantA->slug,
            'app_token' => $tokenA['token'],
        ];

        // Tenant B — Hotel ABC (Hotel/Restaurant)
        $tenantB = $tenantService->create([
            'name' => 'Hotel ABC',
            'slug' => 'hotel-abc',
            'business_type' => 'hotel',
            'status' => 'active',
            'contact_phone' => '+919876543211',
            'contact_email' => 'hotel@example.com',
            'address' => '456 Beach Road, Chennai',
            'configuration' => ['delivery' => ['latitude' => 13.0500, 'longitude' => 80.2500]],
        ]);

        $brandingRepo->upsert($tenantB->id, [
            'primary_color' => '#2563EB',
            'secondary_color' => '#1E40AF',
            'font' => 'Poppins',
        ]);

        $tokenB = $tokenService->generate($tenantB->id);
        $results[] = [
            'tenant' => $tenantB->name,
            'slug' => $tenantB->slug,
            'app_token' => $tokenB['token'],
        ];

        // Tenant C — Amma Home Kitchen
        $tenantC = $tenantService->create([
            'name' => 'Amma Home Kitchen',
            'slug' => 'amma-home-kitchen',
            'business_type' => 'home_kitchen',
            'status' => 'active',
            'contact_phone' => '+919876543212',
            'contact_email' => 'amma@example.com',
            'address' => '789 Lake View, Chennai',
            'configuration' => ['delivery' => ['latitude' => 13.0600, 'longitude' => 80.2200]],
        ]);

        $brandingRepo->upsert($tenantC->id, [
            'primary_color' => '#16A34A',
            'secondary_color' => '#15803D',
            'font' => 'Nunito',
        ]);

        $tokenC = $tokenService->generate($tenantC->id);
        $results[] = [
            'tenant' => $tenantC->name,
            'slug' => $tenantC->slug,
            'app_token' => $tokenC['token'],
        ];

        return $results;
    }
}
