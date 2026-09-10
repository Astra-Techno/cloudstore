<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Database\Connection;
use App\Core\Config\Config;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Auth\Domain\Role;
use Ramsey\Uuid\Uuid;

final class BuildController
{
    public function __construct(
        private readonly Connection $db,
        private readonly TenantRepository $tenantRepo,
        private readonly Config $config,
    ) {
    }

    public function listBuilds(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $builds = $this->db->fetchAll(
            "SELECT uuid, platform, app_mode, build_type, status, app_name, app_id,
                    github_run_id, github_run_url, download_url, share_token, file_size,
                    error_message, expires_at, completed_at, created_at
             FROM app_builds WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50",
            [$tenant->id]
        );

        return Response::success($builds);
    }

    public function triggerBuild(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $data = $request->json();
        $validator = new Validator();
        if (!$validator->validate($data, [
            'platform' => ['required', 'string'],
            'app_mode' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $platform = $data['platform']; // android or ios
        $appMode = $data['app_mode']; // customer or driver
        $buildType = $data['build_type'] ?? ($platform === 'android' ? 'apk' : 'ad-hoc');
        $appName = $data['app_name'] ?? $tenant->name;
        $appId = $data['app_id'] ?? 'com.cloudmarket.cloudstore';

        // Get tenant's active app token prefix for reference
        $tokenRow = $this->db->fetchOne(
            "SELECT id, token_prefix FROM app_tokens WHERE tenant_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$tenant->id]
        );

        $buildUuid = Uuid::uuid4()->toString();
        $shareToken = bin2hex(random_bytes(24));
        $apiBaseUrl = $this->config->get('APP_URL', 'https://market.cloudkart24.com') . '/api/v1';

        $this->db->execute(
            "INSERT INTO app_builds (uuid, tenant_id, platform, app_mode, build_type, status, app_name, app_id, share_token, triggered_by)
             VALUES (?, ?, ?, ?, ?, 'queued', ?, ?, ?, ?)",
            [
                $buildUuid,
                $tenant->id,
                $platform,
                $appMode,
                $buildType,
                $appName,
                $appId,
                $shareToken,
                $request->authClaims['sub'] ?? null,
            ]
        );

        // Dispatch to GitHub Actions
        $githubToken = $this->config->get('GITHUB_TOKEN');
        $githubRepo = $this->config->get('GITHUB_REPO', 'Astra-Techno/cloudstore');
        $webhookSecret = $this->config->get('BUILD_WEBHOOK_SECRET', '');

        $dispatched = false;
        if ($githubToken !== '') {
            $eventType = $platform === 'ios' ? 'build-ios' : 'build-android';
            $webhookUrl = rtrim($this->config->get('APP_URL', ''), '/') . '/api/v1/webhooks/build';

            $payload = [
                'event_type' => $eventType,
                'client_payload' => [
                    'build_id' => $buildUuid,
                    'app_token' => $data['app_token'] ?? '',
                    'api_base_url' => $apiBaseUrl,
                    'app_mode' => $appMode,
                    'app_name' => $appName,
                    'app_id' => $appId,
                    'build_type' => $buildType,
                    'primary_color' => $data['primary_color'] ?? '#4CAF50',
                    'webhook_url' => $webhookUrl,
                    'webhook_secret' => $webhookSecret,
                ],
            ];

            $ch = curl_init("https://api.github.com/repos/{$githubRepo}/dispatches");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/vnd.github+json',
                    "Authorization: Bearer {$githubToken}",
                    'X-GitHub-Api-Version: 2022-11-28',
                    'Content-Type: application/json',
                    'User-Agent: CloudMarket-Platform',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $dispatched = ($httpCode >= 200 && $httpCode < 300);

            if (!$dispatched) {
                $this->db->execute(
                    "UPDATE app_builds SET status = 'failed', error_message = ? WHERE uuid = ?",
                    ["GitHub dispatch failed (HTTP {$httpCode}): " . substr((string) $response, 0, 500), $buildUuid]
                );
            } else {
                $this->db->execute(
                    "UPDATE app_builds SET status = 'building' WHERE uuid = ?",
                    [$buildUuid]
                );
            }
        }

        $build = $this->db->fetchOne("SELECT * FROM app_builds WHERE uuid = ?", [$buildUuid]);

        return Response::success([
            'build' => $this->formatBuild($build),
            'dispatched' => $dispatched,
            'note' => $githubToken === '' ? 'GITHUB_TOKEN not configured — build queued but not dispatched.' : null,
        ], status: 201);
    }

    public function getBuild(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $build = $this->db->fetchOne("SELECT b.*, t.name as tenant_name FROM app_builds b JOIN tenants t ON t.id = b.tenant_id WHERE b.uuid = ?", [$params['buildUuid']]);
        if ($build === null) {
            return Response::notFound('Build not found.');
        }

        return Response::success($this->formatBuild($build));
    }

    /**
     * GitHub Actions webhook callback to update build status.
     * After a successful build, auto-fetches the APK artifact from GitHub.
     */
    public function webhook(Request $request, array $params): Response
    {
        $data = $request->json();
        $buildId = $data['build_id'] ?? '';
        $status = $data['status'] ?? '';

        if ($buildId === '' || $status === '') {
            return Response::error('Missing build_id or status.', 400);
        }

        // Optionally verify webhook secret
        $secret = $this->config->get('BUILD_WEBHOOK_SECRET');
        if ($secret !== '') {
            $providedSecret = $data['secret'] ?? $request->header('X-Build-Secret') ?? '';
            if (!hash_equals($secret, (string) $providedSecret)) {
                return Response::error('Invalid webhook secret.', 403);
            }
        }

        $build = $this->db->fetchOne("SELECT id, uuid, share_token FROM app_builds WHERE uuid = ?", [$buildId]);
        if ($build === null) {
            return Response::notFound('Build not found.');
        }

        $setClauses = ['status = ?'];
        $values = [$status];

        if (!empty($data['run_id'])) {
            $setClauses[] = 'github_run_id = ?';
            $values[] = (string) $data['run_id'];
        }
        if (!empty($data['run_url'])) {
            $setClauses[] = 'github_run_url = ?';
            $values[] = (string) $data['run_url'];
        }
        if (!empty($data['download_url'])) {
            $setClauses[] = 'download_url = ?';
            $values[] = (string) $data['download_url'];
        }
        if (!empty($data['file_size'])) {
            $setClauses[] = 'file_size = ?';
            $values[] = (int) $data['file_size'];
        }
        if (!empty($data['error_message'])) {
            $setClauses[] = 'error_message = ?';
            $values[] = (string) $data['error_message'];
        }
        if ($status === 'completed') {
            $setClauses[] = 'completed_at = NOW()';
            $setClauses[] = 'expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)';
        }

        $values[] = $buildId;
        $sql = "UPDATE app_builds SET " . implode(', ', $setClauses) . " WHERE uuid = ?";
        $this->db->execute($sql, $values);

        // Auto-fetch APK from GitHub when build succeeds
        if ($status === 'completed' && !empty($data['run_id'])) {
            $this->fetchArtifactFromGitHub($buildId, (string) $data['run_id']);
        }

        return Response::success(['received' => true]);
    }

    /**
     * Manual trigger to fetch artifact from GitHub (retry mechanism).
     */
    public function fetchArtifact(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $build = $this->db->fetchOne(
            "SELECT * FROM app_builds WHERE uuid = ?",
            [$params['buildUuid']]
        );
        if ($build === null) {
            return Response::notFound('Build not found.');
        }

        if (empty($build['github_run_id'])) {
            return Response::error('No GitHub run ID — cannot fetch artifact.', 400);
        }

        // If already has a download_url, return it
        if (!empty($build['download_url'])) {
            return Response::success([
                'message' => 'Artifact already fetched.',
                'build' => $this->formatBuild($build),
            ]);
        }

        $result = $this->fetchArtifactFromGitHub($build['uuid'], $build['github_run_id']);

        $build = $this->db->fetchOne("SELECT * FROM app_builds WHERE uuid = ?", [$build['uuid']]);
        return Response::success([
            'message' => $result ? 'Artifact fetched successfully.' : 'Failed to fetch artifact from GitHub.',
            'build' => $this->formatBuild($build),
        ]);
    }

    /**
     * Download artifact ZIP from GitHub Actions, extract APK/AAB, store locally.
     */
    private function fetchArtifactFromGitHub(string $buildUuid, string $runId): bool
    {
        $githubToken = $this->config->get('GITHUB_TOKEN');
        $githubRepo = $this->config->get('GITHUB_REPO', 'Astra-Techno/cloudstore');

        if ($githubToken === '') {
            return false;
        }

        // 1. List artifacts for this run
        $artifactsUrl = "https://api.github.com/repos/{$githubRepo}/actions/runs/{$runId}/artifacts";
        $artifactsData = $this->callGitHub($artifactsUrl, $githubToken);
        if ($artifactsData === null || empty($artifactsData['artifacts'])) {
            return false;
        }

        // 2. Find APK artifact (name starts with "android-apk")
        $targetArtifact = null;
        foreach ($artifactsData['artifacts'] as $artifact) {
            if (str_starts_with($artifact['name'], 'android-apk')) {
                $targetArtifact = $artifact;
                break;
            }
        }

        if ($targetArtifact === null) {
            // Try any artifact with "apk" in its name
            foreach ($artifactsData['artifacts'] as $artifact) {
                if (stripos($artifact['name'], 'apk') !== false) {
                    $targetArtifact = $artifact;
                    break;
                }
            }
        }

        if ($targetArtifact === null) {
            return false;
        }

        // 3. Download the artifact ZIP
        $downloadUrl = $targetArtifact['archive_download_url'];
        $zipContent = $this->callGitHub($downloadUrl, $githubToken, raw: true);
        if ($zipContent === null || $zipContent === '') {
            return false;
        }

        // 4. Save ZIP, extract APK
        $buildsDir = dirname(__DIR__, 4) . '/storage/builds';
        if (!is_dir($buildsDir)) {
            mkdir($buildsDir, 0755, true);
        }

        $zipPath = $buildsDir . "/temp_{$buildUuid}.zip";
        file_put_contents($zipPath, $zipContent);

        $apkFile = null;
        $apkSize = 0;
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            // Find the first APK in the ZIP
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.apk')) {
                    $finalName = "build_{$buildUuid}.apk";
                    // Extract to builds dir
                    $fp = $zip->getStream($name);
                    if ($fp) {
                        $outPath = $buildsDir . '/' . $finalName;
                        file_put_contents($outPath, stream_get_contents($fp));
                        fclose($fp);
                        $apkFile = $finalName;
                        $apkSize = filesize($outPath);
                    }
                    break;
                }
            }
            $zip->close();
        }
        @unlink($zipPath);

        if ($apkFile === null) {
            return false;
        }

        // 5. Build the public download URL using share_token
        $build = $this->db->fetchOne("SELECT share_token FROM app_builds WHERE uuid = ?", [$buildUuid]);
        $shareToken = $build['share_token'] ?? '';
        $appUrl = rtrim($this->config->get('APP_URL', ''), '/');
        $downloadLink = $shareToken !== ''
            ? "{$appUrl}/api/v1/builds/download/{$shareToken}"
            : null;

        // 6. Update build record
        $this->db->execute(
            "UPDATE app_builds SET download_url = ?, file_path = ?, file_size = ? WHERE uuid = ?",
            [$downloadLink, "storage/builds/{$apkFile}", $apkSize, $buildUuid]
        );

        return true;
    }

    /**
     * Helper to call GitHub API.
     */
    private function callGitHub(string $url, string $token, bool $raw = false): mixed
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                'Accept: application/vnd.github+json',
                'User-Agent: CloudMarket-Platform',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return $raw ? $response : json_decode($response, true);
    }

    /**
     * Public download — accessed via share token, no auth needed.
     * If APK file exists on disk, serves it directly. Otherwise returns build info JSON.
     */
    public function download(Request $request, array $params): Response
    {
        $token = $params['token'] ?? '';
        $build = $this->db->fetchOne(
            "SELECT b.*, t.name as tenant_name FROM app_builds b
             JOIN tenants t ON t.id = b.tenant_id
             WHERE b.share_token = ? AND b.status = 'completed'",
            [$token]
        );

        if ($build === null) {
            return Response::notFound('Build not found or not ready.');
        }

        if ($build['expires_at'] !== null && strtotime($build['expires_at']) < time()) {
            return Response::error('This download link has expired.', 410);
        }

        // If file_path exists on disk, serve the APK directly
        if (!empty($build['file_path'])) {
            $basePath = dirname(__DIR__, 4); // api/ root
            $filePath = $basePath . '/' . $build['file_path'];
            if (file_exists($filePath)) {
                // Serve APK binary
                while (ob_get_level()) {
                    ob_end_clean();
                }

                $appName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $build['app_name'] ?? 'app'));
                $filename = $appName . '-' . ($build['app_mode'] ?? 'customer') . '.apk';

                header_remove('Content-Type');
                header('Content-Type: application/vnd.android.package-archive');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: no-store');

                readfile($filePath);
                exit;
            }
        }

        // Fallback: return build info (for download pages / external links)
        return Response::success([
            'app_name' => $build['app_name'],
            'tenant_name' => $build['tenant_name'],
            'platform' => $build['platform'],
            'app_mode' => $build['app_mode'],
            'build_type' => $build['build_type'],
            'download_url' => $build['download_url'],
            'github_run_url' => $build['github_run_url'],
            'file_size' => $build['file_size'] ? (int) $build['file_size'] : null,
            'completed_at' => $build['completed_at'],
            'expires_at' => $build['expires_at'],
        ]);
    }

    private function formatBuild(array $build): array
    {
        return [
            'uuid' => $build['uuid'],
            'platform' => $build['platform'],
            'app_mode' => $build['app_mode'],
            'build_type' => $build['build_type'],
            'status' => $build['status'],
            'app_name' => $build['app_name'],
            'app_id' => $build['app_id'],
            'github_run_id' => $build['github_run_id'],
            'github_run_url' => $build['github_run_url'],
            'download_url' => $build['download_url'],
            'share_token' => $build['share_token'],
            'share_url' => $build['share_token']
                ? rtrim($this->config->get('APP_URL', ''), '/') . '/api/v1/builds/download/' . $build['share_token']
                : null,
            'file_size' => $build['file_size'] ? (int) $build['file_size'] : null,
            'error_message' => $build['error_message'],
            'expires_at' => $build['expires_at'],
            'completed_at' => $build['completed_at'],
            'created_at' => $build['created_at'],
        ];
    }

    private function requirePlatformAdmin(Request $request): void
    {
        $role = $request->authClaims['role'] ?? '';
        if ($role !== Role::PLATFORM_ADMIN) {
            throw new \RuntimeException('Platform admin access required.');
        }
    }
}
