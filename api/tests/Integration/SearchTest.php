<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the search suggestions endpoint.
 *
 * Requires a seeded database. Run with:
 *   vendor/bin/phpunit --testsuite Integration --filter SearchTest
 */
final class SearchTest extends TestCase
{
    private string $baseUrl;
    private string $appToken;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000/api/v1';
        $this->appToken = getenv('TEST_APP_TOKEN') ?: '';
        if (empty($this->appToken)) {
            $this->markTestSkipped('TEST_APP_TOKEN not set');
        }
    }

    public function testSearchSuggestionsRequiresQuery(): void
    {
        $response = $this->get('/search/suggestions');
        $this->assertSame(422, $response['status']);
    }

    public function testSearchSuggestionsMinLength(): void
    {
        $response = $this->get('/search/suggestions?q=a');
        $body = json_decode($response['body'], true);
        $this->assertFalse($body['success'] ?? true);
    }

    public function testSearchSuggestionsReturnsResults(): void
    {
        // "mut" should match seeded products (e.g. Mutton)
        $response = $this->get('/search/suggestions?q=mut');
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('products', $body['data'] ?? []);
        $this->assertArrayHasKey('categories', $body['data'] ?? []);
    }

    public function testSearchSuggestionsEmptyForGibberish(): void
    {
        $response = $this->get('/search/suggestions?q=zzzxxx999');
        $body = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($body['success'] ?? false);
        $this->assertEmpty($body['data']['products'] ?? []);
    }

    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
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

        return ['status' => $status, 'body' => $body ?: ''];
    }
}
