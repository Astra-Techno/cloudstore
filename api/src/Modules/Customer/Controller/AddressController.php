<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Customer\Repository\AddressRepository;
use App\Modules\Delivery\Service\DeliveryFeeService;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class AddressController
{
    public function __construct(
        private readonly AddressRepository $addressRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly DeliveryFeeService $deliveryFeeService,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $addresses = $this->addressRepo->findByCustomer((int) $customer['id'], TenantContext::id());

        return Response::success($addresses);
    }

    public function create(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'address_line_1' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $data['uuid'] = Uuid::uuid4()->toString();
        $data['customer_id'] = $customerId;
        $data['tenant_id'] = $tenantId;

        if (!empty($data['is_default'])) {
            $this->addressRepo->clearDefault($customerId, $tenantId);
        }

        $id = $this->addressRepo->create($data);
        $address = $this->addressRepo->findById($id, $customerId, $tenantId);

        return Response::success($address, status: 201);
    }

    /** Check delivery coverage before an address is saved. */
    public function availability(Request $request, array $params): Response
    {
        if ($this->resolveCustomer($request) === null) {
            return Response::unauthorized();
        }

        $data = $request->json();
        if (!is_numeric($data['latitude'] ?? null) || !is_numeric($data['longitude'] ?? null)) {
            return Response::validationError([
                'location' => ['A GPS location is required to check delivery availability.'],
            ]);
        }

        $tenant = TenantContext::get();
        $storeLocation = ($tenant->configuration ?? [])['delivery'] ?? [];
        if (!is_numeric($storeLocation['latitude'] ?? null) || !is_numeric($storeLocation['longitude'] ?? null)) {
            return Response::error('This store has not configured its delivery location yet.', 'STORE_LOCATION_REQUIRED', 422);
        }

        $distanceKm = DeliveryFeeService::haversineDistance(
            (float) $storeLocation['latitude'],
            (float) $storeLocation['longitude'],
            (float) $data['latitude'],
            (float) $data['longitude'],
        );
        $fee = $this->deliveryFeeService->calculate(TenantContext::id(), $distanceKm, 0);

        return Response::success([
            'available' => $fee !== null,
            'distance_km' => round($distanceKm, 2),
            'delivery_fee' => $fee,
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];

        $address = $this->addressRepo->findByUuid($params['uuid'], $customerId, $tenantId);
        if ($address === null) {
            return Response::notFound('Address not found.');
        }

        $data = $request->json();
        $allowed = ['label', 'recipient_name', 'phone', 'address_line_1', 'address_line_2',
            'landmark', 'city', 'state', 'postal_code', 'latitude', 'longitude',
            'delivery_instructions', 'is_default'];

        $updates = array_intersect_key($data, array_flip($allowed));

        if (empty($updates)) {
            return Response::error('No valid fields to update.', 'VALIDATION_ERROR', 422);
        }

        if (!empty($updates['is_default'])) {
            $this->addressRepo->clearDefault($customerId, $tenantId);
        }

        $this->addressRepo->update((int) $address['id'], $customerId, $tenantId, $updates);
        $updated = $this->addressRepo->findById((int) $address['id'], $customerId, $tenantId);

        return Response::success($updated);
    }

    public function delete(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];

        $address = $this->addressRepo->findByUuid($params['uuid'], $customerId, $tenantId);
        if ($address === null) {
            return Response::notFound('Address not found.');
        }

        $this->addressRepo->delete((int) $address['id'], $customerId, $tenantId);

        return Response::success(['deleted' => true]);
    }

    private function resolveCustomer(Request $request): ?array
    {
        $claims = $request->authClaims ?? null;
        if ($claims === null) {
            return null;
        }

        return $this->customerRepo->findByUuid($claims['sub']);
    }
}
