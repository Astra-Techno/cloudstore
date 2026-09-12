<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Tenant\Domain\TenantContext;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Service\AppTokenService;

final class TenantMiddleware
{
    public function __construct(
        private readonly AppTokenService $tokenService,
        private readonly TenantRepository $tenantRepo,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $appToken = $request->header('x-app-token');

        if ($appToken === '') {
            $marketplaceStore = $request->header('x-marketplace-store');
            if ($marketplaceStore === '') {
                return Response::unauthorized('Missing app token.');
            }

            $tenant = $this->tenantRepo->findActiveMarketplaceBySlug($marketplaceStore);
            if ($tenant === null) {
                return Response::unauthorized('Marketplace store is not available.');
            }
            TenantContext::set($tenant);
            return null;
        }

        $tokenRecord = $this->tokenService->validate($appToken);

        if ($tokenRecord === null) {
            return Response::unauthorized('Invalid or expired app token.');
        }

        $tenant = $this->tenantRepo->findById((int) $tokenRecord['tenant_id']);

        if ($tenant === null || !$tenant->isActive()) {
            return Response::unauthorized('Tenant not available.');
        }

        TenantContext::set($tenant);

        return null; // proceed
    }
}
