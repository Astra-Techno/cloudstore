<?php

declare(strict_types=1);

namespace App\Modules\Payment\Service;

use App\Core\Database\Connection;
use App\Core\Config\Config;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Payment\Repository\PaymentRepository;
use App\Modules\Payment\Repository\RefundRepository;
use Ramsey\Uuid\Uuid;

final class PaymentService
{
    private readonly string $razorpayKeyId;
    private readonly string $razorpayKeySecret;

    public function __construct(
        private readonly Connection $db,
        private readonly PaymentRepository $paymentRepo,
        private readonly RefundRepository $refundRepo,
        private readonly OrderRepository $orderRepo,
        private readonly string $gatewaySecret,
        Config $config,
    ) {
        $this->razorpayKeyId = $config->get('RAZORPAY_KEY_ID', '');
        $this->razorpayKeySecret = $config->get('RAZORPAY_KEY_SECRET', '');
    }

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

        if ($this->razorpayKeyId === '' || $this->razorpayKeySecret === '') {
            return ['error' => 'Online payments not configured', 'code' => 'PAYMENT_NOT_CONFIGURED'];
        }

        // Check if a pending payment already exists for this order
        $existing = $this->paymentRepo->findByOrderId($orderId, $tenantId);
        if ($existing !== null && $existing['status'] === 'pending' && !empty($existing['gateway_order_id'])) {
            return [
                'razorpay_order_id' => $existing['gateway_order_id'],
                'razorpay_key_id' => $this->razorpayKeyId,
                'amount' => (int) $existing['amount'],
                'currency' => $existing['currency'] ?? 'INR',
            ];
        }

        $amount = (int) $order['total'];
        $receipt = $order['uuid'];

        // Create Razorpay order via cURL
        $razorpayResult = $this->createRazorpayOrder($amount, 'INR', $receipt);
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
            'razorpay_key_id' => $this->razorpayKeyId,
            'amount' => $amount,
            'currency' => 'INR',
        ];
    }

    /**
     * Create a Razorpay order via their REST API using cURL.
     */
    private function createRazorpayOrder(int $amountPaise, string $currency, string $receipt): array
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
            CURLOPT_USERPWD => $this->razorpayKeyId . ':' . $this->razorpayKeySecret,
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
    public function confirmPayment(string $gatewayOrderId, string $gatewayPaymentId, string $signature): array
    {
        $payment = $this->paymentRepo->findByGatewayOrderId($gatewayOrderId);
        if ($payment === null) {
            return ['error' => 'Payment not found.', 'code' => 'PAYMENT_NOT_FOUND'];
        }

        if ($payment['status'] === 'paid') {
            return ['error' => 'Payment already confirmed.', 'code' => 'ALREADY_CONFIRMED'];
        }

        if ($this->gatewaySecret === '') {
            return ['error' => 'Payment gateway is not configured.', 'code' => 'PAYMENT_NOT_CONFIGURED'];
        }

        $expectedSignature = hash_hmac('sha256', $gatewayOrderId . '|' . $gatewayPaymentId, $this->gatewaySecret);
        if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
            return ['error' => 'Invalid payment signature.', 'code' => 'INVALID_PAYMENT_SIGNATURE'];
        }

        return $this->db->transaction(function () use ($payment, $gatewayPaymentId, $signature) {
            $this->paymentRepo->updateStatus((int) $payment['id'], 'paid', [
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_signature' => $signature,
            ]);

            $tenantId = (int) $payment['tenant_id'];
            $orderId = (int) $payment['order_id'];

            $order = $this->orderRepo->findById($orderId, $tenantId);

            $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::CONFIRMED);
            $this->orderRepo->addStatusHistory($orderId, $order['status'], OrderStatus::CONFIRMED, 'system', null, 'Payment confirmed');

            // Update payment_status on order
            $this->db->execute(
                "UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE id = ?",
                [$orderId]
            );

            return [
                'status' => 'confirmed',
                'order_id' => $orderId,
                'payment_id' => (int) $payment['id'],
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

        $this->paymentRepo->updateStatus((int) $payment['id'], 'failed', [
            'failure_reason' => $reason,
        ]);

        $tenantId = (int) $payment['tenant_id'];
        $orderId = (int) $payment['order_id'];
        $order = $this->orderRepo->findById($orderId, $tenantId);

        $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::CANCELLED, 'cancelled_at');
        $this->orderRepo->addStatusHistory($orderId, $order['status'], OrderStatus::CANCELLED, 'system', null, 'Payment failed: ' . $reason);

        $this->db->execute(
            "UPDATE orders SET payment_status = 'failed', cancel_reason = ? WHERE id = ?",
            ['Payment failed: ' . $reason, $orderId]
        );

        return ['status' => 'failed', 'order_id' => $orderId];
    }

    /**
     * Initiate a refund for an order.
     */
    public function initiateRefund(int $tenantId, int $orderId, string $reason, string $actorType, int $actorId): array
    {
        $order = $this->orderRepo->findById($orderId, $tenantId);
        if ($order === null) {
            return ['error' => 'Order not found.', 'code' => 'ORDER_NOT_FOUND'];
        }

        if (!OrderStatus::canTransition($order['status'], OrderStatus::REFUNDED)) {
            return ['error' => 'Order cannot be refunded in current status.', 'code' => 'INVALID_STATUS'];
        }

        $payment = $this->paymentRepo->findByOrderId($orderId, $tenantId);

        return $this->db->transaction(function () use ($order, $payment, $tenantId, $orderId, $reason, $actorType, $actorId) {
            $refundAmount = (int) $order['total'];

            if ($payment !== null && $payment['status'] === 'paid') {
                $refundStatus = 'completed';
                $gatewayRefundId = null;

                // If paid via Razorpay, call the refund API
                if ($payment['gateway'] === 'razorpay'
                    && !empty($payment['gateway_payment_id'])
                    && $this->razorpayKeyId !== ''
                    && $this->razorpayKeySecret !== ''
                ) {
                    $refundResult = $this->processRazorpayRefund(
                        $payment['gateway_payment_id'],
                        $refundAmount,
                    );
                    if (isset($refundResult['error'])) {
                        // Log but still record the refund locally as pending
                        $refundStatus = 'pending';
                    } else {
                        $gatewayRefundId = $refundResult['id'] ?? null;
                    }
                }

                $refundId = $this->refundRepo->create([
                    'uuid' => Uuid::uuid4()->toString(),
                    'payment_id' => (int) $payment['id'],
                    'order_id' => $orderId,
                    'tenant_id' => $tenantId,
                    'amount' => $refundAmount,
                    'reason' => $reason,
                    'status' => $refundStatus,
                    'initiated_by_type' => $actorType,
                    'initiated_by_id' => $actorId,
                ]);

                if ($gatewayRefundId !== null) {
                    $this->refundRepo->updateStatus($refundId, 'completed', $gatewayRefundId);
                }

                $this->paymentRepo->updateStatus((int) $payment['id'], 'refunded');
            }

            $this->orderRepo->updateStatus($orderId, $tenantId, OrderStatus::REFUNDED);
            $this->orderRepo->addStatusHistory($orderId, $order['status'], OrderStatus::REFUNDED, $actorType, $actorId, 'Refund: ' . $reason);

            $this->db->execute(
                "UPDATE orders SET payment_status = 'refunded' WHERE id = ?",
                [$orderId]
            );

            return [
                'status' => 'refunded',
                'order_id' => $orderId,
                'refund_amount' => $refundAmount,
            ];
        });
    }

    /**
     * Call Razorpay refund API via cURL.
     */
    private function processRazorpayRefund(string $gatewayPaymentId, int $amountPaise): array
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
            CURLOPT_USERPWD => $this->razorpayKeyId . ':' . $this->razorpayKeySecret,
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
}
