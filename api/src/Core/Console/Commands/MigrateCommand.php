<?php

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Database\Connection;
use App\Core\Database\Migrator;

final class MigrateCommand
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function execute(array $args): int
    {
        echo "Running migrations...\n";

        $connection = new Connection(
            host: $_ENV['DB_HOST'] ?? '127.0.0.1',
            port: (int) ($_ENV['DB_PORT'] ?? 3306),
            database: $_ENV['DB_DATABASE'] ?? 'cloudstore',
            username: $_ENV['DB_USERNAME'] ?? 'root',
            password: $_ENV['DB_PASSWORD'] ?? '',
        );

        $migrator = new Migrator($connection, $this->basePath . '/database/migrations');
        $migrated = $migrator->migrate();

        if (empty($migrated)) {
            echo "Nothing to migrate.\n";
        } else {
            foreach ($migrated as $file) {
                echo "  Migrated: {$file}\n";
            }
        }

        return 0;
    }
}
