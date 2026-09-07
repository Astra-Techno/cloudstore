<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Repository;

use App\Core\Database\Connection;
use App\Modules\Tenant\Domain\Tenant;

final class TenantRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?Tenant
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );

        return $row ? Tenant::fromRow($row) : null;
    }

    public function findByUuid(string $uuid): ?Tenant
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM tenants WHERE uuid = ? AND deleted_at IS NULL",
            [$uuid]
        );

        return $row ? Tenant::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM tenants WHERE slug = ? AND deleted_at IS NULL",
            [$slug]
        );

        return $row ? Tenant::fromRow($row) : null;
    }

    public function create(array $data): Tenant
    {
        $this->db->execute(
            "INSERT INTO tenants (uuid, name, slug, business_type, status, timezone, currency, locale, contact_phone, contact_email, address, configuration)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['uuid'],
                $data['name'],
                $data['slug'],
                $data['business_type'] ?? 'other',
                $data['status'] ?? 'draft',
                $data['timezone'] ?? 'Asia/Kolkata',
                $data['currency'] ?? 'INR',
                $data['locale'] ?? 'en-IN',
                $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null,
                $data['address'] ?? null,
                isset($data['configuration']) ? json_encode($data['configuration']) : null,
            ]
        );

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?Tenant
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $this->db->execute(
            "UPDATE tenants SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );

        return $this->findById($id);
    }

    public function findAll(string $status = null): array
    {
        $sql = "SELECT * FROM tenants WHERE deleted_at IS NULL";
        $params = [];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $rows = $this->db->fetchAll($sql, $params);

        return array_map(fn(array $row) => Tenant::fromRow($row), $rows);
    }
}
