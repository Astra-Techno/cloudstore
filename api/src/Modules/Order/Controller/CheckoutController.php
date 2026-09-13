<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Customer\Repository\AddressRepository;
use App\Modules\Delivery\Service\DeliveryFeeService;
use App\Modules\Order\Service\CheckoutService;
use App\Modules\Order\Service\IdempotencyService;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Tenant\Domain\TenantContext;

final class CheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly IdempotencyService $idempotencyService,
        private readonly CustomerRepository $customerRepo,
        private readonly AddressRepository $addressRepo,
        private readonly DeliveryFeeService $deliveryFeeService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function createOrder(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $data = $request->json();
        if (($data['payment_method'] ?? null) === 'cod') {
            $data['payment_method'] = 'cash_on_delivery';
        }

        $validator = new Validator();
        if (!$validator->validate($data, [
            'payment_method' => ['required', 'string', 'in:cash_on_delivery,online'],
            'order_type' => ['string', 'in:delivery,pickup'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        // Idempotency check
        $idempotencyKey = $request->header('x-idempotency-key');
        if ($idempotencyKey !== '') {
            $requestHash = IdempotencyService::hashRequest($data);
            $cached = $this->idempotencyService->check($tenantId, $idempotencyKey, $requestHash);

            if ($cached !== null) {
                return Response::success($cached['body'], status: $cached['status']);
            }
        }

        $result = $this->checkoutService->createOrder($tenantId, $customerId, $data);

        if (isset($result['error'])) {
            $status = match ($result['code']) {
                'CART_EMPTY' => 400,
                'TENANT_MISMATCH' => 403,
                'PRODUCT_UNAVAILABLE', 'VARIANT_UNAVAILABLE' => 422,
                'ADDRESS_REQUIRED', 'ADDRESS_NOT_FOUND', 'DELIVERY_LOCATION_REQUIRED',
                'DELIVERY_UNAVAILABLE', 'DELIVERY_DISABLED', 'PICKUP_DISABLED',
                'STORE_CLOSED', 'MINIMUM_ORDER_NOT_MET', 'PAYMENT_METHOD_DISABLED',
                'INSUFFICIENT_STOCK' => 422,
                default => 400,
            };

            return Response::error($result['error'], $result['code'], $status);
        }

        // If payment method is online, initiate Razorpay payment
        if (($data['payment_method'] ?? '') === 'online' && isset($result['order'])) {
            $orderUuid = $result['order']['uuid'] ?? null;
            $orderId = (int) ($result['order']['id'] ?? 0);
            if ($orderId > 0) {
                $paymentResult = $this->paymentService->initiatePayment($tenantId, $orderId, $customerId);
                if (!isset($paymentResult['error'])) {
                    $result['payment'] = $paymentResult;
                }
                // If payment initiation fails, still return the order (customer can retry via /payments/initiate)
            }
        }

        // Store idempotency result
        if ($idempotencyKey !== '') {
            $this->idempotencyService->store(
                $tenantId,
                $customerId,
                $idempotencyKey,
                IdempotencyService::hashRequest($data),
                $result,
                201,
            );
        }

        return Response::success($result, status: 201);
    }

    public function validateServiceability(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'address_uuid' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $address = $this->addressRepo->findByUuid($data['address_uuid'], $customerId, $tenantId);
        if ($address === null) {
            return Response::notFound('Address not found.');
        }

        $tenant = TenantContext::get();
        $tenantConfig = $tenant->configuration ?? [];
        $location = $tenantConfig['delivery'] ?? [];
        $tenantLatitude = $location['latitude'] ?? null;
        $tenantLongitude = $location['longitude'] ?? null;
        $addressLatitude = $address['latitude'] ?? null;
        $addressLongitude = $address['longitude'] ?? null;

        if (!is_numeric($tenantLatitude) || !is_numeric($tenantLongitude)
            || !is_numeric($addressLatitude) || !is_numeric($addressLongitude)) {
            return Response::success([
                'serviceable' => false,
                'distance_km' => 0,
                'delivery_fee' => 0,
            ]);
        }

        $distanceKm = DeliveryFeeService::haversineDistance(
            (float) $tenantLatitude,
            (float) $tenantLongitude,
            (float) $addressLatitude,
            (float) $addressLongitude,
        );

        $fee = $this->deliveryFeeService->calculate($tenantId, $distanceKm, 0);

        if ($fee === null) {
            return Response::success([
                'serviceable' => false,
                'distance_km' => round($distanceKm, 2),
                'delivery_fee' => 0,
            ]);
        }

        return Response::success([
            'serviceable' => true,
            'distance_km' => round($distanceKm, 2),
            'delivery_fee' => $fee,
        ]);
    }
}
