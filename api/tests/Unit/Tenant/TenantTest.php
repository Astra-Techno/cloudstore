<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Modules\Tenant\Domain\Tenant;
use App\Modules\Tenant\Domain\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function testFromRowCreatesValidTenant(): void
    {
        $row = [
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Jeyam Mutton',
            'slug' => 'jeyam-mutton',
            'business_type' => 'meat_shop',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'locale' => 'en-IN',
            'contact_phone' => '+919876543210',
            'contact_email' => 'test@example.com',
            'address' => '123 Main St',
            'configuration' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];

        $tenant = Tenant::fromRow($row);

        $this->assertSame(1, $tenant->id);
        $this->assertSame('Jeyam Mutton', $tenant->name);
        $this->assertSame('meat_shop', $tenant->businessType);
        $this->assertTrue($tenant->isActive());
    }

    public function testInactiveTenantIsNotActive(): void
    {
        $row = $this->makeRow(['status' => 'suspended']);
        $tenant = Tenant::fromRow($row);

        $this->assertFalse($tenant->isActive());
    }

    public function testToPublicArrayExposesOnlyPublicData(): void
    {
        $row = $this->makeRow();
        $tenant = Tenant::fromRow($row);
        $public = $tenant->toPublicArray();

        $this->assertArrayHasKey('id', $public);
        $this->assertArrayHasKey('name', $public);
        $this->assertArrayHasKey('slug', $public);
        $this->assertArrayHasKey('business_type', $public);
        // Must use UUID, not internal ID
        $this->assertSame($tenant->uuid, $public['id']);
        // Must NOT expose internal id
        $this->assertNotSame($tenant->id, $public['id']);
    }

    public function testTenantContextSetAndGet(): void
    {
        $tenant = Tenant::fromRow($this->makeRow());
        TenantContext::set($tenant);

        $this->assertTrue(TenantContext::has());
        $this->assertSame($tenant, TenantContext::get());
        $this->assertSame(1, TenantContext::id());

        TenantContext::clear();
        $this->assertFalse(TenantContext::has());
    }

    public function testTenantContextThrowsWhenNotSet(): void
    {
        TenantContext::clear();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tenant context set.');
        TenantContext::get();
    }

    public function testConfigurationJsonDecoded(): void
    {
        $row = $this->makeRow(['configuration' => '{"max_orders_per_day":50}']);
        $tenant = Tenant::fromRow($row);

        $this->assertIsArray($tenant->configuration);
        $this->assertSame(50, $tenant->configuration['max_orders_per_day']);
    }

    public function testValidStatuses(): void
    {
        $this->assertContains('draft', Tenant::VALID_STATUSES);
        $this->assertContains('active', Tenant::VALID_STATUSES);
        $this->assertContains('suspended', Tenant::VALID_STATUSES);
        $this->assertContains('archived', Tenant::VALID_STATUSES);
        $this->assertCount(4, Tenant::VALID_STATUSES);
    }

    public function testBusinessTypes(): void
    {
        $this->assertContains('restaurant', Tenant::BUSINESS_TYPES);
        $this->assertContains('meat_shop', Tenant::BUSINESS_TYPES);
        $this->assertContains('home_kitchen', Tenant::BUSINESS_TYPES);
        $this->assertContains('hotel', Tenant::BUSINESS_TYPES);
    }

    private function makeRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'business_type' => 'restaurant',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'locale' => 'en-IN',
            'contact_phone' => null,
            'contact_email' => null,
            'address' => null,
            'configuration' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides);
    }
}
