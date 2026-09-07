<?php

declare(strict_types=1);

namespace App\Modules\Payment\Repository;

use App\Core\Database\Connection;

final class RefundRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO refunds (uuid, payment_id, order_id, tenant_id, amount, reason, status, initiated_by_type, initiated_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['payment_id'], $data['order_id'], $data['tenant_id'],
                $data['amount'], $data['reason'] ?? null, $data['status'] ?? 'pending',
                $data['initiated_by_type'] ?? null, $data['initiated_by_id'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function findByOrderId(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM refunds WHERE order_id = ? ORDER BY created_at DESC",
            [$orderId]
        );
    }

    public function updateStatus(int $id, string $status, ?string $gatewayRefundId = null): void
    {
        $sql = "UPDATE refunds SET status = ?";
        $params = [$status];

        if ($gatewayRefundId !== null) {
            $sql .= ", gateway_refund_id = ?";
            $params[] = $gatewayRefundId;
        }

        if ($status === 'completed') {
            $sql .= ", processed_at = NOW()";
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $this->db->execute($sql, $params);
    }
}
