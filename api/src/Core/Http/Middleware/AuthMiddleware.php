<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Service\JwtService;
use App\Modules\Tenant\Domain\TenantContext;
use App\Modules\Tenant\Repository\TenantRepository;

final class AuthMiddleware
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly ?string $requiredType = null,
        private readonly ?string $requiredPermission = null,
        private readonly ?TenantRepository $tenantRepo = null,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return Response::unauthorized('Missing authentication token.');
        }

        $claims = $this->jwtService->verify($token);

        if ($claims === null) {
            return Response::unauthorized('Invalid or expired token.');
        }

        // Check user type (admin, customer, driver)
        if ($this->requiredType !== null && ($claims['type'] ?? '') !== $this->requiredType) {
            return Response::forbidden('Access denied for this user type.');
        }

        // Check permission (for admin users)
        if ($this->requiredPermission !== null) {
            $permissions = $claims['permissions'] ?? [];

            if (!in_array('*', $permissions, true) && !in_array($this->requiredPermission, $permissions, true)) {
                return Response::forbidden('Insufficient permissions.');
            }
        }

        $request->authClaims = $claims;

        // Set tenant context from JWT if available (for admin/driver routes without TenantMiddleware)
        if (isset($claims['tenant_id']) && !TenantContext::has() && $this->tenantRepo !== null) {
            $tenant = $this->tenantRepo->findById((int) $claims['tenant_id']);
            if ($tenant !== null) {
                TenantContext::set($tenant);
            }
        }

        return null; // proceed
    }
}
