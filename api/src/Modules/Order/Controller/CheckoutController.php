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

        // Atomically claim the retry key before any inventory or order mutation.
        $idempotencyKey = trim($request->header('x-idempotency-key'));
        $requestHash = '';
        if ($idempotencyKey !== '') {
            if (strlen($idempotencyKey) > 64 || preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $idempotencyKey) !== 1) {
                return Response::error('Invalid checkout request key.', 'INVALID_IDEMPOTENCY_KEY', 422);
            }
            $requestHash = IdempotencyService::hashRequest($data);
            $claim = $this->idempotencyService->claim($tenantId, $customerId, $idempotencyKey, $requestHash);
            if ($claim['state'] === 'cached') {
                return Response::success($claim['body'], status: $claim['status']);
            }
            if ($claim['state'] === 'conflict') {
                return Response::error('This checkout request key was already used for another request.', 'IDEMPOTENCY_CONFLICT', 409);
            }
            if ($claim['state'] === 'processing') {
                return Response::error('This order is already being processed. Please refresh your orders.', 'CHECKOUT_IN_PROGRESS', 409);
            }
        }

        try {
            $result = $this->checkoutService->createOrder($tenantId, $customerId, $data);
        } catch (\Throwable $exception) {
            if ($idempotencyKey !== '') {
                $this->idempotencyService->release($tenantId, $customerId, $idempotencyKey, $requestHash);
            }
            throw $exception;
        }

        if (isset($result['error'])) {
            $status = match ($result['code']) {
                'CART_EMPTY' => 400,
                'TENANT_MISMATCH' => 403,
                'PRODUCT_UNAVAILABLE', 'VARIANT_UNAVAILABLE' => 422,
                'ADDRESS_REQUIRED', 'ADDRESS_NOT_FOUND', 'DELIVERY_LOCATION_REQUIRED',
                'DELIVERY_UNAVAILABLE', 'DELIVERY_DISABLED', 'PICKUP_DISABLED',
                'STORE_CLOSED', 'MINIMUM_ORDER_NOT_MET', 'PAYMENT_METHOD_DISABLED',
                'INSUFFICIENT_STOCK', 'MEAL_SESSION_REQUIRED', 'MEAL_SESSION_UNAVAILABLE',
                'INVALID_SERVICE_DATE', 'MEAL_SESSION_CLOSED', 'MEAL_SESSION_PRODUCT_UNAVAILABLE', 'MEAL_SESSION_FULL' => 422,
                default => 400,
            };

            if ($idempotencyKey !== '') {
                $this->idempotencyService->release($tenantId, $customerId, $idempotencyKey, $requestHash);
            }
            return Response::error($result['error'], $result['code'], $status);
        }

        // If payment method is online, initiate Razorpay payment
        if (($data['payment_method'] ?? '') === 'online' && isset($result['order'])) {
            $orderId = (int) ($result['order']['id'] ?? 0);
            if ($orderId > 0) {
                try {
                    $paymentResult = $this->paymentService->initiatePayment($tenantId, $orderId, $customerId);
                    if (!isset($paymentResult['error'])) {
                        $result['payment'] = $paymentResult;
                    } else {
                        $result['payment_error'] = [
                            'message' => $paymentResult['error'],
                            'code' => $paymentResult['code'] ?? 'PAYMENT_INITIATION_FAILED',
                        ];
                    }
                } catch (\Throwable) {
                    // The order is already committed. Return it to the client
                    // so payment can be retried from order details.
                    $result['payment_error'] = [
                        'message' => 'Payment could not be started. Retry from your order details.',
                        'code' => 'PAYMENT_INITIATION_FAILED',
                    ];
                }
            }
        }

        // Store idempotency result
        if ($idempotencyKey !== '') {
            try {
                $this->idempotencyService->complete(
                    $tenantId,
                    $customerId,
                    $idempotencyKey,
                    $requestHash,
                    $result,
                    201,
                );
            } catch (\Throwable) {
                // The order is durable; cache failure must not change the
                // successful checkout response into an apparent failure.
            }
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
