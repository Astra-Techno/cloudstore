<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use App\Modules\Order\Domain\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function testConfirmedCanTransitionToAccepted(): void
    {
        $this->assertTrue(OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::ACCEPTED));
    }

    public function testConfirmedCanTransitionToRejected(): void
    {
        $this->assertTrue(OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::REJECTED));
    }

    public function testConfirmedCannotTransitionToDelivered(): void
    {
        $this->assertFalse(OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::DELIVERED));
    }

    public function testPreparingCanTransitionToReady(): void
    {
        $this->assertTrue(OrderStatus::canTransition(OrderStatus::PREPARING, OrderStatus::READY));
    }

    public function testDeliveredIsFinal(): void
    {
        $this->assertTrue(OrderStatus::isFinal(OrderStatus::DELIVERED));
    }

    public function testCancelledIsFinal(): void
    {
        $this->assertTrue(OrderStatus::isFinal(OrderStatus::CANCELLED));
    }

    public function testConfirmedIsNotFinal(): void
    {
        $this->assertFalse(OrderStatus::isFinal(OrderStatus::CONFIRMED));
    }

    public function testRejectedHasNoTransitions(): void
    {
        $this->assertEmpty(OrderStatus::getAllowedTransitions(OrderStatus::REJECTED));
    }

    public function testPendingPaymentCanTransitionToConfirmedOrCancelled(): void
    {
        $allowed = OrderStatus::getAllowedTransitions(OrderStatus::PENDING_PAYMENT);

        $this->assertContains(OrderStatus::CONFIRMED, $allowed);
        $this->assertContains(OrderStatus::CANCELLED, $allowed);
        $this->assertContains(OrderStatus::PAYMENT_PROCESSING, $allowed);
    }

    public function testDeliveredCanOnlyRefund(): void
    {
        $allowed = OrderStatus::getAllowedTransitions(OrderStatus::DELIVERED);

        $this->assertCount(1, $allowed);
        $this->assertContains(OrderStatus::REFUNDED, $allowed);
    }

    public function testEveryNonFinalStatusCanCancel(): void
    {
        $cancellable = [
            OrderStatus::PENDING_PAYMENT,
            OrderStatus::PAYMENT_PROCESSING,
            OrderStatus::CONFIRMED,
            OrderStatus::ACCEPTED,
            OrderStatus::PREPARING,
            OrderStatus::READY,
            OrderStatus::READY_FOR_PICKUP,
            OrderStatus::OUT_FOR_DELIVERY,
        ];

        foreach ($cancellable as $status) {
            $this->assertTrue(
                OrderStatus::canTransition($status, OrderStatus::CANCELLED),
                "Expected {$status} to be cancellable"
            );
        }
    }
}
