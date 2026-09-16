<?php

declare(strict_types=1);

namespace App\Modules\Platform\Service;

use App\Core\Config\Config;
use App\Modules\Platform\Repository\OperationalConfigRepository;

final class OperationalConfig
{
    public function __construct(private readonly OperationalConfigRepository $repo, private readonly Config $baseConfig) {}

    public function get(string $key, int $tenantId = 0, string $default = ''): string
    {
        $storedKey = strtolower($key);
        try {
            $row = $tenantId > 0 ? $this->repo->find($storedKey, $tenantId) : null;
            $row ??= $this->repo->find($storedKey, 0);
            if ($row !== null && !empty($row['encrypted_value'])) {
                $value = $this->decrypt((string) $row['encrypted_value']);
                if ($value !== '') return $value;
            }
        } catch (\Throwable) {
            // The database table may not exist during a rolling deployment.
        }

        return $this->baseConfig->get($key, $default);
    }

    private function decrypt(string $value): string
    {
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) return '';
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        return openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', $this->baseConfig->get('APP_KEY'), true), OPENSSL_RAW_DATA, $iv, $tag) ?: '';
    }
}
