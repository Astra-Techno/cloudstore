<?php

declare(strict_types=1);

namespace App\Modules\Payment\Service;

use App\Core\Database\Connection;
use App\Modules\Platform\Service\OperationalConfig;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Payment\Repository\PaymentRepository;
use App\Modules\Payment\Repository\RefundRepository;
use App\Modules\Payment\Domain\RazorpaySignature;
use Ramsey\Uuid\Uuid;

final class PaymentService
{
    public function __construct(
        private readonly Connection $db,
        private readonly PaymentRepository $paymentRepo,
        private readonly RefundRepository $refundRepo,
        private readonly OrderRepository $orderRepo,
        private readonly OperationalConfig $config,
    ) {}

    /**
     * Initiate payment by order UUID (used by controllers).
     */
    public function initiatePaymentByUuid(int $tenantId, string $orderUuid, int $customerId): array
    {
        $order = $this->orderRepo->findByUuid($orderUuid, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        return $this->initiatePayment($tenantId, (int) $order['id'], $customerId);
    }

    /**
     * Initiate a payment for an order via Razorpay.
     * Creates a Razorpay order and stores a local payment record.
     */
    public function initiatePayment(int $tenantId, int $orderId, int $customerId): array
    {
        $order = $this->orderRepo->findById($orderId, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        if ($order['payment_method'] === 'cash_on_delivery') {
            return ['error' => 'Order uses cash on delivery.', 'code' => 'INVALID_PAYMENT_METHOD'];
        }

        $credentials = $this->credentials($tenantId);
        if ($credentials['key_id'] === '' || $credentials['key_secret'] === '') {
            return ['error' => 'Online payments not configured', 'code' => 'PAYMENT_NOT_CONFIGURED'];
        }

        // Check if a pending payment already exists for this order
        $existing = $this->paymentRepo->findByOrderId($orderId, $tenantId);
        if ($existing !== null && $existing['status'] === 'pending' && !empty($existing['gateway_order_id'])) {
            return [
                'razorpay_order_id' => $existing['gateway_order_id'],
                'razorpay_key_id' => $credentials['key_id'],
                'amount' => (int) $existing['amount'],
                'currency' => $existing['currency'] ?? 'INR',
            ];
        }

        $amount = (int) $order['total'];
        $receipt = $order['uuid'];

        // Create Razorpay order via cURL
        $razorpayResult = $this->createRazorpayOrder($amount, 'INR', $receipt, $credentials);
        if (isset($razorpayResult['error'])) {
            return $razorpayResult;
        }

        $gatewayOrderId = $razorpayResult['id'];

        // Store payment record
        $this->paymentRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'gateway' => 'razorpay',
            'gateway_order_id' => $gatewayOrderId,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => 'pending',
            'metadata' => ['razorpay_order' => $razorpayResult],
        ]);

        return [
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_key_id' => $credentials['key_id'],
            'amount' => $amount,
            'currency' => 'INR',
        ];
    }

    /**
     * Create a Razorpay order via their REST API using cURL.
     */
    /** @param array{key_id:string,key_secret:string,webhook_secret:string} $credentials */
    private function createRazorpayOrder(int $amountPaise, string $currency, string $receipt, array $credentials): array
    {
        $url = 'https://api.razorpay.com/v1/orders';
        $payload = json_encode([
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $credentials['key_id'] . ':' . $credentials['key_secret'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['error' => 'Payment gateway unavailable: ' . $curlError, 'code' => 'GATEWAY_ERROR'];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['id'])) {
            $msg = $data['error']['description'] ?? 'Failed to create payment order';
            return ['error' => $msg, 'code' => 'GATEWAY_ERROR'];
        }

        return $data;
    }

    /**
     * Confirm payment (webhook or client callback).
     * Verifies the payment signature and updates order status.
     */
    public function confirmPayment(
        string $gatewayOrderId,
        string $gatewayPaymentId,
        string $signature,
        ?int $expectedTenantId = null,
        ?int $expectedCustomerId = null,
    ): array
    {
        $payment = $this->paymentRepo->findByGatewayOrderId($gatewayOrderId);
        if ($payment === null) {
            return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
        }

        if (($expectedTenantId !== null && (int) $payment['tenant_id'] !== $expectedTenantId)
            || ($expectedCustomerId !== null && (int) $payment['customer_id'] !== $expectedCustomerId)) {
            return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
        }

        if ($payment['status'] === 'paid') {
            $paidOrder = $this->orderRepo->findById((int) $payment['order_id'], (int) $payment['tenant_id']);
            $needsReview = $paidOrder === null || in_array(
                $paidOrder['status'],
                [OrderStatus::CANCELLED, OrderStatus::REJECTED, OrderStatus::REFUNDED],
                true,
            );
            return [
                'status' => $needsReview ? 'payment_review_required' : 'confirmed',
                'order_id' => (int) $payment['order_id'],
                'payment_id' => (int) $payment['id'],
            ];
        }
        if (in_array($payment['status'], ['failed', 'refunded'], true)) {
            return ['error' => 'Payment can no longer be confirmed.', 'code' => 'PAYMENT_NOT_CONFIRMABLE'];
        }

        $credentials = $this->credentials((int) $payment['tenant_id']);
        if ($credentials['key_secret'] === '') {
            return ['error' => 'Payment gateway is not configured.', 'code' => 'PAYMENT_NOT_CONFIGURED'];
        }

        if (!RazorpaySignature::verifyCheckout($gatewayOrderId, $gatewayPaymentId, $signature, $credentials['key_secret'])) {
            return ['error' => 'Invalid payment signature.', 'code' => 'INVALID_PAYMENT_SIGNATURE'];
        }

        return $this->markPaymentPaid($payment, $gatewayPaymentId, $signature, 'Payment confirmed');
    }

    public function handleWebhook(string $rawBody, string $signature): array
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['error' => 'Invalid webhook payload.', 'code' => 'INVALID_WEBHOOK'];
        }
        $event = (string) ($payload['event'] ?? '');

        if (in_array($event, ['refund.processed', 'refund.failed'], true)) {
            $refundEntity = $payload['payload']['refund']['entity'] ?? null;
            $gatewayPaymentId = (string) ($refundEntity['payment_id'] ?? '');
            $gatewayRefundId = (string) ($refundEntity['id'] ?? '');
            if (!is_array($refundEntity) || $gatewayPaymentId === '' || $gatewayRefundId === '') {
                return ['error' => 'Refund identifiers are missing.', 'code' => 'INVALID_WEBHOOK'];
            }
            $payment = $this->paymentRepo->findByGatewayPaymentId($gatewayPaymentId);
            if ($payment === null) {
                return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
            }
            $secret = $this->credentials((int) $payment['tenant_id'])['webhook_secret'];
            if (!RazorpaySignature::verifyWebhook($rawBody, $signature, $secret)) {
                return ['error' => 'Invalid webhook signature.', 'code' => 'INVALID_WEBHOOK_SIGNATURE'];
            }
            $refund = $this->refundRepo->findForGatewayEvent((int) $payment['order_id'], $gatewayRefundId);
            if ($refund === null) {
                return ['error' => 'Refund record not found.', 'code' => 'REFUND_NOT_FOUND'];
            }
            if ($event === 'refund.failed') {
                if ($refund['status'] !== 'completed') {
                    $this->refundRepo->updateStatus((int) $refund['id'], 'failed', $gatewayRefundId);
                }
                return ['status' => 'failed', 'order_id' => (int) $payment['order_id']];
            }

            return $this->finalizeRefund((int) $refund['id'], $gatewayRefundId);
        }

        $gatewayOrderId = (string) ($payload['payload']['payment']['entity']['order_id']
            ?? $payload['payload']['order']['entity']['id'] ?? '');
        if ($gatewayOrderId === '') {
            return ['error' => 'Payment order is missing.', 'code' => 'INVALID_WEBHOOK'];
        }
        $payment = $this->paymentRepo->findByGatewayOrderId($gatewayOrderId);
        if ($payment === null) {
            return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
        }
        $secret = $this->credentials((int) $payment['tenant_id'])['webhook_secret'];
        if (!RazorpaySignature::verifyWebhook($rawBody, $signature, $secret)) {
            return ['error' => 'Invalid webhook signature.', 'code' => 'INVALID_WEBHOOK_SIGNATURE'];
        }

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $paymentId = (string) ($payload['payload']['payment']['entity']['id'] ?? '');
            if ($paymentId === '') {
                return ['error' => 'Payment identifier is missing.', 'code' => 'INVALID_WEBHOOK'];
            }
            return $this->markPaymentPaid($payment, $paymentId, $signature, 'Razorpay webhook confirmed');
        }
        if ($event === 'payment.failed') {
            $reason = (string) ($payload['payload']['payment']['entity']['error_description'] ?? 'Payment failed');
            return $this->failPayment($gatewayOrderId, $reason);
        }
        return ['status' => 'ignored', 'event' => $event];
    }

    private function markPaymentPaid(array $payment, string $gatewayPaymentId, string $signature, string $note): array
    {
        return $this->db->transaction(function () use ($payment, $gatewayPaymentId, $signature, $note) {
            $lockedPayment = $this->db->fetchOne(
                'SELECT * FROM payments WHERE id = ? FOR UPDATE',
                [(int) $payment['id']],
            );
            if ($lockedPayment === null) {
                return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
            }
            if ($lockedPayment['status'] === 'paid') {
                $paidOrder = $this->orderRepo->findById(
                    (int) $lockedPayment['order_id'],
                    (int) $lockedPayment['tenant_id'],
                );
                $needsReview = $paidOrder === null || in_array(
                    $paidOrder['status'],
                    [OrderStatus::CANCELLED, OrderStatus::REJECTED, OrderStatus::REFUNDED],
                    true,
                );
                return [
                    'status' => $needsReview ? 'payment_review_required' : 'confirmed',
                    'order_id' => (int) $lockedPayment['order_id'],
                    'payment_id' => (int) $lockedPayment['id'],
                ];
            }
            if (in_array($lockedPayment['status'], ['failed', 'refunded'], true)) {
                return ['error' => 'Payment can no longer be confirmed.', 'code' => 'PAYMENT_NOT_CONFIRMABLE'];
            }

            $this->paymentRepo->updateStatus((int) $lockedPayment['id'], 'paid', [
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_signature' => $signature,
            ]);
            $tenantId = (int) $lockedPayment['tenant_id'];
            $orderId = (int) $lockedPayment['order_id'];
            $order = $this->db->fetchOne(
                'SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE',
                [$orderId, $tenantId],
            );
            $confirmable = $order !== null && in_array(
                $order['status'],
                [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_PROCESSING],
                true
            );
            if ($confirmable) {
                $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::CONFIRMED);
                $this->orderRepo->addStatusHistory($orderId, $order['status'], OrderStatus::CONFIRMED, 'system', null, $note);
            }
            $this->db->execute(
                "UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$orderId, $tenantId],
            );
            return [
                'status' => $confirmable ? 'confirmed' : 'payment_review_required',
                'order_id' => $orderId,
                'payment_id' => (int) $lockedPayment['id'],
            ];
        });
    }

    /**
     * Handle payment failure.
     */
    public function failPayment(string $gatewayOrderId, string $reason): array
    {
        $payment = $this->paymentRepo->findByGatewayOrderId($gatewayOrderId);
        if ($payment === null) {
            return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
        }
        return $this->db->transaction(function () use ($payment, $reason) {
            $lockedPayment = $this->db->fetchOne(
                'SELECT * FROM payments WHERE id = ? FOR UPDATE',
                [(int) $payment['id']],
            );
            if ($lockedPayment === null) {
                return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
            }
            if (in_array($lockedPayment['status'], ['paid', 'refunded'], true)) {
                return ['error' => 'A successful payment cannot be marked failed.', 'code' => 'PAYMENT_ALREADY_PAID'];
            }
            if ($lockedPayment['status'] !== 'failed') {
                $this->paymentRepo->updateStatus((int) $lockedPayment['id'], 'failed', [
                    'failure_reason' => $reason,
                ]);
            }

            return ['status' => 'failed', 'order_id' => (int) $lockedPayment['order_id']];
        });
    }

    /**
     * Initiate a refund for an order.
     */
    public function initiateRefund(int $tenantId, int $orderId, string $reason, string $actorType, int $actorId): array
    {
        $claim = $this->db->transaction(function () use ($tenantId, $orderId, $reason, $actorType, $actorId) {
            $order = $this->db->fetchOne(
                'SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE',
                [$orderId, $tenantId],
            );
            if ($order === null) {
                return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
            }
            if (!OrderStatus::canTransition((string) $order['status'], OrderStatus::REFUNDED)) {
                return ['error' => 'Order cannot be refunded in current status.', 'code' => 'INVALID_STATUS'];
            }

            $existing = $this->db->fetchOne(
                'SELECT * FROM refunds WHERE order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$orderId],
            );
            if ($existing !== null) {
                return [
                    'existing' => true,
                    'status' => $existing['status'],
                    'order_id' => $orderId,
                    'refund_amount' => (int) $existing['amount'],
                ];
            }

            $payment = $this->db->fetchOne(
                'SELECT * FROM payments WHERE order_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$orderId, $tenantId],
            );
            if ($payment === null || $payment['status'] !== 'paid' || empty($payment['gateway_payment_id'])) {
                return ['error' => 'No captured online payment is available to refund.', 'code' => 'PAYMENT_NOT_REFUNDABLE'];
            }
            if ($payment['gateway'] !== 'razorpay') {
                return ['error' => 'This payment provider is not supported for automatic refunds.', 'code' => 'REFUND_PROVIDER_UNSUPPORTED'];
            }

            $refundId = $this->refundRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'payment_id' => (int) $payment['id'],
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
                'amount' => (int) $order['total'],
                'reason' => $reason,
                'status' => 'pending',
                'initiated_by_type' => $actorType,
                'initiated_by_id' => $actorId,
            ]);

            return [
                'refund_id' => $refundId,
                'payment_id' => (int) $payment['id'],
                'gateway_payment_id' => (string) $payment['gateway_payment_id'],
                'refund_amount' => (int) $order['total'],
            ];
        });

        if (isset($claim['error'])) {
            return $claim;
        }
        if (!empty($claim['existing'])) {
            return [
                'status' => $claim['status'],
                'order_id' => $orderId,
                'refund_amount' => $claim['refund_amount'],
                'message' => 'A refund request already exists for this order.',
            ];
        }

        $credentials = $this->credentials($tenantId);
        if ($credentials['key_id'] === '' || $credentials['key_secret'] === '') {
            return [
                'status' => 'pending',
                'order_id' => $orderId,
                'refund_amount' => $claim['refund_amount'],
                'message' => 'Refund credentials are unavailable. This request requires reconciliation.',
            ];
        }

        $gatewayResult = $this->processRazorpayRefund(
            $claim['gateway_payment_id'],
            $claim['refund_amount'],
            $credentials,
        );
        if (isset($gatewayResult['error'])) {
            return [
                'status' => 'pending',
                'order_id' => $orderId,
                'refund_amount' => $claim['refund_amount'],
                'message' => 'The gateway refund is pending and requires reconciliation.',
            ];
        }

        $gatewayRefundId = (string) ($gatewayResult['id'] ?? '');
        if (($gatewayResult['status'] ?? 'pending') !== 'processed') {
            if ($gatewayRefundId !== '') {
                $this->refundRepo->updateStatus((int) $claim['refund_id'], 'pending', $gatewayRefundId);
            }
            return [
                'status' => 'pending',
                'order_id' => $orderId,
                'refund_amount' => $claim['refund_amount'],
                'message' => 'The gateway accepted the refund and processing is pending.',
            ];
        }

        return $this->finalizeRefund((int) $claim['refund_id'], $gatewayRefundId);
    }

    private function finalizeRefund(int $refundId, string $gatewayRefundId): array
    {
        return $this->db->transaction(function () use ($refundId, $gatewayRefundId) {
            $refund = $this->db->fetchOne(
                'SELECT * FROM refunds WHERE id = ? FOR UPDATE',
                [$refundId],
            );
            if ($refund === null) {
                return ['error' => 'Refund record not found.', 'code' => 'REFUND_NOT_FOUND'];
            }
            $tenantId = (int) $refund['tenant_id'];
            $orderId = (int) $refund['order_id'];
            if ($refund['status'] !== 'completed') {
                $this->refundRepo->updateStatus((int) $refund['id'], 'completed', $gatewayRefundId ?: null);
                $this->paymentRepo->updateStatus((int) $refund['payment_id'], 'refunded');
            }

            $order = $this->db->fetchOne(
                'SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE',
                [$orderId, $tenantId],
            );
            $orderUpdated = false;
            if ($order !== null && OrderStatus::canTransition((string) $order['status'], OrderStatus::REFUNDED)) {
                $orderUpdated = $this->orderRepo->transitionStatus(
                    $orderId,
                    $tenantId,
                    (string) $order['status'],
                    OrderStatus::REFUNDED,
                );
                if ($orderUpdated) {
                    $this->orderRepo->addStatusHistory(
                        $orderId,
                        $order['status'],
                        OrderStatus::REFUNDED,
                        $refund['initiated_by_type'] ?? 'system',
                        isset($refund['initiated_by_id']) ? (int) $refund['initiated_by_id'] : null,
                        'Refund: ' . ($refund['reason'] ?? 'Payment refunded'),
                    );
                }
            }
            $this->db->execute(
                "UPDATE orders SET payment_status = 'refunded' WHERE id = ? AND tenant_id = ?",
                [$orderId, $tenantId],
            );

            return [
                'status' => $orderUpdated ? 'refunded' : 'payment_refunded_order_review_required',
                'order_id' => $orderId,
                'refund_amount' => (int) $refund['amount'],
            ];
        });
    }

    /**
     * Call Razorpay refund API via cURL.
     */
    /** @param array{key_id:string,key_secret:string,webhook_secret:string} $credentials */
    private function processRazorpayRefund(string $gatewayPaymentId, int $amountPaise, array $credentials): array
    {
        $url = "https://api.razorpay.com/v1/payments/{$gatewayPaymentId}/refund";
        $payload = json_encode([
            'amount' => $amountPaise,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $credentials['key_id'] . ':' . $credentials['key_secret'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['error' => 'Refund gateway unavailable: ' . $curlError, 'code' => 'GATEWAY_ERROR'];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['id'])) {
            $msg = $data['error']['description'] ?? 'Refund request failed';
            return ['error' => $msg, 'code' => 'GATEWAY_ERROR'];
        }

        return $data;
    }

    /** @return array{key_id:string,key_secret:string,webhook_secret:string} */
    private function credentials(int $tenantId): array
    {
        return [
            'key_id' => $this->config->get('RAZORPAY_KEY_ID', $tenantId),
            'key_secret' => $this->config->get('RAZORPAY_KEY_SECRET', $tenantId),
            'webhook_secret' => $this->config->get(
                'RAZORPAY_WEBHOOK_SECRET',
                $tenantId,
                $this->config->get('PAYMENT_SECRET', $tenantId),
            ),
        ];
    }
}
