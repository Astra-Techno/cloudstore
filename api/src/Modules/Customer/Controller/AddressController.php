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

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $addresses = $this->addressRepo->findByCustomer($customerId, $tenantId);

        // Repair legacy customers created before defaults were enforced.
        if ($addresses !== [] && !array_filter($addresses, fn (array $address): bool => (bool) ($address['is_default'] ?? false))) {
            $this->addressRepo->clearDefault($customerId, $tenantId);
            $this->addressRepo->update((int) $addresses[0]['id'], $customerId, $tenantId, ['is_default' => 1]);
            $addresses = $this->addressRepo->findByCustomer($customerId, $tenantId);
        }

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

        $availability = $this->checkDeliveryAvailability($data['latitude'] ?? null, $data['longitude'] ?? null);
        if (isset($availability['error'])) {
            return Response::error($availability['error'], $availability['code'], 422);
        }

        $data['uuid'] = Uuid::uuid4()->toString();
        $data['customer_id'] = $customerId;
        $data['tenant_id'] = $tenantId;

        // The first saved address must always be usable as the customer's
        // default. The app also sends this explicitly, but enforcing it here
        // protects all clients and prevents an address list with no default.
        $existingAddresses = $this->addressRepo->findByCustomer($customerId, $tenantId);
        $data['is_default'] = empty($existingAddresses) || !empty($data['is_default']);

        if ($data['is_default']) {
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
        $availability = $this->checkDeliveryAvailability($data['latitude'] ?? null, $data['longitude'] ?? null);
        if (isset($availability['error'])) {
            return Response::error($availability['error'], $availability['code'], 422);
        }

        return Response::success([
            'available' => true,
            'distance_km' => $availability['distance_km'],
            'delivery_fee' => $availability['delivery_fee'],
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

        if (array_key_exists('latitude', $updates) || array_key_exists('longitude', $updates)) {
            $availability = $this->checkDeliveryAvailability(
                $updates['latitude'] ?? $address['latitude'],
                $updates['longitude'] ?? $address['longitude'],
            );
            if (isset($availability['error'])) {
                return Response::error($availability['error'], $availability['code'], 422);
            }
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

    /** @return array{distance_km: float, delivery_fee: int}|array{error: string, code: string} */
    private function checkDeliveryAvailability(mixed $latitude, mixed $longitude): array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)
            || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            return ['error' => 'A valid GPS location is required for delivery.', 'code' => 'LOCATION_REQUIRED'];
        }

        $tenant = TenantContext::get();
        $storeLocation = ($tenant->configuration ?? [])['delivery'] ?? [];
        if (!is_numeric($storeLocation['latitude'] ?? null) || !is_numeric($storeLocation['longitude'] ?? null)) {
            return ['error' => 'This store has not configured its delivery location yet.', 'code' => 'STORE_LOCATION_REQUIRED'];
        }

        $distanceKm = DeliveryFeeService::haversineDistance(
            (float) $storeLocation['latitude'],
            (float) $storeLocation['longitude'],
            (float) $latitude,
            (float) $longitude,
        );
        $fee = $this->deliveryFeeService->calculate(TenantContext::id(), $distanceKm, 0);
        if ($fee === null) {
            return ['error' => 'This location is outside the store delivery area.', 'code' => 'ADDRESS_OUTSIDE_DELIVERY_AREA'];
        }

        return ['distance_km' => round($distanceKm, 2), 'delivery_fee' => $fee];
    }
}
