<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for analytics and audit log endpoints.
 *
 * Run with:
 *   vendor/bin/phpunit --testsuite Integration --filter AnalyticsTest
 */
final class AnalyticsTest extends TestCase
{
    private string $baseUrl;
    private string $appToken;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000/api/v1';
        $this->appToken = getenv('TEST_APP_TOKEN') ?: '';
        $this->adminToken = getenv('TEST_ADMIN_TOKEN') ?: '';

        if (empty($this->appToken) || empty($this->adminToken)) {
            $this->markTestSkipped('TEST_APP_TOKEN and TEST_ADMIN_TOKEN required');
        }
    }

    public function testAnalyticsEndpoint(): void
    {
        $response = $this->get('/admin/analytics');
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('data', $body);
    }

    public function testAnalyticsWithDateRange(): void
    {
        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d');
        $response = $this->get("/admin/analytics?from={$from}&to={$to}");
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($body['success'] ?? false);
    }

    public function testAuditLogEndpoint(): void
    {
        $response = $this->get('/admin/audit-log?limit=10&offset=0');
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('data', $body);
    }

    public function testAnalyticsRequiresAuth(): void
    {
        $ch = curl_init($this->baseUrl . '/admin/analytics');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-App-Token: ' . $this->appToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(401, $status);
    }

    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-App-Token: ' . $this->appToken,
                'Authorization: Bearer ' . $this->adminToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body ?: ''];
    }
}
