<?php

declare(strict_types=1);

namespace App\Modules\Auth\Service;

use App\Core\Config\Config;

/**
 * Delivers one-time codes through a configurable trusted SMS/WhatsApp relay.
 * The relay owns provider-specific credentials; the API only posts the
 * recipient and code over HTTPS, keeping gateway secrets out of tenant apps.
 */
final class OtpDeliveryService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function deliver(string $phone, string $otp): bool
    {
        if ($this->config->getBool('APP_DEBUG')) {
            return true;
        }

        $url = $this->config->get('OTP_WEBHOOK_URL');
        if ($url === '' || !function_exists('curl_init')) {
            return false;
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $token = $this->config->get('OTP_WEBHOOK_TOKEN');
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $payload = json_encode([
            'phone' => $phone,
            'otp' => $otp,
            'message' => "Your verification code is {$otp}. It expires in 5 minutes.",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return false;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return $response !== false && $status >= 200 && $status < 300;
    }
}
