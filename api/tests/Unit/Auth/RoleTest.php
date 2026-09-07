<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Modules\Auth\Domain\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testPlatformAdminHasWildcardPermission(): void
    {
        $permissions = Role::getDefaultPermissions(Role::PLATFORM_ADMIN);

        $this->assertContains('*', $permissions);
    }

    public function testTenantOwnerHasManagePermissions(): void
    {
        $permissions = Role::getDefaultPermissions(Role::TENANT_OWNER);

        $this->assertContains('orders.view', $permissions);
        $this->assertContains('orders.manage', $permissions);
        $this->assertContains('orders.refund', $permissions);
        $this->assertContains('settings.manage', $permissions);
        $this->assertContains('tokens.manage', $permissions);
    }

    public function testStaffHasLimitedPermissions(): void
    {
        $permissions = Role::getDefaultPermissions(Role::STAFF);

        $this->assertContains('orders.view', $permissions);
        $this->assertContains('orders.manage', $permissions);
        $this->assertNotContains('orders.refund', $permissions);
        $this->assertNotContains('settings.manage', $permissions);
    }

    public function testKitchenStaffCanOnlyViewOrders(): void
    {
        $permissions = Role::getDefaultPermissions(Role::KITCHEN_STAFF);

        $this->assertContains('orders.view', $permissions);
        $this->assertCount(1, $permissions);
    }

    public function testRoleHierarchy(): void
    {
        $this->assertTrue(Role::isHigherOrEqual(Role::PLATFORM_ADMIN, Role::TENANT_OWNER));
        $this->assertTrue(Role::isHigherOrEqual(Role::TENANT_OWNER, Role::STAFF));
        $this->assertFalse(Role::isHigherOrEqual(Role::STAFF, Role::TENANT_OWNER));
        $this->assertTrue(Role::isHigherOrEqual(Role::MANAGER, Role::MANAGER));
    }
}
