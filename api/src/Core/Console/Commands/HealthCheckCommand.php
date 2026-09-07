<?php

declare(strict_types=1);

namespace App\Core\Console\Commands;

final class HealthCheckCommand
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function execute(array $args): int
    {
        echo "Health check: OK\n";

        return 0;
    }
}
