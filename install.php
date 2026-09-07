<?php
/**
 * CloudStore - Web Composer Installer
 *
 * Run via browser: https://market.cloudkart24.com/install.php?key=YOUR_DEPLOY_KEY
 * Downloads Composer and runs `composer install --no-dev` in api/ folder.
 * Delete this file after first successful install.
 */

ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
error_reporting(E_ALL);

define('BASE_DIR', __DIR__);
define('API_DIR', BASE_DIR . '/api');

// Load .env for deploy key
$envFile = API_DIR . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}

// Auth
$deployKey = $_ENV['DEPLOY_KEY'] ?? '';
$givenKey  = $_GET['key'] ?? '';
header('Content-Type: text/html; charset=UTF-8');

if (!$deployKey || $givenKey !== $deployKey) {
    http_response_code(403);
    die('<h2>403 - Invalid deploy key</h2>');
}

echo '<!DOCTYPE html><html><head><title>Composer Install</title>
<style>body{font-family:system-ui;max-width:700px;margin:40px auto;padding:0 20px;background:#f8f8fa;color:#1a1a2e}
pre{background:#1a1a2e;color:#e5e5ea;padding:16px;border-radius:10px;overflow-x:auto;font-size:13px;line-height:1.6}
.ok{color:#22c55e}.err{color:#ef4444}.warn{color:#f59e0b}h1{font-size:1.2rem}
</style></head><body><h1>CloudStore - Composer Installer</h1><pre>';
ob_flush(); flush();

// Step 1: Check api/ exists
if (!is_dir(API_DIR)) {
    echo "<span class='err'>ERROR: api/ folder not found. Run deploy.php first.</span>";
    die('</pre></body></html>');
}

if (!file_exists(API_DIR . '/composer.json')) {
    echo "<span class='err'>ERROR: api/composer.json not found. Run deploy.php first.</span>";
    die('</pre></body></html>');
}

// Step 2: Download composer.phar if not present
$composerPhar = API_DIR . '/composer.phar';
if (!file_exists($composerPhar)) {
    echo "Downloading composer.phar...\n";
    ob_flush(); flush();

    $installerUrl = 'https://getcomposer.org/installer';
    $installer = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($installerUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $installer = curl_exec($ch);
        curl_close($ch);
    } else {
        $installer = @file_get_contents($installerUrl);
    }

    if (!$installer) {
        echo "<span class='err'>ERROR: Could not download Composer installer.</span>";
        die('</pre></body></html>');
    }

    $installerFile = API_DIR . '/composer-setup.php';
    file_put_contents($installerFile, $installer);

    // Set COMPOSER_HOME so composer doesn't complain
    $composerHome = API_DIR . '/.composer';
    if (!is_dir($composerHome)) mkdir($composerHome, 0755, true);
    putenv("COMPOSER_HOME={$composerHome}");
    putenv("HOME=" . dirname(BASE_DIR));

    // Run installer
    $cwd = getcwd();
    chdir(API_DIR);

    $envPrefix = "HOME=" . escapeshellarg(dirname(BASE_DIR)) . " COMPOSER_HOME=" . escapeshellarg($composerHome);

    if (function_exists('exec')) {
        $output = [];
        exec("{$envPrefix} php composer-setup.php 2>&1", $output, $exitCode);
        echo implode("\n", $output) . "\n";
    } elseif (function_exists('shell_exec')) {
        echo shell_exec("{$envPrefix} php composer-setup.php 2>&1") . "\n";
    } else {
        $_SERVER['argv'] = ['composer-setup.php'];
        include $installerFile;
    }

    chdir($cwd);
    @unlink($installerFile);

    if (file_exists($composerPhar)) {
        echo "<span class='ok'>composer.phar downloaded successfully.</span>\n";
    } else {
        echo "<span class='err'>ERROR: composer.phar was not created. Try uploading it manually.</span>\n";
        echo "<span class='warn'>Download from: https://getcomposer.org/download/</span>\n";
        echo "<span class='warn'>Upload composer.phar to: /public_html/market/api/composer.phar</span>";
        die('</pre></body></html>');
    }
} else {
    echo "<span class='ok'>composer.phar already exists.</span>\n";
}

ob_flush(); flush();

// Step 3: Run composer install
echo "\nRunning: composer install --no-dev --optimize-autoloader\n";
echo "This may take 1-3 minutes...\n\n";
ob_flush(); flush();

$cwd = getcwd();
chdir(API_DIR);

$composerHome = API_DIR . '/.composer';
if (!is_dir($composerHome)) mkdir($composerHome, 0755, true);
$envPrefix = "HOME=" . escapeshellarg(dirname(BASE_DIR)) . " COMPOSER_HOME=" . escapeshellarg($composerHome);
$command = "{$envPrefix} php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1";
$result = '';

if (function_exists('exec')) {
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $result = implode("\n", $output);
} elseif (function_exists('shell_exec')) {
    $result = shell_exec($command) ?? '';
} else {
    echo "<span class='err'>ERROR: Neither exec() nor shell_exec() are available.</span>\n";
    echo "<span class='warn'>Ask MilesWeb support to enable exec() for your account,</span>\n";
    echo "<span class='warn'>or run composer locally and upload vendor/ via File Manager.</span>";
    chdir($cwd);
    die('</pre></body></html>');
}

chdir($cwd);
echo htmlspecialchars($result) . "\n\n";

// Step 4: Verify
if (is_dir(API_DIR . '/vendor') && file_exists(API_DIR . '/vendor/autoload.php')) {
    echo "<span class='ok'>SUCCESS! Composer dependencies installed.</span>\n";
    echo "<span class='ok'>vendor/autoload.php exists.</span>\n\n";
    echo "<span class='warn'>IMPORTANT: Delete this install.php file now for security!</span>\n";
    echo "<span class='warn'>Path: /public_html/market/install.php</span>";
} else {
    echo "<span class='err'>WARNING: vendor/autoload.php not found.</span>\n";
    echo "<span class='warn'>If exec() is blocked, try the manual approach:</span>\n";
    echo "<span class='warn'>1. Run locally: cd api && composer install --no-dev</span>\n";
    echo "<span class='warn'>2. Zip the vendor/ folder</span>\n";
    echo "<span class='warn'>3. Upload and extract via cPanel File Manager</span>";
}

echo '</pre></body></html>';
