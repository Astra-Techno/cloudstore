<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Service\JwtService;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Auth\Domain\Role;

final class AdminAuthController
{
    public function __construct(
        private readonly AdminRepository $adminRepo,
        private readonly JwtService $jwtService,
        private readonly PasswordService $passwordService,
    ) {
    }

    public function login(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $admin = $this->adminRepo->findByEmail($data['email']);

        if ($admin === null) {
            return Response::unauthorized('Invalid credentials.');
        }

        if ($admin['status'] !== 'active') {
            return Response::unauthorized('Account is not active.');
        }

        if (!$this->passwordService->verify($data['password'], $admin['password_hash'])) {
            return Response::unauthorized('Invalid credentials.');
        }

        $this->adminRepo->updateLastLogin((int) $admin['id']);

        $permissions = $this->adminRepo->getPermissions((int) $admin['id']);

        // If no explicit permissions, use role defaults
        if (empty($permissions)) {
            $permissions = Role::getDefaultPermissions($admin['role']);
        }

        $token = $this->jwtService->issue([
            'sub' => $admin['uuid'],
            'type' => 'admin',
            'role' => $admin['role'],
            'tenant_id' => $admin['tenant_id'],
            'permissions' => $permissions,
        ]);

        return Response::success([
            'token' => $token,
            'admin' => [
                'id' => $admin['uuid'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => $admin['role'],
                'permissions' => $permissions,
            ],
        ]);
    }

    public function changePassword(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $admin = $this->adminRepo->findByUuid($request->authClaims['sub']);
        if ($admin === null) {
            return Response::unauthorized();
        }

        if (!$this->passwordService->verify($data['current_password'], $admin['password_hash'])) {
            return Response::validationError(['current_password' => ['Current password is incorrect.']]);
        }

        $newHash = $this->passwordService->hash($data['new_password']);
        $this->adminRepo->updatePassword((int) $admin['id'], $newHash);

        return Response::success(['changed' => true]);
    }

    public function me(Request $request, array $params): Response
    {
        $claims = $request->authClaims ?? null;

        if ($claims === null) {
            return Response::unauthorized();
        }

        $admin = $this->adminRepo->findByUuid($claims['sub']);

        if ($admin === null) {
            return Response::unauthorized();
        }

        $permissions = $this->adminRepo->getPermissions((int) $admin['id']);
        if (empty($permissions)) {
            $permissions = Role::getDefaultPermissions($admin['role']);
        }

        return Response::success([
            'id' => $admin['uuid'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'permissions' => $permissions,
        ]);
    }
}
