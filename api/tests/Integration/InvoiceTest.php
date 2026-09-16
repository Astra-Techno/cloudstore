<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the invoice endpoint.
 *
 * Run with:
 *   vendor/bin/phpunit --testsuite Integration --filter InvoiceTest
 */
final class InvoiceTest extends TestCase
{
    private string $baseUrl;
    private string $appToken;
    private string $customerToken;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000/api/v1';
        $this->appToken = getenv('TEST_APP_TOKEN') ?: '';
        $this->customerToken = getenv('TEST_CUSTOMER_TOKEN') ?: '';

        if (empty($this->appToken) || empty($this->customerToken)) {
            $this->markTestSkipped('TEST_APP_TOKEN and TEST_CUSTOMER_TOKEN required');
        }
    }

    public function testInvoiceRequiresAuth(): void
    {
        $response = $this->get('/customer/orders/fake-uuid/invoice', '');
        $this->assertSame(401, $response['status']);
    }

    public function testInvoiceReturns404ForInvalidOrder(): void
    {
        $response = $this->get('/customer/orders/00000000-0000-0000-0000-000000000000/invoice', $this->customerToken);
        $this->assertContains($response['status'], [404, 403, 400]);
    }

    private function get(string $path, string $authToken): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'X-App-Token: ' . $this->appToken,
            'Accept: application/json',
        ];
        if (!empty($authToken)) {
            $headers[] = 'Authorization: Bearer ' . $authToken;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body ?: ''];
    }
}
