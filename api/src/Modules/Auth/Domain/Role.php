<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain;

final class Role
{
    public const PLATFORM_ADMIN = 'platform_admin';
    public const TENANT_OWNER = 'tenant_owner';
    public const TENANT_ADMIN = 'tenant_admin';
    public const MANAGER = 'manager';
    public const STAFF = 'staff';
    public const KITCHEN_STAFF = 'kitchen_staff';
    public const DISPATCHER = 'dispatcher';

    public const ALL = [
        self::PLATFORM_ADMIN,
        self::TENANT_OWNER,
        self::TENANT_ADMIN,
        self::MANAGER,
        self::STAFF,
        self::KITCHEN_STAFF,
        self::DISPATCHER,
    ];

    /**
     * Role hierarchy — higher roles inherit all permissions of lower roles.
     */
    private const HIERARCHY = [
        self::PLATFORM_ADMIN => 100,
        self::TENANT_OWNER => 90,
        self::TENANT_ADMIN => 80,
        self::MANAGER => 60,
        self::DISPATCHER => 40,
        self::STAFF => 30,
        self::KITCHEN_STAFF => 20,
    ];

    /**
     * Default permissions per role.
     */
    public static function getDefaultPermissions(string $role): array
    {
        return match ($role) {
            self::PLATFORM_ADMIN => ['*'],
            self::TENANT_OWNER => [
                'orders.view', 'orders.manage', 'orders.cancel', 'orders.refund',
                'products.view', 'products.manage',
                'inventory.manage',
                'customers.view',
                'drivers.manage',
                'reports.view',
                'settings.manage',
                'tokens.manage',
            ],
            self::TENANT_ADMIN => [
                'orders.view', 'orders.manage', 'orders.cancel',
                'products.view', 'products.manage',
                'inventory.manage',
                'customers.view',
                'drivers.manage',
                'reports.view',
                'settings.manage',
            ],
            self::MANAGER => [
                'orders.view', 'orders.manage',
                'products.view', 'products.manage',
                'inventory.manage',
                'customers.view',
                'drivers.manage',
            ],
            self::STAFF => [
                'orders.view', 'orders.manage',
                'products.view',
                'customers.view',
            ],
            self::KITCHEN_STAFF => [
                'orders.view',
            ],
            self::DISPATCHER => [
                'orders.view',
                'drivers.manage',
            ],
            default => [],
        };
    }

    public static function isHigherOrEqual(string $role, string $requiredRole): bool
    {
        $roleLevel = self::HIERARCHY[$role] ?? 0;
        $requiredLevel = self::HIERARCHY[$requiredRole] ?? 0;

        return $roleLevel >= $requiredLevel;
    }
}
