<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controller;

use App\Core\Config\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Tenant\Repository\TenantIntegrationRepository;

final class TenantIntegrationController
{
    private const MAPPLS_KEY = 'mappls_static_key';
    private const MAP_PROVIDER = 'map_provider';
    private const PUSH_ENABLED = 'push_notifications_enabled';

    public function __construct(private readonly TenantIntegrationRepository $repo, private readonly Config $config, private readonly AdminRepository $admins) {}

    public function get(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        // Mappls browser keys must reach the authenticated administrator's
        // browser to render the map. They are not server credentials, so the
        // real protection is Mappls' allowed-origin whitelist. Keep FCM and
        // any other server credentials out of this response entirely.
        $mapKey = $this->value($tenantId, self::MAPPLS_KEY)
            ?: $this->config->get('MAPPLS_STATIC_KEY', $this->config->get('VITE_MAPPLS_STATIC_KEY'));
        return Response::success([
            'map_provider' => $this->value($tenantId, self::MAP_PROVIDER) ?: 'openstreetmap',
            'mappls_browser_key' => $mapKey,
            'has_mappls_static_key' => $mapKey !== '',
            'push_notifications_enabled' => $this->enabled($tenantId, self::PUSH_ENABLED),
            'fcm_configured' => $this->config->get('FCM_SERVER_KEY') !== '',
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $admin = $this->admins->findByUuid((string) ($request->authClaims['sub'] ?? ''));
        if ($admin === null) return Response::unauthorized();
        $adminId = (int) $admin['id'];
        $data = $request->json();
        $provider = (string) ($data['map_provider'] ?? 'openstreetmap');
        if (!in_array($provider, ['openstreetmap', 'mappls'], true)) return Response::validationError(['map_provider' => ['Choose OpenStreetMap or Mappls.']]);
        $this->repo->upsert($tenantId, self::MAP_PROVIDER, $this->encrypt($provider), true, $adminId);
        if (array_key_exists('mappls_static_key', $data)) {
            $key = trim((string) $data['mappls_static_key']);
            if (strlen($key) > 500) return Response::validationError(['mappls_static_key' => ['Key is too long.']]);
            // An empty password-style field means “leave the existing key
            // alone”, rather than silently disabling it when another setting
            // is saved.
            if ($key !== '') {
                $this->repo->upsert($tenantId, self::MAPPLS_KEY, $this->encrypt($key), true, $adminId);
            }
        }
        $push = (bool) ($data['push_notifications_enabled'] ?? false);
        $this->repo->upsert($tenantId, self::PUSH_ENABLED, null, $push, $adminId);
        return $this->get($request, $params);
    }

    private function enabled(int $tenantId, string $key): bool { return (bool) ($this->repo->find($tenantId, $key)['enabled'] ?? false); }
    private function value(int $tenantId, string $key): string
    {
        $row = $this->repo->find($tenantId, $key);
        return $row === null || empty($row['encrypted_value']) ? '' : $this->decrypt((string) $row['encrypted_value']);
    }
    private function encrypt(string $value): string
    {
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', $this->config->get('APP_KEY'), true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('Unable to secure integration configuration.');
        return base64_encode($iv . $tag . $cipher);
    }
    private function decrypt(string $value): string
    {
        $raw = base64_decode($value, true); if ($raw === false || strlen($raw) < 29) return '';
        $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $cipher = substr($raw, 28);
        return openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', $this->config->get('APP_KEY'), true), OPENSSL_RAW_DATA, $iv, $tag) ?: '';
    }
}
