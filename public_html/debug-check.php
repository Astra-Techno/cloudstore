<?php
// Standalone debug - no framework, no autoloader
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');

$apiDir = __DIR__ . '/api';
$checks = [];

// PHP version
$checks['php_version'] = phpversion();
$checks['deploy_marker'] = '2026-09-09-v3';

// Check key files exist
$files = [
    'api/public/index.php',
    'api/src/Modules/Offer/Controller/AdminOfferController.php',
    'api/src/Modules/Offer/Repository/CouponRepository.php',
    'api/src/Modules/Offer/Repository/PromotionRepository.php',
    'api/src/Modules/Offer/Repository/BundleRepository.php',
    'api/src/Modules/Offer/Service/CouponService.php',
    'api/vendor/composer/autoload_classmap.php',
    'api/database/Seeders/OfferSeeder.php',
];
$checks['files'] = [];
foreach ($files as $f) {
    $full = __DIR__ . '/' . $f;
    $checks['files'][$f] = file_exists($full) ? filesize($full) . ' bytes' : 'MISSING';
}

// Check classmap for Offer entries
$classmapFile = $apiDir . '/vendor/composer/autoload_classmap.php';
if (file_exists($classmapFile)) {
    $classmap = require $classmapFile;
    $checks['classmap_offer_entries'] = [];
    foreach ($classmap as $cls => $path) {
        if (str_contains($cls, 'Offer')) {
            $checks['classmap_offer_entries'][$cls] = file_exists($path) ? 'OK' : 'FILE MISSING';
        }
    }
    if (empty($checks['classmap_offer_entries'])) {
        $checks['classmap_offer_entries'] = 'NONE FOUND - autoloader not regenerated';
    }
} else {
    $checks['classmap'] = 'autoload_classmap.php not found';
}

// Check DB
$envFile = $apiDir . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if ($line && $line[0] !== '#' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }

    try {
        $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? '127.0.0.1')
             . ';port=' . ($_ENV['DB_PORT'] ?? '3306')
             . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'cloudstore');
        $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $checks['db'] = 'connected';
        foreach (['tenants', 'coupons', 'promotions', 'bundles', 'bundle_items', '_migrations'] as $t) {
            try {
                $r = $pdo->query("SELECT COUNT(*) as cnt FROM `{$t}`");
                $checks['tables'][$t] = (int) $r->fetch()['cnt'];
            } catch (Throwable $e) {
                $checks['tables'][$t] = 'ERROR: ' . $e->getMessage();
            }
        }

        // Show migrations
        try {
            $r = $pdo->query("SELECT migration FROM _migrations ORDER BY migration");
            $checks['migrations'] = array_column($r->fetchAll(PDO::FETCH_ASSOC), 'migration');
        } catch (Throwable $e) {
            $checks['migrations'] = 'ERROR: ' . $e->getMessage();
        }
    } catch (Throwable $e) {
        $checks['db'] = 'ERROR: ' . $e->getMessage();
    }
} else {
    $checks['db'] = 'api/.env not found';
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
