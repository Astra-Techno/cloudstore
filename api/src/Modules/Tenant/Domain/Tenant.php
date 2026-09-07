<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Domain;

final class Tenant
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_ARCHIVED,
    ];

    public const BUSINESS_TYPES = [
        'restaurant',
        'hotel',
        'home_kitchen',
        'meat_shop',
        'fish_shop',
        'bakery',
        'cloud_kitchen',
        'catering',
        'sweet_shop',
        'juice_shop',
        'other',
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $businessType,
        public readonly string $status,
        public readonly string $timezone,
        public readonly string $currency,
        public readonly string $locale,
        public readonly ?string $contactPhone,
        public readonly ?string $contactEmail,
        public readonly ?string $address,
        public readonly ?array $configuration,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            name: $row['name'],
            slug: $row['slug'],
            businessType: $row['business_type'],
            status: $row['status'],
            timezone: $row['timezone'],
            currency: $row['currency'],
            locale: $row['locale'],
            contactPhone: $row['contact_phone'],
            contactEmail: $row['contact_email'],
            address: $row['address'],
            configuration: $row['configuration'] ? json_decode($row['configuration'], true) : null,
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'business_type' => $this->businessType,
        ];
    }
}
