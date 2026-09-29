<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use App\Modules\Payment\Domain\RazorpaySignature;
use PHPUnit\Framework\TestCase;

final class RazorpaySignatureTest extends TestCase
{
    public function testCheckoutSignatureUsesOrderAndPaymentWithApiSecret(): void
    {
        $signature = hash_hmac('sha256', 'order_123|pay_456', 'api-secret');
        self::assertTrue(RazorpaySignature::verifyCheckout('order_123', 'pay_456', $signature, 'api-secret'));
        self::assertFalse(RazorpaySignature::verifyCheckout('order_123', 'pay_changed', $signature, 'api-secret'));
    }

    public function testWebhookSignatureUsesExactRawBody(): void
    {
        $raw = '{"event":"order.paid"}';
        $signature = hash_hmac('sha256', $raw, 'webhook-secret');
        self::assertTrue(RazorpaySignature::verifyWebhook($raw, $signature, 'webhook-secret'));
        self::assertFalse(RazorpaySignature::verifyWebhook($raw . "\n", $signature, 'webhook-secret'));
        self::assertFalse(RazorpaySignature::verifyWebhook($raw, '', 'webhook-secret'));
    }
}
