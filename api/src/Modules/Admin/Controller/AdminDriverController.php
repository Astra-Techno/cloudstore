<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\DriverRepository;
use Ramsey\Uuid\Uuid;

final class AdminDriverController
{
    public function __construct(
        private readonly DriverRepository $driverRepo,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $page = (int) ($request->input('page', 1));
        $perPage = min((int) ($request->input('per_page', 50)), 100);

        $drivers = $this->driverRepo->findByTenant($tenantId);
        $total = $this->driverRepo->countByTenant($tenantId);

        // Apply pagination manually since findByTenant returns all
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($drivers, $offset, $perPage);

        // Strip internal IDs from output
        $items = array_map(fn(array $d) => $this->formatDriver($d), $paged);

        return Response::success($items, [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage) ?: 1,
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'phone' => ['required', 'string', 'min:1', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        // Check phone uniqueness within tenant
        $existing = $this->driverRepo->findByPhone($tenantId, $data['phone']);
        if ($existing !== null) {
            return Response::validationError(['phone' => ['A driver with this phone number already exists.']]);
        }

        $id = $this->driverRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'vehicle_number' => $data['vehicle_number'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $driver = $this->driverRepo->findById($id);

        return Response::success($this->formatDriver($driver), status: 201);
    }

    public function update(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $driver = $this->driverRepo->findByUuid($params['uuid']);
        if ($driver === null || (int) $driver['tenant_id'] !== $tenantId) {
            return Response::notFound('Driver not found.');
        }

        $allowed = ['name', 'phone', 'email', 'vehicle_type', 'vehicle_number', 'status'];
        $updateData = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // If updating phone, verify uniqueness
        if (isset($updateData['phone']) && $updateData['phone'] !== $driver['phone']) {
            $existing = $this->driverRepo->findByPhone($tenantId, $updateData['phone']);
            if ($existing !== null) {
                return Response::validationError(['phone' => ['A driver with this phone number already exists.']]);
            }
        }

        // If updating password, hash it
        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        if (!empty($updateData)) {
            $this->driverRepo->update((int) $driver['id'], $updateData);
        }

        $updated = $this->driverRepo->findByUuid($params['uuid']);

        return Response::success($this->formatDriver($updated));
    }

    public function delete(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $driver = $this->driverRepo->findByUuid($params['uuid']);
        if ($driver === null || (int) $driver['tenant_id'] !== $tenantId) {
            return Response::notFound('Driver not found.');
        }

        $this->driverRepo->delete((int) $driver['id'], $tenantId);

        return Response::success(['deleted' => true]);
    }

    private function formatDriver(array $driver): array
    {
        return [
            'id' => (int) $driver['id'],
            'uuid' => $driver['uuid'],
            'name' => $driver['name'],
            'phone' => $driver['phone'],
            'email' => $driver['email'] ?? null,
            'vehicle_type' => $driver['vehicle_type'],
            'vehicle_number' => $driver['vehicle_number'],
            'status' => $driver['status'],
            'availability' => $driver['availability'] ?? null,
            'last_login_at' => $driver['last_login_at'] ?? null,
            'created_at' => $driver['created_at'],
        ];
    }
}
