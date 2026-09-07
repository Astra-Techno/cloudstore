<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Service;

use App\Modules\Tenant\Repository\AppTokenRepository;

final class AppTokenService
{
    private const TOKEN_LENGTH = 32;
    private const PREFIX_LENGTH = 8;

    public function __construct(
        private readonly AppTokenRepository $tokenRepo,
    ) {
    }

    /**
     * Generate a new app token for a tenant.
     * Returns the plain token (show once to admin, never stored).
     */
    public function generate(int $tenantId, ?string $expiresAt = null): array
    {
        $plainToken = $this->generateSecureToken();
        $prefix = substr($plainToken, 0, self::PREFIX_LENGTH);
        $hash = $this->hashToken($plainToken);

        $id = $this->tokenRepo->create($tenantId, $hash, $prefix, $expiresAt);

        return [
            'id' => $id,
            'token' => $plainToken,
            'prefix' => $prefix,
        ];
    }

    /**
     * Validate an app token and return the token record with tenant info.
     */
    public function validate(string $plainToken): ?array
    {
        $hash = $this->hashToken($plainToken);
        $record = $this->tokenRepo->findByHash($hash);

        if ($record === null) {
            return null;
        }

        if ($record['status'] !== 'active') {
            return null;
        }

        if ($record['expires_at'] !== null && strtotime($record['expires_at']) < time()) {
            return null;
        }

        if ($record['tenant_status'] !== 'active') {
            return null;
        }

        $this->tokenRepo->updateLastUsed((int) $record['id']);

        return $record;
    }

    public function revoke(int $tokenId): void
    {
        $this->tokenRepo->revoke($tokenId);
    }

    public function revokeAllForTenant(int $tenantId): void
    {
        $this->tokenRepo->revokeAllForTenant($tenantId);
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
