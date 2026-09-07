<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;

final class IdempotencyService
{
    private const TTL_HOURS = 24;

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function check(int $tenantId, string $key, string $requestHash): ?array
    {
        $record = $this->db->fetchOne(
            "SELECT * FROM idempotency_keys WHERE tenant_id = ? AND idempotency_key = ? AND expires_at > NOW()",
            [$tenantId, $key]
        );

        if ($record === null) {
            return null;
        }

        if ($record['request_hash'] !== $requestHash) {
            return null; // Same key but different request — reject
        }

        return [
            'body' => json_decode($record['response_body'], true),
            'status' => (int) $record['response_status'],
        ];
    }

    public function store(int $tenantId, ?int $customerId, string $key, string $requestHash, array $responseBody, int $responseStatus): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600);

        $this->db->execute(
            "INSERT INTO idempotency_keys (tenant_id, customer_id, idempotency_key, request_hash, response_body, response_status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE response_body = VALUES(response_body), response_status = VALUES(response_status)",
            [$tenantId, $customerId, $key, $requestHash, json_encode($responseBody), $responseStatus, $expiresAt]
        );
    }

    public static function hashRequest(array $data): string
    {
        return hash('sha256', json_encode($data));
    }
}
