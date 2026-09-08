<?php

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Database\Connection;
use Database\Seeders\TenantSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\OfferSeeder;

final class SeedCommand
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function execute(array $args): int
    {
        echo "Running seeders...\n";

        $connection = new Connection(
            host: $_ENV['DB_HOST'] ?? '127.0.0.1',
            port: (int) ($_ENV['DB_PORT'] ?? 3306),
            database: $_ENV['DB_DATABASE'] ?? 'cloudstore',
            username: $_ENV['DB_USERNAME'] ?? 'root',
            password: $_ENV['DB_PASSWORD'] ?? '',
        );

        // Tenant seeder
        $seeder = new TenantSeeder();
        $results = $seeder->run($connection);

        echo "\nSeeded tenants:\n";
        foreach ($results as $result) {
            echo "  {$result['tenant']} ({$result['slug']})\n";
            echo "    App Token: {$result['app_token']}\n\n";
        }

        // Admin seeder
        $adminSeeder = new AdminSeeder();
        $admins = $adminSeeder->run($connection);

        echo "Seeded admins:\n";
        foreach ($admins as $admin) {
            $tenant = $admin['tenant'] ?? 'Platform';
            echo "  [{$tenant}] {$admin['name']} ({$admin['email']}) — {$admin['role']}\n";
        }

        // Catalog seeder
        echo "\nSeeding catalogs:\n";
        $catalogSeeder = new CatalogSeeder();
        $catalogSeeder->run($connection);

        // Delivery zone seeder
        echo "\nSeeding delivery zones:\n";
        $deliveryZoneSeeder = new DeliveryZoneSeeder();
        $deliveryZoneSeeder->run($connection);

        // Offers seeder
        echo "\nSeeding offers:\n";
        $offerSeeder = new OfferSeeder();
        $offerSeeder->run($connection);

        echo "\nIMPORTANT: Save these app tokens. They cannot be retrieved later.\n";
        echo "Default admin password: Admin@123\n";

        return 0;
    }
}
