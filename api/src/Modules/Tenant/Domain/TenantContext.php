<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Domain;

final class TenantContext
{
    private static ?Tenant $current = null;

    public static function set(Tenant $tenant): void
    {
        self::$current = $tenant;
    }

    public static function get(): Tenant
    {
        if (self::$current === null) {
            throw new \RuntimeException('No tenant context set.');
        }

        return self::$current;
    }

    public static function id(): int
    {
        return self::get()->id;
    }

    public static function has(): bool
    {
        return self::$current !== null;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
