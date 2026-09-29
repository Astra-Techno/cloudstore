<?php

declare(strict_types=1);

namespace App\Modules\Payment\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Tenant\Domain\TenantContext;

final class PaymentController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    public function initiate(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'order_uuid' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $result = $this->paymentService->initiatePaymentByUuid(
            TenantContext::id(),
            $data['order_uuid'],
            (int) $customer['id'],
        );

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }

        return Response::success($result, status: 201);
    }

    public function confirm(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }
        $data = $request->json();
        $validator = new Validator();
        if (!$validator->validate($data, [
            'gateway_order_id' => ['required', 'string'],
            'gateway_payment_id' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $result = $this->paymentService->confirmPayment(
            $data['gateway_order_id'],
            $data['gateway_payment_id'],
            $data['signature'],
            TenantContext::id(),
            (int) $customer['id'],
        );
        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }
        return Response::success($result);
    }

    /**
     * Payment gateway webhook — no auth, verified via signature.
     */
    public function webhook(Request $request, array $params): Response
    {
        $result = $this->paymentService->handleWebhook(
            $request->rawBody(),
            $request->header('x-razorpay-signature'),
        );

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }

        return Response::success($result);
    }
}
