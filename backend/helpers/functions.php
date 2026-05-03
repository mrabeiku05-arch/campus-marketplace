<?php
/**
 * Global Helper Functions
 */

// ── DATABASE HELPERS ──

function sqlBool(bool $val, PDO $pdo): string {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
        ? ($val ? 'true' : 'false') 
        : ($val ? '1' : '0');
}

// ── JSON RESPONSES ──

function jsonResponse($data, int $code = 200): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function jsonSuccess(string $message, array $extra = []): void {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $extra));
}

// ── REQUEST HELPERS ──

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getRequestMethod(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function ensureLegacySessionStarted(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

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

function getQueryParam(string $key, $default = null) {
    return $_GET[$key] ?? $default;
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

    return $cached = $scheme . '://' . $host;
}

function runSchemaStatement(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('API feature schema statement failed: ' . $e->getMessage());
    }
}

function ensureFeatureSupportSchema(PDO $pdo): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at TIMESTAMP NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications BOOLEAN NOT NULL DEFAULT TRUE");
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
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id SERIAL PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id SERIAL PRIMARY KEY,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
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
    } else {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at DATETIME NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) NOT NULL DEFAULT 1");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    }

    $ready = true;
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensureFeatureSupportSchema($pdo);
}

function notificationTitleFor(string $type): string {
    return match ($type) {
        'message', 'new_message' => 'New message',
        'order', 'new_order', 'order_received' => 'New order',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'Order update',
        'approval' => 'Product Approved',
        'rejection' => 'Product Rejected',
        'subscription' => 'Subscription Update',
        'slot' => 'Slot Update',
        'payment' => 'Payment Confirmed',
        'admin_alert', 'dispute' => 'Admin alert',
        default => 'Campus Marketplace',
    };
}

function notificationLinkFor(string $type, ?int $referenceId = null): string {
    return match ($type) {
        'message', 'new_message' => $referenceId ? 'chat.php?user=' . $referenceId : 'chat.php',
        'order', 'new_order', 'order_received' => 'dashboard.php#seller_orders',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'dashboard.php#buyer_orders',
        'approval', 'rejection' => 'dashboard.php#inventory',
        'subscription', 'slot', 'payment' => 'dashboard.php#wallet',
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
    $fromName = (string) env('MAIL_FROM_NAME', 'Campus Marketplace');

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
    $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
        <h2 style=\"margin:0 0 12px;color:#7c3aed\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>
        <p>Hello " . htmlspecialchars($targetUser['username'] ?? 'there', ENT_QUOTES, 'UTF-8') . ",</p>
        <p>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>
        <p><a href=\"" . htmlspecialchars($absoluteLink, ENT_QUOTES, 'UTF-8') . "\" style=\"display:inline-block;padding:10px 18px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Open Campus Marketplace</a></p>
    </div>";

    sendMarketplaceEmail($targetUser['email'], $title . ' | Campus Marketplace', $html);
}

function createMessageNotification(PDO $pdo, int $receiverId, int $senderId, string $messagePreview = ''): void {
    $sender = getUser($pdo, $senderId);
    $senderName = $sender['username'] ?? 'Someone';
    $preview = $messagePreview !== '' ? mb_strimwidth($messagePreview, 0, 90, '…') : 'Open the chat to read it.';
    createNotification(
        $pdo,
        $receiverId,
        'message',
        $senderName . ' sent you a message: ' . $preview,
        $senderId,
        [
            'title' => 'New message from ' . $senderName,
            'link_url' => 'chat.php?user=' . $senderId,
        ]
    );
}

// ── DATABASE HELPERS ──

function getUser(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        unset($user['password']); // Never expose password
    }
    return $user ?: null;
}

function getUserPublic(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT id, username, profile_pic, seller_tier, verified, department, 
               faculty, level, bio, vacation_mode, last_seen, created_at,
               (SELECT COUNT(*) FROM products WHERE user_id = u.id AND status = 'approved') as product_count,
               (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.user_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type = 'sale' AND status = 'completed') as total_sales
        FROM users u WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $user['is_online'] = $user['last_seen'] && (time() - strtotime($user['last_seen'])) < 300;
    }
    return $user ?: null;
}

function updateLastSeen(PDO $pdo, int $userId): void {
    try {
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);
    } catch (PDOException $e) {
        $errorCode = (int)($e->errorInfo[1] ?? 0);
        if ($errorCode !== 1205 && $errorCode !== 1213) {
            throw $e;
        }
    }
}

function auditLog(PDO $pdo, int $adminId, string $action, string $targetType = '', int $targetId = 0): void {
    if ($adminId <= 0) {
        error_log("auditLog skipped for unauthenticated event: " . $action);
        return;
    }

    try {
        $pdo->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id) VALUES (?, ?, ?, ?)")
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

function getSetting(PDO $pdo, string $key, $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: (string) $default;
}
function setSetting(PDO $pdo, string $key, string $value): void {
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'pgsql') {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                       ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value")
            ->execute([$key, $value]);
    } else {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([$key, $value]);
    }
}



function getAccountTiers(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC");
        $rows = $stmt->fetchAll();
        $tiers = [];
        foreach ($rows as $r) {
            $tiers[$r['tier_name']] = $r;
        }
        if (empty($tiers)) {
            // Auto-seed
            $driver = getenv('DB_DRIVER') ?: 'mysql';
            $sql = $driver === 'pgsql' 
                ? "INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0, 'forever', 2, 1, '#7c3aed', FALSE) ON CONFLICT (tier_name) DO NOTHING"
                : "INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0, 'forever', 2, 1, '#7c3aed', 0)";
            
            $pdo->exec($sql);
            
            // Seed remaining
            if ($driver === 'pgsql') {
                $pdo->exec("INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('pro', 10, '2_weeks', 5, 2, '#8e8e93', FALSE),
                    ('premium', 20, 'weekly', 15, 3, '#ff9f0a', TRUE) ON CONFLICT (tier_name) DO NOTHING");
            } else {
                $pdo->exec("INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('pro', 10, '2_weeks', 5, 2, '#8e8e93', 0),
                    ('premium', 20, 'weekly', 15, 3, '#ff9f0a', 1)");
            }
            return getAccountTiers($pdo);
        }
        return $tiers;
    } catch (PDOException $e) {
        return [];
    }
}

function canAddProduct(PDO $pdo, int $userId): bool {
    $user = getUser($pdo, $userId);
    if (!$user) return false;

    $tier = $user['seller_tier'] ?: 'basic';
    $tiers = getAccountTiers($pdo);
    $limit = (int)($tiers[$tier]['product_limit'] ?? 2);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status IN ('approved', 'pending', 'paused')");
    $stmt->execute([$userId]);
    $count = (int) $stmt->fetchColumn();

    return $count < $limit;
}

function getMaxImages(PDO $pdo, int $userId): int {
    $user = getUser($pdo, $userId);
    $tier = $user['seller_tier'] ?? 'basic';
    $tiers = getAccountTiers($pdo);
    return (int)($tiers[$tier]['images_per_product'] ?? 1);
}

function hasUnreviewedOrders(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders o
        LEFT JOIN reviews r ON r.product_id = o.product_id AND r.user_id = ?
        WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL
    ");
    $stmt->execute([$userId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getUnreadMessageCount(PDO $pdo, int $userId): int {
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function generateReferralCode(): string {
    return 'CM' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function generateRef(string $prefix = 'TX'): string {
    return $prefix . '_' . time() . '_' . strtoupper(substr(md5(uniqid()), 0, 6));
}

function formatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '+233' . substr($phone, 1);
    }
    return $phone;
}

function getBadgeData(string $tier): array {
    $badges = [
        'basic' => ['label' => 'Basic', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        'pro' => ['label' => 'Pro', 'color' => '#8e8e93', 'bg' => 'rgba(142,142,147,0.12)'],
        'premium' => ['label' => 'Premium', 'color' => '#ff9f0a', 'bg' => 'rgba(255,159,10,0.12)'],
    ];
    return $badges[$tier] ?? $badges['basic'];
}

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function getAvgRating(PDO $pdo, int $productId): float {
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    return round((float) $stmt->fetchColumn(), 1);
}

// ── SECURITY / RATE LIMITING ──

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

function clearRateLimit(string $ip): void {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);
    @unlink($cache_file);
}

function logSecurityEvent(PDO $pdo, string $eventType, string $description, ?int $userId = null, ?string $ipAddress = null): void {
    try {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $pdo->prepare("INSERT INTO security_logs (event_type, description, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$eventType, $description, $userId, $ip]);
    } catch (Exception $e) {
        error_log("[SECURITY] $eventType: $description (User: $userId, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ")");
    }
}
