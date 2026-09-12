<?php

declare(strict_types=1);

namespace App\Modules\Payment\Service;

use App\Core\Database\Connection;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Payment\Repository\PaymentRepository;
use App\Modules\Payment\Repository\RefundRepository;
use Ramsey\Uuid\Uuid;

final class PaymentService
{
    public function __construct(
        private readonly Connection $db,
        private readonly PaymentRepository $paymentRepo,
        private readonly RefundRepository $refundRepo,
        private readonly OrderRepository $orderRepo,
        private readonly string $gatewaySecret,
    ) {
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
     * Initiate a payment for an order.
     * A real gateway adapter must be configured before an online payment can
     * be initiated. This service never fabricates a successful payment.
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

        // This deployment does not include a gateway order-creation adapter.
        // Reject rather than manufacture a gateway reference that cannot charge
        // the customer. COD remains available through the checkout service.
        return ['error' => 'Online payments are not configured for this store.', 'code' => 'PAYMENT_NOT_CONFIGURED'];

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
                $this->refundRepo->create([
                    'uuid' => Uuid::uuid4()->toString(),
                    'payment_id' => (int) $payment['id'],
                    'order_id' => $orderId,
                    'tenant_id' => $tenantId,
                    'amount' => $refundAmount,
                    'reason' => $reason,
                    'status' => 'completed',
                    'initiated_by_type' => $actorType,
                    'initiated_by_id' => $actorId,
                ]);

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
}
