<?php

declare(strict_types=1);

namespace App\Modules\Payment\Repository;

use App\Core\Database\Connection;

final class PaymentRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM payments WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    public function findByUuid(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM payments WHERE uuid = ? AND tenant_id = ?",
            [$uuid, $tenantId]
        );
    }

    public function findByOrderId(int $orderId, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM payments WHERE order_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$orderId, $tenantId]
        );
    }

    public function findByGatewayOrderId(string $gatewayOrderId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM payments WHERE gateway_order_id = ?",
            [$gatewayOrderId]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO payments (uuid, tenant_id, order_id, customer_id, gateway, gateway_order_id, amount, currency, status, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['tenant_id'], $data['order_id'], $data['customer_id'],
                $data['gateway'], $data['gateway_order_id'] ?? null,
                $data['amount'], $data['currency'] ?? 'INR',
                $data['status'] ?? 'pending', isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, array $extra = []): void
    {
        $sql = "UPDATE payments SET status = ?";
        $params = [$status];

        if (isset($extra['gateway_payment_id'])) {
            $sql .= ", gateway_payment_id = ?";
            $params[] = $extra['gateway_payment_id'];
        }

        if (isset($extra['gateway_signature'])) {
            $sql .= ", gateway_signature = ?";
            $params[] = $extra['gateway_signature'];
        }

        if ($status === 'paid') {
            $sql .= ", paid_at = NOW()";
        }

        if (isset($extra['failure_reason'])) {
            $sql .= ", failure_reason = ?";
            $params[] = $extra['failure_reason'];
        }

        if ($status === 'refunded') {
            $sql .= ", refunded_at = NOW()";
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $this->db->execute($sql, $params);
    }

    public function findByTenant(int $tenantId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.*, o.order_number FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.tenant_id = ?";
        $params = [$tenantId];

        if ($status !== null) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }
}
