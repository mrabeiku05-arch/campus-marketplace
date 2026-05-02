<?php
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, username, totp_secret, totp_enabled FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$error = '';
$success = '';
$totpEnabled = filter_var($user['totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
$existingSecret = trim((string) ($user['totp_secret'] ?? ''));

// Handle Enabling 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf();
    $action = $_POST['action'];

    if ($action === 'enable') {
        $setupSecret = $_POST['setup_secret'] ?? '';
        $code = trim($_POST['code'] ?? '');

        if ($setupSecret === '' || !verify_totp_code($setupSecret, $code)) {
            $error = 'Invalid verification code. Please try again.';
        } else {
            $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = ? WHERE id = ?")
                ->execute([$setupSecret, 1, $userId]);
            $totpEnabled = true;
            $success = 'Two-factor authentication has been enabled successfully.';
            unset($_SESSION['pending_totp_setup_secret']);
        }
    } elseif ($action === 'disable') {
        $code = trim($_POST['code'] ?? '');

        if ($existingSecret === '' || !verify_totp_code($existingSecret, $code)) {
            $error = 'Invalid verification code. 2FA was not disabled.';
        } else {
            $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = ? WHERE id = ?")
                ->execute([0, $userId]);
            $totpEnabled = false;
            $success = 'Two-factor authentication has been disabled.';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Verify current password (fetch with password hash this time)
        $stmt_p = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_p->execute([$userId]);
        $user_p = $stmt_p->fetch();

        if (!$user_p || !password_verify($current, $user_p['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 12 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $new)) {
            $error = 'New password must be at least 12 characters and include uppercase, lowercase, number, and special character.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
            $success = 'Your password has been changed successfully.';
        }
    } elseif ($action === 'revoke_session') {
        $sessionId = $_POST['session_id'] ?? '';
        if ($sessionId !== '' && $sessionId !== session_id()) {
            $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ? AND user_id = ?")
                ->execute([$sessionId, $userId]);
            $success = 'Session revoked successfully.';
        }
    }
}

// Fetch active sessions
$sessions = [];
try {
    $stmt_s = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
    $stmt_s->execute([$userId]);
    $sessions = $stmt_s->fetchAll();
} catch (PDOException $e) {}

// Prepare setup secret if not enabled
$setupSecret = '';
$otpauthUrl = '';
if (!$totpEnabled) {
    $setupSecret = $_SESSION['pending_totp_setup_secret'] ?? '';
    if ($setupSecret === '') {
        $setupSecret = generate_totp_secret();
        $_SESSION['pending_totp_setup_secret'] = $setupSecret;
    }
    $issuer = rawurlencode('CampusMarketplace');
    $account = rawurlencode($user['username']);
    $otpauthUrl = "otpauth://totp/{$issuer}:{$account}?secret={$setupSecret}&issuer={$issuer}&digits=6&period=30";
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="glass p-4 fade-in">
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-circle bg-primary-soft me-3">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h2 class="mb-0">Account Security</h2>
                        <p class="text-muted mb-0">Manage your two-factor authentication settings.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success mb-4"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="security-card p-4 border rounded-3 bg-light-soft mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="mb-1">Two-Factor Authentication (2FA)</h4>
                            <p class="text-muted small mb-0">Add an extra layer of security to your account using an authenticator app.</p>
                        </div>
                        <span class="badge <?= $totpEnabled ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                            <?= $totpEnabled ? 'ENABLED' : 'DISABLED' ?>
                        </span>
                    </div>

                    <?php if ($totpEnabled): ?>
                        <div class="mt-4">
                            <p class="mb-3">Your account is protected with 2FA. To disable it, enter a 6-digit code from your app below.</p>
                            <form method="POST" class="row g-3 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="disable">
                                <div class="col-md-6">
                                    <label class="form-label" for="disableCode">Authenticator Code</label>
                                    <input type="text" name="code" id="disableCode" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-outline-danger w-100">Disable 2FA</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 pt-3 border-top">
                            <h5 class="mb-3">Setup 2FA</h5>
                            <ol class="ps-3 mb-4">
                                <li class="mb-2">Download an authenticator app like <strong>Google Authenticator</strong> or <strong>Authy</strong>.</li>
                                <li class="mb-2">Scan the QR code below or enter the secret key manually.</li>
                                <li>Enter the 6-digit code from your app to verify and enable.</li>
                            </ol>

                            <div class="row align-items-center">
                                <div class="col-md-5 text-center mb-4 mb-md-0">
                                    <div id="qrcode" class="d-inline-block p-3 bg-white rounded-3 shadow-sm"></div>
                                </div>
                                <div class="col-md-7">
                                    <div class="p-3 bg-dark-soft rounded-3 border mb-3">
                                        <span class="small text-muted mb-1 d-block">Secret Key (Manual Entry)</span>
                                        <code class="d-block word-break-all text-primary fw-bold" style="font-size:1.1rem;"><?= htmlspecialchars($setupSecret) ?></code>
                                    </div>
                                    <p class="small text-muted mb-4">
                                        Can't scan? <a href="<?= htmlspecialchars($otpauthUrl) ?>" class="text-primary text-decoration-none fw-bold">Open in App Directly</a>
                                    </p>
                                    
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="enable">
                                        <input type="hidden" name="setup_secret" value="<?= htmlspecialchars($setupSecret) ?>">
                                        <div class="mb-3">
                                            <label class="form-label" for="enableCode">Enter 6-digit Verification Code</label>
                                            <input type="text" name="code" id="enableCode" class="form-control form-control-lg" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Enable 2FA Now</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                        <script>
                            new QRCode(document.getElementById("qrcode"), {
                                text: <?= json_encode($otpauthUrl) ?>,
                                width: 180,
                                height: 180,
                                colorDark : "#000000",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });
                        </script>
                    <?php endif; ?>
                </div>

                <div class="security-card p-4 border rounded-3 bg-light-soft mb-4">
                    <h4 class="mb-3">Change Password</h4>
                    <p class="text-muted small mb-4">Regularly changing your password helps keep your account secure.</p>
                    
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="12" autocomplete="new-password">
                                <div class="form-text small">Min 12 chars, include A-Z, a-z, 0-9, and symbol.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="12" autocomplete="new-password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary px-4 py-2">Update Password</button>
                    </form>
                </div>

                <div class="security-card p-4 border rounded-3 bg-light-soft mb-4">
                    <h4 class="mb-3">Active Sessions</h4>
                    <p class="text-muted small mb-4">View and manage your active sessions across different devices.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" style="font-size:0.875rem;">
                            <thead class="text-muted">
                                <tr>
                                    <th>Device / Browser</th>
                                    <th>IP Address</th>
                                    <th>Last Activity</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <?php 
                                        $isCurrent = ($session['session_id'] === session_id());
                                        // Simple UA parser for display
                                        $ua = $session['user_agent'] ?? 'Unknown';
                                        $device = 'Device';
                                        if (stripos($ua, 'Windows') !== false) $device = 'Windows';
                                        elseif (stripos($ua, 'iPhone') !== false) $device = 'iPhone';
                                        elseif (stripos($ua, 'Android') !== false) $device = 'Android';
                                        elseif (stripos($ua, 'Mac') !== false) $device = 'Mac';

                                        $browser = 'Browser';
                                        if (stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
                                        elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
                                        elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';
                                        elseif (stripos($ua, 'Edge') !== false) $browser = 'Edge';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars("$device ($browser)") ?></div>
                                            <div class="text-muted extra-small" style="font-size:0.75rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($ua) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($session['ip_address']) ?></td>
                                        <td>
                                            <?= date('M j, H:i', strtotime($session['last_activity'])) ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge bg-success-soft text-success ms-1" style="font-size:0.7rem; background:rgba(25,135,84,0.1);">Current</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!$isCurrent): ?>
                                                <form method="POST" onsubmit="return confirm('Log out this device?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="revoke_session">
                                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['session_id']) ?>">
                                                    <button type="submit" class="btn btn-link text-danger p-0" style="font-size:0.875rem;">Revoke</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-top">
                    <a href="dashboard.php" class="text-muted text-decoration-none d-inline-flex align-items-center">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-2"><path d="M15 19l-7-7 7-7"/></svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.icon-circle {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
}
.bg-primary-soft { background: hsla(263,70%,56%,0.1); color: hsl(263,70%,56%); }
.bg-light-soft { background: rgba(0,0,0,0.02); }
:root.dark-mode .bg-light-soft { background: rgba(255,255,255,0.03); }
.bg-dark-soft { background: rgba(0,0,0,0.05); }
:root.dark-mode .bg-dark-soft { background: rgba(255,255,255,0.05); }
.word-break-all { word-break: break-all; }
</style>

<?php require_once 'includes/footer.php'; ?>
