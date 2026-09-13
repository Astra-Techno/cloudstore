<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// Serve uploaded files directly
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($requestUri, '/uploads/')) {
    $filePath = BASE_PATH . '/storage' . $requestUri;
    if (file_exists($filePath) && is_file($filePath)) {
        $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit;
    }
}

// Serve APK build downloads directly (bypass framework for large binary files)
if (preg_match('#/api/v1/builds/download/([a-f0-9]+)$#', $requestUri, $m)) {
    $shareToken = $m[1];

    // Load .env for DB credentials
    $envFile = BASE_PATH . '/.env';
    $envVars = [];
    if (file_exists($envFile)) {
        foreach (file($envFile) as $line) {
            $line = trim($line);
            if (!$line || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $envVars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $envVars['DB_HOST'] ?? '127.0.0.1',
                $envVars['DB_PORT'] ?? '3306',
                $envVars['DB_DATABASE'] ?? ''
            ),
            $envVars['DB_USERNAME'] ?? 'root',
            $envVars['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare(
            "SELECT app_name, app_mode, file_path, file_size, expires_at, status
             FROM app_builds WHERE share_token = ? AND status = 'completed' LIMIT 1"
        );
        $stmt->execute([$shareToken]);
        $build = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($build && !empty($build['file_path'])) {
            if ($build['expires_at'] !== null && strtotime($build['expires_at']) < time()) {
                header('Content-Type: application/json');
                http_response_code(410);
                echo json_encode(['success' => false, 'error' => ['code' => 'EXPIRED', 'message' => 'Download link expired.']]);
                exit;
            }

            $filePath = BASE_PATH . '/' . $build['file_path'];
            if (file_exists($filePath)) {
                // Disable all compression
                if (function_exists('apache_setenv')) {
                    apache_setenv('no-gzip', '1');
                }
                @ini_set('zlib.output_compression', 'Off');
                @set_time_limit(300);

                $appName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $build['app_name'] ?? 'app'));
                $filename = $appName . '-' . ($build['app_mode'] ?? 'customer') . '.apk';
                $fileSize = filesize($filePath);

                header('Content-Type: application/vnd.android.package-archive');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . $fileSize);
                header('Content-Encoding: identity');
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: no-store');
                header('X-Content-Type-Options: nosniff');
                header('Connection: close');

                readfile($filePath);
                exit;
            }
        }
    } catch (\Throwable $e) {
        // Fall through to framework
    }
}

use App\Core\Application;

try {
    $app = Application::boot(BASE_PATH);
    $app->handleRequest();
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
        ],
    ]);
}
