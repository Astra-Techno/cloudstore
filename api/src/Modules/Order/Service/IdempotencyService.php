<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;

final class IdempotencyService
{
    private const TTL_HOURS = 24;
    private const PROCESSING_TTL_SECONDS = 300;
    private const PROCESSING_STATUS = 102;

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array{state:'claimed'|'cached'|'processing'|'conflict',body?:array,status?:int} */
    public function claim(int $tenantId, int $customerId, string $key, string $requestHash): array
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::PROCESSING_TTL_SECONDS);
        $inserted = $this->db->execute(
            "INSERT IGNORE INTO idempotency_keys
                (tenant_id, customer_id, idempotency_key, request_hash, response_body, response_status, expires_at)
             VALUES (?, ?, ?, ?, '{}', ?, ?)",
            [$tenantId, $customerId, $key, $requestHash, self::PROCESSING_STATUS, $expiresAt]
        );
        if ($inserted === 1) {
            return ['state' => 'claimed'];
        }

        $record = $this->db->fetchOne(
            'SELECT * FROM idempotency_keys WHERE tenant_id = ? AND idempotency_key = ? AND expires_at > NOW()',
            [$tenantId, $key]
        );
        if ($record === null) {
            $claimed = $this->db->execute(
                "UPDATE idempotency_keys
                 SET customer_id = ?, request_hash = ?, response_body = '{}', response_status = ?, expires_at = ?
                 WHERE tenant_id = ? AND idempotency_key = ? AND expires_at <= NOW()",
                [$customerId, $requestHash, self::PROCESSING_STATUS, $expiresAt, $tenantId, $key]
            );
            return ['state' => $claimed === 1 ? 'claimed' : 'processing'];
        }

        if ((int) $record['customer_id'] !== $customerId
            || !hash_equals((string) $record['request_hash'], $requestHash)) {
            return ['state' => 'conflict'];
        }
        if ((int) $record['response_status'] === self::PROCESSING_STATUS) {
            return ['state' => 'processing'];
        }

        return [
            'state' => 'cached',
            'body' => json_decode((string) $record['response_body'], true, 512, JSON_THROW_ON_ERROR),
            'status' => (int) $record['response_status'],
        ];
    }

    public function complete(int $tenantId, int $customerId, string $key, string $requestHash, array $body, int $status): void
    {
        $this->db->execute(
            'UPDATE idempotency_keys SET response_body = ?, response_status = ?, expires_at = ?
             WHERE tenant_id = ? AND customer_id = ? AND idempotency_key = ? AND request_hash = ? AND response_status = ?',
            [
                json_encode($body, JSON_THROW_ON_ERROR),
                $status,
                date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600),
                $tenantId,
                $customerId,
                $key,
                $requestHash,
                self::PROCESSING_STATUS,
            ]
        );
    }

    public function release(int $tenantId, int $customerId, string $key, string $requestHash): void
    {
        $this->db->execute(
            'DELETE FROM idempotency_keys
             WHERE tenant_id = ? AND customer_id = ? AND idempotency_key = ? AND request_hash = ? AND response_status = ?',
            [$tenantId, $customerId, $key, $requestHash, self::PROCESSING_STATUS]
        );
    }

    public static function hashRequest(array $data): string
    {
        self::sortRecursively($data);
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function sortRecursively(array &$data): void
    {
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sortRecursively($value);
            }
        }
    }
}
