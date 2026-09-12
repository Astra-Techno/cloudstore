<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Service;

use App\Core\Config\Config;
use App\Modules\Tenant\Repository\AppTokenRepository;

final class AppTokenService
{
    private const TOKEN_LENGTH = 32;
    private const PREFIX_LENGTH = 8;

    public function __construct(
        private readonly AppTokenRepository $tokenRepo,
        private readonly Config $config,
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

        $id = $this->tokenRepo->create($tenantId, $hash, $this->encrypt($plainToken), $prefix, $expiresAt);

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

    /** Returns the active tenant credential for a build, never for an API response. */
    public function getReusableBuildToken(int $tenantId): ?string
    {
        $record = $this->tokenRepo->findActiveByTenant($tenantId);
        if ($record === null || empty($record['token_ciphertext'])) {
            return null;
        }

        return $this->decrypt((string) $record['token_ciphertext']);
    }

    /** Migrates a legacy hash-only token after the admin supplies and validates it once. */
    public function storeReusableBuildToken(int $tokenId, string $plainToken): void
    {
        $this->tokenRepo->updateCiphertext($tokenId, $this->encrypt($plainToken));
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function encryptionKey(): string
    {
        // APP_KEY is deployment-specific and never leaves the server. A
        // separate APP_TOKEN_ENCRYPTION_KEY may be supplied for key rotation.
        $material = $this->config->get('APP_TOKEN_ENCRYPTION_KEY', $this->config->get('APP_KEY'));
        if ($material === '') {
            throw new \RuntimeException('APP_KEY or APP_TOKEN_ENCRYPTION_KEY must be configured for app builds.');
        }

        return hash('sha256', $material, true);
    }

    private function encrypt(string $plainToken): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainToken, $nonce, $this->encryptionKey());

        return base64_encode($nonce . $ciphertext);
    }

    private function decrypt(string $encoded): ?string
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainToken = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey());

        return $plainToken === false ? null : $plainToken;
    }
}
