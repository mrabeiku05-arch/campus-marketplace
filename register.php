<?php
require_once 'includes/db.php';
if (isLoggedIn())
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');

$error = getFlashMessage('auth_error');
$success = getFlashMessage('auth_success');
$googleEnabled = googleSignInEnabled();
$mode = $_GET['mode'] ?? 'buyer'; // buyer or seller

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $mode = $_POST['mode'] ?? 'buyer';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ref_code = trim($_POST['referral_code'] ?? '');
    $honeypot = $_POST['website'] ?? ''; // anti-bot
    $terms = $_POST['terms'] ?? '';

    // Faculty (required for all users)
    $faculty = trim($_POST['faculty'] ?? '');

    // Seller-specific
    $department = trim($_POST['department'] ?? '');
    $level = $_POST['level'] ?? '';
    // Use hall_residence from POST for both hall and hall_residence to maintain backwards compatibility
    $hall = trim($_POST['hall_residence'] ?? '');
    $hall_residence = trim($_POST['hall_residence'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($honeypot)) {
        $error = "Bot detected.";
    } elseif (empty($terms)) {
        $error = "You must accept the Terms & Conditions.";
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
        $error = "Password must be at least 12 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (empty($faculty)) {
        $error = "Please select your faculty.";
    } elseif ($mode === 'seller' && (empty($department) || empty($level) || empty($phone))) {
        $error = "Sellers must fill department, level, and phone.";
    } else {
        $email = strtolower($email); // Always store email in lowercase
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = "Email or Username already taken.";
        } else {
            try {
                $pdo->beginTransaction();

                $referred_by = null;
                if (!empty($ref_code)) {
                    $ref_stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $ref_stmt->execute([$ref_code]);
                    $r = $ref_stmt->fetch();
                    if ($r)
                        $referred_by = $r['id'];
                }

                // Handle profile pic upload for sellers
                $pic = null;
                if ($mode === 'seller' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        // SECURITY: Validate MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                        // SECURITY: Check file size (10MB max for avatars)
                        $maxFileSize = 10 * 1024 * 1024;
                        if (in_array($mimeType, $allowedMimes) && $_FILES['profile_pic']['size'] <= $maxFileSize) {
                            if (!is_dir('uploads/avatars'))
                                mkdir('uploads/avatars', 0755, true);

                            // SECURITY: Strip EXIF and re-encode
                            $image = null;
                            if ($mimeType === 'image/jpeg') {
                                $image = @imagecreatefromjpeg($_FILES['profile_pic']['tmp_name']);
                            } elseif ($mimeType === 'image/png') {
                                $image = @imagecreatefrompng($_FILES['profile_pic']['tmp_name']);
                            } elseif ($mimeType === 'image/webp') {
                                $image = @imagecreatefromwebp($_FILES['profile_pic']['tmp_name']);
                            }

                            if ($image) {
                                $pic = 'avatars/' . uniqid('av_', true) . '.' . $ext;
                                $uploadPath = 'uploads/' . $pic;
                                // Ensure path doesn't escape uploads directory
                                $realPath = realpath(dirname($uploadPath));
                                if ($realPath && strpos($realPath, realpath('uploads')) === 0) {
                                    if ($ext === 'jpg' || $ext === 'jpeg') {
                                        imagejpeg($image, $uploadPath, 85);
                                    } elseif ($ext === 'png') {
                                        imagepng($image, $uploadPath, 8);
                                    } elseif ($ext === 'webp') {
                                        imagewebp($image, $uploadPath, 85);
                                    }
                                }
                            }
                        }
                    }
                }

                // Format phone
                if ($phone && substr($phone, 0, 1) === '0') {
                    $phone = '+233' . substr($phone, 1);
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $new_ref = generateReferralCode();
                $role = ($mode === 'seller') ? 'seller' : 'buyer';
                $verificationToken = bin2hex(random_bytes(32));

                $insertParams = [$username, $email, $hashed, $role, $faculty, $department ?: null, $level ?: null, $hall ?: null, $hall_residence ?: null, $phone ?: null, $pic, $new_ref, $referred_by, $verificationToken];
                if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, department, level, hall, hall_residence, phone, profile_pic, referral_code, referred_by, terms_accepted, whatsapp_joined, email_verification_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,false,false,?) RETURNING id");
                    $stmt->execute($insertParams);
                    $user_id = (int) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, department, level, hall, hall_residence, phone, profile_pic, referral_code, referred_by, terms_accepted, whatsapp_joined, email_verification_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?)");
                    $stmt->execute($insertParams);
                    $user_id = (int) $pdo->lastInsertId();
                }

                if ($user_id <= 0) {
                    throw new RuntimeException('Could not determine new user ID after registration.');
                }

                // Send Verification Email
                $verifyUrl = rtrim(getSiteRootUrl(), '/') . '/verify_email.php?token=' . $verificationToken;
                $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
                $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
                    <h2 style=\"color:#7c3aed;margin-bottom:12px\">Verify your email</h2>
                    <p>Hello " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>Welcome to CampusMarketplace! Please verify your email address to activate your account.</p>
                    <p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 24px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Verify Email Address</a></p>
                    <p>If you cannot click the button, copy and paste this link into your browser:</p>
                    <p style=\"font-size:0.85rem;color:#6b7280\">{$safeUrl}</p>
                </div>";
                sendMarketplaceEmail($email, 'Verify your email | CampusMarketplace', $html);

                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                
                // Redirect to a pending verification page or show notice
                setFlashMessage('auth_success', 'Account created! Please check your email to verify your account.');
                redirect('verify_email.php?pending=1');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('register.php registration failed: ' . $e->getMessage());
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
/* Reuse auth-page styles from login.php — defined here too for standalone use */
.reg-page-root {
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
    min-height: 100vh; display: flex; background: var(--auth-bg);
}
:root.dark-mode .reg-page-root {
    --auth-bg: hsl(222.2,84%,4.9%);
    --auth-card: hsl(222.2,84%,4.9%);
    --auth-border: hsl(217.2,32.6%,17.5%);
    --auth-input: hsl(217.2,32.6%,17.5%);
    --auth-text: hsl(210,40%,98%);
    --auth-muted: hsl(215,20.2%,65.1%);
    --auth-secondary: hsl(217.2,32.6%,17.5%);
}
.reg-form-panel {
    flex: 1; display: flex; align-items: flex-start; justify-content: center;
    padding: 2rem 1.5rem; background: var(--auth-bg); overflow-y: auto;
}
.reg-form-inner { width: 100%; max-width: 560px; padding: 1rem 0 3rem; }
.reg-header { text-align: center; margin-bottom: 1.75rem; }
.auth-icon-wrap {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px; border-radius: 18px;
    background: hsla(263,70%,56%,0.1); border: 1px solid hsla(263,70%,56%,0.2); margin-bottom: 1rem;
}
.auth-icon-wrap svg { color: var(--auth-primary); }
.auth-title { font-size: 1.875rem; font-weight: 800; letter-spacing: -0.03em; color: var(--auth-text); margin: 0 0 0.35rem; }
.auth-subtitle { font-size: 0.95rem; color: var(--auth-muted); margin: 0; }

/* Role toggle */
.reg-tabs { display: flex; border: 1px solid var(--auth-border); border-radius: 0.75rem; overflow: hidden; background: hsla(210,40%,96%,0.4); margin-bottom: 1.5rem; }
:root.dark-mode .reg-tabs { background: hsla(217.2,32.6%,17.5%,0.4); }
.reg-tab {
    flex: 1; padding: 0.625rem; text-align: center; font-weight: 600; font-size: 0.875rem;
    text-decoration: none; transition: all 0.2s; color: var(--auth-muted);
}
.reg-tab.active { background: var(--auth-primary); color: var(--auth-primary-fg); }

/* Card */
.auth-card-new {
    background: var(--auth-card); border: 1px solid var(--auth-border); border-radius: 1rem;
    padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 24px rgba(0,0,0,0.04);
}
/* Alerts */
.auth-alert { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.625rem 0.875rem; border-radius: var(--auth-radius); font-size: 0.875rem; margin-bottom: 1.25rem; }
.auth-alert-error { background: hsla(0,84.2%,60.2%,0.08); border: 1px solid hsla(0,84.2%,60.2%,0.25); color: var(--auth-destructive); }
.auth-alert-success { background: hsla(142,76%,36%,0.08); border: 1px solid hsla(142,76%,36%,0.25); color: hsl(142,76%,30%); }
:root.dark-mode .auth-alert-success { color: hsl(142,60%,55%); }

/* Fields */
.auth-field { margin-bottom: 1rem; }
.reg-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 480px) { .reg-field-grid { grid-template-columns: 1fr; } }
.auth-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--auth-text); margin-bottom: 0.375rem; }
.auth-label .auth-req { color: var(--auth-destructive); }
.auth-label .auth-hint { font-size: 0.72rem; color: var(--auth-muted); font-weight: 400; }
.auth-input-wrap { position: relative; }
.auth-input-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--auth-muted); }
.auth-input {
    width: 100%; height: 44px; padding: 0 0.75rem 0 2.5rem; border: 1px solid var(--auth-input);
    border-radius: var(--auth-radius); background: var(--auth-bg); color: var(--auth-text); font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none; box-sizing: border-box;
}
.auth-input.no-icon { padding-left: 0.75rem; }
.auth-input::placeholder { color: var(--auth-muted); }
.auth-input:focus { border-color: var(--auth-ring); box-shadow: 0 0 0 3px hsla(263,70%,56%,0.15); }
.auth-input-pw { padding-right: 2.75rem; }
.auth-pw-toggle { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; color: var(--auth-muted); transition: color 0.2s; line-height: 0; }
.auth-pw-toggle:hover { color: var(--auth-text); }
.auth-select {
    width: 100%; height: 44px; padding: 0 0.75rem; border: 1px solid var(--auth-input);
    border-radius: var(--auth-radius); background: var(--auth-bg); color: var(--auth-text);
    font-size: 0.875rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; appearance: auto;
}
.auth-select:focus { border-color: var(--auth-ring); box-shadow: 0 0 0 3px hsla(263,70%,56%,0.15); }

/* Seller section */
.reg-seller-section { border: 1px solid var(--auth-border); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; background: hsla(210,40%,96%,0.3); }
:root.dark-mode .reg-seller-section { background: hsla(217.2,32.6%,17.5%,0.3); }
.reg-seller-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--auth-muted); margin-bottom: 1rem; display: block; }

/* Terms */
.reg-terms { display: flex; gap: 0.75rem; align-items: flex-start; margin: 1.25rem 0; padding: 1rem; border-radius: 0.75rem; border: 1px solid hsla(263,70%,56%,0.12); background: hsla(263,70%,56%,0.04); }
.reg-terms input[type=checkbox] { width: 18px; height: 18px; margin-top: 2px; cursor: pointer; flex-shrink: 0; accent-color: var(--auth-primary); }
.reg-terms label { font-size: 0.875rem; color: var(--auth-text); line-height: 1.5; cursor: pointer; margin: 0; }

/* Buttons */
.auth-btn-primary {
    width: 100%; height: 44px; border: none; border-radius: var(--auth-radius);
    background: var(--auth-primary); color: var(--auth-primary-fg); font-size: 0.9rem; font-weight: 600;
    cursor: pointer; box-shadow: 0 4px 16px hsla(263,70%,56%,0.3); transition: opacity 0.2s, transform 0.1s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.auth-btn-primary:hover { opacity: 0.9; }
.auth-btn-primary:active { transform: scale(0.98); }
.auth-divider { display: flex; align-items: center; gap: 0.75rem; margin: 1.25rem 0; font-size: 0.75rem; color: var(--auth-muted); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
.auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: var(--auth-border); }
.auth-btn-google {
    width: 100%; height: 44px; border: 1px solid var(--auth-border); border-radius: var(--auth-radius);
    background: var(--auth-bg); color: var(--auth-text); font-size: 0.875rem; font-weight: 500;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.6rem; transition: background 0.2s;
}
.auth-btn-google:hover { background: var(--auth-secondary); }
.google-auth-button-wrap { display: flex; justify-content: center; width: 100%; min-height: 44px; }
.auth-google-hint { font-size: 0.78rem; color: var(--auth-muted); text-align: center; margin-top: 0.75rem; }
.auth-card-footer { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--auth-border); text-align: center; }
.auth-card-footer p { font-size: 0.9rem; color: var(--auth-muted); margin: 0; }
.auth-link { color: var(--auth-primary); font-weight: 600; font-size: 0.875rem; text-decoration: none; transition: opacity 0.2s; }
.auth-link:hover { opacity: 0.75; }

/* Hero panel */
.auth-hero-panel { display: none; flex: 1; position: relative; overflow: hidden; }
@media (min-width: 1024px) { .auth-hero-panel { display: flex; } }

@media (max-width: 1024px) {
    .reg-page-root { flex-direction: column; height: auto; min-height: auto !important; }
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
.auth-hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #0f0f1a 0%, #2e1065 50%, #0f0f1a 100%); }
.auth-blob { position: absolute; border-radius: 9999px; mix-blend-mode: screen; opacity: 0.3; animation: authBlob 7s infinite; }
.auth-blob-1 { width: 18rem; height: 18rem; background: hsla(280,80%,60%,0.4); top: 0; left: -2rem; }
.auth-blob-2 { width: 18rem; height: 18rem; background: hsla(192,100%,50%,0.35); top: 0; right: -2rem; animation-delay: 2s; }
.auth-blob-3 { width: 18rem; height: 18rem; background: hsla(240,80%,60%,0.35); bottom: -2rem; left: 5rem; animation-delay: 4s; }
@keyframes authBlob { 0%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-50px) scale(1.1)} 66%{transform:translate(-20px,20px) scale(0.9)} 100%{transform:translate(0,0) scale(1)} }
.auth-hero-wave { position: absolute; inset: 0; opacity: 0.2; pointer-events: none; }
.auth-hero-content { position: relative; z-index: 10; display: flex; align-items: center; justify-content: center; padding: 3rem; width: 100%; }
.auth-hero-inner { text-align: center; max-width: 26rem; }
.auth-hero-icon-badge { display: inline-flex; border-radius: 9999px; padding: 0.875rem; background: rgba(255,255,255,0.1); color: #fff; margin-bottom: 1.5rem; }
.auth-hero-title { font-size: 2.25rem; font-weight: 800; color: #fff; letter-spacing: -0.04em; margin: 0 0 1rem; line-height: 1.15; }
.auth-hero-desc { font-size: 1.05rem; color: rgba(255,255,255,0.78); margin: 0 0 1.75rem; line-height: 1.6; }
.auth-hero-dots { display: flex; justify-content: center; gap: 0.5rem; }
.auth-hero-dot { width: 8px; height: 8px; border-radius: 9999px; background: rgba(255,255,255,0.3); }
.auth-hero-dot.active { background: rgba(255,255,255,0.95); }
.auth-hero-dot.mid { background: rgba(255,255,255,0.6); }

/* File input */
.auth-input-file { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--auth-input); border-radius: var(--auth-radius); background: var(--auth-bg); color: var(--auth-text); font-size: 0.875rem; box-sizing: border-box; cursor: pointer; }
</style>

<div class="reg-page-root">
    <!-- Hero Panel (left on register) -->
    <div class="auth-hero-panel">
        <div class="auth-hero-bg"></div>
        <div class="auth-blob auth-blob-1"></div>
        <div class="auth-blob auth-blob-2"></div>
        <div class="auth-blob auth-blob-3"></div>
        <svg class="auth-hero-wave" preserveAspectRatio="none" viewBox="0 0 1440 560">
            <defs>
                <linearGradient id="regGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#a855f7" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.1"/>
                </linearGradient>
            </defs>
            <path fill="url(#regGrad1)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,234.7C960,224,1056,192,1152,186.7C1248,181,1344,203,1392,213.3L1440,224L1440,560L1392,560C1344,560,1248,560,1152,560C1056,560,960,560,864,560C768,560,672,560,576,560C480,560,384,560,288,560C192,560,96,560,48,560L0,560Z"/>
        </svg>
        <div class="auth-hero-content">
            <div class="auth-hero-inner">
                <div class="auth-hero-icon-badge">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                </div>
                <h2 class="auth-hero-title">Join CampusMarketplace</h2>
                <p class="auth-hero-desc">Connect with thousands of students. Buy, sell, and grow your campus business with ease.</p>
                <div class="auth-hero-dots">
                    <div class="auth-hero-dot active"></div>
                    <div class="auth-hero-dot mid"></div>
                    <div class="auth-hero-dot"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="reg-form-panel">
        <div class="reg-form-inner">
            <!-- Header -->
            <div class="reg-header">
                <div class="auth-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                </div>
                <h1 class="auth-title">Create account</h1>
                <p class="auth-subtitle">Join your campus marketplace today</p>
            </div>

            <!-- Role tabs -->
            <div class="reg-tabs">
                <a href="?mode=buyer" class="reg-tab <?= $mode === 'buyer' ? 'active' : '' ?>">Buyer</a>
                <a href="?mode=seller" class="reg-tab <?= $mode === 'seller' ? 'active' : '' ?>">Seller</a>
            </div>

            <!-- Card -->
            <div class="auth-card-new fade-in">
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

                <?php if ($googleEnabled): ?>
                    <div id="googleRegisterButton" class="google-auth-button-wrap"></div>
                    <p class="auth-google-hint">Continue with Google as a <?= htmlspecialchars($mode) ?>. You can finish profile details after sign-up.</p>
                    <form id="googleRegisterForm" method="POST" action="google_signin.php" style="display:none;">
                        <input type="hidden" name="credential" id="googleRegisterCredential">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    </form>
                    <script src="https://accounts.google.com/gsi/client" async defer></script>
                    <script>
                        function handleGoogleRegister(response) {
                            const input = document.getElementById('googleRegisterCredential');
                            if (!response || !response.credential || !input) return;
                            input.value = response.credential;
                            document.getElementById('googleRegisterForm').submit();
                        }
                        window.addEventListener('load', function () {
                            if (!window.google || !google.accounts || !document.getElementById('googleRegisterButton')) return;
                            const buttonWidth = Math.min(420, Math.max(220, document.getElementById('googleRegisterButton').offsetWidth || 0));
                            google.accounts.id.initialize({
                                client_id: <?= json_encode(env('GOOGLE_CLIENT_ID', '')) ?>,
                                callback: handleGoogleRegister
                            });
                            google.accounts.id.renderButton(
                                document.getElementById('googleRegisterButton'),
                                { theme: 'outline', size: 'large', shape: 'pill', text: 'signup_with', width: buttonWidth }
                            );
                        });
                    </script>
                    <div class="auth-divider">or use email</div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="registerForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    <div style="display:none;"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                    <div class="reg-field-grid">
                        <div>
                            <label class="auth-label" for="regUsername">Username <span class="auth-req">*</span></label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                                <input type="text" name="username" id="regUsername" class="auth-input" placeholder="e.g. john_doe" required autocomplete="username">
                            </div>
                        </div>
                        <div>
                            <label class="auth-label" for="regEmail">Email <span class="auth-req">*</span></label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span>
                                <input type="email" name="email" id="regEmail" class="auth-input" placeholder="name@example.com" required autocomplete="email">
                            </div>
                        </div>
                    </div>

                    <div class="reg-field-grid">
                        <div>
                            <label class="auth-label" for="regPassword">
                                Password <span class="auth-req">*</span>
                            </label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                                <input type="password" name="password" id="regPassword" class="auth-input auth-input-pw"
                                    placeholder="Min 12 chars, upper, lower, #, symbol" required minlength="12"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:&quot;\\|,.<>\/?]).{12,}"
                                    title="Must contain at least 12 characters, including uppercase, lowercase, number, and special character."
                                    autocomplete="new-password">
                                <button type="button" class="auth-pw-toggle" onclick="togglePw('regPassword','eyeIconReg')" aria-label="Toggle password">
                                    <svg id="eyeIconReg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="auth-label" for="referral_code">Referral Code <span class="auth-hint">(optional)</span></label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                                <input type="text" name="referral_code" id="referral_code" class="auth-input" placeholder="Enter code">
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label class="auth-label" for="facultyInput">Faculty <span class="auth-req">*</span> <span class="auth-hint">Type to search</span></label>
                        <input type="text" name="faculty" id="facultyInput" class="auth-input no-icon" list="facultyList" required autocomplete="off" placeholder="Start typing faculty name...">
                        <datalist id="facultyList">
                            <option value="Faculty of Applied Arts and Technology">
                            <option value="Faculty of Applied Sciences">
                            <option value="Faculty of Engineering">
                            <option value="Faculty of Business Studies">
                            <option value="Faculty of Built and Natural Environment">
                            <option value="Faculty of Health and Allied Sciences">
                            <option value="Faculty of Maritime and Nautical Studies">
                            <option value="Faculty of Media Technology and Liberal Studies">
                        </datalist>
                    </div>

                    <?php if ($mode === 'seller'): ?>
                    <div class="reg-seller-section">
                        <span class="reg-seller-label">Seller details</span>

                        <div class="reg-field-grid">
                            <div>
                                <label class="auth-label" for="departmentInput">Department <span class="auth-req">*</span> <span class="auth-hint">Type to search</span></label>
                                <input type="text" name="department" id="departmentInput" class="auth-input no-icon" list="departmentList" required autocomplete="off" placeholder="Select faculty first...">
                                <datalist id="departmentList"></datalist>
                            </div>
                            <div>
                                <label class="auth-label" for="regLevel">Level <span class="auth-req">*</span></label>
                                <select name="level" id="regLevel" class="auth-select" required>
                                    <option value="">Select level</option>
                                    <option value="100">Level 100</option>
                                    <option value="200">Level 200</option>
                                    <option value="300">Level 300</option>
                                    <option value="400">Level 400</option>
                                    <option value="Postgraduate">Postgraduate</option>
                                </select>
                            </div>
                        </div>

                        <div class="reg-field-grid" style="margin-top:0;">
                            <div>
                                <label class="auth-label" for="hallInput">Hall / Residence <span class="auth-hint">Type to search</span></label>
                                <input type="text" name="hall_residence" id="hallInput" class="auth-input no-icon" list="hallList" autocomplete="off" placeholder="Start typing residence...">
                                <datalist id="hallList">
                                    <option value="Ahanta Hall">
                                    <option value="Nzema-Mensah Hall">
                                    <option value="Prof Duncan Hall">
                                    <option value="University Hall">
                                    <option value="Akatakyi Campus Hostel">
                                    <option value="BU Campus Accommodation">
                                    <option value="Off Campus">
                                </datalist>
                            </div>
                            <div>
                                <label class="auth-label" for="regPhone">Phone <span class="auth-req">*</span></label>
                                <div class="auth-input-wrap">
                                    <span class="auth-input-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 9.81a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                    <input type="tel" name="phone" id="regPhone" class="auth-input" placeholder="024XXXXXXX" required pattern="(0[0-9]{9}|\+233[0-9]{9})">
                                </div>
                            </div>
                        </div>

                        <div class="auth-field" style="margin-bottom:0;">
                            <label class="auth-label" for="regPic">Profile Photo <span class="auth-hint">(optional)</span></label>
                            <div style="text-align: center; margin-bottom: 0.75rem;">
                                <img id="regProfilePicPreview" src="<?= getAssetUrl('assets/img/default-avatar.svg') ?>" 
                                     style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--auth-border); display: inline-block;" 
                                     alt="Profile Preview">
                            </div>
                            <input type="file" name="profile_pic" id="regPic" class="auth-input-file" accept="image/*" onchange="previewSelectedImage(this, 'regProfilePicPreview')">
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Step 1: Read Terms button (always visible and clickable) -->
                    <div style="margin:1.25rem 0 0.75rem;">
                        <button type="button" onclick="openTermsModalReg()" id="readTermsBtn" style="width:100%; padding:0.75rem 1rem; background:rgba(124,58,237,0.08); border:1.5px solid rgba(124,58,237,0.3); border-radius:10px; color:#7c3aed; font-weight:700; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:0.5rem; transition:all 0.2s;" onmouseover="this.style.background='rgba(124,58,237,0.14)'" onmouseout="this.style.background='rgba(124,58,237,0.08)'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Read Terms &amp; Conditions (required)
                        </button>
                        <p id="termsStatusMsg" style="font-size:0.78rem; color:var(--auth-muted); margin:0.4rem 0 0; text-align:center;">You must read to the end before you can agree.</p>
                    </div>

                    <!-- Hidden input — set to 1 by JS when terms are accepted; ensures it always submits -->
                    <input type="hidden" name="terms" id="termsHidden" value="">

                    <!-- Step 2: Agree checkbox — locked until terms are read (visual only) -->
                    <div class="reg-terms" id="termsRow" style="opacity:0.45; pointer-events:none;">
                        <input type="checkbox" id="termsCheckbox" disabled>
                        <label for="termsCheckbox" id="termsLabel" style="cursor:not-allowed;">
                            I have read and agree to the Terms &amp; Conditions.
                        </label>
                    </div>

                    <button type="submit" id="regSubmitBtn" class="auth-btn-primary">
                        Create <?= $mode === 'seller' ? 'seller' : 'buyer' ?> account
                    </button>
                </form>

                <div class="auth-card-footer">
                    <p>Already have an account? <a href="login.php" class="auth-link">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inline Terms Modal for Registration -->
<div id="regTermsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:1000000; align-items:center; justify-content:center; padding:20px;">
    <div style="width:100%; max-width:780px; height:85vh; border-radius:28px; display:flex; flex-direction:column; overflow:hidden; position:relative; box-shadow:0 30px 100px rgba(0,0,0,0.35); background:var(--card-bg, #fff); border:1px solid rgba(0,0,0,0.07);">
        <div style="padding:1.25rem 1.75rem; border-bottom:1px solid rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; font-weight:800;">Terms & Conditions</h3>
            <button onclick="closeRegTermsModal()" style="background:rgba(0,0,0,0.06); border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.4rem; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        <!-- Scroll progress bar -->
        <div style="width:100%; height:3px; background:rgba(124,58,237,0.1); position:relative; overflow:hidden; flex-shrink:0;">
            <div id="regTermsProgress" style="position:absolute; top:0; left:0; height:100%; width:0%; background:#7c3aed; transition:width 0.1s;"></div>
        </div>
        <div id="regTermsBody" onscroll="onRegTermsScroll(this)" style="flex:1; overflow-y:auto; padding:1.75rem 2rem; font-size:0.92rem; line-height:1.8; color:var(--text-main, #1a1a1a);">
            <?php require_once __DIR__ . '/includes/terms_content.php'; ?>
        </div>
        <div style="padding:1.25rem 1.75rem; border-top:1px solid rgba(0,0,0,0.08);">
            <button id="regTermsAcceptBtn" onclick="acceptRegTerms()" disabled style="width:100%; padding:0.9rem; background:#7c3aed; color:#fff; border:none; border-radius:12px; font-size:0.95rem; font-weight:700; cursor:not-allowed; opacity:0.45; transition:opacity 0.2s;">
                Scroll to the bottom to agree
            </button>
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

function previewSelectedImage(input, imgId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            if (img) {
                img.src = e.target.result;
                img.style.display = 'inline-block';
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function openTermsModalReg() {
    const m = document.getElementById('regTermsModal');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeRegTermsModal() {
    const m = document.getElementById('regTermsModal');
    m.style.display = 'none';
    document.body.style.overflow = '';
}
function onRegTermsScroll(el) {
    const pct = (el.scrollTop / (el.scrollHeight - el.clientHeight)) * 100;
    document.getElementById('regTermsProgress').style.width = Math.min(pct, 100) + '%';
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) {
        const btn = document.getElementById('regTermsAcceptBtn');
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.textContent = 'I have read & agree to the Terms';
    }
}
function acceptRegTerms() {
    closeRegTermsModal();
    const cb = document.getElementById('termsCheckbox');
    const hidden = document.getElementById('termsHidden');
    const row = document.getElementById('termsRow');
    const lbl = document.getElementById('termsLabel');
    const msg = document.getElementById('termsStatusMsg');
    const readBtn = document.getElementById('readTermsBtn');
    // Set the hidden input so it always submits regardless of browser/disabled quirks
    if (hidden) hidden.value = '1';
    // Unlock the visual checkbox too
    cb.disabled = false;
    cb.checked = true;
    row.style.opacity = '1';
    row.style.pointerEvents = 'auto';
    lbl.style.cursor = 'pointer';
    lbl.innerHTML = 'I have read and agree to the Terms &amp; Conditions.';
    // Update status
    if (msg) { msg.textContent = '✓ Terms read. You may now submit.'; msg.style.color = '#16a34a'; }
    // Update the read button to show it's done
    if (readBtn) {
        readBtn.style.background = 'rgba(22,163,74,0.08)';
        readBtn.style.border = '1.5px solid rgba(22,163,74,0.3)';
        readBtn.style.color = '#16a34a';
        readBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Terms read — click to review again';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

<script>
    const facultyDepartmentsMap = {
        "Faculty of Applied Arts and Technology": [
            "Ceramics Technology",
            "Fashion Design and Technology",
            "Graphic Design Technology",
            "Industrial Painting and Design",
            "Sculpture Technology",
            "Textiles Design and Technology"
        ],
        "Faculty of Applied Sciences": [
            "Computer Science",
            "Hospitality Management",
            "Mathematics, Statistics, and Actuarial Science",
            "Tourism Management",
            "Industrial and Health Science"
        ],
        "Faculty of Engineering": [
            "Civil Engineering",
            "Electrical/Electronic Engineering",
            "Mechanical Engineering (Automotive, Plant, Production, Refrigeration)",
            "Oil and Natural Gas Engineering",
            "Renewable Energy Engineering"
        ],
        "Faculty of Business Studies": [
            "Accounting and Finance",
            "Marketing and Strategy",
            "Procurement and Supply Chain Management",
            "Secretaryship and Management Studies",
            "Professional Studies"
        ],
        "Faculty of Built and Natural Environment": [
            "Building Technology",
            "Estate Management",
            "Interior Design and Upholstery Technology"
        ],
        "Faculty of Health and Allied Sciences": [
            "Medical Laboratory Sciences",
            "Pharmaceutical Sciences"
        ],
        "Faculty of Maritime and Nautical Studies": [
            "Marine Engineering",
            "Maritime Transport"
        ],
        "Faculty of Media Technology and Liberal Studies": [
            "Media and Communication Technology"
        ]
    };

    document.addEventListener('DOMContentLoaded', function () {
        const facultyInput = document.getElementById('facultyInput');
        const departmentInput = document.getElementById('departmentInput');
        const departmentList = document.getElementById('departmentList');

        if (facultyInput) {
            const updateDepartments = function () {
                if (!departmentInput || !departmentList) return;
                const selectedFaculty = facultyInput.value;
                departmentList.innerHTML = '';
                departmentInput.value = '';
                departmentInput.placeholder = 'Select faculty first...';
                if (facultyDepartmentsMap[selectedFaculty]) {
                    departmentInput.placeholder = 'Start typing department...';
                    facultyDepartmentsMap[selectedFaculty].forEach(function (dept) {
                        const opt = document.createElement('option');
                        opt.value = dept;
                        departmentList.appendChild(opt);
                    });
                }
            };
            facultyInput.addEventListener('change', updateDepartments);
            facultyInput.addEventListener('input', updateDepartments);

            // Trigger once on load in case a value was preserved by browser autofill
            updateDepartments();
        }
    });


<script>
    const facultyDepartmentsMap = {
        "Faculty of Applied Arts and Technology": [
            "Ceramics Technology",
            "Fashion Design and Technology",
            "Graphic Design Technology",
            "Industrial Painting and Design",
            "Sculpture Technology",
            "Textiles Design and Technology"
        ],
        "Faculty of Applied Sciences": [
            "Computer Science",
            "Hospitality Management",
            "Mathematics, Statistics, and Actuarial Science",
            "Tourism Management",
            "Industrial and Health Science"
        ],
        "Faculty of Engineering": [
            "Civil Engineering",
            "Electrical/Electronic Engineering",
            "Mechanical Engineering (Automotive, Plant, Production, Refrigeration)",
            "Oil and Natural Gas Engineering",
            "Renewable Energy Engineering"
        ],
        "Faculty of Business Studies": [
            "Accounting and Finance",
            "Marketing and Strategy",
            "Procurement and Supply Chain Management",
            "Secretaryship and Management Studies",
            "Professional Studies"
        ],
        "Faculty of Built and Natural Environment": [
            "Building Technology",
            "Estate Management",
            "Interior Design and Upholstery Technology"
        ],
        "Faculty of Health and Allied Sciences": [
            "Medical Laboratory Sciences",
            "Pharmaceutical Sciences"
        ],
        "Faculty of Maritime and Nautical Studies": [
            "Marine Engineering",
            "Maritime Transport"
        ],
        "Faculty of Media Technology and Liberal Studies": [
            "Media and Communication Technology"
        ]
    };

    document.addEventListener('DOMContentLoaded', function () {
        const facultyInput = document.getElementById('facultyInput');
        const departmentInput = document.getElementById('departmentInput');
        const departmentList = document.getElementById('departmentList');

        if (facultyInput) {
            const updateDepartments = function () {
                if (!departmentInput || !departmentList) return;
                const selectedFaculty = facultyInput.value;
                departmentList.innerHTML = '';
                departmentInput.value = '';
                departmentInput.placeholder = 'Select faculty first...';
                if (facultyDepartmentsMap[selectedFaculty]) {
                    departmentInput.placeholder = 'Start typing department...';
                    facultyDepartmentsMap[selectedFaculty].forEach(function (dept) {
                        const opt = document.createElement('option');
                        opt.value = dept;
                        departmentList.appendChild(opt);
                    });
                }
            };
            facultyInput.addEventListener('change', updateDepartments);
            facultyInput.addEventListener('input', updateDepartments);

            // Trigger once on load in case a value was preserved by browser autofill
            updateDepartments();
        }
    });
</script>
