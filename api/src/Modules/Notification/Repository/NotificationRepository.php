<?php

declare(strict_types=1);

namespace App\Modules\Notification\Repository;

use App\Core\Database\Connection;

final class NotificationRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO notifications (uuid, tenant_id, recipient_type, recipient_id, channel, type, title, body, data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'], $data['tenant_id'], $data['recipient_type'], $data['recipient_id'],
                $data['channel'] ?? 'in_app', $data['type'], $data['title'], $data['body'],
                isset($data['data']) ? json_encode($data['data']) : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function findByRecipient(string $recipientType, int $recipientId, int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM notifications
             WHERE recipient_type = ? AND recipient_id = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$recipientType, $recipientId, $tenantId, $limit, $offset]
        );
    }

    public function countUnread(string $recipientType, int $recipientId, int $tenantId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications
             WHERE recipient_type = ? AND recipient_id = ? AND tenant_id = ? AND read_at IS NULL",
            [$recipientType, $recipientId, $tenantId]
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function markRead(int $id, string $recipientType, int $recipientId): void
    {
        $this->db->execute(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND recipient_type = ? AND recipient_id = ?",
            [$id, $recipientType, $recipientId]
        );
    }

    public function markAllRead(string $recipientType, int $recipientId, int $tenantId): void
    {
        $this->db->execute(
            "UPDATE notifications SET read_at = NOW()
             WHERE recipient_type = ? AND recipient_id = ? AND tenant_id = ? AND read_at IS NULL",
            [$recipientType, $recipientId, $tenantId]
        );
    }

    public function markSent(int $id): void
    {
        $this->db->execute("UPDATE notifications SET sent_at = NOW() WHERE id = ?", [$id]);
    }

    public function markFailed(int $id): void
    {
        $this->db->execute("UPDATE notifications SET failed_at = NOW() WHERE id = ?", [$id]);
    }
}
