<?php

declare(strict_types=1);

namespace App\Core\Console;

final class Application
{
    /** @var array<string, class-string> */
    private array $commands = [];

    private function __construct(
        private readonly string $basePath,
    ) {
    }

    public static function boot(string $basePath): self
    {
        $app = new self($basePath);

        if (file_exists($basePath . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->load();
        }

        $app->registerCommands();

        return $app;
    }

    private function registerCommands(): void
    {
        $this->commands = [
            'migrate' => \App\Core\Console\Commands\MigrateCommand::class,
            'migrate:rollback' => \App\Core\Console\Commands\MigrateRollbackCommand::class,
            'seed' => \App\Core\Console\Commands\SeedCommand::class,
            'health:check' => \App\Core\Console\Commands\HealthCheckCommand::class,
        ];
    }

    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;

        if ($commandName === null || $commandName === 'list') {
            $this->listCommands();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            echo "Unknown command: {$commandName}\n";
            return 1;
        }

        $args = array_slice($argv, 2);
        $command = new ($this->commands[$commandName])($this->basePath);

        return $command->execute($args);
    }

    private function listCommands(): void
    {
        echo "CloudStore CLI\n\n";
        echo "Available commands:\n";

        foreach (array_keys($this->commands) as $name) {
            echo "  {$name}\n";
        }
    }
}
