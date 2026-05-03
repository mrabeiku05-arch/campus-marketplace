<?php
if (session_status() === PHP_SESSION_NONE) {
    // Fix: Use local sessions folder to avoid XAMPP tmp permission issues
    $sessionPath = __DIR__ . '/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $sessionParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $sessionParams['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Site Configuration ──
function getSiteSetting(PDO $pdo, string $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// ── Global Error Handling (Hardening) ──
$app_env = getenv('APP_ENV') ?: 'production'; // Default to production for safety

if ($app_env === 'production') {
    // Hide errors from users on the live site
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    
    // Log errors in the background instead
    ini_set('log_errors', '1');
    // Ensure logs directory exists
    $log_path = __DIR__ . '/logs/error.log';
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }
    ini_set('error_log', $log_path);

    // Professional Fallback Screen for Fatal Errors
    set_exception_handler(function($e) {
        error_log($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $errMsg = htmlspecialchars($e->getMessage() . ' | TRACE: ' . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $errFile = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $errLine = $e->getLine();
        die("<!DOCTYPE html><html><head><title>Debug</title><style>body{background:#0a0f1e;color:#fff;font-family:monospace;padding:2rem;}pre{background:#111;padding:1rem;border-radius:12px;overflow:auto;font-size:0.8rem;color:#ff6b6b;}</style></head><body><h1 style='color:#7c3aed'>Debug Error</h1><pre>Message: $errMsg\nFile: $errFile\nLine: $errLine</pre></body></html>");
    });
    register_shutdown_function(function() {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
            $m = htmlspecialchars($err['message'], ENT_QUOTES, 'UTF-8');
            $f = htmlspecialchars($err['file'], ENT_QUOTES, 'UTF-8');
            $l = $err['line'];
            echo "<!DOCTYPE html><html><head><title>Fatal</title><style>body{background:#0a0f1e;color:#fff;font-family:monospace;padding:2rem;}pre{background:#111;padding:1rem;border-radius:12px;overflow:auto;font-size:0.8rem;color:#ff6b6b;}</style></head><body><h1 style='color:#ef4444'>Fatal/Parse Error</h1><pre>$m\nFile: $f\nLine: $l</pre></body></html>";
        }
    });
} else {
    // Show everything during development
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

$host = env('DB_HOST', 'localhost');
$dbname = env('DB_NAME', 'campus_marketplace');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');
$db_port = env('DB_PORT', '3306');
$db_type = env('DB_DRIVER', '') ?: env('DB_TYPE', 'mysql'); // 'mysql' or 'pgsql'

try {
    if ($db_type === 'pgsql' || $db_port == '5432' || $db_port == '6543') {
        $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname";
        $pdo = new PDO($dsn, $user = $db_user, $pass = $db_pass);
    } else {
        $dsn = "mysql:host=$host;port=$db_port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $db_type === 'pgsql');
    if (($db_type === 'pgsql' || $db_port == '5432' || $db_port == '6543') && defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
        $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
    }

} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        $pdo = null;
    } else {
        // PROFESSIONAL LIVE MODE ERROR SCREEN
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance | CampusMarketplace</title><style>body{background:#0a0f1e;color:#fff;font-family:system-ui, -apple-system, sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;overflow:hidden;text-align:center;} .glass{background:#111;padding:3rem;border-radius:24px;border:1px solid rgba(255,255,255,0.1);max-width:400px;box-shadow:0 40px 100px rgba(0,0,0,0.4);} h1{font-size:3rem;margin:0;background:linear-gradient(135deg, #fff 0%, #666 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.05em;} p{color:rgba(255,255,255,0.6);line-height:1.6;margin-top:1rem;font-size:1.1rem;} .dot{height:8px;width:8px;background:#7c3aed;border-radius:50%;display:inline-block;margin-right:8px;box-shadow:0 0 15px #7c3aed;}</style></head><body><div class="glass"><h1>503</h1><p><span class="dot"></span>We are currently optimizing our servers. <br>The CampusMarketplace will be back online shortly.</p></div></body></html>');
    }
}

// ── CSRF Protection ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function check_csrf(): void {
    $token = $_POST['csrf_token'] ?? null;
    
    // Check JSON input if POST is empty
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    }

    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']));
        }
        http_response_code(403);
        die('CSRF token validation failed. Please return, refresh the page, and try again.');
    }
}

function env(string $key, $default = '') {
    // 1. Check system environment (e.g. Render/Netlify)
    $val = getenv($key);
    if ($val !== false) return $val;

    // 2. Check local .env file
    static $env = null;
    if ($env === null) {
        $env = [];
        $path = __DIR__ . '/.env';
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    $v = trim($v, "\"'");
                    $env[$k] = $v;
                }
            }
        }
    }
    return $env[$key] ?? $default;
}

function get_env_var(string $key, $default = '') {
    return env($key, $default);
}

// ── Helper Functions ──

function sqlBool(bool $val, PDO $pdo): string {
    global $db_type;
    $isPgsql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql')
               || ($db_type === 'pgsql');
    return $isPgsql
        ? ($val ? 'TRUE' : 'FALSE')
        : ($val ? '1' : '0');
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function ensureAdminPageAccess(PDO $pdo, bool $requireTwoFactor = true): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isAdmin()) {
        redirect('login.php');
    }

    if (!is_admin_ip_allowed()) {
        http_response_code(403);
        exit('Access denied: your IP is not allowed for admin access.');
    }

    $admin2faVerified = filter_var($_SESSION['admin_2fa_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($requireTwoFactor && !$admin2faVerified) {
        header('Location: verify_2fa.php');
        exit;
    }

    $adminTimeoutSeconds = 1800; // 30 minutes idle timeout for admin sessions
    $adminLastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($adminLastActivity > 0 && (time() - $adminLastActivity) > $adminTimeoutSeconds) {
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }

    $_SESSION['admin_last_activity'] = time();

    $adminUserId = (int) ($_SESSION['user_id'] ?? 0);

    return [
        'admin_2fa_verified' => $admin2faVerified,
        'admin_user_id' => $adminUserId,
        'admin_unread_notifications' => getUnreadNotificationCount($pdo, $adminUserId),
    ];
}

function isSeller(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'seller';
}

function isBuyer(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'buyer';
}

function getPrimaryAdminId(PDO $pdo): int {
    static $cachedAdminId = null;

    if ($cachedAdminId !== null) {
        return $cachedAdminId;
    }

    try {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $adminId = (int) ($stmt ? $stmt->fetchColumn() : 0);
        $cachedAdminId = $adminId > 0 ? $adminId : 0;
    } catch (PDOException $e) {
        $cachedAdminId = 0;
    }

    return $cachedAdminId;
}

function getBaseUrl(): string {
    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_path);
    if ($dir === '/' || $dir === '\\') return '/';
    return rtrim($dir, '/\\') . '/';
}

$baseUrl = getBaseUrl();

function getSpaUrl(string $path = '/', array $params = []): string {
    global $baseUrl;

    $normalizedBase = rtrim($baseUrl, '/');
    $normalizedPath = '/' . ltrim($path, '/');
    $query = $params ? '?' . http_build_query($params) : '';

    if ($normalizedPath === '/' && $query === '') {
        return $normalizedBase === '' ? '/' : $normalizedBase . '/';
    }

    $base = $normalizedBase === '' ? '' : $normalizedBase;
    return $base . '/#' . $normalizedPath . $query;
}

function getAssetUrl(string $path): string {
    global $baseUrl;
    static $asset_domains = null;
    $path = trim(str_replace('\\', '/', $path));
    
    if ($asset_domains === null) {
        $domains_str = env('ASSET_DOMAINS', '');
        $asset_domains = $domains_str ? array_filter(array_map('trim', explode(',', $domains_str))) : [];
    }

    // Normalize relative local paths stored in mixed formats such as
    // ../uploads/foo.jpg, ./uploads/foo.jpg, /uploads/foo.jpg, or uploads\foo.jpg.
    $path = preg_replace('#^(?:\./|\../)+#', '', $path);
    
    // Handle cases where templates force 'uploads/' prefix on Cloudinary absolute URLs
    if (strpos($path, 'uploads/http') === 0) {
        return substr($path, 8);
    }

    // Normalize duplicated local-upload prefixes, e.g. uploads/uploads/foo.jpg
    while (strpos($path, 'uploads/uploads/') === 0) {
        $path = substr($path, 8);
    }

    if (strpos($path, '/uploads/') === 0) {
        $path = ltrim($path, '/');
    }
    
    // If no asset domains are configured, return the local base URL joined with the path
    if (empty($asset_domains)) {
        if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) return $path;
        return $baseUrl . ltrim($path, '/');
    }
    
    // Domain sharding logic: pick a domain based on path hash
    // We use crc32 to consistently map the same asset to the same domain (better for caching)
    $index = abs(crc32($path)) % count($asset_domains);
    $domain = $asset_domains[$index];
    
    // Ensure domain has protocol
    if (strpos($domain, 'http') !== 0 && strpos($domain, '//') !== 0) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $protocol . $domain;
    }
    
    // To support sharding from project root, we need to handle the marketplace/ prefix if it's there
    // If the path already has the base URL, we might need to strip it if shards point to root
    // But usually it's safer to just ltrim the path and append to domain.
    return rtrim($domain, '/') . '/' . ltrim($path, '/');
}


function redirect(string $url): void {
    global $baseUrl;
    // SECURITY: only allow relative paths — reject absolute URLs and
    // protocol-relative URLs (//evil.com) which browsers treat as external.
    // A valid relative path starts with a single '/' (not '//') or no slash.
    if (preg_match('#^https?://#i', $url) || preg_match('#^//#', $url)) {
        // Discard the untrusted URL and fall back to the dashboard
        $url = 'dashboard.php';
    }
    if (strpos($url, '/') !== 0) {
        $url = $baseUrl . $url;
    }
    header("Location: $url");
    exit();
}

function setFlashMessage(string $key, string $message): void {
    $_SESSION['_flash'][$key] = $message;
}

function getFlashMessage(string $key): string {
    $message = $_SESSION['_flash'][$key] ?? '';
    unset($_SESSION['_flash'][$key]);
    return (string) $message;
}

function getAppUrl(): string {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $configured = rtrim((string) env('APP_URL', ''), '/');
    if ($configured !== '') {
        return $cached = $configured;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    global $baseUrl;
    $suffix = rtrim($baseUrl, '/');

    return $cached = $scheme . '://' . $host . $suffix;
}

/**
 * Returns the public root URL of the site, stripped of any /api suffix.
 * Used for email links and other public-facing URLs that should point
 * to the root PHP files, not the backend API router.
 */
function getSiteRootUrl(): string {
    $url = getAppUrl();
    // If APP_URL is configured with /api, strip it
    if (str_ends_with($url, '/api')) {
        return substr($url, 0, -4);
    }
    // If APP_URL is configured with /backend (AlwaysData default), strip it
    if (str_ends_with($url, '/backend')) {
        return substr($url, 0, -8);
    }
    return $url;
}

/**
 * Formats a phone number into a clickable WhatsApp link.
 * Handles +233, 0..., and raw numbers.
 */
function formatWhatsAppLink(?string $phone): string {
    if (!$phone) return '#';
    // Remove everything except numbers
    $clean = preg_replace('/[^0-9]/', '', $phone);
    // If it starts with 0 and is 10 digits, assume Ghana and prefix with 233
    if (str_starts_with($clean, '0') && strlen($clean) === 10) {
        $clean = '233' . substr($clean, 1);
    }
    // If it doesn't have a country code but is 9 digits, assume Ghana
    if (strlen($clean) === 9) {
        $clean = '233' . $clean;
    }
    return "https://wa.me/" . $clean;
}

function googleSignInEnabled(): bool {
    return trim((string) env('GOOGLE_CLIENT_ID', '')) !== '';
}

function runSchemaStatement(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('Feature schema statement failed: ' . $e->getMessage());
    }
}

function ensureFeatureSupportSchema(PDO $pdo): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(191)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(32)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar TEXT");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at TIMESTAMP NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications BOOLEAN NOT NULL DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS browser_notifications BOOLEAN NOT NULL DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp_joined BOOLEAN NOT NULL DEFAULT FALSE");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(100)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled BOOLEAN NOT NULL DEFAULT FALSE");
        
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS user_sessions (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions (user_id)");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_user_sessions_session_id ON user_sessions (session_id)");

        runSchemaStatement($pdo, "CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id ON users (google_id)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Seed default WhatsApp verification code if not exists
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) SELECT 'whatsapp_verification_code', 'CAMPUS_JOIN_2026' WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'whatsapp_verification_code')");
        $stmt->execute();

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector VARCHAR(32) NOT NULL UNIQUE,
            token_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_reset_tokens (user_id)");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_password_resets_expires_at ON password_reset_tokens (expires_at)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_notifications_user_created ON notifications (user_id, created_at DESC)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id SERIAL PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_security_logs_created_at ON security_logs (created_at DESC)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id SERIAL PRIMARY KEY,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_payment_verification_logs_reference ON payment_verification_logs (reference)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS ad_placements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_path TEXT DEFAULT '',
            link_url TEXT DEFAULT '#',
            placement VARCHAR(50) DEFAULT 'homepage',
            is_active BOOLEAN DEFAULT TRUE,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_path TEXT DEFAULT ''");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS link_url TEXT DEFAULT '#'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS placement VARCHAR(50) DEFAULT 'homepage'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS impressions INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN image_path TYPE TEXT");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN link_url TYPE TEXT");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT ''");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN image_url TYPE TEXT");
        runSchemaStatement($pdo, "UPDATE ad_placements SET image_path = COALESCE(NULLIF(image_path, ''), image_url, '')");
        runSchemaStatement($pdo, "ALTER TABLE products ADD COLUMN IF NOT EXISTS promo_tag VARCHAR(50) DEFAULT ''");
    } else {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(191) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(32) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at DATETIME NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) NOT NULL DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS browser_notifications TINYINT(1) NOT NULL DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp_joined TINYINT(1) NOT NULL DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(100) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB");

        runSchemaStatement($pdo, "CREATE UNIQUE INDEX idx_users_google_id ON users (google_id)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Seed default WhatsApp verification code if not exists
        $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('whatsapp_verification_code', 'CAMPUS_JOIN_2026')");
        $stmt->execute();

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector VARCHAR(32) NOT NULL UNIQUE,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_resets_user_id (user_id),
            INDEX idx_password_resets_expires_at (expires_at)
        ) ENGINE=InnoDB");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user_created (user_id, created_at)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS redirect_path VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_security_logs_created_at (created_at)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_payment_verification_logs_reference (reference)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS ad_placements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_path TEXT NULL,
            link_url TEXT NULL,
            placement VARCHAR(50) DEFAULT 'homepage',
            is_active TINYINT(1) DEFAULT 1,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_path TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS link_url TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS placement VARCHAR(50) DEFAULT 'homepage'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS impressions INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_url TEXT NULL");
        runSchemaStatement($pdo, "UPDATE ad_placements SET image_path = COALESCE(NULLIF(image_path, ''), image_url, '')");
        runSchemaStatement($pdo, "ALTER TABLE products ADD COLUMN IF NOT EXISTS promo_tag VARCHAR(50) DEFAULT ''");

        // CROSS-DRIVER: Site Settings table (MySQL + PostgreSQL)
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // CROSS-DRIVER: Tier Subscriptions table for Admin Visibility (PostgreSQL + MySQL)
    $subTableSql = ($driver === 'pgsql')
        ? "CREATE TABLE IF NOT EXISTS tier_subscriptions (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            tier_name VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            status VARCHAR(20) DEFAULT 'active'
        )"
        : "CREATE TABLE IF NOT EXISTS tier_subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tier_name VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            status VARCHAR(20) DEFAULT 'active'
        ) ENGINE=InnoDB";

    runSchemaStatement($pdo, $subTableSql);

    // DEEP FIX: Ensure expires_at is nullable in existing tables
    if ($driver === 'pgsql') {
        runSchemaStatement($pdo, "ALTER TABLE tier_subscriptions ALTER COLUMN expires_at DROP NOT NULL");
    } else {
        runSchemaStatement($pdo, "ALTER TABLE tier_subscriptions MODIFY expires_at DATETIME NULL");
    }
    runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_tier_subs_user ON tier_subscriptions(user_id)");
    runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_tier_subs_expiry ON tier_subscriptions(expires_at)");

    // Seed default WhatsApp verification code if not exists
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) SELECT 'whatsapp_verification_code', 'CAMPUS_JOIN_2026' WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'whatsapp_verification_code')");
    } else {
        $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('whatsapp_verification_code', 'CAMPUS_JOIN_2026')");
    }
    $stmt->execute();

    $ready = true;
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensureFeatureSupportSchema($pdo);
}

function notificationTitleFor(string $type): string {
    return match ($type) {
        'new_message' => 'New message',
        'new_order', 'order_received' => 'New order',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'Order update',
        'admin_alert', 'dispute' => 'Admin alert',
        'password_reset' => 'Password reset',
        default => 'CampusMarketplace',
    };
}

function notificationLinkFor(string $type, ?int $referenceId = null): string {
    return match ($type) {
        'new_message' => $referenceId ? 'chat.php?user=' . $referenceId : 'chat.php',
        'new_order', 'order_received' => 'dashboard.php#seller_orders',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'dashboard.php#buyer_orders',
        'admin_alert', 'dispute' => 'admin/',
        default => 'dashboard.php',
    };
}

function sendMarketplaceEmail(string $to, string $subject, string $html, string $plainText = ''): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $host = parse_url(getAppUrl(), PHP_URL_HOST) ?: 'campusmarketplace.local';
    $fromAddress = (string) env('MAIL_FROM_ADDRESS', 'no-reply@' . $host);
    $fromName = (string) env('MAIL_FROM_NAME', 'CampusMarketplace');

    if ($plainText === '') {
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $html))));
    }

    $boundary = 'cm-' . bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . $fromName . ' <' . $fromAddress . '>';
    $headers[] = 'Reply-To: ' . $fromAddress;
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plainText . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function createNotification(PDO $pdo, int $userId, string $type, string $message, ?int $referenceId = null, array $options = []): void {
    if ($userId <= 0) {
        return;
    }

    ensureFeatureSupportSchema($pdo);

    $title = $options['title'] ?? notificationTitleFor($type);
    $linkUrl = $options['link_url'] ?? notificationLinkFor($type, $referenceId);

    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $title, $message, $linkUrl, $referenceId]);

    $shouldEmail = array_key_exists('email', $options) ? (bool) $options['email'] : true;
    if (!$shouldEmail) {
        return;
    }

    $userStmt = $pdo->prepare("SELECT email, username, email_notifications FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $targetUser = $userStmt->fetch();

    if (!$targetUser || empty($targetUser['email']) || !filter_var($targetUser['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }

    if (isset($targetUser['email_notifications']) && !filter_var($targetUser['email_notifications'], FILTER_VALIDATE_BOOLEAN)) {
        return;
    }

    $absoluteLink = rtrim(getAppUrl(), '/') . '/' . ltrim($linkUrl, '/');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeLink = htmlspecialchars($absoluteLink, ENT_QUOTES, 'UTF-8');

    $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
        <h2 style=\"margin:0 0 12px;color:#7c3aed\">{$safeTitle}</h2>
        <p>Hello " . htmlspecialchars($targetUser['username'] ?? 'there', ENT_QUOTES, 'UTF-8') . ",</p>
        <p>{$safeMessage}</p>
        <p><a href=\"{$safeLink}\" style=\"display:inline-block;padding:10px 18px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Open CampusMarketplace</a></p>
        <p style=\"font-size:12px;color:#6b7280\">You are receiving this because activity happened on your CampusMarketplace account.</p>
    </div>";

    sendMarketplaceEmail($targetUser['email'], $title . ' | CampusMarketplace', $html);
}

function createMessageNotification(PDO $pdo, int $receiverId, int $senderId, string $messagePreview = ''): void {
    $sender = getUser($pdo, $senderId);
    $senderName = $sender['username'] ?? 'Someone';
    $preview = $messagePreview !== '' ? mb_strimwidth($messagePreview, 0, 90, '…') : 'Open the chat to read it.';
    createNotification(
        $pdo,
        $receiverId,
        'new_message',
        $senderName . ' sent you a message: ' . $preview,
        $senderId,
        [
            'title' => 'New message from ' . $senderName,
            'link_url' => 'chat.php?user=' . $senderId,
        ]
    );
}

function issuePasswordResetToken(PDO $pdo, int $userId): array {
    ensureFeatureSupportSchema($pdo);

    $selector = bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < ?")
        ->execute([$userId, date('Y-m-d H:i:s')]);

    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $selector, $tokenHash, $expiresAt]);

    return ['selector' => $selector, 'token' => $token, 'expires_at' => $expiresAt];
}

function findPasswordResetToken(PDO $pdo, string $selector): ?array {
    ensureFeatureSupportSchema($pdo);

    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE selector = ? LIMIT 1");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function consumePasswordResetToken(PDO $pdo, string $selector): void {
    $pdo->prepare("UPDATE password_reset_tokens SET used_at = ? WHERE selector = ?")
        ->execute([date('Y-m-d H:i:s'), $selector]);
}

/**
 * Parses a duration string (e.g., 'weekly', '2_weeks', '3_months', 'forever')
 * and returns a modification string for DateTime::modify() and a human-friendly label.
 */
function parse_tier_duration(string $duration): array
{
    $value = strtolower(trim($duration));

    if ($value === '' || $value === '0' || $value === 'forever' || $value === 'lifetime') {
        return ['modify' => null, 'label' => 'lifetime access'];
    }

    if ($value === 'weekly' || $value === '1_week') {
        return ['modify' => '+1 week', 'label' => '1 week'];
    }

    if ($value === '2_weeks') {
        return ['modify' => '+2 weeks', 'label' => '2 weeks'];
    }

    if (preg_match('/^(\d+)_weeks?$/', $value, $matches)) {
        $weeks = max(1, (int)$matches[1]);
        return ['modify' => '+' . $weeks . ' weeks', 'label' => $weeks . ' week' . ($weeks === 1 ? '' : 's')];
    }

    if (preg_match('/^(\d+)_months?$/', $value, $matches)) {
        $months = max(1, (int)$matches[1]);
        return ['modify' => '+' . $months . ' months', 'label' => $months . ' month' . ($months === 1 ? '' : 's')];
    }

    if (is_numeric($value)) {
        $months = max(1, (int)$value);
        return ['modify' => '+' . $months . ' months', 'label' => $months . ' month' . ($months === 1 ? '' : 's')];
    }

    return ['modify' => '+1 month', 'label' => '1 month'];
}

/**
 * Calculates the next expiry date based on the current expiry and a duration string.
 * If current expiry is in the future, the new duration is added to it.
 * Otherwise, it's added to 'now'.
 */
function next_tier_expiry(?string $currentExpiry, string $duration): ?string
{
    $parsed = parse_tier_duration($duration);
    if ($parsed['modify'] === null) {
        return null;
    }

    $base = new DateTimeImmutable('now');
    if (!empty($currentExpiry)) {
        try {
            $existing = new DateTimeImmutable($currentExpiry);
            if ($existing > $base) {
                $base = $existing;
            }
        } catch (Exception $e) {
        }
    }

    return $base->modify($parsed['modify'])->format('Y-m-d H:i:s');
}

if ($pdo instanceof PDO) {
    ensureFeatureSupportSchema($pdo);
}

function generateReferralCode(int $length = 8): string {
    return strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
}

function generateRef(string $prefix = 'TX'): string {
    return $prefix . '_' . strtoupper(uniqid());
}

function getUser(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if ($u) {
        unset($u['password']); // Never carry the hash beyond auth code
    }
    return $u ?: null;
}

function getUnreadCount(PDO $pdo, int $userId): int {
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getUnreadMessageCount(PDO $pdo, int $userId): int {
    return getUnreadCount($pdo, $userId);
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    ensureFeatureSupportSchema($pdo);
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function updateLastSeen(PDO $pdo, int $userId): void {
    try {
        // ── AUTO-DOWNGRADE LOGIC (Phase 2 Hardening) ──
        $stmt_exp = $pdo->prepare("SELECT seller_tier, tier_expires_at FROM users WHERE id = ? LIMIT 1");
        $stmt_exp->execute([$userId]);
        $u_exp = $stmt_exp->fetch();

        if ($u_exp && $u_exp['seller_tier'] !== 'basic' && !empty($u_exp['tier_expires_at'])) {
            if (strtotime($u_exp['tier_expires_at']) < time()) {
                // Tier has expired!
                $oldTier = $u_exp['seller_tier'];
                $boolT = sqlBool(true, $pdo);
                $pdo->prepare("UPDATE users SET seller_tier = 'basic', tier_expires_at = NULL, vacation_mode = $boolT WHERE id = ?")
                    ->execute([$userId]);
                
                // Create a notification for the user
                createNotification($pdo, $userId, 'admin_alert', 'Your subscription has expired. Your account has been set to Basic and Vacation Mode has been enabled.', null, ['title' => 'Subscription Expired']);
                
                // Log the event in security_logs
                $log = $pdo->prepare("INSERT INTO security_logs (event_type, description, user_id, ip_address) VALUES (?, ?, ?, ?)");
                $log->execute([
                    'tier_expired', 
                    "User's {$oldTier} tier expired. Automatically downgraded to basic and enabled vacation mode.",
                    $userId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);

                // Notify user via flash message
                if (function_exists('setFlashMessage')) {
                    setFlashMessage('error', 'Your subscription has expired. Your account has been set to Vacation Mode and downgraded to Basic.');
                }
            }
        }

        $pdo->prepare("UPDATE users SET last_seen = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $userId]);
        
        // Also update session activity
        $sid = session_id();
        if ($sid !== '') {
            $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ? AND user_id = ?")
                ->execute([$sid, $userId]);
        }
    } catch (PDOException $e) {
        // Frequent heartbeat writes can hit lock waits; keep requests non-blocking.
        $errorCode = (int)($e->errorInfo[1] ?? 0);
        if ($errorCode !== 1205 && $errorCode !== 1213) {
            throw $e;
        }
    }
}

function registerUserSession(PDO $pdo, int $userId): void {
    $sid = session_id();
    if ($sid === '') return;
    
    $ip = get_login_client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        // Clean up this specific session if it already exists for some reason
        $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")->execute([$sid]);
        
        $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $sid, $ip, $ua]);
            
        // Check for new login alert (Phase 3)
        // Check if this user has ever logged in from this IP before
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND ip_address = ? AND created_at < NOW() - INTERVAL '1 minute'");
        $stmt->execute([$userId, $ip]);
        $hasHistory = (int) $stmt->fetchColumn() > 0;
        
        if (!$hasHistory) { // This is a new IP for this user
            $user = getUser($pdo, $userId);
            // Re-fetch with email since getUser might unset it in some versions (ours doesn't)
            $stmt_e = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt_e->execute([$userId]);
            $email = $stmt_e->fetchColumn();

            if ($user && $email) {
                $time = date('F j, Y, g:i a');
                $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
                    <h2 style=\"color:#7c3aed;margin-bottom:12px\">New Login Detected</h2>
                    <p>Hello " . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>Your CampusMarketplace account was just signed into from a new device or location.</p>
                    <div style=\"background:#f3f4f6;padding:15px;border-radius:8px;margin:15px 0;\">
                        <strong>Time:</strong> {$time}<br>
                        <strong>IP Address:</strong> {$ip}<br>
                        <strong>Device:</strong> " . htmlspecialchars($ua, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    <p>If this was you, you can ignore this email. If you don't recognize this activity, please <strong>change your password immediately</strong> and log out other sessions from your Security settings.</p>
                    <p><a href=\"" . rtrim(getAppUrl(), '/') . "/security.php\" style=\"display:inline-block;padding:10px 18px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Manage Sessions</a></p>
                </div>";
                sendMarketplaceEmail($email, 'Security Alert: New Login Detected | CampusMarketplace', $html);
            }
        }
    } catch (PDOException $e) {
        error_log('Failed to register user session: ' . $e->getMessage());
    }
}

function auditLog(PDO $pdo, int $adminId, string $action, string $targetType = '', int $targetId = 0): void {
    if ($adminId <= 0) {
        error_log("auditLog skipped for unauthenticated event: " . $action);
        return;
    }

    try {
        $pdo->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id) VALUES (?,?,?,?)")
            ->execute([$adminId, $action, $targetType, $targetId]);
    } catch (PDOException $e) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '23503' || $sqlState === '23000') {
            error_log("auditLog skipped due to missing admin reference #{$adminId}: " . $e->getMessage());
            return;
        }
        throw $e;
    }
}

function getProductImages(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$productId]);
    return $stmt->fetchAll() ?: [];
}

function getSellerProductCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getBadgeHtml(PDO $pdo, string $tier): string {
    $tier = strtolower($tier);
    try {
        $stmt = $pdo->prepare("SELECT badge FROM account_tiers WHERE tier_name = ?");
        $stmt->execute([$tier]);
        $color = strtolower((string)$stmt->fetchColumn());
    } catch(Exception $e) { $color = null; }
    
    // Default Fallback
    if(!$color) {
        $color = match($tier) { 
            'premium' => 'gold', 
            'pro' => 'silver', 
            default => 'blue' 
        };
    }
    
    $bg = match($color) { 
        'gold' => 'linear-gradient(135deg, #ff9f0a 0%, #d4af37 100%)', 
        'silver' => 'linear-gradient(135deg, #8b939a 0%, #5d6d7e 100%)', 
        'blue' => 'linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%)',
        default => $color // Support hex codes in DB
    };
    
    $txt = '#fff';
    $icon = match($tier) {
        'premium' => '',
        'pro' => '',
        default => ''
    };

    return "<span class='badge' style='background:".htmlspecialchars($bg, ENT_QUOTES, 'UTF-8')."; color:".htmlspecialchars($txt, ENT_QUOTES, 'UTF-8')."; padding:4px 12px; border-radius:30px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); border:1px solid rgba(255,255,255,0.1);'>{$icon}" . ucfirst($tier) . "</span>";
}


function canAddProduct($pdo, $userId) {
    if (!$userId) return false;
    $u = getUser($pdo, $userId);
    if (!$u) return false;
    $tier = $u['seller_tier'] ?: 'basic';
    
    $tiers = getAccountTiers($pdo);
    $limit = $tiers[$tier]['product_limit'] ?? 2;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status != 'deleted'");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() >= $limit) return false;

    // Batch frequency check based on 'duration' (stored as months)
    if ($tier !== 'basic' && !empty($u['last_upload_at'])) {
        $duration = $tiers[$tier]['duration'] ?? '1';
        $monthsNeeded = is_numeric($duration) ? (int)$duration : 0;
        if ($monthsNeeded > 0) {
            $last = strtotime($u['last_upload_at']);
            // Using 30 days as a standard month for restriction logic
            if (time() - $last < ($monthsNeeded * 30 * 86400)) return false;
        }
    }
    
    return true;
}

function getMaxImages($pdo, $userId) {
    $u = getUser($pdo, $userId);
    if (!$u) return 1;
    $tier = $u['seller_tier'] ?: 'basic';
    
    $tiers = getAccountTiers($pdo);
    return (int)($tiers[$tier]['images_per_product'] ?? 1);
}

/**
 * Checks if user must submit a review before browsing.
 * Rule: 10. Reviews required before further browsing.
 */
function hasUnreviewedOrders($pdo, $userId) {
    if (!$userId) return false;
    $s = $pdo->prepare("SELECT COUNT(*) FROM orders o 
        LEFT JOIN reviews r ON o.product_id = r.product_id AND r.user_id = o.buyer_id
        WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL");
    $s->execute([$userId]);
    return ((int)$s->fetchColumn() > 0);
}

function getFirstUnreviewedProductId(PDO $pdo, int $userId): ?int
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT o.product_id
         FROM orders o
         LEFT JOIN reviews r ON o.product_id = r.product_id AND r.user_id = o.buyer_id
         WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL
         ORDER BY o.created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $productId = (int) ($stmt->fetchColumn() ?: 0);

    return $productId > 0 ? $productId : null;
}

// System-wide tables (settings, orders, tiers, profile_edit_requests) 
// are now managed in migrate.php to prevent excessive server load.


function getAccountTiers(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT * FROM account_tiers");
        $tiers = [];
        while($row = $stmt->fetch()) {
            $tiers[$row['tier_name']] = $row;
        }

        if(empty($tiers)) {
            // Auto-seed if somehow empty during a query
            $boolF = sqlBool(false, $pdo);
            $boolT = sqlBool(true, $pdo);
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                $pdo->exec("INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0.00, '0', 2, 1, 'blue', $boolF),
                    ('pro', 10.00, '1', 5, 2, 'silver', $boolF),
                    ('premium', 20.00, '1', 15, 3, 'gold', $boolT)
                    ON CONFLICT (tier_name) DO NOTHING
                ");
            } else {
                $pdo->exec("INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0.00, '0', 2, 1, 'blue', $boolF),
                    ('pro', 10.00, '1', 5, 2, 'silver', $boolF),
                    ('premium', 20.00, '1', 15, 3, 'gold', $boolT)
                ");
            }

            // Retry fetch
            $stmt = $pdo->query("SELECT * FROM account_tiers");
            while($row = $stmt->fetch()) { $tiers[$row['tier_name']] = $row; }
        }
        return $tiers;
    } catch(Exception $e) { return []; }
}

function getSetting(PDO $pdo, string $key, $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? (string)$val : (string)$default;
    } catch(Exception $e) { return (string)$default; }
}



function getAvgRating(PDO $pdo, int $productId): float {
    $stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    return (float)$stmt->fetchColumn() ?: 0.0;
}

// ────────────────────────────────────────────────────────────────────────
// SECURITY: Rate Limiting and Logging Functions
// ────────────────────────────────────────────────────────────────────────

/**
 * Check if an IP/user has exceeded rate limit for login attempts
 * @param string $ip Client IP address
 * @param int $maxAttempts Maximum failed attempts before blocking
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'attempts' => int, 'cooldown' => int]
 */
function checkRateLimit(string $ip, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);

    $data = ['attempts' => 0, 'first_attempt' => time()];

    if (file_exists($cache_file)) {
        $cached = @json_decode(file_get_contents($cache_file), true);
        if ($cached && (time() - $cached['first_attempt']) < $windowSeconds) {
            $data = $cached;
        } else {
            @unlink($cache_file);
        }
    }

    $allowed = $data['attempts'] < $maxAttempts;
    $cooldown = max(0, $windowSeconds - (time() - $data['first_attempt']));

    return [
        'allowed' => $allowed,
        'attempts' => $data['attempts'],
        'cooldown' => $cooldown
    ];
}

/**
 * Record a failed login attempt for rate limiting
 */
function recordFailedLogin(string $ip): void {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);

    $data = ['attempts' => 0, 'first_attempt' => time()];

    if (file_exists($cache_file)) {
        $cached = @json_decode(file_get_contents($cache_file), true);
        if ($cached && (time() - $cached['first_attempt']) < 900) {
            $data = $cached;
        }
    }

    $data['attempts']++;
    @file_put_contents($cache_file, json_encode($data));
}

/**
 * Clear rate limit for successful login
 */
function clearRateLimit(string $ip): void {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);
    @unlink($cache_file);
}

/**
 * Log security events for monitoring
 */
function logSecurityEvent(PDO $pdo, string $eventType, string $description, ?int $userId = null, ?string $ipAddress = null): void {
    try {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $pdo->prepare("INSERT INTO security_logs (event_type, description, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$eventType, $description, $userId, $ip]);
    } catch (Exception $e) {
        // Log to file if database fails
        error_log("[SECURITY] $eventType: $description (User: $userId, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ")");
    }
}

/**
 * Log payment verification attempt
 */
function logPaymentVerification(PDO $pdo, int $userId, string $reference, string $status, ?string $error = null): void {
    try {
        $pdo->prepare("INSERT INTO payment_verification_logs (user_id, reference, status, error_message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$userId, $reference, $status, $error, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        error_log("[PAYMENT] Verification failed for ref $reference: " . ($error ?? 'unknown'));
    }
}

// ────────────────────────────────────────────────────────────────────────
// Shared login rate-limiting helpers (DB-backed, used by login.php + admin/settings.php)
// ────────────────────────────────────────────────────────────────────────

if (!defined('LOGIN_ATTEMPT_LIMIT')) {
    define('LOGIN_ATTEMPT_LIMIT', 5);
    define('LOGIN_ATTEMPT_WINDOW', 15); // minutes
    define('LOGIN_DUMMY_HASH', '$2y$12$invalidsaltvaluethatisnevertrue000000000000000000000000');
}

function ensure_login_attempts_table(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                id SERIAL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username_tried VARCHAR(255) NOT NULL DEFAULT '',
                attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts (ip_address, attempt_time)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username_tried VARCHAR(255) NOT NULL DEFAULT '',
                attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time (ip_address, attempt_time)
            ) ENGINE=InnoDB");
        }
        $ready = true;
    } catch (PDOException $e) {
        error_log('login_attempts schema check failed: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function get_login_client_ip(): string
{
    if (defined('TRUST_PROXY') && TRUST_PROXY === true && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_admin_ip_allowed(): bool
{
    $raw = trim((string) env('ADMIN_ALLOWED_IPS', ''));
    if ($raw === '') {
        return true;
    }

    $clientIp = get_login_client_ip();
    if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
        return false;
    }

    $rules = array_filter(array_map('trim', explode(',', $raw)));
    foreach ($rules as $rule) {
        if ($rule === $clientIp) {
            return true;
        }

        if (strpos($rule, '/') !== false && strpos($clientIp, ':') === false) {
            [$subnet, $bits] = explode('/', $rule, 2);
            $subnetLong = ip2long($subnet);
            $ipLong = ip2long($clientIp);
            $bits = (int) $bits;
            if ($subnetLong === false || $ipLong === false || $bits < 0 || $bits > 32) {
                continue;
            }
            $mask = $bits === 0 ? 0 : (~((1 << (32 - $bits)) - 1) & 0xFFFFFFFF);
            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }
    }

    return false;
}

function is_login_throttled(PDO $pdo, string $ip): bool
{
    if (!ensure_login_attempts_table($pdo)) return false;
    $interval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "INTERVAL '" . LOGIN_ATTEMPT_WINDOW . " minutes'"
        : "INTERVAL " . LOGIN_ATTEMPT_WINDOW . " MINUTE";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > NOW() - $interval");
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= LOGIN_ATTEMPT_LIMIT;
}

function record_login_failure(PDO $pdo, string $ip, string $usernameTried): void
{
    if (!ensure_login_attempts_table($pdo)) return;
    $pdo->prepare("INSERT INTO login_attempts (ip_address, username_tried, attempt_time) VALUES (?, ?, NOW())")
        ->execute([$ip, $usernameTried]);
}

function clear_login_attempts(PDO $pdo, string $ip): void
{
    if (!ensure_login_attempts_table($pdo)) return;
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

function purge_old_login_attempts(PDO $pdo): void
{
    if (!ensure_login_attempts_table($pdo)) return;
    $interval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "INTERVAL '" . LOGIN_ATTEMPT_WINDOW . " minutes'"
        : "INTERVAL " . LOGIN_ATTEMPT_WINDOW . " MINUTE";
    $pdo->exec("DELETE FROM login_attempts WHERE attempt_time < NOW() - $interval");
}

function remaining_login_attempts(PDO $pdo, string $ip): int
{
    if (!ensure_login_attempts_table($pdo)) return LOGIN_ATTEMPT_LIMIT;
    $interval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "INTERVAL '" . LOGIN_ATTEMPT_WINDOW . " minutes'"
        : "INTERVAL " . LOGIN_ATTEMPT_WINDOW . " MINUTE";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > NOW() - $interval");
    $stmt->execute([$ip]);
    return max(0, LOGIN_ATTEMPT_LIMIT - (int) $stmt->fetchColumn());
}

function ensure_admin_2fa_schema(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_totp_secret VARCHAR(64)");
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_totp_enabled BOOLEAN NOT NULL DEFAULT FALSE");
        } else {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_totp_secret VARCHAR(64) NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_totp_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }
        $ready = true;
    } catch (PDOException $e) {
        error_log('admin 2fa schema check failed: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function generate_totp_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function base32_decode_safe(string $input): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
    if ($input === '') return '';

    $bits = '';
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $input[$i]);
        if ($val === false) return '';
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    $output = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $output .= chr(bindec(substr($bits, $i, 8)));
    }
    return $output;
}

function totp_code_for_time(string $secret, int $timestamp): string
{
    $key = base32_decode_safe($secret);
    if ($key === '') return '';
    $counter = intdiv($timestamp, 30);
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $chunk = substr($hash, $offset, 4);
    $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function verify_totp_code(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code_for_time($secret, $now + ($i * 30)), $code)) {
            return true;
        }
    }
    return false;
}

// ────────────────────────────────────────────────────────────────────────
// Password-reset rate limiting (separate table/limits from login attempts)
// ────────────────────────────────────────────────────────────────────────

if (!defined('RESET_ATTEMPT_LIMIT')) {
    define('RESET_ATTEMPT_LIMIT', 3);   // max reset requests per IP
    define('RESET_ATTEMPT_WINDOW', 30); // minutes
}

function ensure_password_reset_attempts_table(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
                id SERIAL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pra_ip_time ON password_reset_attempts (ip_address, attempt_time)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pra_ip_time (ip_address, attempt_time)
            ) ENGINE=InnoDB");
        }
        $ready = true;
    } catch (PDOException $e) {
        error_log('password_reset_attempts schema check failed: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function is_reset_throttled(PDO $pdo, string $ip): bool
{
    if (!ensure_password_reset_attempts_table($pdo)) return false;
    $interval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "INTERVAL '" . RESET_ATTEMPT_WINDOW . " minutes'"
        : "INTERVAL " . RESET_ATTEMPT_WINDOW . " MINUTE";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = ? AND attempt_time > NOW() - $interval");
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= RESET_ATTEMPT_LIMIT;
}

function record_reset_attempt(PDO $pdo, string $ip): void
{
    if (!ensure_password_reset_attempts_table($pdo)) return;
    $pdo->prepare("INSERT INTO password_reset_attempts (ip_address, attempt_time) VALUES (?, NOW())")
        ->execute([$ip]);
}

function purge_old_reset_attempts(PDO $pdo): void
{
    if (!ensure_password_reset_attempts_table($pdo)) return;
    $interval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "INTERVAL '" . RESET_ATTEMPT_WINDOW . " minutes'"
        : "INTERVAL " . RESET_ATTEMPT_WINDOW . " MINUTE";
    $pdo->exec("DELETE FROM password_reset_attempts WHERE attempt_time < NOW() - $interval");
}

// ────────────────────────────────────────────────────────────────────────

// Update last_seen on each request
if (isLoggedIn() && $pdo) {
    updateLastSeen($pdo, $_SESSION['user_id']);
}
