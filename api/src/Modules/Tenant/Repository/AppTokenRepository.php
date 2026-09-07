<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Repository;

use App\Core\Database\Connection;

final class AppTokenRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function create(int $tenantId, string $tokenHash, string $tokenPrefix, ?string $expiresAt = null): int
    {
        $this->db->execute(
            "INSERT INTO app_tokens (tenant_id, token_hash, token_prefix, status, expires_at)
             VALUES (?, ?, ?, 'active', ?)",
            [$tenantId, $tokenHash, $tokenPrefix, $expiresAt]
        );

        return (int) $this->db->lastInsertId();
    }

    public function findByHash(string $tokenHash): ?array
    {
        return $this->db->fetchOne(
            "SELECT at.*, t.uuid as tenant_uuid, t.name as tenant_name, t.status as tenant_status
             FROM app_tokens at
             JOIN tenants t ON t.id = at.tenant_id AND t.deleted_at IS NULL
             WHERE at.token_hash = ?",
            [$tokenHash]
        );
    }

    public function updateLastUsed(int $tokenId): void
    {
        $this->db->execute(
            "UPDATE app_tokens SET last_used_at = NOW() WHERE id = ?",
            [$tokenId]
        );
    }

    public function revoke(int $tokenId): void
    {
        $this->db->execute(
            "UPDATE app_tokens SET status = 'revoked', revoked_at = NOW() WHERE id = ?",
            [$tokenId]
        );
    }

    public function revokeAllForTenant(int $tenantId): void
    {
        $this->db->execute(
            "UPDATE app_tokens SET status = 'revoked', revoked_at = NOW() WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );
    }

    public function findByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT id, token_prefix, status, expires_at, last_used_at, created_at, revoked_at
             FROM app_tokens WHERE tenant_id = ? ORDER BY created_at DESC",
            [$tenantId]
        );
    }
}
