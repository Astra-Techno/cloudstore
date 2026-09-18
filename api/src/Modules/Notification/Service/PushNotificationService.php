<?php

declare(strict_types=1);

namespace App\Modules\Notification\Service;

use App\Core\Config\Config;
use App\Core\Database\Connection;
use App\Core\Logging\Logger;
use App\Modules\Platform\Service\OperationalConfig;

/**
 * Sends push notifications via Firebase Cloud Messaging v1 API.
 *
 * Requires a service-account JSON stored in OperationalConfig as
 * `fcm_service_account` (per-tenant or global).  The JSON must contain
 * at least: client_email, private_key, project_id.
 */
final class PushNotificationService
{
    /** Access tokens cached per project_id for the lifetime of the request. */
    private array $tokenCache = [];

    public function __construct(
        private readonly Connection $db,
        private readonly Logger $logger,
        private readonly OperationalConfig $config,
    ) {}

    /**
     * Send a push notification to a customer by their internal ID.
     */
    public function sendToCustomer(int $customerId, string $title, string $body, array $data = []): bool
    {
        $customer = $this->db->fetchOne(
            "SELECT fcm_token, tenant_id FROM customers WHERE id = ? AND deleted_at IS NULL",
            [$customerId]
        );

        if ($customer === null || empty($customer['fcm_token'])) {
            return false;
        }

        return $this->sendToToken($customer['fcm_token'], (int) $customer['tenant_id'], $title, $body, $data);
    }

    /**
     * Send a push notification to a driver by their internal ID.
     */
    public function sendToDriver(int $driverId, string $title, string $body, array $data = []): bool
    {
        $driver = $this->db->fetchOne(
            "SELECT fcm_token, tenant_id FROM drivers WHERE id = ?",
            [$driverId]
        );

        if ($driver === null || empty($driver['fcm_token'])) {
            return false;
        }

        return $this->sendToToken($driver['fcm_token'], (int) $driver['tenant_id'], $title, $body, $data);
    }

    /**
     * Send push notification to a specific FCM token using FCM v1 HTTP API.
     */
    private function sendToToken(string $token, int $tenantId, string $title, string $body, array $data = []): bool
    {
        $serviceAccount = $this->getServiceAccount($tenantId);
        if ($serviceAccount === null) {
            $this->logger->debug('FCM push skipped: fcm_service_account not configured');
            return false;
        }

        $accessToken = $this->getAccessToken($serviceAccount);
        if ($accessToken === null) {
            return false;
        }

        $projectId = $serviceAccount['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // FCM v1 payload — data values must all be strings
        $stringData = array_map('strval', $data);

        $payload = json_encode([
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData ?: new \stdClass(),
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'orders',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
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

        if ($httpCode !== 200) {
            $this->logger->warning('FCM push failed', [
                'http_code' => $httpCode,
                'error' => $result['error']['message'] ?? $response,
                'token_prefix' => substr($token, 0, 12),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Parse the service-account JSON from OperationalConfig.
     */
    private function getServiceAccount(int $tenantId): ?array
    {
        $json = $this->config->get('fcm_service_account', $tenantId);
        if ($json === '') {
            return null;
        }

        $sa = json_decode($json, true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
            $this->logger->error('FCM service account JSON is invalid or incomplete');
            return null;
        }

        return $sa;
    }

    /**
     * Mint a short-lived OAuth2 access token using the service-account
     * private key (RS256 JWT → Google token endpoint).
     */
    private function getAccessToken(array $sa): ?string
    {
        $projectId = $sa['project_id'];
        if (isset($this->tokenCache[$projectId])) {
            return $this->tokenCache[$projectId];
        }

        $now = time();
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64url(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $header . '.' . $claims;

        $privateKey = openssl_pkey_get_private($sa['private_key']);
        if ($privateKey === false) {
            $this->logger->error('FCM: cannot parse service-account private key');
            return null;
        }

        $signature = '';
        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $this->logger->error('FCM: JWT signing failed');
            return null;
        }

        $jwt = $unsigned . '.' . $this->base64url($signature);

        // Exchange JWT for access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            $this->logger->error('FCM: token exchange failed', ['http_code' => $httpCode, 'response' => $response]);
            return null;
        }

        $tokenData = json_decode($response, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if ($accessToken === null) {
            $this->logger->error('FCM: no access_token in response', ['response' => $tokenData]);
            return null;
        }

        $this->tokenCache[$projectId] = $accessToken;
        return $accessToken;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
