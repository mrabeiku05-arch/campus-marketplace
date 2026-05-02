<?php
require_once 'includes/db.php';

$error = '';
$success = '';
$isPending = isset($_GET['pending']) && $_GET['pending'] == '1';
$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    // Attempt to verify the token
    $stmt = $pdo->prepare("SELECT id, username, referred_by FROM users WHERE email_verification_token = ? AND email_verified_at IS NULL LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        try {
            $pdo->beginTransaction();
            $userId = (int) $user['id'];
            $referredBy = $user['referred_by'] ? (int) $user['referred_by'] : null;

            // Mark as verified
            $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?")
                ->execute([$userId]);

            // Now that email is verified, apply referral bonuses if applicable
            if ($referredBy) {
                // Referrer gets 5.00
                $pdo->prepare("UPDATE users SET balance = balance + 5.00 WHERE id = ?")->execute([$referredBy]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 5.00, 'completed', ?, 'Referral bonus')")->execute([$referredBy, generateRef('REF')]);
                
                // Referred user gets 2.00
                $pdo->prepare("UPDATE users SET balance = balance + 2.00 WHERE id = ?")->execute([$userId]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 2.00, 'completed', ?, 'Signup referral bonus')")->execute([$userId, generateRef('REF')]);
                
                // Record referral
                $pdo->prepare("INSERT INTO referrals (referrer_id, referred_user_id, bonus) VALUES (?,?,5.00)")->execute([$referredBy, $userId]);
            }

            $pdo->commit();
            $success = 'Email verified successfully! You can now access your account.';
            
            // If logged in, update session. If not, they'll login normally.
            if (isLoggedIn() && $_SESSION['user_id'] == $userId) {
                // They stay logged in
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Email verification failed: ' . $e->getMessage());
            $error = 'Verification failed. Please contact support.';
        }
    } else {
        $error = 'Invalid or expired verification token.';
    }
}

// Resend logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    check_csrf();
    if (isLoggedIn()) {
        $u = getUser($pdo, $_SESSION['user_id']);
        if ($u && empty($u['email_verified_at'])) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET email_verification_token = ? WHERE id = ?")->execute([$token, $u['id']]);
            
            $verifyUrl = rtrim(getSiteRootUrl(), '/') . '/verify_email.php?token=' . $token;
            $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
            $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
                <h2 style=\"color:#7c3aed;margin-bottom:12px\">Verify your email</h2>
                <p>Hello " . htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') . ",</p>
                <p>Please verify your email address to activate your account.</p>
                <p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 24px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Verify Email Address</a></p>
            </div>";
            
            // We need the email which getUser() unsets for safety in some versions, 
            // but our getUser doesn't unset email, only password.
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$u['id']]);
            $email = $stmt->fetchColumn();
            
            if ($email) {
                sendMarketplaceEmail($email, 'Verify your email | CampusMarketplace', $html);
                $success = 'Verification email resent! Please check your inbox.';
                $isPending = true;
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-page-root" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding:20px;">
    <div class="auth-card-new" style="width:100%; max-width:500px; text-align:center;">
        <div class="auth-header">
            <div class="auth-icon-wrap">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h2 class="auth-title">Email Verification</h2>
        </div>

        <?php if ($error): ?>
            <div class="auth-alert auth-alert-error" style="margin-bottom:1.5rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success" style="margin-bottom:1.5rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
            <div style="margin-top:1.5rem;">
                <a href="dashboard.php" class="btn btn-primary" style="width:100%; justify-content:center;">Go to Dashboard</a>
            </div>
        <?php elseif ($isPending): ?>
            <p style="color:var(--auth-text); margin-bottom:1.5rem;">
                We've sent a verification link to your email address. <br>
                Please click the link in the email to activate your account.
            </p>
            <div style="background:rgba(124,58,237,0.05); border:1px solid rgba(124,58,237,0.15); border-radius:12px; padding:1rem; margin-bottom:1.5rem; text-align:left;">
                <p style="margin:0; font-size:0.875rem; color:var(--auth-muted);">
                    <strong>Didn't receive the email?</strong><br>
                    • Check your spam or junk folder. <br>
                    • Wait a few minutes for it to arrive. <br>
                    • Make sure your email is correct.
                </p>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <button type="submit" name="resend" class="btn btn-outline" style="width:100%; justify-content:center; border:1px solid var(--auth-border);">
                    Resend Verification Email
                </button>
            </form>
        <?php else: ?>
            <p style="color:var(--auth-muted);">Please use the link sent to your email or log in to request a new one.</p>
            <div style="margin-top:1.5rem;">
                <a href="login.php" class="btn btn-primary" style="width:100%; justify-content:center;">Back to Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
