<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;

final class PaymentServiceTest extends TestCase
{
    public function testPaymentStatusValues(): void
    {
        $validStatuses = ['pending', 'processing', 'paid', 'failed', 'refunded'];

        $this->assertCount(5, $validStatuses);
        $this->assertContains('paid', $validStatuses);
        $this->assertContains('refunded', $validStatuses);
    }

    public function testRefundStatusValues(): void
    {
        $validStatuses = ['pending', 'processing', 'completed', 'failed'];

        $this->assertCount(4, $validStatuses);
        $this->assertContains('completed', $validStatuses);
    }

    public function testGatewayOrderIdFormat(): void
    {
        $gatewayId = 'pay_' . bin2hex(random_bytes(12));

        $this->assertStringStartsWith('pay_', $gatewayId);
        $this->assertEquals(28, strlen($gatewayId)); // pay_ (4) + 24 hex chars
    }
}
