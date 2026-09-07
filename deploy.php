<?php
/**
 * CloudStore - One-Click Deployer
 *
 * Place this file at the ROOT of your hosting (public_html/).
 * Visit: https://yourdomain.com/deploy.php?key=YOUR_DEPLOY_KEY
 *
 * What it does:
 *   1. Downloads latest code from GitHub
 *   2. Cleans + replaces all API (PHP) files    - preserves api/.env & api/vendor/ & api/storage/
 *   3. Cleans + replaces all frontend files      - preserves deploy.php, api/, .htaccess
 *   4. Runs any pending DB migrations
 *
 * Add these to api/.env:
 *   DEPLOY_KEY=your_secret_key_here
 *   GITHUB_REPO=Astra-Techno/cloudstore
 *   GITHUB_BRANCH=master
 *   GITHUB_TOKEN=ghp_xxx          (only for private repos)
 *
 * Repo structure expected:
 *   api/           - PHP backend source
 *   public_html/   - Built Vue admin frontend (run locally: cd admin && npm run build, then copy dist/ to public_html/)
 *
 * Shared hosting setup:
 *   public_html/
 *     deploy.php        <- this file
 *     .htaccess         <- routes /api/* to api/public/index.php
 *     index.html        <- admin SPA (from build)
 *     assets/           <- admin assets (from build)
 *     api/
 *       .env            <- your environment config
 *       vendor/         <- composer dependencies
 *       public/
 *         index.php     <- API entry point
 *       ...
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// -- Bootstrap ----------------------------------------------------------------
define('BASE_DIR',  __DIR__);
define('API_DIR',   BASE_DIR . '/api');
define('START_TIME', microtime(true));

// Load api/.env
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

// -- Auth ---------------------------------------------------------------------
$deployKey = $_ENV['DEPLOY_KEY'] ?? '';
$givenKey  = $_GET['key'] ?? '';

header('Content-Type: text/html; charset=UTF-8');

if (!$deployKey || $givenKey !== $deployKey) {
    http_response_code(403);
    die(page('Access Denied', '<div class="err-box">403 - Invalid or missing deploy key.<br>Add DEPLOY_KEY to api/.env</div>'));
}

// -- Config -------------------------------------------------------------------
$repo   = $_ENV['GITHUB_REPO']   ?? '';
$branch = $_ENV['GITHUB_BRANCH'] ?? 'master';
$token  = $_ENV['GITHUB_TOKEN']  ?? '';

if (!$repo) {
    die(page('Config Error', '<div class="err-box">GITHUB_REPO is not set in api/.env</div>'));
}

// -- Log helpers --------------------------------------------------------------
$log = [];

function log_step(string $icon, string $msg, string $type = 'info'): void {
    global $log;
    $log[] = ['icon' => $icon, 'msg' => $msg, 'type' => $type];
    ob_flush(); flush();
}

function abort(string $msg): void {
    log_step('!', $msg, 'err');
    echo render_log();
    echo render_banner(false, $msg);
    echo '</div></body></html>';
    exit;
}

// =============================================================================
// STEP 1 - Download repo archive from GitHub
// =============================================================================
echo page_open();
log_step('>', "Downloading <strong>{$repo}@{$branch}</strong> from GitHub...");

$zipUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.tar.gz";

$zipData = false;
if (function_exists('curl_init')) {
    $ch = curl_init($zipUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'CloudStore-Deployer/1.0',
        CURLOPT_HTTPHEADER     => array_filter([
            $token ? "Authorization: Bearer {$token}" : null,
        ]),
    ]);
    $zipData  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 404) abort("Repo not found (404). Check GITHUB_REPO in api/.env");
    if ($httpCode === 401 || $httpCode === 403) abort("GitHub auth failed ({$httpCode}). Add GITHUB_TOKEN to api/.env");
    if ($httpCode !== 200) abort("GitHub returned HTTP {$httpCode}. cURL: {$curlErr}");
} else {
    $ctx     = stream_context_create(['http' => [
        'timeout' => 90,
        'header'  => $token ? "Authorization: Bearer {$token}\r\n" : '',
        'user_agent' => 'CloudStore-Deployer/1.0',
    ]]);
    $zipData = @file_get_contents($zipUrl, false, $ctx);
}

if (!$zipData) abort('Failed to download archive. Enable cURL or allow_url_fopen.');
log_step('OK', 'Download complete (' . round(strlen($zipData) / 1024) . ' KB)', 'ok');

// =============================================================================
// STEP 2 - Extract tar.gz to temp dir
// =============================================================================
log_step('#', 'Extracting archive...');

$tmpTar = sys_get_temp_dir() . '/cloudstore_deploy_' . time() . '.tar.gz';
$tmpDir = sys_get_temp_dir() . '/cloudstore_extract_' . time();

file_put_contents($tmpTar, $zipData);
unset($zipData);

mkdir($tmpDir, 0755, true);

$extracted = false;

if (class_exists('PharData')) {
    try {
        $phar = new PharData($tmpTar);
        $phar->extractTo($tmpDir);
        unset($phar);
        $extracted = true;
    } catch (Throwable $e) {}
}

if (!$extracted && function_exists('exec')) {
    $out = []; $rc = 0;
    exec('tar -xzf ' . escapeshellarg($tmpTar) . ' -C ' . escapeshellarg($tmpDir) . ' 2>&1', $out, $rc);
    if ($rc === 0) $extracted = true;
    else log_step('~', 'exec tar failed: ' . implode(' ', $out), 'warn');
}

if (!$extracted && function_exists('shell_exec')) {
    $result = shell_exec('tar -xzf ' . escapeshellarg($tmpTar) . ' -C ' . escapeshellarg($tmpDir) . ' 2>&1');
    if (is_dir($tmpDir) && count(glob($tmpDir . '/*')) > 0) $extracted = true;
    else log_step('~', 'shell_exec tar output: ' . $result, 'warn');
}

@unlink($tmpTar);

if (!$extracted) {
    abort('Cannot extract archive. Enable phar, exec, or shell_exec on this server.');
}

// GitHub names the top folder: repo-branch/
$extractedDirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
if (empty($extractedDirs)) {
    rrmdir($tmpDir);
    abort('Archive extraction failed - no directories found.');
}
$repoRoot = $extractedDirs[0];
log_step('OK', 'Extracted to temp directory', 'ok');

// =============================================================================
// STEP 3 - Clean + replace API (PHP backend) files
// =============================================================================
log_step('-', 'Cleaning old API files...');

$srcApi = $repoRoot . '/api';
if (!is_dir($srcApi)) {
    cleanup($tmpDir);
    abort('api/ folder not found in repo. Make sure GITHUB_REPO is correct.');
}

// Delete everything in api/ EXCEPT .env, vendor/, and storage/
$deletedApi = clean_dir(API_DIR, ['.env', 'vendor', 'storage']);
log_step('.', "Deleted {$deletedApi} old item(s) from api/", 'muted');

log_step('+', 'Copying fresh API files...');
$apiFiles = rcopy($srcApi, API_DIR, ['.env']);
log_step('OK', 'API updated - ' . count($apiFiles) . ' file(s)', 'ok');
log_step('.', render_file_list($apiFiles, API_DIR, 'api'), 'muted');

// =============================================================================
// STEP 4 - Clean + replace frontend files
// =============================================================================

// Look for public_html/ first, then admin/dist/ as fallback
$srcFrontend = null;
if (is_dir($repoRoot . '/public_html')) {
    $srcFrontend = $repoRoot . '/public_html';
    log_step('.', 'Found public_html/ in repo', 'muted');
} elseif (is_dir($repoRoot . '/admin/dist')) {
    $srcFrontend = $repoRoot . '/admin/dist';
    log_step('.', 'Found admin/dist/ in repo', 'muted');
}

if (!$srcFrontend) {
    log_step('~', 'No frontend build found (public_html/ or admin/dist/) - skipping frontend deploy.', 'warn');
    log_step('~', 'Build locally: cd admin && npm run build, then copy dist/ to public_html/ and commit.', 'warn');
} else {
    log_step('-', 'Cleaning old frontend files...');
    // Delete everything in root EXCEPT deploy.php, install.php, api/, and .htaccess
    $deletedFe = clean_dir(BASE_DIR, ['deploy.php', 'install.php', 'api', '.htaccess', '.env']);
    log_step('.', "Deleted {$deletedFe} old item(s) from frontend", 'muted');

    log_step('+', 'Copying fresh frontend files...');
    $feFiles = rcopy($srcFrontend, BASE_DIR, ['deploy.php', 'install.php', 'api', '.htaccess']);
    log_step('OK', 'Frontend updated - ' . count($feFiles) . ' file(s)', 'ok');
    log_step('.', render_file_list($feFiles, BASE_DIR, ''), 'muted');
}

// Copy root-level scripts from repo (deploy.php, install.php) to keep them updated
foreach (['install.php'] as $rootFile) {
    $srcFile = $repoRoot . '/' . $rootFile;
    if (file_exists($srcFile)) {
        copy($srcFile, BASE_DIR . '/' . $rootFile);
        log_step('.', "Updated {$rootFile} from repo", 'muted');
    }
}

// =============================================================================
// STEP 5 - Ensure .htaccess exists for API routing
// =============================================================================
$htaccess = BASE_DIR . '/.htaccess';
if (!file_exists($htaccess)) {
    log_step('+', 'Creating .htaccess for API routing...');
    file_put_contents($htaccess, <<<'HTACCESS'
RewriteEngine On

# API requests -> api/public/index.php
RewriteCond %{REQUEST_URI} ^/api/(.*)$
RewriteRule ^api/(.*)$ api/public/index.php [QSA,L]

# Frontend SPA - serve existing files, fallback to index.html
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^(.*)$ index.html [QSA,L]
HTACCESS
    );
    log_step('OK', '.htaccess created', 'ok');
} else {
    log_step('.', '.htaccess already exists - skipped', 'muted');
}

// =============================================================================
// STEP 6 - Ensure storage directories exist with proper permissions
// =============================================================================
$storageDirs = [
    API_DIR . '/storage',
    API_DIR . '/storage/logs',
    API_DIR . '/storage/uploads',
];
foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
log_step('OK', 'Storage directories verified', 'ok');

// =============================================================================
// STEP 7 - Run DB migrations
// =============================================================================
log_step('>', 'Running database migrations...');
$migLog = runMigrations();
foreach ($migLog as $m) {
    log_step(
        $m['status'] === 'done' ? 'OK' : ($m['status'] === 'skip' ? '.' : '!'),
        htmlspecialchars($m['name']) . ($m['error'] ?? ''),
        $m['status'] === 'done' ? 'ok' : ($m['status'] === 'fail' ? 'err' : 'muted')
    );
}

// =============================================================================
// Done
// =============================================================================
cleanup($tmpDir);

$elapsed  = round(microtime(true) - START_TIME, 2);
$hasError = (bool) array_filter($log, fn($l) => $l['type'] === 'err');
log_step('*', "Finished in {$elapsed}s", 'ok');

echo render_log();
echo render_banner(!$hasError, $hasError ? 'Deployment completed with errors.' : 'Deployment successful!');
echo '</div></body></html>';

// =============================================================================
// Helpers
// =============================================================================

/**
 * Delete all files and subdirectories inside $dir, skipping entries whose
 * basename is in $except. Returns count of deleted items.
 */
function clean_dir(string $dir, array $except = []): int {
    if (!is_dir($dir)) return 0;
    $count = 0;
    foreach (new DirectoryIterator($dir) as $item) {
        if ($item->isDot()) continue;
        if (in_array($item->getFilename(), $except)) continue;
        if ($item->isDir()) {
            rrmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
        $count++;
    }
    return $count;
}

/**
 * Recursively copy $src to $dst, skipping filenames listed in $skip.
 * Returns list of copied file paths.
 */
function rcopy(string $src, string $dst, array $skip = [], array &$copied = []): array {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (new DirectoryIterator($src) as $item) {
        if ($item->isDot()) continue;
        if (in_array($item->getFilename(), $skip)) continue;
        $srcPath = $item->getPathname();
        $dstPath = $dst . '/' . $item->getFilename();
        if ($item->isDir()) {
            rcopy($srcPath, $dstPath, [], $copied);
        } else {
            copy($srcPath, $dstPath);
            $copied[] = $dstPath;
        }
    }
    return $copied;
}

/** Recursively delete a directory and all its contents. */
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (new DirectoryIterator($dir) as $item) {
        if ($item->isDot()) continue;
        $item->isDir() ? rrmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function cleanup(string $tmpDir): void { rrmdir($tmpDir); }

/** Run migration files from api/database/migrations/ (supports .php and .sql) */
function runMigrations(): array {
    $log = [];
    try {
        $host = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port = $_ENV['DB_PORT']     ?? '3306';
        $db   = $_ENV['DB_DATABASE'] ?? '';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        if (!$db) {
            $log[] = ['status' => 'skip', 'name' => 'DB_DATABASE not set in api/.env - skipping migrations'];
            return $log;
        }

        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $applied = $pdo->query("SELECT filename FROM _migrations ORDER BY filename")
                       ->fetchAll(PDO::FETCH_COLUMN);

        $migDir = API_DIR . '/database/migrations';
        // Support both .php and .sql migration files
        $phpFiles = is_dir($migDir) ? glob($migDir . '/*.php') : [];
        $sqlFiles = is_dir($migDir) ? glob($migDir . '/*.sql') : [];
        $files = array_merge($phpFiles, $sqlFiles);
        sort($files);

        if (empty($files)) {
            $log[] = ['status' => 'skip', 'name' => 'No migration files found in api/database/migrations/'];
            return $log;
        }

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied)) {
                $log[] = ['status' => 'skip', 'name' => $name];
                continue;
            }

            // Get SQL from file
            $sql = '';
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if ($ext === 'php') {
                $migration = require $file;
                if (is_array($migration) && isset($migration['up'])) {
                    $sql = $migration['up'];
                } else {
                    $log[] = ['status' => 'fail', 'name' => $name, 'error' => ' - Invalid PHP migration format'];
                    continue;
                }
            } else {
                $sql = file_get_contents($file);
            }

            $statements = array_filter(
                array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql))),
                fn($s) => $s !== ''
            );
            try {
                foreach ($statements as $stmt) { $pdo->exec($stmt); }
                $pdo->prepare("INSERT INTO _migrations (filename) VALUES (?)")->execute([$name]);
                $log[] = ['status' => 'done', 'name' => $name];
            } catch (PDOException $e) {
                $log[] = ['status' => 'fail', 'name' => $name, 'error' => ' - ' . $e->getMessage()];
                break;
            }
        }
    } catch (Throwable $e) {
        $log[] = ['status' => 'fail', 'name' => 'DB connection failed', 'error' => ' - ' . $e->getMessage()];
    }
    return $log;
}

function render_file_list(array $files, string $baseDir, string $prefix): string {
    if (empty($files)) return 'No files copied.';
    $base  = rtrim($baseDir, '/\\');
    $items = '';
    foreach ($files as $f) {
        $rel    = ltrim(str_replace([$base, '\\'], ['', '/'], $f), '/');
        $items .= '<li>' . htmlspecialchars($prefix ? $prefix . '/' . $rel : $rel) . '</li>';
    }
    return '<details><summary>' . count($files) . ' files - click to expand</summary>'
         . '<ul>' . $items . '</ul></details>';
}

function render_log(): string {
    global $log;
    $out = '';
    foreach ($log as $l) {
        $cls = ['ok' => 'ok', 'err' => 'err', 'warn' => 'warn', 'muted' => 'mu', 'info' => ''][$l['type']] ?? '';
        $out .= "<div class=\"row{$cls}\"><span class=\"ic\">{$l['icon']}</span><span>{$l['msg']}</span></div>";
    }
    return $out;
}

function render_banner(bool $ok, string $msg): string {
    $cls = $ok ? 'ok' : 'err';
    $ico = $ok ? 'OK' : '!!';
    return "<div class=\"banner {$cls}\">{$ico} {$msg}</div>";
}

function page_open(): string {
    $host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost');
    $now  = date('Y-m-d H:i:s');
    return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CloudStore Deployer</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:700px;margin:32px auto;padding:0 18px;background:#f8fafc;color:#1e293b}
h1{font-size:1.25rem;font-weight:700;margin:0 0 2px}
.sub{color:#64748b;font-size:.82rem;margin-bottom:22px}
.wrap{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px}
.row,.rowok,.rowerr,.rowwarn,.rowmu{display:flex;align-items:flex-start;gap:10px;padding:8px 12px;border-radius:8px;margin-bottom:3px;font-size:.86rem;border:1px solid #e2e8f0;background:#fff}
.rowok{border-color:#bbf7d0;background:#f0fdf4}
.rowerr{border-color:#fecaca;background:#fff5f5}
.rowwarn{border-color:#fde68a;background:#fffbeb}
.rowmu{opacity:.5}
.ic{flex-shrink:0;width:20px;text-align:center;font-weight:700}
.banner{padding:13px 16px;border-radius:10px;margin-top:18px;font-weight:600;font-size:.93rem}
.banner.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.banner.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.err-box{background:#fee2e2;color:#991b1b;padding:16px;border-radius:10px;border:1px solid #fecaca}
details{margin:2px 0}
summary{font-size:.78rem;color:#64748b;cursor:pointer}
ul{margin:4px 0 0 16px;padding:0;list-style:disc;font-family:monospace;font-size:.75rem;color:#475569}
</style></head><body>
<h1>CloudStore Deployer</h1>
<p class="sub">{$host} &middot; {$now}</p>
<div class="wrap">
HTML;
}

function page(string $title, string $body): string {
    return page_open() . $body . '</div></body></html>';
}
