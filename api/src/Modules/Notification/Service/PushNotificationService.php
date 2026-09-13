<?php

declare(strict_types=1);

namespace App\Modules\Notification\Service;

use App\Core\Config\Config;
use App\Core\Database\Connection;
use App\Core\Logging\Logger;

final class PushNotificationService
{
    private readonly string $fcmServerKey;

    public function __construct(
        private readonly Connection $db,
        private readonly Logger $logger,
        Config $config,
    ) {
        $this->fcmServerKey = $config->get('FCM_SERVER_KEY', '');
    }

    /**
     * Send a push notification to a customer by their internal ID.
     */
    public function sendToCustomer(int $customerId, string $title, string $body, array $data = []): bool
    {
        $customer = $this->db->fetchOne(
            "SELECT fcm_token FROM customers WHERE id = ? AND deleted_at IS NULL",
            [$customerId]
        );

        if ($customer === null || empty($customer['fcm_token'])) {
            return false;
        }

        return $this->sendToToken($customer['fcm_token'], $title, $body, $data);
    }

    /**
     * Send a push notification to a driver by their internal ID.
     */
    public function sendToDriver(int $driverId, string $title, string $body, array $data = []): bool
    {
        $driver = $this->db->fetchOne(
            "SELECT fcm_token FROM drivers WHERE id = ?",
            [$driverId]
        );

        if ($driver === null || empty($driver['fcm_token'])) {
            return false;
        }

        return $this->sendToToken($driver['fcm_token'], $title, $body, $data);
    }

    /**
     * Send push notification to a specific FCM token using the legacy HTTP API.
     */
    private function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if ($this->fcmServerKey === '') {
            $this->logger->debug('FCM push skipped: FCM_SERVER_KEY not configured');
            return false;
        }

        $payload = json_encode([
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
        ]);

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: key=' . $this->fcmServerKey,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error('FCM push failed (cURL)', ['error' => $curlError, 'token_prefix' => substr($token, 0, 12)]);
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200 || ($result['success'] ?? 0) < 1) {
            $this->logger->warning('FCM push failed', [
                'http_code' => $httpCode,
                'response' => $result,
                'token_prefix' => substr($token, 0, 12),
            ]);
            return false;
        }

        return true;
    }
}
