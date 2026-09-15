<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repository;

use App\Core\Database\Connection;

final class SupportTicketRepository
{
    public function __construct(private readonly Connection $db) {}

    /** @return array<int, array<string, mixed>> */
    public function findByCustomer(int $tenantId, int $customerId): array
    {
        return $this->db->fetchAll(
            "SELECT st.*, o.order_number
             FROM support_tickets st
             LEFT JOIN orders o ON o.id = st.order_id
             WHERE st.tenant_id = ? AND st.customer_id = ?
             ORDER BY COALESCE(st.last_message_at, st.created_at) DESC",
            [$tenantId, $customerId],
        );
    }

    public function findForCustomer(string $uuid, int $tenantId, int $customerId): ?array
    {
        return $this->db->fetchOne(
            "SELECT st.*, o.order_number
             FROM support_tickets st
             LEFT JOIN orders o ON o.id = st.order_id
             WHERE st.uuid = ? AND st.tenant_id = ? AND st.customer_id = ?",
            [$uuid, $tenantId, $customerId],
        );
    }

    public function findForAdmin(string $uuid, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT st.*, c.name AS customer_name, c.phone AS customer_phone, o.order_number
             FROM support_tickets st
             JOIN customers c ON c.id = st.customer_id
             LEFT JOIN orders o ON o.id = st.order_id
             WHERE st.uuid = ? AND st.tenant_id = ?",
            [$uuid, $tenantId],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function findForAdminList(int $tenantId, ?string $status, int $limit): array
    {
        $sql = "SELECT st.*, c.name AS customer_name, c.phone AS customer_phone, o.order_number
                FROM support_tickets st
                JOIN customers c ON c.id = st.customer_id
                LEFT JOIN orders o ON o.id = st.order_id
                WHERE st.tenant_id = ?";
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND st.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(st.priority, \'high\', \'normal\'), COALESCE(st.last_message_at, st.created_at) DESC LIMIT ?';
        $params[] = $limit;
        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO support_tickets (uuid, tenant_id, customer_id, order_id, subject, category, priority, last_message_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$data['uuid'], $data['tenant_id'], $data['customer_id'], $data['order_id'] ?? null,
                $data['subject'], $data['category'], $data['priority'] ?? 'normal'],
        );
        return (int) $this->db->lastInsertId();
    }

    public function addMessage(int $ticketId, string $uuid, string $senderType, int $senderId, string $body): int
    {
        $this->db->execute(
            'INSERT INTO support_ticket_messages (uuid, ticket_id, sender_type, sender_id, body) VALUES (?, ?, ?, ?, ?)',
            [$uuid, $ticketId, $senderType, $senderId, $body],
        );
        $this->db->execute('UPDATE support_tickets SET last_message_at = NOW(), status = IF(status = \'closed\', \'open\', status) WHERE id = ?', [$ticketId]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function messages(int $ticketId): array
    {
        return $this->db->fetchAll(
            'SELECT uuid, sender_type, body, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC, id ASC',
            [$ticketId],
        );
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        $this->db->execute(
            'UPDATE support_tickets SET status = ?, resolved_at = CASE WHEN ? IN (\'resolved\', \'closed\') THEN NOW() ELSE NULL END WHERE id = ?',
            [$status, $status, $ticketId],
        );
    }
}
