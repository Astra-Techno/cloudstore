<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Request
{
    public ?array $authClaims = null;
    /** @var array<string, mixed> */
    public array $attributes = [];

    private function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        private readonly string $rawBody,
    ) {
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            uri: $uri,
            path: $path,
            query: $_GET,
            headers: self::extractHeaders(),
            rawBody: file_get_contents('php://input') ?: '',
        );
    }

    public function json(): array
    {
        $data = json_decode($this->rawBody, true);

        return is_array($data) ? $data : [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = $this->json();

        return $data[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, string $default = ''): string
    {
        $normalized = strtolower($name);

        return $this->headers[$normalized] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function files(string $key): array
    {
        if (!isset($_FILES[$key])) {
            return [];
        }

        $files = $_FILES[$key];
        if (!is_array($files['name'])) {
            return [$files];
        }

        $result = [];
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name' => $name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }

        return $result;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    private static function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }
}
