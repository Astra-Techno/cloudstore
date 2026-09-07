<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Tenant\Service\AppTokenService;
use App\Modules\Tenant\Service\TenantService;

final class BootstrapController
{
    public function __construct(
        private readonly AppTokenService $tokenService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function bootstrap(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        $valid = $validator->validate($data, [
            'app_token' => ['required', 'string'],
        ]);

        if (!$valid) {
            return Response::validationError($validator->getErrors());
        }

        $tokenRecord = $this->tokenService->validate($data['app_token']);

        if ($tokenRecord === null) {
            return Response::unauthorized('Invalid or expired app token.');
        }

        $tenantId = (int) $tokenRecord['tenant_id'];

        try {
            $bootstrapData = $this->tenantService->getBootstrapData($tenantId);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage(), 'TENANT_ERROR', 400);
        }

        return Response::success($bootstrapData);
    }
}
