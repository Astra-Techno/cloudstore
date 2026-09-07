<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Auth\Domain\Role;
use Ramsey\Uuid\Uuid;

final class AdminSeeder
{
    public function run(Connection $db): array
    {
        $adminRepo = new AdminRepository($db);
        $passwordService = new PasswordService();

        $results = [];

        // Platform admin (no tenant)
        $platformAdminId = $adminRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'admin@cloudstore.com',
            'password_hash' => $passwordService->hash('Admin@123'),
            'role' => Role::PLATFORM_ADMIN,
            'status' => 'active',
        ]);
        $adminRepo->setPermissions($platformAdminId, ['*']);
        $results[] = ['name' => 'Platform Admin', 'email' => 'admin@cloudstore.com', 'password' => 'Admin@123', 'role' => Role::PLATFORM_ADMIN];

        // Get tenant IDs
        $tenants = $db->fetchAll("SELECT id, name, slug FROM tenants WHERE deleted_at IS NULL ORDER BY id");

        foreach ($tenants as $tenant) {
            $email = $tenant['slug'] . '@cloudstore.com';
            $adminId = $adminRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_id' => $tenant['id'],
                'name' => $tenant['name'] . ' Admin',
                'email' => $email,
                'password_hash' => $passwordService->hash('Admin@123'),
                'role' => Role::TENANT_OWNER,
                'status' => 'active',
            ]);

            $adminRepo->setPermissions($adminId, Role::getDefaultPermissions(Role::TENANT_OWNER));

            $results[] = [
                'name' => $tenant['name'] . ' Admin',
                'email' => $email,
                'password' => 'Admin@123',
                'role' => Role::TENANT_OWNER,
                'tenant' => $tenant['name'],
            ];
        }

        return $results;
    }
}
