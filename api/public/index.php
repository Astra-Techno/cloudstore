<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// Quick debug endpoint: /api/debug-check
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestUri === '/api/debug-check') {
    header('Content-Type: application/json');
    $checks = [
        'php_version' => phpversion(),
        'deploy_version' => '2026-09-09-v2',
        'offer_controller_exists' => class_exists('App\\Modules\\Offer\\Controller\\AdminOfferController'),
        'coupon_repo_exists' => class_exists('App\\Modules\\Offer\\Repository\\CouponRepository'),
        'promotion_repo_exists' => class_exists('App\\Modules\\Offer\\Repository\\PromotionRepository'),
        'bundle_repo_exists' => class_exists('App\\Modules\\Offer\\Repository\\BundleRepository'),
        'classmap_has_offer' => isset(require(BASE_PATH . '/vendor/composer/autoload_classmap.php')['App\\Modules\\Offer\\Controller\\AdminOfferController']),
    ];
    try {
        $checks['tables'] = [];
        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile) as $line) {
                $line = trim($line);
                if ($line && $line[0] !== '#' && str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $_ENV[trim($k)] = trim($v);
                }
            }
        }
        $pdo = new PDO(
            'mysql:host=' . ($_ENV['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'cloudstore'),
            $_ENV['DB_USERNAME'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? ''
        );
        foreach (['coupons', 'promotions', 'bundles'] as $t) {
            try {
                $r = $pdo->query("SELECT COUNT(*) as cnt FROM {$t}");
                $checks['tables'][$t] = (int)$r->fetch()['cnt'];
            } catch (\Throwable $e) {
                $checks['tables'][$t] = 'ERROR: ' . $e->getMessage();
            }
        }
    } catch (\Throwable $e) {
        $checks['db_error'] = $e->getMessage();
    }
    echo json_encode($checks, JSON_PRETTY_PRINT);
    exit;
}

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

use App\Core\Application;

$app = Application::boot(BASE_PATH);
$app->handleRequest();
