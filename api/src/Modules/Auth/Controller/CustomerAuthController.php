<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Auth\Service\JwtService;
use App\Modules\Auth\Service\OtpService;
use App\Modules\Auth\Service\OtpDeliveryService;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class CustomerAuthController
{
    public function __construct(
        private readonly CustomerRepository $customerRepo,
        private readonly JwtService $jwtService,
        private readonly OtpService $otpService,
        private readonly OtpDeliveryService $otpDelivery,
    ) {
    }

    /**
     * Request OTP for customer login/registration.
     */
    public function requestOtp(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $tenantId = TenantContext::id();
        $result = $this->otpService->generate($tenantId, $data['phone']);

        if (isset($result['error'])) {
            return Response::error($result['error'], 'OTP_COOLDOWN', 429);
        }

        if (!$this->otpDelivery->deliver($data['phone'], $result['otp'])) {
            $this->otpService->discardLatest($tenantId, $data['phone']);
            return Response::error('Unable to send a verification code. Please try again shortly.', 'OTP_DELIVERY_FAILED', 503);
        }

        $response = ['message' => 'OTP sent successfully.', 'expires_in' => $result['expires_in']];

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $response['otp'] = $result['otp']; // Only in debug mode
        }

        return Response::success($response);
    }

    /**
     * Verify OTP and login/register customer.
     */
    public function verifyOtp(Request $request, array $params): Response
    {
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'otp' => ['required', 'string', 'min:6', 'max:6'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $tenantId = TenantContext::id();

        if (!$this->otpService->verify($tenantId, $data['phone'], $data['otp'])) {
            return Response::unauthorized('Invalid or expired OTP.');
        }

        // Find or create customer
        $customer = $this->customerRepo->findByPhone($tenantId, $data['phone']);

        if ($customer === null) {
            $customerId = $this->customerRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_id' => $tenantId,
                'phone' => $data['phone'],
                'name' => $data['name'] ?? null,
            ]);
            $customer = $this->customerRepo->findById($customerId);
        }

        if ($customer['status'] !== 'active') {
            return Response::unauthorized('Account is not active.');
        }

        $isNew = $customer['last_login_at'] === null;
        $this->customerRepo->updateLastLogin((int) $customer['id']);

        $token = $this->jwtService->issue([
            'sub' => $customer['uuid'],
            'type' => 'customer',
            'tenant_id' => $tenantId,
        ]);

        return Response::success([
            'token' => $token,
            'customer' => [
                'id' => $customer['uuid'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
            ],
            'is_new' => $isNew,
        ]);
    }

    public function me(Request $request, array $params): Response
    {
        $claims = $request->authClaims ?? null;

        if ($claims === null) {
            return Response::unauthorized();
        }

        $customer = $this->customerRepo->findByUuid($claims['sub']);

        if ($customer === null) {
            return Response::unauthorized();
        }

        return Response::success([
            'id' => $customer['uuid'],
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
        ]);
    }

    public function updateProfile(Request $request, array $params): Response
    {
        $claims = $request->authClaims ?? null;
        if ($claims === null) {
            return Response::unauthorized();
        }

        $customer = $this->customerRepo->findByUuid($claims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $data = $request->json();
        $update = [];

        if (isset($data['name']) && is_string($data['name']) && trim($data['name']) !== '') {
            $update['name'] = trim($data['name']);
        }
        if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== '') {
            $update['email'] = trim($data['email']);
        }

        if ($update === []) {
            return Response::error('Nothing to update.', 'NO_CHANGES', 422);
        }

        $this->customerRepo->update((int) $customer['id'], $update);
        $customer = $this->customerRepo->findById((int) $customer['id']);

        return Response::success([
            'id' => $customer['uuid'],
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
        ]);
    }
}
