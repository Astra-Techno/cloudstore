<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Order\Service\CheckoutService;
use App\Modules\Order\Service\IdempotencyService;
use App\Modules\Tenant\Domain\TenantContext;

final class CheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly IdempotencyService $idempotencyService,
        private readonly CustomerRepository $customerRepo,
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

        $validator = new Validator();
        if (!$validator->validate($data, [
            'payment_method' => ['required', 'string', 'in:cash_on_delivery,online'],
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
                'DELIVERY_UNAVAILABLE', 'INSUFFICIENT_STOCK' => 422,
                default => 400,
            };

            return Response::error($result['error'], $result['code'], $status);
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
}
