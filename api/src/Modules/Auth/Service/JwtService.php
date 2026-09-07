<?php

declare(strict_types=1);

namespace App\Modules\Auth\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 3600,
    ) {
    }

    public function issue(array $claims): string
    {
        $now = time();

        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }
}
