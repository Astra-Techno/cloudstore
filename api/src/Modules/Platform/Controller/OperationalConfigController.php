<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controller;

use App\Core\Config\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Domain\Role;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Platform\Repository\OperationalConfigRepository;

final class OperationalConfigController
{
    /** @var array<string, bool> key => secret */
    private const ALLOWED = [
        'fcm_service_account' => true,
        'otp_webhook_url' => false,
        'otp_webhook_token' => true,
        'razorpay_key_id' => false,
        'razorpay_key_secret' => true,
        'razorpay_webhook_secret' => true,
        'github_token' => true,
        'github_repo' => false,
        'build_webhook_secret' => true,
        'mobile_api_origin' => false,
        // This is a browser key, protected by the Mappls allowed-origin
        // whitelist. It is encrypted at rest but is intentionally returned to
        // authenticated tenant administrators to render their store map.
        'mappls_static_key' => false,
    ];

    public function __construct(
        private readonly OperationalConfigRepository $repo,
        private readonly Config $config,
        private readonly AdminRepository $admins,
    ) {}

    public function get(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);
        $tenantId = max(0, (int) ($request->input('tenant_id', 0)));
        return $this->responseFor($tenantId);
    }

    private function responseFor(int $tenantId): Response
    {
        $rows = [];
        foreach ($this->repo->findForScope($tenantId) as $row) $rows[(string) $row['config_key']] = $row;

        $settings = [];
        foreach (self::ALLOWED as $key => $secret) {
            $settings[$key] = [
                'configured' => isset($rows[$key]) && !empty($rows[$key]['encrypted_value']),
                'is_secret' => $secret,
                'updated_at' => $rows[$key]['updated_at'] ?? null,
            ];
        }

        return Response::success(['tenant_id' => $tenantId, 'settings' => $settings]);
    }

    public function update(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);
        $admin = $this->admins->findByUuid((string) ($request->authClaims['sub'] ?? ''));
        if ($admin === null) return Response::unauthorized();
        $data = $request->json();
        $tenantId = max(0, (int) ($data['tenant_id'] ?? 0));
        $settings = $data['settings'] ?? null;
        if (!is_array($settings)) return Response::validationError(['settings' => ['Settings object is required.']]);

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, self::ALLOWED)) {
                return Response::validationError(['settings' => ['Unsupported configuration key.']]);
            }
            if (!is_string($value) || strlen(trim($value)) > 2000) {
                return Response::validationError(['settings' => ["Invalid value for {$key}."]]);
            }
            // Empty password fields mean retain the previously configured value.
            if (trim($value) !== '') {
                $this->repo->upsert($key, $this->encrypt(trim($value)), self::ALLOWED[$key], (int) $admin['id'], $tenantId);
            }
        }

        return $this->responseFor($tenantId);
    }

    private function requirePlatformAdmin(Request $request): void
    {
        if (($request->authClaims['role'] ?? '') !== Role::PLATFORM_ADMIN) {
            throw new \RuntimeException('Platform admin access required.');
        }
    }

    private function encrypt(string $value): string
    {
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', $this->config->get('APP_KEY'), true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('Unable to secure configuration.');
        return base64_encode($iv . $tag . $cipher);
    }
}
