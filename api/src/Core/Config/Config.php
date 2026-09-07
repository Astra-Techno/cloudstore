<?php

declare(strict_types=1);

namespace App\Core\Config;

final class Config
{
    public function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['true', '1', 'yes'], true);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value !== '' ? (int) $value : $default;
    }
}
