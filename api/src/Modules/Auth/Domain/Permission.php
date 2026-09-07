<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain;

final class Permission
{
    public const ORDERS_VIEW = 'orders.view';
    public const ORDERS_MANAGE = 'orders.manage';
    public const ORDERS_CANCEL = 'orders.cancel';
    public const ORDERS_REFUND = 'orders.refund';
    public const PRODUCTS_VIEW = 'products.view';
    public const PRODUCTS_MANAGE = 'products.manage';
    public const INVENTORY_MANAGE = 'inventory.manage';
    public const CUSTOMERS_VIEW = 'customers.view';
    public const DRIVERS_MANAGE = 'drivers.manage';
    public const REPORTS_VIEW = 'reports.view';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const TOKENS_MANAGE = 'tokens.manage';

    public const ALL = [
        self::ORDERS_VIEW,
        self::ORDERS_MANAGE,
        self::ORDERS_CANCEL,
        self::ORDERS_REFUND,
        self::PRODUCTS_VIEW,
        self::PRODUCTS_MANAGE,
        self::INVENTORY_MANAGE,
        self::CUSTOMERS_VIEW,
        self::DRIVERS_MANAGE,
        self::REPORTS_VIEW,
        self::SETTINGS_MANAGE,
        self::TOKENS_MANAGE,
    ];
}
