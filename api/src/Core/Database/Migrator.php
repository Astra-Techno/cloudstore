<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

final class Migrator
{
    private PDO $pdo;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsPath,
    ) {
        $this->pdo = $this->connection->getPdo();
    }

    public function initialize(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function migrate(): array
    {
        $this->initialize();

        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $pending = array_diff($files, $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatch();
        $migrated = [];

        foreach ($pending as $file) {
            $this->runMigration($file, $batch);
            $migrated[] = $file;
        }

        return $migrated;
    }

    public function rollback(): array
    {
        $this->initialize();

        $batch = $this->getLastBatch();

        if ($batch === 0) {
            return [];
        }

        $migrations = $this->getMigrationsForBatch($batch);
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    private function runMigration(string $file, int $batch): void
    {
        $path = $this->migrationsPath . '/' . $file;
        $migration = require $path;

        if (!isset($migration['up'])) {
            throw new \RuntimeException("Migration {$file} must return an array with 'up' key.");
        }

        $statements = is_array($migration['up']) ? $migration['up'] : [$migration['up']];

        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }

        $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")
            ->execute([$file, $batch]);
    }

    private function rollbackMigration(string $file): void
    {
        $path = $this->migrationsPath . '/' . $file;
        $migration = require $path;

        if (isset($migration['down'])) {
            $statements = is_array($migration['down']) ? $migration['down'] : [$migration['down']];

            foreach ($statements as $sql) {
                $this->pdo->exec($sql);
            }
        }

        $this->pdo->prepare("DELETE FROM migrations WHERE migration = ?")
            ->execute([$file]);
    }

    private function getRanMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM migrations ORDER BY id");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        $names = array_map('basename', $files);
        sort($names);

        return $names;
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM migrations");
        $result = $stmt->fetchColumn();

        return $result ? (int) $result : 0;
    }

    private function getMigrationsForBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id");
        $stmt->execute([$batch]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
