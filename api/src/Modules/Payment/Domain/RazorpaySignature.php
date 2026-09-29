<?php

declare(strict_types=1);

namespace App\Modules\Payment\Domain;

final class RazorpaySignature
{
    public static function verifyCheckout(string $orderId, string $paymentId, string $signature, string $keySecret): bool
    {
        return self::verify($orderId . '|' . $paymentId, $signature, $keySecret);
    }

    public static function verifyWebhook(string $rawBody, string $signature, string $webhookSecret): bool
    {
        return self::verify($rawBody, $signature, $webhookSecret);
    }

    private static function verify(string $payload, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
