<?php
require_once 'includes/db.php';
if (isLoggedIn()) redirect(isAdmin() ? 'admin/' : 'dashboard.php');

$pageTitle = 'Login | CampusMarketplace';

$error = getFlashMessage('auth_error');
$success = getFlashMessage('auth_success');
$googleEnabled = googleSignInEnabled();
$mode = strtolower(trim($_GET['mode'] ?? 'login'));
$mode = in_array($mode, ['login', 'admin'], true) ? $mode : 'login';
$googleHint = match ($mode) {
    'admin' => 'Continue with Google as an approved admin account.',
    default => 'Continue with Google to sign in. If this is your first time, we will ask whether you want a buyer or seller account.',
};
$_login_ip = get_login_client_ip();
purge_old_login_attempts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $submittedMode = strtolower(trim($_POST['mode'] ?? $mode));
    $submittedMode = in_array($submittedMode, ['login', 'admin'], true) ? $submittedMode : 'login';
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (is_login_throttled($pdo, $_login_ip)) {
        $error = "Too many failed login attempts. Please wait " . LOGIN_ATTEMPT_WINDOW . " minutes and try again.";
    } else {
        // Case-sensitive username check but insensitive email check
        $stmt = $pdo->prepare("SELECT id, password, role, username, suspended, whatsapp_joined, totp_enabled, email_verified_at FROM users WHERE LOWER(email) = LOWER(?) OR username = ? LIMIT 1");
        $stmt->execute([$login_id, $login_id]);
        $user = $stmt->fetch();

        // Always run password_verify (even on no-match) to prevent timing attacks
        $hashToCheck = $user ? $user['password'] : LOGIN_DUMMY_HASH;
        $passwordOk  = password_verify($password, $hashToCheck);

        if ($user && $passwordOk) {
            // Check if account is suspended (robust boolean check for PG/MySQL)
            $isSuspended = filter_var($user['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isSuspended) {
                $error = "⛔ Your account has been suspended. Contact admin for assistance.";
            } else {
                $isAdmin = ($user['role'] ?? '') === 'admin';
                if ($isAdmin && $submittedMode !== 'admin') {
                    $error = "Admin accounts must sign in via the dedicated admin login.";
                } elseif (!$isAdmin && $submittedMode === 'admin') {
                    $error = "This login is restricted to admin accounts only.";
                } else {
                    clear_login_attempts($pdo, $_login_ip);
                    if ($isAdmin) {
                        ensure_admin_2fa_schema($pdo);
                        session_regenerate_id(true);
                        $_SESSION['pending_admin_2fa_user_id'] = (int) $user['id'];
                        $_SESSION['pending_admin_2fa_username'] = (string) $user['username'];
                        $_SESSION['pending_admin_2fa_ip'] = $_login_ip;
                        redirect('admin/verify_2fa.php');
                    }
                    
                    // Check for User 2FA
                    $userTotpEnabled = filter_var($user['totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($userTotpEnabled) {
                        session_regenerate_id(true);
                        $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
                        $_SESSION['pending_2fa_username'] = (string) $user['username'];
                        $_SESSION['pending_2fa_ip'] = $_login_ip;
                        $_SESSION['pending_2fa_role'] = (string) $user['role'];
                        redirect('verify_2fa.php');
                    }

                    // Check for Email Verification
                    $isAdminCheck = ($user['role'] ?? '') === 'admin';
                    if (!$isAdminCheck && empty($user['email_verified_at'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        redirect('verify_email.php?pending=1');
                    }

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Register session (Phase 3)
                    registerUserSession($pdo, (int)$user['id']);
                // Best-effort last_seen update. Do not block login when DB row is locked.
                try {
                    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (PDOException $e) {
                    // MySQL lock wait timeout / deadlock should not prevent authentication success.
                    $errorCode = (int)($e->errorInfo[1] ?? 0);
                    if ($errorCode !== 1205 && $errorCode !== 1213) {
                        throw $e;
                    }
                }
                    // Non-admin users must have joined the WhatsApp channel
                    $hasJoined = filter_var($user['whatsapp_joined'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if (!$isAdmin && !$hasJoined) {
                        redirect('whatsapp_join.php');
                    }
                    redirect($isAdmin ? 'admin/' : 'dashboard.php');
                }
            }
        } else {
            record_login_failure($pdo, $_login_ip, $login_id);
            $remaining = remaining_login_attempts($pdo, $_login_ip);
            $error = $remaining > 0
                ? "Invalid email/username or password. {$remaining} attempt(s) remaining."
                : "Too many failed login attempts. Please wait " . LOGIN_ATTEMPT_WINDOW . " minutes and try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<style>
/* ── Auth page CSS variables (shadcn-style) ── */
.auth-page-root {
    --auth-bg: #ffffff;
    --auth-card: #ffffff;
    --auth-border: hsl(214.3,31.8%,91.4%);
    --auth-input: hsl(214.3,31.8%,91.4%);
    --auth-text: hsl(222.2,84%,4.9%);
    --auth-muted: hsl(215.4,16.3%,46.9%);
    --auth-primary: hsl(263,70%,56%);
    --auth-primary-fg: #ffffff;
    --auth-destructive: hsl(0,84.2%,60.2%);
    --auth-ring: hsl(263,70%,56%);
    --auth-secondary: hsl(210,40%,96.1%);
    --auth-radius: 0.5rem;
}

:root.dark-mode .auth-page-root {
    --auth-bg: hsl(222.2,84%,4.9%);
    --auth-card: hsl(222.2,84%,4.9%);
    --auth-border: hsl(217.2,32.6%,17.5%);
    --auth-input: hsl(217.2,32.6%,17.5%);
    --auth-text: hsl(210,40%,98%);
    --auth-muted: hsl(215,20.2%,65.1%);
    --auth-secondary: hsl(217.2,32.6%,17.5%);
}

.auth-page-root {
    min-height: 100vh;
    display: flex;
    background: var(--auth-bg);
}

/* ── Left form panel ── */
.auth-form-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    background: var(--auth-bg);
}

.auth-form-inner {
    width: 100%;
    max-width: 440px;
}

/* ── Header ── */
.auth-header { text-align: center; margin-bottom: 2rem; }
.auth-icon-wrap {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px; border-radius: 18px;
    background: hsla(263,70%,56%,0.1); border: 1px solid hsla(263,70%,56%,0.2);
    margin-bottom: 1rem;
}
.auth-icon-wrap svg { color: var(--auth-primary); }
.auth-title {
    font-size: 1.875rem; font-weight: 800; letter-spacing: -0.03em;
    color: var(--auth-text); margin: 0 0 0.35rem;
}
.auth-subtitle { font-size: 0.95rem; color: var(--auth-muted); margin: 0; }

/* ── Card ── */
.auth-card-new {
    background: var(--auth-card);
    border: 1px solid var(--auth-border);
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 24px rgba(0,0,0,0.04);
}

/* ── Alerts ── */
.auth-alert {
    display: flex; align-items: flex-start; gap: 0.5rem;
    padding: 0.625rem 0.875rem; border-radius: var(--auth-radius);
    font-size: 0.875rem; margin-bottom: 1.25rem;
}
.auth-alert-error {
    background: hsla(0,84.2%,60.2%,0.08);
    border: 1px solid hsla(0,84.2%,60.2%,0.25);
    color: var(--auth-destructive);
}
.auth-alert-success {
    background: hsla(142,76%,36%,0.08);
    border: 1px solid hsla(142,76%,36%,0.25);
    color: hsl(142,76%,30%);
}
:root.dark-mode .auth-alert-success { color: hsl(142,60%,55%); }

/* ── Form fields ── */
.auth-field { margin-bottom: 1rem; }
.auth-label {
    display: block; font-size: 0.875rem; font-weight: 500;
    color: var(--auth-text); margin-bottom: 0.375rem;
}
.auth-input-wrap { position: relative; }
.auth-input-icon {
    position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
    pointer-events: none; color: var(--auth-muted);
}
.auth-input {
    width: 100%; height: 44px; padding: 0 0.75rem 0 2.5rem;
    border: 1px solid var(--auth-input); border-radius: var(--auth-radius);
    background: var(--auth-bg); color: var(--auth-text); font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none; box-sizing: border-box;
}
.auth-input::placeholder { color: var(--auth-muted); }
.auth-input:focus {
    border-color: var(--auth-ring);
    box-shadow: 0 0 0 3px hsla(263,70%,56%,0.15);
}
.auth-input-pw { padding-right: 2.75rem; }
.auth-pw-toggle {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; padding: 0;
    color: var(--auth-muted); transition: color 0.2s; line-height: 0;
}
.auth-pw-toggle:hover { color: var(--auth-text); }

/* ── Forgot link row ── */
.auth-forgot-row { text-align: right; margin-bottom: 1.25rem; }
.auth-link {
    color: var(--auth-primary); font-weight: 600; font-size: 0.875rem;
    text-decoration: none; transition: opacity 0.2s;
}
.auth-link:hover { opacity: 0.75; }

/* ── Primary button ── */
.auth-btn-primary {
    width: 100%; height: 44px; border: none; border-radius: var(--auth-radius);
    background: var(--auth-primary); color: var(--auth-primary-fg);
    font-size: 0.9rem; font-weight: 600; cursor: pointer;
    box-shadow: 0 4px 16px hsla(263,70%,56%,0.3);
    transition: opacity 0.2s, transform 0.1s; display: flex;
    align-items: center; justify-content: center; gap: 0.5rem;
}
.auth-btn-primary:hover { opacity: 0.9; }
.auth-btn-primary:active { transform: scale(0.98); }

/* ── Divider ── */
.auth-divider {
    display: flex; align-items: center; gap: 0.75rem;
    margin: 1.25rem 0; font-size: 0.75rem; color: var(--auth-muted);
    text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;
}
.auth-divider::before, .auth-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--auth-border);
}

/* ── Google button ── */
.auth-btn-google {
    width: 100%; height: 44px; border: 1px solid var(--auth-border);
    border-radius: var(--auth-radius); background: var(--auth-bg);
    color: var(--auth-text); font-size: 0.875rem; font-weight: 500;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    gap: 0.6rem; transition: background 0.2s;
}
.auth-btn-google:hover { background: var(--auth-secondary); }

/* ── Google GIS button container ── */
.google-auth-button-wrap {
    display: flex; justify-content: center; width: 100%; min-height: 44px;
}

/* ── Google hint ── */
.auth-google-hint {
    font-size: 0.78rem; color: var(--auth-muted); text-align: center;
    margin-top: 0.75rem;
}

/* ── Footer ── */
.auth-card-footer {
    margin-top: 1.5rem; padding-top: 1.25rem;
    border-top: 1px solid var(--auth-border); text-align: center;
}
.auth-card-footer p { font-size: 0.9rem; color: var(--auth-muted); margin: 0; }

/* ── Hero panel (right side) ── */
.auth-hero-panel {
    display: none; flex: 1; position: relative; overflow: hidden;
}
@media (min-width: 1024px) {
    .auth-hero-panel { display: flex; }
}
.auth-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #0f0f1a 0%, #2e1065 50%, #0f0f1a 100%);
}
.auth-blob {
    position: absolute; border-radius: 9999px;
    mix-blend-mode: screen; opacity: 0.3;
    animation: authBlob 7s infinite;
}
.auth-blob-1 { width: 18rem; height: 18rem; background: hsla(280,80%,60%,0.4); top: 0; left: -2rem; }
.auth-blob-2 { width: 18rem; height: 18rem; background: hsla(192,100%,50%,0.35); top: 0; right: -2rem; animation-delay: 2s; }
.auth-blob-3 { width: 18rem; height: 18rem; background: hsla(240,80%,60%,0.35); bottom: -2rem; left: 5rem; animation-delay: 4s; }
@keyframes authBlob {
    0%   { transform: translate(0,0) scale(1); }
    33%  { transform: translate(30px,-50px) scale(1.1); }
    66%  { transform: translate(-20px,20px) scale(0.9); }
    100% { transform: translate(0,0) scale(1); }
}
.auth-hero-wave {
    position: absolute; inset: 0; opacity: 0.2; pointer-events: none;
}
.auth-hero-content {
    position: relative; z-index: 10; display: flex;
    align-items: center; justify-content: center;
    padding: 3rem; width: 100%;
}
.auth-hero-inner { text-align: center; max-width: 26rem; }
.auth-hero-icon-badge {
    display: inline-flex; border-radius: 9999px; padding: 0.875rem;
    background: rgba(255,255,255,0.1);
    color: #fff; margin-bottom: 1.5rem;
}
.auth-hero-title {
    font-size: 2.25rem; font-weight: 800; color: #fff;
    letter-spacing: -0.04em; margin: 0 0 1rem; line-height: 1.15;
}
.auth-hero-desc { font-size: 1.05rem; color: rgba(255,255,255,0.78); margin: 0 0 1.75rem; line-height: 1.6; }
.auth-hero-dots { display: flex; justify-content: center; gap: 0.5rem; }
.auth-hero-dot {
    width: 8px; height: 8px; border-radius: 9999px;
    background: rgba(255,255,255,0.3);
}
.auth-hero-dot.active { background: rgba(255,255,255,0.95); }
.auth-hero-dot.mid { background: rgba(255,255,255,0.6); }

@media (max-width: 1024px) {
    .auth-page-root { flex-direction: column; height: auto; min-height: auto !important; }
    .auth-form-panel { padding: 2rem 1rem; }
    .auth-form-inner { max-width: 100%; }
}

@media (max-width: 768px) {
    .auth-form-panel { padding: 1.5rem 1rem; align-items: flex-start; }
    .auth-card-new { padding: 1.5rem 1.25rem; border-radius: 1.25rem; width: 100% !important; max-width: 100% !important; }
    .auth-title { font-size: 1.6rem; }
    .auth-header { margin-bottom: 1.5rem; }
    .auth-icon-wrap { width: 48px; height: 48px; margin-bottom: 0.75rem; }
}
</style>

<div class="auth-page-root">
    <!-- Form Panel -->
    <div class="auth-form-panel">
        <div class="auth-form-inner">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                </div>
                <h1 class="auth-title">Welcome back</h1>
                <p class="auth-subtitle">Access your safe campus marketplace</p>
            </div>

            <!-- Card -->
            <div class="auth-card-new fade-in">
                <div style="background:rgba(124,58,237,0.06); border:1px solid rgba(124,58,237,0.12); border-radius:12px; padding:0.75rem 1rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:0.75rem;">
                    <div style="background:var(--auth-primary); color:#fff; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.7rem; margin-top:2px;">i</div>
                    <p style="margin:0; font-size:0.82rem; color:var(--auth-text); line-height:1.4;">
                        <strong>Security Reminder:</strong> CampusMarketplace staff will <u>never</u> ask for your password via WhatsApp or email.
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="auth-alert auth-alert-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="auth-alert auth-alert-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:2px"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="auth-field">
                        <label class="auth-label" for="login_id">Email or Username</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </span>
                            <input type="text" id="login_id" name="login_id" class="auth-input"
                                placeholder="Enter your identifier" required autocomplete="username">
                        </div>
                    </div>

                    <div class="auth-field">
                        <label class="auth-label" for="password">Password</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" id="password" name="password" class="auth-input auth-input-pw"
                                placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" class="auth-pw-toggle" onclick="togglePw('password','eyeIcon')" aria-label="Toggle password">
                                <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="auth-forgot-row">
                        <a href="forgot_password.php" class="auth-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="auth-btn-primary">Sign in</button>
                </form>

                <?php if ($googleEnabled): ?>
                    <div class="auth-divider">or continue with</div>
                    <div id="googleLoginButton" class="google-auth-button-wrap"></div>
                    <form id="googleLoginForm" method="POST" action="google_signin.php" style="display:none;">
                        <input type="hidden" name="credential" id="googleLoginCredential">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
                    </form>
                    <p class="auth-google-hint"><?= htmlspecialchars($googleHint) ?></p>
                    <script src="https://accounts.google.com/gsi/client" async defer></script>
                    <script>
                        function handleGoogleLogin(response) {
                            const input = document.getElementById('googleLoginCredential');
                            if (!response || !response.credential || !input) return;
                            input.value = response.credential;
                            document.getElementById('googleLoginForm').submit();
                        }
                        window.addEventListener('load', function () {
                            if (!window.google || !google.accounts || !document.getElementById('googleLoginButton')) return;
                            const buttonWidth = Math.min(360, Math.max(220, document.getElementById('googleLoginButton').offsetWidth || 0));
                            google.accounts.id.initialize({
                                client_id: <?= json_encode(env('GOOGLE_CLIENT_ID', '')) ?>,
                                callback: handleGoogleLogin
                            });
                            google.accounts.id.renderButton(
                                document.getElementById('googleLoginButton'),
                                { theme: 'outline', size: 'large', shape: 'pill', text: 'continue_with', width: buttonWidth }
                            );
                        });
                    </script>
                <?php else: ?>
                    <div class="auth-divider">or</div>
                    <a href="google_signin.php" class="auth-btn-google">
                        <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Continue with Google
                    </a>
                <?php endif; ?>

                <div class="auth-card-footer">
                    <p>Don't have an account? <a href="register.php" class="auth-link">Join now</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Panel (right side, desktop only) -->
    <div class="auth-hero-panel">
        <div class="auth-hero-bg"></div>
        <div class="auth-blob auth-blob-1"></div>
        <div class="auth-blob auth-blob-2"></div>
        <div class="auth-blob auth-blob-3"></div>
        <svg class="auth-hero-wave" preserveAspectRatio="none" viewBox="0 0 1440 560">
            <defs>
                <linearGradient id="authGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#a855f7" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.1"/>
                </linearGradient>
            </defs>
            <path fill="url(#authGrad1)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,234.7C960,224,1056,192,1152,186.7C1248,181,1344,203,1392,213.3L1440,224L1440,560L1392,560C1344,560,1248,560,1152,560C1056,560,960,560,864,560C768,560,672,560,576,560C480,560,384,560,288,560C192,560,96,560,48,560L0,560Z"/>
        </svg>
        <div class="auth-hero-content">
            <div class="auth-hero-inner">
                <div class="auth-hero-icon-badge">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="auth-hero-title">Your CampusMarketplace</h2>
                <p class="auth-hero-desc">Buy, sell, and connect safely with fellow students. Your data is protected and your transactions are secure.</p>
                <div class="auth-hero-dots">
                    <div class="auth-hero-dot"></div>
                    <div class="auth-hero-dot mid"></div>
                    <div class="auth-hero-dot active"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (!inp) return;
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    if (ico) {
        ico.innerHTML = isText
            ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
            : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
