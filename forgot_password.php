<?php
require_once 'includes/db.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');
}

$error = '';
$success = '';

$_reset_ip = get_login_client_ip();
purge_old_reset_attempts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $loginId = strtolower(trim($_POST['login_id'] ?? ''));

    if ($loginId === '') {
        $error = 'Please enter your email address or username.';
    } elseif (is_reset_throttled($pdo, $_reset_ip)) {
        // Reveal as little as possible — same message as success to avoid leaking throttle state
        $success = 'If that account exists, a password reset link has been sent to its email address.';
    } else {
        // Record the attempt regardless of whether the account exists (prevents enumeration via timing)
        record_reset_attempt($pdo, $_reset_ip);

        $stmt = $pdo->prepare("SELECT id, email, username FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1");
        $stmt->execute([$loginId, $loginId]);
        $user = $stmt->fetch();

        if ($user && !empty($user['email'])) {
            $tokenData = issuePasswordResetToken($pdo, (int) $user['id']);
            $resetUrl = rtrim(getSiteRootUrl(), '/') . '/reset_password.php?selector=' . rawurlencode($tokenData['selector']) . '&token=' . rawurlencode($tokenData['token']);
            $safeName = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

            $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
                <h2 style=\"color:#7c3aed;margin-bottom:12px\">Reset your password</h2>
                <p>Hello {$safeName},</p>
                <p>We received a request to reset your CampusMarketplace password.</p>
                <p><a href="{$safeUrl}" style="display:inline-block;padding:10px 18px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700">Reset Password</a></p>
                <p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>
            </div>";

            sendMarketplaceEmail($user['email'], 'Reset your password | CampusMarketplace', $html);
        }

        $success = 'If that account exists, a password reset link has been sent to its email address.';
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding:20px;">
    <div class="glass form-container fade-in" style="width:100%; max-width:480px;">
        <div class="text-center" style="margin-bottom:2rem;">
            <h1 style="font-size:1.9rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Forgot Password</h1>
            <p style="color:var(--text-muted); margin-top:0.5rem;">We’ll send you a secure link to set a new one.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:1rem; text-align:center;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:1rem; text-align:center;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="login_id">Email or Username</label>
                <input type="text" name="login_id" id="login_id" class="form-control" required autocomplete="username email" placeholder="Enter your email or username">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Send Reset Link</button>
        </form>

        <div style="margin-top:1.5rem; text-align:center;">
            <a href="login.php" style="color:var(--primary); font-weight:700; text-decoration:none;">Back to Sign In</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
