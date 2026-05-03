<?php
require_once __DIR__ . '/db.php';

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net cdnjs.cloudflare.com js.stripe.com connect.facebook.net https://js.paystack.co https://accounts.google.com https://accounts.gstatic.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com cdnjs.cloudflare.com https://paystack.com; img-src 'self' data: https: blob:; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com; connect-src 'self' api.example.com cdn.jsdelivr.net https://api.paystack.co https://js.paystack.co https://accounts.google.com https://oauth2.googleapis.com; frame-src 'self' js.stripe.com https://js.paystack.co https://checkout.paystack.com https://standard.paystack.co https://paystack.com https://accounts.google.com; media-src 'self' data: https: blob:;");

if (isLoggedIn()) {
    $current_file = basename($_SERVER['PHP_SELF']);
    // Skip enforcement for certain pages to prevent redirect loops
    $exempt_pages = ['terms.php', 'logout.php', 'login.php', 'register.php', 'whatsapp_join.php', 'verify_email.php'];
    
    if (!in_array($current_file, $exempt_pages)) {
        $user_meta = getUser($pdo, $_SESSION['user_id']);
        $isAdminRole = ($user_meta['role'] ?? '') === 'admin';
        
        // Admins should only be forced to the admin panel if they hit the main dashboard
        // This allows them to browse products and use the public site normally.
        if ($isAdminRole && $current_file === 'dashboard.php' && empty($_GET['view']) && empty($_POST['dashboard_view'])) {
            redirect('admin/');
        }

        // 1. Mandatory Email Verification
        if ($user_meta && !$isAdminRole && empty($user_meta['email_verified_at'])) {
            redirect('verify_email.php?pending=1');
        }

        // 2. Non-admin must have joined WhatsApp channel first
        if ($user_meta && !$isAdminRole && !filter_var($user_meta['whatsapp_joined'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            redirect('whatsapp_join.php');
        }
        // 3. Then must accept terms
        if ($user_meta && !$user_meta['terms_accepted']) {
            redirect('terms.php');
        }
    }

    if (($_SESSION['role'] ?? '') !== 'admin') {
        try {
            $needs_review = getFirstUnreviewedProductId($pdo, (int) $_SESSION['user_id']);
            if ($needs_review) {
                if ($current_file !== 'product.php' && $current_file !== 'logout.php' && $current_file !== 'terms.php' && empty($_GET['action'])) {
                    header("Location: product.php?id=$needs_review&review_required=1#review");
                    exit;
                }
            }
        } catch(PDOException $e) {}
    }
}

if (file_exists(__DIR__ . '/../.maintenance') && !isAdmin()) {
    if (strpos($_SERVER['REQUEST_URI'], '/login.php') === false && strpos($_SERVER['REQUEST_URI'], '/api/') === false) {
        $maintenanceLoginUrl = htmlspecialchars($baseUrl . 'login.php', ENT_QUOTES, 'UTF-8');
        die("<!DOCTYPE html><html><head><title>Under Maintenance</title><style>body{background:#0a0f1e;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;}</style></head><body><div><h1>We're currently down for maintenance.</h1><p>Our engineers are upgrading the campus marketplace. We'll be back shortly!</p><br><a href='{$maintenanceLoginUrl}' style='color:#6366f1;text-decoration:none;'>Admin Login &rarr;</a></div></body></html>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CampusMarketplace — Buy and sell safely within your university community.">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'CampusMarketplace', ENT_QUOTES, 'UTF-8') ?></title>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
        }
        window.MARKETPLACE_BASE_URL = '<?= $baseUrl ?>';
    </script>
    <!-- Fonts loaded via CSS @import in style.css -->
    <link rel="stylesheet" href="<?= getAssetUrl('assets/css/style.css?v=1.9') ?>">

    <!-- LOAD REACT ASSETS EVERYWHERE -->
    <link rel="stylesheet" href="<?= getAssetUrl('assets/dist/app.css?v=1.9') ?>">
    <script type="module" src="<?= getAssetUrl('assets/dist/app.js?v=1.9') ?>" onerror="console.error('Failed to load module')"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>


</head>
<body class="<?= isLoggedIn() ? 'is-logged-in' : '' ?>">
    <nav style="position:sticky; top:0; z-index:999999; background:var(--card-bg, #ffffff); border-bottom:1px solid var(--border, #e5e7eb); transition: background 0.3s, border-color 0.3s; padding: 0 5%;">
        <div class="nav-shell" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; height:58px; max-width:1400px; margin:0 auto; width:100%;">
            <!-- Brand -->
            <a href="<?= $baseUrl ?>" class="nav-brand-link" aria-label="CampusMarketplace home" title="CampusMarketplace" style="color:var(--text-main); text-decoration:none; display:flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:12px; flex-shrink:0; transition:background 0.2s, opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </a>

            <!-- Center Nav Links -->
            <div class="nav-links" id="mobileNavLinks" style="display:flex; align-items:center; justify-content:center; gap:0.5rem; flex:1; min-width:0;">
                <a href="<?= getSpaUrl() ?>" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Explore</a>
                <a href="<?= $baseUrl ?>about.php" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">About</a>

                <!-- Categories Dropdown -->
                <div class="cat-dropdown" style="position:relative; display:inline-block; flex-shrink:1;">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('catMenu').classList.toggle('cat-open');" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; cursor:pointer; display:flex; align-items:center; gap:4px; white-space:nowrap;">
                        Categories
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </a>
                    <div id="catMenu" class="cat-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 8px); left:50%; transform:translateX(-50%); z-index:999; width:220px; background:var(--card-bg); border:1px solid var(--border); border-radius:16px; box-shadow:0 8px 20px #d1d5db; overflow:hidden;">
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Computer & Accessories']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            Computer &amp; Accessories
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Phone & Accessories']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            Phone &amp; Accessories
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Electrical Appliances']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            Electrical Appliances
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Fashion']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.13z"/></svg>
                            Fashion
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Food & Groceries']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                            Food &amp; Groceries
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Education & Books']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            Education &amp; Books
                        </a>
                        <a href="<?= htmlspecialchars(getSpaUrl('/', ['category' => 'Hostels for Rent']), ENT_QUOTES, 'UTF-8') ?>" class="cat-item" style="font-size:0.8rem; padding:0.6rem 0.9rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Hostels for Rent
                        </a>
                    </div>
                </div>

                <?php if(isLoggedIn()): ?>
                    <?php
                        $unread = getUnreadCount($pdo, $_SESSION['user_id']);
                        $unreadNotifications = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
                        $isAdminMainAppView = isAdmin();
                        $accountHomeUrl = $baseUrl . 'dashboard.php';
                        $accountHomeLabel = $isAdminMainAppView ? 'Seller Dashboard' : 'Dashboard';
                        $alertsUrl = $accountHomeUrl;
                        $messagesUrl = $baseUrl . 'chat.php';
                    ?>
                    <a href="<?= $baseUrl ?>leaderboard.php" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Rank</a>
                    <a href="<?= $accountHomeUrl ?>" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'"><?= htmlspecialchars($accountHomeLabel, ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= $baseUrl ?>security.php" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Security</a>
                    <a href="<?= $alertsUrl ?>" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; position:relative; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">
                        Alerts
                        <span class="notif-badge notif-unread-badge" style="<?= $unreadNotifications > 0 ? 'display:flex;' : 'display:none;' ?>"><?= $unreadNotifications ?></span>
                    </a>
                    <a href="<?= $messagesUrl ?>" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; position:relative; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">
                        Messages
                        <span class="notif-badge msg-unread-badge" style="<?= $unread > 0 ? 'display:flex;' : 'display:none;' ?>"><?= $unread ?></span>
                    </a>
                    <?php if($isAdminMainAppView): ?>
                        <a href="<?= $baseUrl ?>admin/" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.6rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Admin Panel</a>
                    <?php endif; ?>
                    <?php if(isSeller()): ?>
                        <a href="<?= $baseUrl ?>add_product.php" class="react-liquid-btn" data-label="+ Sell" style="display:inline-flex; align-items:center; justify-content:center; min-height:38px; flex-shrink:0; transform:scale(0.8);"></a>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>logout.php" class="nav-pill-link" style="color:var(--text-muted); font-weight:600; font-size:0.82rem; padding:0.4rem 0.75rem; border-radius:999px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:1;" onmouseover="this.style.color='#ff3b30'; this.style.background='#ffe5e5'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Sign Out</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>login.php" class="nav-pill-link" style="color:var(--text-muted); font-weight:500; font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:999px; transition:all 0.2s; text-decoration:none;" onmouseover="this.style.color='var(--text-main)'; this.style.background='#f3f4f6'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Sign In</a>
                    <a href="<?= $baseUrl ?>register.php" style="background:#7c3aed; color:#fff; font-weight:600; font-size:0.85rem; padding:0.5rem 1.1rem; min-height:48px; border-radius:980px; text-decoration:none; transition:all 0.2s; display:inline-flex; align-items:center; justify-content:center;" onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">Sign Up</a>
                <?php endif; ?>
            </div>

            <!-- Right-side icons -->
            <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                <div id="react-theme-toggle"></div>
                <a href="javascript:void(0)" onclick="if(typeof openSideCart === 'function') openSideCart(); return false;" style="position:relative; color:var(--text-main); text-decoration:none; padding:6px; border-radius:8px; transition:all 0.2s; display:flex; align-items:center; justify-content:center;" title="Cart" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="cart-count-badge" style="display:none; position:absolute; top:-5px; right:-6px; background:#ff3b30; color:#fff; font-size:0.6rem; font-weight:700; width:17px; height:17px; border-radius:50%; align-items:center; justify-content:center; box-shadow:0 2px 8px rgb(255, 59, 48);">0</span>
                </a>
                <button class="mobile-toggle" id="mobileNavToggle" onclick="toggleMobileNav()" style="color:var(--text-main); cursor:pointer; background:none; border:none; padding:6px; border-radius:8px;" aria-label="Toggle menu">
                    <svg id="menuIconOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    <svg id="menuIconClose" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </nav>
    <script>
    function toggleMobileNav() {
        var navLinks = document.getElementById('mobileNavLinks');
        var navBar = document.querySelector('nav');
        var openIcon = document.getElementById('menuIconOpen');
        var closeIcon = document.getElementById('menuIconClose');
        var isOpen = navLinks.classList.contains('open');
        if (isOpen) {
            navLinks.classList.remove('open');
            openIcon.style.display = '';
            closeIcon.style.display = 'none';
            document.body.style.overflow = '';
            document.body.classList.remove('nav-open');
            navBar.style.background = '';
        } else {
            navLinks.classList.add('open');
            openIcon.style.display = 'none';
            closeIcon.style.display = '';
            document.body.style.overflow = 'hidden';
            document.body.classList.add('nav-open');
            navBar.style.background = document.documentElement.classList.contains('dark-mode') ? '#1c1c1e' : '#ffffff';
        }
    }

    // Close mobile nav when clicking a link (unless it's a dropdown toggle)
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('#mobileNavLinks a');
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') return;
                var nav = document.getElementById('mobileNavLinks');
                if (nav && nav.classList.contains('open')) {
                    toggleMobileNav();
                }
            });
        });
    });

    // Close category dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cat-dropdown')) {
            var m = document.getElementById('catMenu');
            if (m) m.classList.remove('cat-open');
        }
    });
    </script>
    <div class="container">
