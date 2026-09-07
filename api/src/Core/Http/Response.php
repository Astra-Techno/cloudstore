<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Response
{
    private function __construct(
        private readonly int $status,
        private readonly array $data,
        private readonly array $headers,
    ) {
    }

    public static function success(mixed $data = null, array $meta = [], int $status = 200): self
    {
        $body = ['success' => true];

        if ($data !== null) {
            $body['data'] = $data;
        }

        if (!empty($meta)) {
            $body['meta'] = $meta;
        }

        return new self($status, $body, ['Content-Type' => 'application/json']);
    }

    public static function error(
        string $message,
        string $code = 'ERROR',
        int $status = 400,
        array $fields = [],
    ): self {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (!empty($fields)) {
            $error['fields'] = $fields;
        }

        return new self($status, ['success' => false, 'error' => $error], ['Content-Type' => 'application/json']);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return self::error($message, 'NOT_FOUND', 404);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): self
    {
        return self::error($message, 'UNAUTHORIZED', 401);
    }

    public static function forbidden(string $message = 'Forbidden.'): self
    {
        return self::error($message, 'FORBIDDEN', 403);
    }

    public static function validationError(array $fields): self
    {
        return self::error('Validation failed.', 'VALIDATION_ERROR', 422, $fields);
    }

    public static function tooManyRequests(string $message = 'Too many requests.'): self
    {
        return self::error($message, 'RATE_LIMITED', 429);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
