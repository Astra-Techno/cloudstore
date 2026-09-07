<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Auth\Service\JwtService;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Tenant\Domain\TenantContext;

final class DriverAuthController
{
    public function __construct(
        private readonly DriverRepository $driverRepo,
        private readonly JwtService $jwtService,
        private readonly PasswordService $passwordService,
    ) {
    }

    public function login(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $tenantId = TenantContext::id();
        $driver = $this->driverRepo->findByPhone($tenantId, $data['phone']);

        if ($driver === null) {
            return Response::unauthorized('Invalid credentials.');
        }

        if ($driver['status'] === 'suspended') {
            return Response::unauthorized('Account is suspended.');
        }

        if (!$this->passwordService->verify($data['password'], $driver['password_hash'])) {
            return Response::unauthorized('Invalid credentials.');
        }

        $this->driverRepo->updateLastLogin((int) $driver['id']);

        $token = $this->jwtService->issue([
            'sub' => $driver['uuid'],
            'type' => 'driver',
            'tenant_id' => $tenantId,
        ]);

        return Response::success([
            'token' => $token,
            'driver' => [
                'id' => $driver['uuid'],
                'name' => $driver['name'],
                'phone' => $driver['phone'],
                'availability' => $driver['availability'],
            ],
        ]);
    }

    public function me(Request $request, array $params): Response
    {
        $claims = $request->authClaims ?? null;

        if ($claims === null) {
            return Response::unauthorized();
        }

        $driver = $this->driverRepo->findByUuid($claims['sub']);

        if ($driver === null) {
            return Response::unauthorized();
        }

        return Response::success([
            'id' => $driver['uuid'],
            'name' => $driver['name'],
            'phone' => $driver['phone'],
            'availability' => $driver['availability'],
        ]);
    }
}
