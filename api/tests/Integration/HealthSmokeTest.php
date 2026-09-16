<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests that verify all critical API endpoints respond correctly.
 *
 * Run with:
 *   vendor/bin/phpunit --testsuite Integration --filter HealthSmokeTest
 */
final class HealthSmokeTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000';
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->get('/health');
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertSame('ok', $body['status'] ?? '');
    }

    public function testHealthReady(): void
    {
        $response = $this->get('/health/ready');
        $this->assertSame(200, $response['status']);
    }

    public function testHealthLive(): void
    {
        $response = $this->get('/health/live');
        $this->assertSame(200, $response['status']);
    }

    public function testBootstrapRequiresAppToken(): void
    {
        $ch = curl_init($this->baseUrl . '/api/v1/app/bootstrap');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Should fail without X-App-Token
        $this->assertContains($status, [401, 403]);
    }

    public function testAdminLoginEndpointExists(): void
    {
        $ch = curl_init($this->baseUrl . '/api/v1/admin/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['email' => 'x', 'password' => 'x']),
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Should be 401 (bad creds) or 422 (validation), NOT 404
        $this->assertNotSame(404, $status);
    }

    public function testCorsPreflightAllowed(): void
    {
        $ch = curl_init($this->baseUrl . '/api/v1/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_HTTPHEADER => [
                'Origin: http://localhost:3000',
                'Access-Control-Request-Method: GET',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // OPTIONS should succeed
        $this->assertContains($status, [200, 204]);
    }

    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body ?: ''];
    }
}
