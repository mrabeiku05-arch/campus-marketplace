<?php
$activeSellerTier = (string)($user['seller_tier'] ?: 'basic');
$tierMap = getAccountTiers($pdo);
$inventoryLimit = (int)($tierMap[$activeSellerTier]['product_limit'] ?? max(1, count($products)));
$inventoryUsage = $inventoryLimit > 0 ? min(100, (int)round((count($products) / $inventoryLimit) * 100)) : 0;
$isTierExpired = ($activeSellerTier === 'basic' && !empty($user['tier_expires_at']) && strtotime((string)$user['tier_expires_at']) < time());
$isLifetimeTier = ($activeSellerTier !== 'basic' && empty($user['tier_expires_at']));
$canCreateProduct = canAddProduct($pdo, $user['id']);
$withdrawalTotal = 0.0;
// $activityFeed is now populated by UNION ALL query in dashboard.php
// Inventory counts via DB (accurate regardless of active filter)
$_uid = $user['id'];
$stmtAll = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND status!='deleted'");
$stmtAll->execute([$_uid]);
$allCount = (int)$stmtAll->fetchColumn();

$inventoryStats = [
    'all' => $allCount ?: count($products),
    'approved' => 0,
    'pending' => 0,
    'paused' => 0,
    'sold' => 0,
    'low' => 0,
];
try {
    $cntStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM products WHERE user_id=? AND status!='deleted' GROUP BY status");
    $cntStmt->execute([$_uid]);
    while ($row = $cntStmt->fetch(PDO::FETCH_ASSOC)) {
        $s = strtolower($row['status']);
        if (isset($inventoryStats[$s])) $inventoryStats[$s] = (int)$row['cnt'];
    }
    $lowStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND status='approved' AND quantity > 0 AND quantity <= 5");
    $lowStmt->execute([$_uid]);
    $inventoryStats['low'] = (int)$lowStmt->fetchColumn();
    $inventoryStats['all'] = $inventoryStats['approved'] + $inventoryStats['pending'] + $inventoryStats['paused'] + $inventoryStats['sold'];
} catch (PDOException $e) {
    // Fallback: count from loaded $products array
    foreach ($products as $product) {
        $status = strtolower((string)($product['status'] ?? ''));
        if (isset($inventoryStats[$status])) $inventoryStats[$status]++;
        if ((int)($product['quantity'] ?? 0) > 0 && (int)($product['quantity'] ?? 0) <= 5 && $status === 'approved') $inventoryStats['low']++;
    }
}

$sellerQuickContacts = [];

foreach ($transactions as $tx) {
    if (($tx['type'] ?? '') === 'withdrawal') {
        $withdrawalTotal += (float)($tx['amount'] ?? 0);
    }
}

$pendingOrderCount = 0;
foreach ($seller_orders as $order) {
    $orderStatus = strtolower((string)($order['status'] ?? 'ordered'));
    if ($orderStatus === 'ordered') {
        $pendingOrderCount++;
    }


    $buyerId = (int)($order['buyer_id'] ?? 0);
    if ($buyerId > 0 && !isset($sellerQuickContacts[$buyerId])) {
        $sellerQuickContacts[$buyerId] = $order;
    }
}

// Activity feed is now sourced from DB UNION ALL query (dashboard.php)
// $activityFeed already contains: type, title, subtitle, created_at
$sellerQuickContacts = array_slice(array_values($sellerQuickContacts), 0, 5);

$lastRevenuePoint = (float)(end($chart_revenue) ?: 0);
$firstRevenuePoint = (float)(reset($chart_revenue) ?: 0);
$trendDelta = $lastRevenuePoint - $firstRevenuePoint;
$trendDirection = $trendDelta > 0 ? 'Up' : ($trendDelta < 0 ? 'Down' : 'Flat');
$trendLabel = $trendDirection . ' from the start of the period';

// Smart timestamp formatter for activity feed
$formatActivityTime = function(string $dateStr): string {
    $ts = strtotime($dateStr);
    if (!$ts) return '';
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    if ($ts >= $today) return date('H:i', $ts);
    if ($ts >= $yesterday) return 'Yesterday';
    return date('d M', $ts);
};

$tiers = dashboardSortTiers(array_values(getAccountTiers($pdo)));
$tierPricingConfig = [];
foreach ($tiers as &$tier) {
    $tier['visual'] = array_replace([
        'accent' => '#7c3aed',
        'background' => 'linear-gradient(180deg, rgba(17,24,39,1), rgba(17,24,39,0.92))',
        'border' => 'rgba(124,58,237,0.18)',
        'button_text' => '#ffffff',
        'text' => '#ffffff',
        'label' => 'rgba(255,255,255,0.72)',
        'soft' => 'rgba(124,58,237,0.16)',
        'shadow' => 'rgba(124,58,237,0.18)',
    ], dashboardTierVisual((string)($tier['tier_name'] ?? 'basic')));
    $tier['duration_label'] = (string)dashboardTierDurationLabel($tier['duration'] ?? '');
    $tier['feature_list'] = array_values(array_filter(dashboardTierFeatures($tier), static fn($feature) => is_string($feature) && trim($feature) !== ''));
    $tierPricingConfig[(string)($tier['tier_name'] ?? 'basic')] = [
        'price' => (float)($tier['price'] ?? 0),
        'duration_label' => (string)$tier['duration_label'],
    ];
}
unset($tier);

$unreadMsgCount = getUnreadCount($pdo, $_SESSION['user_id']);
$unreadNotifCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);

$activeTab = $_GET['tab'] ?? 'overview';
$tabTitles = [
    'overview' => 'Overview',
    'inventory' => 'Inventory',
    'orders' => 'Orders',
    'analytics' => 'Analytics',
    'wallet' => 'Wallet',
    'messages' => 'Messages',
    'settings' => 'Settings'
];
$headerTitle = $tabTitles[$activeTab] ?? 'Dashboard';

// Force body + html classes for layout reset (overrides style.css body{display:flex;flex-direction:column})
echo '<style>html, body.dashboard-v2 { margin: 0 !important; padding: 0 !important; width: 100% !important; overflow-x: hidden !important; display: block !important; flex-direction: unset !important; }</style>';
echo '<script>document.body.classList.add("dashboard-v2", "dashboard-page"); document.documentElement.classList.add("dashboard-v2-html");</script>';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
echo '<link rel="stylesheet" href="' . getAssetUrl('assets/css/dashboard-v2.css') . '">';
?>

<!-- MOBILE TOGGLE & OVERLAY -->
<div class="dash-sidebar-overlay sidebar-overlay" id="dashSidebarOverlay"></div>

<!-- SHELL -->
<div class="dash-shell">

    <!-- LEFT SIDEBAR -->
    <aside class="dash-sidebar sidebar" id="dashSidebar">
        <div class="dash-sidebar-brand">
            <div class="dash-brand-icon">
                <i class="fa-solid fa-store" style="color:#fff; font-size:1.1rem;"></i>
            </div>
            <div class="dash-brand-text">
                <strong>CampusMarketplace</strong>
                <span>Seller</span>
            </div>
        </div>

        <div class="dash-sidebar-profile">
            <div class="dash-sidebar-avatar">
                <?php if (!empty($user['profile_pic'])): ?>
                    <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>" alt="<?= htmlspecialchars($user['username']) ?>">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr((string)$user['username'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="dash-profile-name"><?= htmlspecialchars($user['username']) ?></div>
                <?php if ($user['verified']): ?>
                    <div class="dash-verified-badge"><i class="fa-solid fa-check"></i> Verified</div>
                <?php endif; ?>
                <div class="dash-plan-label"><?= htmlspecialchars(ucfirst($activeSellerTier)) ?> PLAN</div>
            </div>
        </div>

        <nav class="dash-nav">
            <a href="dashboard.php?tab=overview" class="<?= $activeTab === 'overview' ? 'active' : '' ?>">
                <i class="fa-solid fa-house dash-nav-icon"></i> Overview
            </a>
            <a href="dashboard.php?tab=inventory" class="<?= $activeTab === 'inventory' ? 'active' : '' ?>">
                <i class="fa-solid fa-box dash-nav-icon"></i> Inventory
            </a>
            <a href="dashboard.php?tab=orders" class="<?= $activeTab === 'orders' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping dash-nav-icon"></i> Orders
                <?php if ($pendingOrderCount > 0): ?>
                    <span class="dash-nav-badge orange"><?= $pendingOrderCount ?></span>
                <?php endif; ?>
            </a>
            <a href="dashboard.php?tab=analytics" class="<?= $activeTab === 'analytics' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-simple dash-nav-icon"></i> Analytics
            </a>
            <a href="dashboard.php?tab=wallet" class="<?= $activeTab === 'wallet' ? 'active' : '' ?>">
                <i class="fa-solid fa-wallet dash-nav-icon"></i> Wallet
            </a>
            <a href="messages.php" class="<?= $activeTab === 'messages' ? 'active' : '' ?>">
                <i class="fa-solid fa-envelope dash-nav-icon"></i> Messages
                <?php if ($unreadMsgCount > 0): ?>
                    <span class="dash-nav-badge purple"><?= $unreadMsgCount ?></span>
                <?php endif; ?>
            </a>
            <a href="dashboard.php?tab=settings" class="<?= $activeTab === 'settings' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear dash-nav-icon"></i> Settings
            </a>
            <a href="logout.php">
                <i class="fa-solid fa-arrow-right-from-bracket dash-nav-icon"></i> Logout
            </a>
        </nav>

        <div class="dash-sidebar-wallet">
            <div class="dash-sidebar-wallet-label">Wallet Balance</div>
            <div class="dash-sidebar-wallet-balance">GHS <?= number_format((float)$user['balance'], 2) ?></div>
            <a href="dashboard.php?tab=wallet" class="dash-withdraw-btn">Withdraw</a>
        </div>
        <div class="sidebar-footer" style="padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto;">
          <a href="/" style="display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.7); text-decoration:none; font-size:0.85rem; padding: 8px 12px; border-radius:8px;">
            <i class="fas fa-arrow-left"></i> Back to Site
          </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="dash-main main-content">
        <!-- TOP HEADER -->
        <header class="dash-topbar" style="
          position: sticky; top: 0; z-index: 100;
          background: #fff; border-bottom: 1px solid #e5e7eb;
          display: flex; align-items: center;
          padding: 0 16px; height: 60px; gap: 12px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        ">
          <!-- Hamburger -->
          <button id="sidebarToggle" class="sidebar-toggle hamburger-btn" onclick="toggleSidebar()" style="background:none;border:none;cursor:pointer;padding:4px;flex-shrink:0;">
            <i class="fas fa-bars" style="font-size:1.2rem;color:#374151;"></i>
          </button>

          <!-- Title block — takes all remaining space -->
          <div style="flex:1; min-width:0;">
            <h1 style="
              margin:0; font-size:1.1rem; font-weight:700;
              color:#111827; white-space:nowrap;
              overflow:hidden; text-overflow:ellipsis;
            "><?= htmlspecialchars($headerTitle ?? 'Dashboard') ?></h1>
          </div>

          <!-- Right actions — never shrink -->
          <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
            <div style="display:inline-flex; align-items:center; gap:6px;">
              <a href="add_product.php" id="topAddProductBtn" style="
                display:inline-flex; align-items:center; gap:6px;
                background:#6d28d9; color:#fff; font-size:0.8rem;
                font-weight:600; padding:7px 12px; border-radius:8px;
                text-decoration:none; white-space:nowrap; transition: background 0.2s, opacity 0.2s;
              "><i class="fa-solid fa-plus"></i> Add Product</a>
              <span id="topSlotCounter" class="dash-slot-counter" title="Loading slots...">
                <i class="fa-solid fa-layer-group" style="font-size:0.65rem;"></i>
                <span id="topSlotText">...</span>
              </span>
            </div>

            <!-- Notification Bell + Dropdown -->
            <div class="dash-notif-wrapper" id="notifWrapper">
              <button type="button" class="dash-notif-bell" id="notifBellBtn" onclick="toggleNotifDropdown()" aria-label="Notifications">
                <i class="fas fa-bell" style="color:#6b7280;font-size:1rem;transition:color 0.2s;"></i>
                <span class="dash-notif-count" id="notifBadge" style="<?= $unreadNotifCount > 0 ? '' : 'display:none;' ?>"><?= $unreadNotifCount ?></span>
              </button>
              <div class="dash-notif-dropdown" id="notifDropdown">
                <div class="dash-notif-dropdown-header">
                  <span class="dash-notif-dropdown-title">Notifications</span>
                  <button type="button" id="markAllReadBtn" onclick="markAllNotifRead()" class="dash-notif-mark-read" title="Mark all as read">
                    <i class="fa-solid fa-check-double"></i> Mark all read
                  </button>
                </div>
                <div class="dash-notif-list" id="notifList">
                  <div class="dash-notif-empty" id="notifEmpty">
                    <i class="fa-regular fa-bell-slash" style="font-size:1.5rem;margin-bottom:8px;opacity:0.4;"></i>
                    <div>No notifications yet</div>
                  </div>
                </div>
                <a href="dashboard.php?tab=overview" class="dash-notif-dropdown-footer">
                  View all activity <i class="fa-solid fa-arrow-right" style="font-size:0.65rem;"></i>
                </a>
              </div>
            </div>

            <div style="position:relative;" onclick="document.getElementById('headerProfileDropdown').classList.toggle('open')">
              <?php if (!empty($user['profile_pic'])): ?>
              <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>"
                style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;cursor:pointer;"
                onerror="this.src='<?= getAssetUrl('assets/img/default-avatar_generated.png') ?>'">
              <?php else: ?>
              <div style="width:36px; height:36px; border-radius:50%; border:2px solid #e5e7eb; background:var(--dash-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; cursor:pointer;">
                  <?= htmlspecialchars(strtoupper(substr((string)$user['username'], 0, 1))) ?>
              </div>
              <?php endif; ?>
              
              <!-- Dropdown -->
              <div id="headerProfileDropdown" class="dash-profile-dropdown" style="display:none; position:absolute; top:calc(100% + 12px); right:0; width:200px; background:var(--dash-card); border:1px solid var(--dash-border); border-radius:12px; box-shadow:var(--dash-shadow-lg); padding:8px; z-index:100;">
                  <a href="edit_profile.php" class="dash-dropdown-item" style="display:flex; align-items:center; gap:10px; padding:10px 12px; color:var(--dash-text); text-decoration:none; font-size:0.85rem; font-weight:600; border-radius:8px; transition:background 0.2s;"><i class="fa-regular fa-user" style="width:16px;"></i> Profile</a>
                  <a href="dashboard.php?tab=settings" class="dash-dropdown-item" style="display:flex; align-items:center; gap:10px; padding:10px 12px; color:var(--dash-text); text-decoration:none; font-size:0.85rem; font-weight:600; border-radius:8px; transition:background 0.2s;"><i class="fa-solid fa-gear" style="width:16px;"></i> Settings</a>
                  <div style="height:1px; background:var(--dash-border); margin:4px 0;"></div>
                  <a href="logout.php" class="dash-dropdown-item" style="display:flex; align-items:center; gap:10px; padding:10px 12px; color:var(--dash-red); text-decoration:none; font-size:0.85rem; font-weight:600; border-radius:8px; transition:background 0.2s;"><i class="fa-solid fa-arrow-right-from-bracket" style="width:16px;"></i> Logout</a>
              </div>
            </div>
          </div>
        </header>

        <div class="dash-content">
            <?php
            // Consume flash message from redirect (e.g. delete)
            if (!empty($_SESSION['flash'])) {
                $msg = $_SESSION['flash'];
                unset($_SESSION['flash']);
            }
            ?>
            <?php if ($msg): ?>
                <div class="dash-card" style="margin-bottom:24px; padding:16px; background:rgba(0,201,167,0.1); border-color:var(--dash-green); display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:600; color:var(--dash-green);"><i class="fa-solid fa-circle-check" style="margin-right:8px;"></i><?= htmlspecialchars($msg) ?></div>
                </div>
            <?php endif; ?>

            <!-- OVERVIEW TAB -->
            <?php if ($activeTab === 'overview'): ?>
            <div id="tab-overview" class="dash-tab-panel active">
                <?php
                $renderChange = function($val) {
                    $color = $val > 0 ? 'var(--dash-green)' : ($val < 0 ? 'var(--dash-red)' : 'var(--dash-text-muted)');
                    $arrow = $val > 0 ? '↑ ' : ($val < 0 ? '↓ ' : '');
                    return '<div class="dash-stat-change" style="color:'.$color.'; font-weight:600; font-size:0.75rem;">'.$arrow.abs($val).'% vs previous 7d</div>';
                };
                ?>
                <div class="dash-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon purple"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <div class="dash-stat-label">Total Revenue</div>
                            <div class="dash-stat-value">GHS <?= number_format($sellerRevenue, 2) ?></div>
                            <?= $renderChange($revenue_change ?? 0) ?>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green"><i class="fa-solid fa-bag-shopping"></i></div>
                        <div>
                            <div class="dash-stat-label">Active Listings</div>
                            <div class="dash-stat-value"><?= (int)$totalApproved ?></div>
                            <?= $renderChange($listings_change ?? 0) ?>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fa-solid fa-cart-arrow-down"></i></div>
                        <div>
                            <div class="dash-stat-label">Items Sold</div>
                            <div class="dash-stat-value"><?= (int)$sellerTotalSold ?></div>
                            <?= $renderChange($sold_change ?? 0) ?>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fa-regular fa-eye"></i></div>
                        <div>
                            <div class="dash-stat-label">Total Views</div>
                            <div class="dash-stat-value"><?= (int)$viewsTotal ?></div>
                            <?= $renderChange($views_change ?? 0) ?>
                        </div>
                    </div>
                </div>

                <div class="dash-row-2">
                    <!-- Chart -->
                    <div class="dash-card">
                        <div class="dash-chart-header">
                            <h2 class="dash-card-title">Weekly Performance</h2>
                            <select class="dash-period-select" id="chartPeriodSelect" onchange="window.location.href='dashboard.php?tab=overview&range=' + this.value;">
                                <option value="7" <?= ($chart_range ?? 7) == 7 ? 'selected' : '' ?>>7 Days</option>
                                <option value="30" <?= ($chart_range ?? 7) == 30 ? 'selected' : '' ?>>30 Days</option>
                                <option value="90" <?= ($chart_range ?? 7) == 90 ? 'selected' : '' ?>>90 Days</option>
                            </select>
                        </div>
                        <div style="height:260px; position:relative;">
                            <canvas id="overviewChartCanvas"></canvas>
                        </div>
                        <div class="dash-chart-stats">
                            <div>
                                <div class="dash-chart-stat-label">Total Revenue</div>
                                <div class="dash-chart-stat-val">GHS <?= number_format(array_sum($chart_revenue ?? []), 2) ?></div>
                            </div>
                            <div>
                                <div class="dash-chart-stat-label">Views</div>
                                <div class="dash-chart-stat-val"><?= (int)array_sum($chart_views ?? []) ?></div>
                            </div>
                            <div>
                                <div class="dash-chart-stat-label">Orders</div>
                                <div class="dash-chart-stat-val"><?= count($seller_orders) ?></div>
                            </div>
                            <div>
                                <div class="dash-chart-stat-label">Conversion Rate</div>
                                <div class="dash-chart-stat-val"><?= $viewsTotal > 0 ? number_format(($sellerTotalSold / $viewsTotal) * 100, 2) : '0.00' ?>%</div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Recent Activity</h2>
                            <a href="#" onclick="switchTab('analytics'); return false;" class="dash-card-link">View all</a>
                        </div>
                        <?php if (!empty($activityFeed)): ?>
                            <div style="display:flex; flex-direction:column;">
                                <?php foreach ($activityFeed as $event): ?>
                                    <?php 
                                        $evType = $event['type'] ?? 'transaction';
                                        if ($evType === 'order' && stripos($event['subtitle'] ?? '', 'completed') !== false) {
                                            $iconClass = 'green'; $iconHtml = '<i class="fa-solid fa-check"></i>';
                                        } elseif ($evType === 'product') {
                                            $iconClass = 'amber'; $iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
                                        } elseif ($evType === 'message') {
                                            $iconClass = 'purple'; $iconHtml = '<i class="fa-regular fa-comment-dots"></i>';
                                        } elseif ($evType === 'order') {
                                            $iconClass = 'blue'; $iconHtml = '<i class="fa-solid fa-bag-shopping"></i>';
                                        } else {
                                            $iconClass = 'purple'; $iconHtml = '<i class="fa-solid fa-bolt"></i>';
                                        }
                                    ?>
                                    <div class="dash-activity-item">
                                        <div class="dash-activity-icon <?= $iconClass ?>"><?= $iconHtml ?></div>
                                        <div class="dash-activity-info">
                                            <div class="dash-activity-title"><?= htmlspecialchars($event['title'] ?? '') ?></div>
                                            <div class="dash-activity-meta"><?= htmlspecialchars(mb_strimwidth($event['subtitle'] ?? '', 0, 60, '...')) ?></div>
                                        </div>
                                        <div class="dash-activity-time"><?= $formatActivityTime($event['created_at'] ?? '') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">
                                <div class="dash-empty-title">No activity yet</div>
                                <div>Your store's events will appear here.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-row-2">
                    <!-- Inventory Summary -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Inventory Summary</h2>
                            <a href="#" onclick="switchTab('inventory'); return false;" class="dash-card-link">View all products</a>
                        </div>
                        
                        <div class="dash-inventory-stats">
                            <div class="dash-inv-stat">
                                <i class="fa-solid fa-box text-purple" style="color:var(--dash-primary)"></i>
                                <span><?= count($products) ?> Total Products</span>
                            </div>
                            <div class="dash-inv-stat">
                                <i class="fa-solid fa-layer-group text-blue" style="color:var(--dash-blue)"></i>
                                <span><?= $inventoryLimit ?> Inventory Slots</span>
                            </div>
                            <?php if ($inventoryStats['low'] > 0): ?>
                            <div class="dash-inv-stat">
                                <i class="fa-solid fa-triangle-exclamation text-amber" style="color:var(--dash-amber)"></i>
                                <span style="color:var(--dash-amber)"><?= $inventoryStats['low'] ?> Low Stock Items</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (count($products) > 0): ?>
                            <div class="dash-product-grid">
                                <?php foreach (array_slice($products, 0, 4) as $p): ?>
                                    <div class="dash-product-thumb-card">
                                        <?php if (!empty($p['main_image'])): ?>
                                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" alt="">
                                        <?php else: ?>
                                            <div style="width:100%; height:120px; background:#e5e7eb; display:flex; align-items:center; justify-content:center; color:#9ca3af;"><i class="fa-regular fa-image" style="font-size:2rem;"></i></div>
                                        <?php endif; ?>
                                        <div class="dash-product-thumb-info">
                                            <div class="dash-product-thumb-name"><?= htmlspecialchars($p['title']) ?></div>
                                            <div class="dash-product-thumb-price">GHS <?= number_format($p['price'], 2) ?></div>
                                            <?php 
                                            $qty = (int)$p['quantity'];
                                            if ($qty <= 0) echo '<div class="dash-stock-pill sold-out">Sold Out</div>';
                                            elseif ($qty <= 5) echo '<div class="dash-stock-pill low-stock">Low Stock</div>';
                                            else echo '<div class="dash-stock-pill in-stock">In Stock</div>';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">
                                <div class="dash-empty-title">Your inventory is empty</div>
                                <div>Add your first product to start selling.</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Orders Overview -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Orders Overview</h2>
                            <a href="#" onclick="switchTab('orders'); return false;" class="dash-card-link">View all orders</a>
                        </div>

                        <div class="dash-order-tabs">
                            <button class="dash-order-tab active" data-filter="all">All</button>
                            <button class="dash-order-tab" data-filter="pending">Pending</button>
                            <button class="dash-order-tab" data-filter="processing">Processing</button>
                            <button class="dash-order-tab" data-filter="completed">Completed</button>
                        </div>

                        <?php if (count($seller_orders) > 0): ?>
                            <div id="overviewOrderList">
                                <?php foreach (array_slice($seller_orders, 0, 3) as $ord): ?>
                                    <?php
                                    $status = strtolower($ord['status']);
                                    $statusClass = match($status) {
                                        'completed' => 'completed',
                                        'delivered' => 'shipped',
                                        'seller_seen' => 'processing',
                                        default => 'pending'
                                    };
                                    $statusText = match($status) {
                                        'delivered' => 'Shipped',
                                        'seller_seen' => 'Processing',
                                        default => ucfirst($status)
                                    };
                                    ?>
                                    <div class="dash-order-item" data-status="<?= $statusClass ?>">
                                        <div>
                                            <div class="dash-order-id">#ORDER-<?= $ord['id'] ?></div>
                                            <div class="dash-order-product"><?= htmlspecialchars($ord['product_title']) ?></div>
                                            <div class="dash-order-date"><?= date('M d, Y • h:i A', strtotime($ord['created_at'])) ?></div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="dash-status-pill <?= $statusClass ?>" style="margin-bottom:6px; display:inline-block;"><?= $statusText ?></div>
                                            <div class="dash-order-price">GHS <?= number_format($ord['product_price'], 2) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--dash-text-muted); margin-top:12px;">Showing latest 3 orders</div>
                        <?php else: ?>
                            <div class="dash-empty" style="padding:20px;">
                                <div>No orders yet.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- INVENTORY TAB -->
            <?php if ($activeTab === 'inventory'): ?>
            <div id="tab-inventory" class="dash-tab-panel active">
                <div class="dash-card">
                    <div class="dash-card-head" style="margin-bottom:16px;">
                        <div>
                            <h2 class="dash-card-title" style="font-size:1.2rem;">Product Command Center</h2>
                            <p style="font-size:0.85rem; color:var(--dash-text-muted); margin:4px 0 0;">Search, filter, and manage stock without leaving the workspace.</p>
                        </div>
                        <div style="display:inline-flex; align-items:center; gap:8px;">
                            <a href="add_product.php" class="dash-add-btn" id="invAddProductBtn" style="padding:8px 16px; font-size:0.8rem;"><i class="fa-solid fa-plus"></i> Add Product</a>
                            <span class="dash-slot-counter" id="invSlotCounter" title="Loading slots...">
                                <i class="fa-solid fa-layer-group" style="font-size:0.65rem;"></i>
                                <span id="invSlotText">...</span>
                            </span>
                        </div>
                    </div>

                    <form method="GET" action="dashboard.php" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:16px; flex-wrap:wrap;">
                        <input type="hidden" name="tab" value="inventory">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($invFilter) ?>">
                        <input type="text" name="search" class="dash-search" id="invSearch" placeholder="Search products..." value="<?= htmlspecialchars($invSearch ?? '') ?>" style="flex:1; min-width:200px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <button type="submit" class="dash-btn dash-btn-outline" style="padding:6px 16px; font-size:0.8rem;"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                            <div style="font-size:0.85rem; font-weight:600; color:var(--dash-text-muted);">
                                <?= $inventoryStats['all'] ?> / <?= $inventoryLimit ?> slots used
                            </div>
                        </div>
                    </form>
                    
                    <?php $invFilter = $_GET['filter'] ?? 'all'; ?>
                    <div class="dash-filter-row" style="margin-bottom:24px;">
                        <a href="dashboard.php?tab=inventory&filter=all" class="dash-filter-pill <?= $invFilter === 'all' ? 'active' : '' ?>" style="text-decoration:none;">All (<?= $inventoryStats['all'] ?>)</a>
                        <a href="dashboard.php?tab=inventory&filter=approved" class="dash-filter-pill <?= $invFilter === 'approved' ? 'active' : '' ?>" style="text-decoration:none;">Active (<?= $inventoryStats['approved'] ?>)</a>
                        <a href="dashboard.php?tab=inventory&filter=pending" class="dash-filter-pill <?= $invFilter === 'pending' ? 'active' : '' ?>" style="text-decoration:none;">Pending (<?= $inventoryStats['pending'] ?>)</a>
                        <a href="dashboard.php?tab=inventory&filter=paused" class="dash-filter-pill <?= $invFilter === 'paused' ? 'active' : '' ?>" style="text-decoration:none;">Paused (<?= $inventoryStats['paused'] ?>)</a>
                        <a href="dashboard.php?tab=inventory&filter=low" class="dash-filter-pill <?= $invFilter === 'low' ? 'active' : '' ?>" style="text-decoration:none;">Low Stock (<?= $inventoryStats['low'] ?>)</a>
                    </div>

                    <?php if (count($products) > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="dash-table" id="invTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th>Stock</th>
                                        <th>Price</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $p): ?>
                                        <?php
                                        $pStatus = strtolower($p['status']);
                                        $qty = (int)$p['quantity'];
                                        $stockClass = $qty <= 0 ? 'sold-out' : ($qty <= 5 ? 'low-stock' : 'in-stock');
                                        $statusClass = match($pStatus) {
                                            'approved' => 'completed',
                                            'pending' => 'processing',
                                            'paused' => 'pending',
                                            default => 'pending'
                                        };
                                        // Filtering is now handled by the DB query
                                        $isLowStock = ($qty > 0 && $qty <= 3 && $pStatus === 'approved');
                                        ?>
                                        <tr data-status="<?= $pStatus ?>" data-lowstock="<?= $isLowStock ? 'true' : 'false' ?>">
                                            <td>
                                                <div class="dash-table-product">
                                                    <div class="dash-table-thumb" style="width:40px; height:40px; flex-shrink:0;">
                                                        <?php if (!empty($p['main_image'])): ?>
                                                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" alt="">
                                                        <?php else: ?>
                                                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#9ca3af;"><i class="fa-regular fa-image"></i></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="dash-table-product-name inv-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;"><?= htmlspecialchars($p['title']) ?></div>
                                                        <div class="dash-table-product-date">Created <?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><div class="dash-status-pill <?= $statusClass ?>" style="display:inline-block;"><?= ucfirst($pStatus) ?></div></td>
                                            <td>
                                                <div style="font-weight:700; color:<?= $qty <= 3 ? 'var(--dash-amber)' : 'var(--dash-green)' ?>;"><?= $qty ?></div>
                                            </td>
                                            <td><div style="font-weight:800;">GHS <?= number_format($p['price'], 2) ?></div></td>
                                            <td>
                                                <div class="dash-btn-group" style="align-items:center;">
                                                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="dash-btn dash-btn-outline"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                                                    <?php if ($pStatus === 'approved'): ?>
                                                        <form method="POST" style="margin:0;">
                                                            <input type="hidden" name="action" value="boost">
                                                            <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dash-btn dash-btn-primary" onclick="return confirm('Boost product for 24h?')"><i class="fa-solid fa-rocket"></i> Boost</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="action" value="request_delete">
                                                        <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" style="background:none; border:none; cursor:pointer; font-size:0.8rem; font-weight:600; color:var(--dash-red); padding:0 8px;" onclick="return confirm('Request removal?')"><i class="fa-regular fa-trash-can"></i> Remove</button>
                                                    </form>
                                                    <a href="generate_flyer.php?id=<?= $p['id'] ?>" target="_blank" style="color:var(--dash-text-muted); text-decoration:none; font-size:0.8rem; font-weight:600; padding:0 8px;"><i class="fa-solid fa-bullhorn"></i> Flyer</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="invResultCount" style="margin-top:16px; font-size:0.8rem; color:var(--dash-text-muted);"><?= count($products) ?> results</div>
                    <?php else: ?>
                        <div class="dash-empty">
                            <div class="dash-empty-title">No products found</div>
                            <div>You have no products in your inventory.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ORDERS TAB -->
            <?php if ($activeTab === 'orders'): ?>
            <div id="tab-orders" class="dash-tab-panel active">
                <div class="dash-card">
                    <div class="dash-card-head" style="margin-bottom:24px;">
                        <div>
                            <h2 class="dash-card-title" style="font-size:1.2rem;">Order Management</h2>
                            <p style="font-size:0.85rem; color:var(--dash-text-muted); margin:4px 0 0;">Track and fulfill all your store orders.</p>
                        </div>
                    </div>

                    <?php $orderFilter = $_GET['order_filter'] ?? 'all'; ?>
                    <div class="dash-filter-row" style="margin-bottom:24px;">
                        <a href="dashboard.php?tab=orders&order_filter=all" class="dash-filter-pill <?= $orderFilter === 'all' ? 'active' : '' ?>" style="text-decoration:none;">All</a>
                        <a href="dashboard.php?tab=orders&order_filter=ordered" class="dash-filter-pill <?= $orderFilter === 'ordered' ? 'active' : '' ?>" style="text-decoration:none;">Pending</a>
                        <a href="dashboard.php?tab=orders&order_filter=seller_seen" class="dash-filter-pill <?= $orderFilter === 'seller_seen' ? 'active' : '' ?>" style="text-decoration:none;">Processing</a>
                        <a href="dashboard.php?tab=orders&order_filter=delivered" class="dash-filter-pill <?= $orderFilter === 'delivered' ? 'active' : '' ?>" style="text-decoration:none;">Shipped</a>
                        <a href="dashboard.php?tab=orders&order_filter=completed" class="dash-filter-pill <?= $orderFilter === 'completed' ? 'active' : '' ?>" style="text-decoration:none;">Completed</a>
                        <a href="dashboard.php?tab=orders&order_filter=cancelled" class="dash-filter-pill <?= $orderFilter === 'cancelled' ? 'active' : '' ?>" style="text-decoration:none;">Cancelled</a>
                    </div>

                    <?php if (count($seller_orders) > 0): ?>
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                            <table class="dash-table orders-table" style="min-width: 600px; width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Buyer</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seller_orders as $ord): ?>
                                        <?php
                                        $status = strtolower($ord['status']);
                                        $statusClass = match($status) {
                                            'completed' => 'completed',
                                            'delivered' => 'shipped',
                                            'seller_seen' => 'processing',
                                            'cancelled' => 'cancelled',
                                            default => 'pending'
                                        };
                                        $statusText = match($status) {
                                            'delivered' => 'Shipped',
                                            'seller_seen' => 'Processing',
                                            'cancelled' => 'Cancelled',
                                            default => ucfirst($status)
                                        };
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:700;">#ORDER-<?= $ord['id'] ?></div>
                                                <div style="font-size:0.72rem; color:var(--dash-text-muted);"><?= date('M d, Y H:i', strtotime($ord['created_at'])) ?></div>
                                            </td>
                                            <td><div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($ord['product_title']) ?></div></td>
                                            <td><div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($ord['buyer_name']) ?></div></td>
                                            <td><div class="dash-status-pill <?= $statusClass ?>" style="display:inline-block;"><?= $statusText ?></div></td>
                                            <td><div style="font-weight:800;">GHS <?= number_format($ord['product_price'], 2) ?></div></td>
                                            <td>
                                                <div class="dash-btn-group">
                                                    <?php if($status === 'ordered'): ?>
                                                        <form method="POST" style="margin:0; display:flex; gap:6px; flex-wrap:wrap;">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="accept_order">
                                                            <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                                            <button type="submit" class="dash-btn dash-btn-primary">Acknowledge</button>
                                                            <button type="submit" name="action" value="reject_order" class="dash-btn dash-btn-danger" onclick="return confirm('Decline order?');">Decline</button>
                                                        </form>
                                                    <?php elseif($status === 'seller_seen'): ?>
                                                        <form method="POST" style="margin:0; display:flex; gap:6px; flex-wrap:wrap;">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                                            <button type="submit" name="action" value="deliver_order" class="dash-btn dash-btn-primary" onclick="return confirm('Confirm sold?');">Confirm Sold</button>
                                                            <button type="submit" name="action" value="ship_order" class="dash-btn dash-btn-outline" onclick="return confirm('Mark as shipped?');"><i class="fa-solid fa-truck"></i> Mark Shipped</button>
                                                        </form>
                                                    <?php elseif($status === 'delivered'): ?>
                                                        <form method="POST" style="margin:0;">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="complete_order">
                                                            <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                                            <button type="submit" class="dash-btn dash-btn-primary" onclick="return confirm('Mark as completed?');"><i class="fa-solid fa-check"></i> Mark Completed</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="dash-btn dash-btn-outline"><i class="fa-regular fa-message"></i> Message</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        $opg = $order_page ?? 1;
                        $otp = $order_total_pages ?? 1;
                        $of = $orderFilter ?? 'all';
                        if ($otp > 1): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:16px; border-top:1px solid var(--dash-border);">
                            <div style="font-size:0.8rem; color:var(--dash-text-muted);">Page <?= $opg ?> of <?= $otp ?></div>
                            <div style="display:flex; gap:8px;">
                                <?php if ($opg > 1): ?>
                                    <a href="dashboard.php?tab=orders&order_filter=<?= $of ?>&page=<?= $opg - 1 ?>" class="dash-btn dash-btn-outline" style="padding:6px 14px; font-size:0.8rem;"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                                <?php endif; ?>
                                <?php if ($opg < $otp): ?>
                                    <a href="dashboard.php?tab=orders&order_filter=<?= $of ?>&page=<?= $opg + 1 ?>" class="dash-btn dash-btn-outline" style="padding:6px 14px; font-size:0.8rem;">Next <i class="fa-solid fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="dash-empty">
                            <div class="dash-empty-title">No orders yet</div>
                            <div>When customers buy your products, orders will appear here.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ANALYTICS TAB -->
            <?php if ($activeTab === 'analytics'): ?>
            <div id="tab-analytics" class="dash-tab-panel active">
                <div class="dash-row-2-equal">
                    <div class="dash-card" style="display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
                        <div style="font-size:3rem; margin-bottom:12px; color:var(--dash-primary);"><i class="fa-solid fa-chart-line"></i></div>
                        <h3 style="font-size:1.5rem; margin:0 0 8px;">Detailed Analytics</h3>
                        <p style="color:var(--dash-text-muted); font-size:0.9rem; max-width:300px; line-height:1.5;">Coming soon! Get deeper insights into your audience, product performance, and sales trends.</p>
                    </div>
                    <div class="dash-card">
                        <h3 style="margin:0 0 16px; font-size:1.1rem;">Quick Stats</h3>
                        <div style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--dash-border);">
                            <span style="color:var(--dash-text-muted); font-weight:600;">Conversion Rate</span>
                            <span style="font-weight:800;"><?= $viewsTotal > 0 ? number_format(($sellerTotalSold / $viewsTotal) * 100, 2) : '0.00' ?>%</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--dash-border);">
                            <span style="color:var(--dash-text-muted); font-weight:600;">Average Order Value</span>
                            <span style="font-weight:800;">GHS <?= $sellerTotalSold > 0 ? number_format($sellerRevenue / $sellerTotalSold, 2) : '0.00' ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:12px 0;">
                            <span style="color:var(--dash-text-muted); font-weight:600;">Top Product Views</span>
                            <span style="font-weight:800;"><?= $sellerTopProduct ? (int)$sellerTopProduct['views'] : 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- WALLET TAB -->
            <?php if ($activeTab === 'wallet'): ?>
            <div id="tab-wallet" class="dash-tab-panel active">
                <div class="dash-row-2">
                    <!-- Balance + Withdraw -->
                    <div>
                        <div class="dash-card" style="margin-bottom:24px;">
                            <div class="dash-card-head">
                                <h2 class="dash-card-title">Wallet Balance</h2>
                            </div>
                            <div style="font-size:2.5rem; font-weight:800; color:var(--dash-green); margin-bottom:8px;">GHS <?= number_format($wallet_balance, 2) ?></div>
                            <div style="font-size:0.8rem; color:var(--dash-text-muted); margin-bottom:24px;">Available for withdrawal</div>
                            <div class="dash-inventory-stats" style="margin-bottom:0;">
                                <div class="dash-inv-stat">
                                    <i class="fa-solid fa-arrow-up" style="color:var(--dash-green);"></i>
                                    <span>GHS <?= number_format($salesAmount ?? 0, 2) ?> Earned</span>
                                </div>
                                <div class="dash-inv-stat">
                                    <i class="fa-solid fa-arrow-down" style="color:var(--dash-red);"></i>
                                    <span>GHS <?= number_format($withdrawalTotal ?? 0, 2) ?> Withdrawn</span>
                                </div>
                            </div>
                        </div>

                        <div class="dash-card">
                            <div class="dash-card-head">
                                <h2 class="dash-card-title">Withdraw Funds</h2>
                            </div>
                            <?php if ($wallet_balance <= 0): ?>
                                <div class="dash-empty" style="padding:16px 0;">
                                    <div class="dash-empty-title">No funds available</div>
                                    <div>Start selling to earn funds you can withdraw.</div>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="dashboard.php?tab=wallet" id="withdrawForm">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="request_withdrawal">
                                    <div style="display:flex; flex-direction:column; gap:16px;">
                                        <div>
                                            <label style="font-size:0.8rem; font-weight:600; color:var(--dash-text-muted); display:block; margin-bottom:6px;">Amount (GHS)</label>
                                            <input type="number" name="withdraw_amount" step="0.01" min="1" max="<?= $wallet_balance ?>" placeholder="Enter amount" class="dash-search" style="width:100%;" required>
                                        </div>
                                        <div>
                                            <label style="font-size:0.8rem; font-weight:600; color:var(--dash-text-muted); display:block; margin-bottom:6px;">Mobile Network</label>
                                            <select name="momo_network" class="dash-period-select" style="width:100%; padding:10px 14px; font-size:0.85rem;" required>
                                                <option value="">Select network</option>
                                                <option value="MTN">MTN Mobile Money</option>
                                                <option value="Vodafone">Vodafone Cash</option>
                                                <option value="AirtelTigo">AirtelTigo Money</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="font-size:0.8rem; font-weight:600; color:var(--dash-text-muted); display:block; margin-bottom:6px;">Mobile Money Number</label>
                                            <input type="tel" name="momo_number" placeholder="e.g. 024XXXXXXX" class="dash-search" style="width:100%;" pattern="[0-9]{10}" maxlength="10" required>
                                        </div>
                                        <button type="submit" class="dash-btn dash-btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:0.9rem;" onclick="return confirm('Withdraw GHS ' + this.form.withdraw_amount.value + '?');">
                                            <i class="fa-solid fa-paper-plane"></i> Submit Withdrawal
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transactions -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Recent Transactions</h2>
                            <a href="transactions.php" class="dash-card-link">View all</a>
                        </div>
                        <?php if(count($transactions) > 0): ?>
                            <div style="display:flex; flex-direction:column;">
                                <?php foreach(array_slice($transactions, 0, 20) as $tx): ?>
                                    <?php $is_credit = in_array($tx['type'], ['deposit', 'sale', 'referral']); ?>
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid rgba(0,0,0,0.04);">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <div style="width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:<?= $is_credit ? 'rgba(0,201,167,0.1)' : 'rgba(239,68,68,0.1)' ?>; color:<?= $is_credit ? 'var(--dash-green)' : 'var(--dash-red)' ?>; font-size:0.85rem;">
                                                <i class="fa-solid <?= $is_credit ? 'fa-arrow-down-left' : 'fa-arrow-up-right' ?>"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($tx['description'] ?? ucfirst($tx['type'])) ?></div>
                                                <div style="font-size:0.72rem; color:var(--dash-text-muted); margin-top:2px;"><?= date('M d, Y • h:i A', strtotime($tx['created_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div style="font-weight:800; font-size:0.9rem; color:<?= $is_credit ? 'var(--dash-green)' : 'var(--dash-red)' ?>;">
                                                <?= $is_credit ? '+' : '-' ?>GHS <?= number_format($tx['amount'], 2) ?>
                                            </div>
                                            <div class="dash-status-pill <?= $is_credit ? 'completed' : 'cancelled' ?>" style="display:inline-block; margin-top:4px; font-size:0.6rem;">
                                                <?= $is_credit ? 'Credit' : 'Debit' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty" style="padding:20px 0;">No transactions yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- MESSAGES TAB -->
            <?php if ($activeTab === 'messages'): ?>
            <?php $me = $me ?? $user['id']; ?>
            <div id="tab-messages" class="dash-tab-panel active">
                <style>
                .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; }
                .msg-from-buyer { background: #f3f4f6; color: #1f2937; align-self: flex-start; border-bottom-left-radius: 4px; }
                .msg-from-seller { background: #6d28d9; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
                .msg-thread { display: flex; flex-direction: column; gap: 10px; padding: 16px; overflow-y: auto; max-height: calc(100vh - 200px); }
                .msg-send-bar { display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid #e5e7eb; background: #fff; }
                .msg-send-bar input { flex:1; border:1px solid #e5e7eb; border-radius:8px; padding:10px 14px; font-size:0.9rem; }
                .msg-send-bar button { background:#6d28d9; color:#fff; border:none; border-radius:8px; padding:10px 18px; font-weight:600; cursor:pointer; }
                </style>
                <?php
                if (!isset($dash_chat_users)) {
                    echo "<script>window.location.href='messages.php';</script>";
                }
                ?>
                <div class="dash-card" style="padding:0; overflow:hidden;">
                    <?php if (empty($dash_chat_active_user)): ?>
                        <!-- State 1: Conversation List -->
                        <div class="dash-card-head" style="padding:16px;">
                            <h2 class="dash-card-title">Conversations</h2>
                        </div>
                        <?php if (empty($dash_chat_users)): ?>
                            <div class="dash-empty" style="padding:24px;">No active conversations.</div>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column;">
                                <?php foreach ($dash_chat_users as $u): ?>
                                    <a href="messages.php?conversation_id=<?= $u['id'] ?>" style="display:flex; align-items:center; gap:12px; padding:16px; border-bottom:1px solid #e5e7eb; text-decoration:none; color:inherit; transition:background 0.2s;">
                                        <div style="position:relative;">
                                            <?php if ($u['profile_pic']): ?>
                                                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($u['profile_pic'])) ?>" style="width:48px; height:48px; border-radius:50%; object-fit:cover;">
                                            <?php else: ?>
                                                <div style="width:48px; height:48px; border-radius:50%; background:var(--dash-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.2rem;"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                                            <?php endif; ?>
                                            <?php if ($u['unread'] > 0): ?>
                                                <span style="position:absolute; top:-2px; right:-2px; background:#ef4444; color:#fff; font-size:0.6rem; font-weight:700; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid #fff;"><?= $u['unread'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-weight:700; color:#111827;"><?= htmlspecialchars($u['username']) ?></div>
                                            <div style="font-size:0.8rem; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($u['last_msg'] ?? 'New conversation') ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- State 2: Conversation Thread -->
                        <div style="display:flex; align-items:center; gap:12px; padding:16px; border-bottom:1px solid #e5e7eb; background:#f9fafb;">
                            <a href="messages.php" style="color:#6b7280; text-decoration:none;"><i class="fas fa-arrow-left"></i></a>
                            <?php if ($dash_chat_active_user['profile_pic']): ?>
                                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($dash_chat_active_user['profile_pic'])) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <div style="width:36px; height:36px; border-radius:50%; background:var(--dash-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem;"><?= strtoupper(substr($dash_chat_active_user['username'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div style="font-weight:700; color:#111827;"><?= htmlspecialchars($dash_chat_active_user['username']) ?></div>
                        </div>
                        <div class="msg-thread">
                            <?php if (empty($dash_chat_messages)): ?>
                                <div style="text-align:center; color:#9ca3af; font-size:0.85rem; padding:20px;">Say hello to <?= htmlspecialchars($dash_chat_active_user['username']) ?>!</div>
                            <?php else: ?>
                                <?php foreach ($dash_chat_messages as $msg): ?>
                                    <?php $isMe = ($msg['sender_id'] == $me); ?>
                                    <div class="msg-bubble <?= $isMe ? 'msg-from-seller' : 'msg-from-buyer' ?>">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        <div style="font-size:0.65rem; margin-top:4px; opacity:0.7; text-align:right;">
                                            <?= date('h:i A', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="messages.php" class="msg-send-bar">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conversation_id" value="<?= $dash_chat_active_user['id'] ?>">
                            <input type="text" name="message_body" placeholder="Type a message..." required autocomplete="off" style="background:#fff !important; color:#1f2937 !important; border:1px solid #e5e7eb !important;">
                            <button type="submit">Send</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            // Auto-scroll chat thread to bottom
            (function() {
                var t = document.querySelector('.msg-thread');
                if (t) t.scrollTop = t.scrollHeight;
            })();
            </script>
            <?php endif; ?>

            <!-- SETTINGS TAB -->
            <?php if ($activeTab === 'settings'): ?>
            <div id="tab-settings" class="dash-tab-panel active">
                <div class="dash-row-2-equal">
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Profile Settings</h2>
                            <a href="edit_profile.php" class="dash-btn dash-btn-outline">Edit Profile</a>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(0,0,0,0.04); padding-bottom:16px;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Username</div>
                                    <div style="font-size:0.75rem; color:var(--dash-text-muted); margin-top:4px;">Your store name on the marketplace.</div>
                                </div>
                                <div style="font-weight:700;"><?= htmlspecialchars($user['username']) ?></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(0,0,0,0.04); padding-bottom:16px;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Email Address</div>
                                </div>
                                <div style="font-weight:600; color:var(--dash-text-muted);"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(0,0,0,0.04); padding-bottom:16px;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Phone / WhatsApp</div>
                                </div>
                                <div style="font-weight:600; color:var(--dash-text-muted);"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not set' ?></div>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Verification Status</div>
                                </div>
                                <div>
                                    <?php if ($user['verified']): ?>
                                        <span class="dash-stock-pill in-stock" style="font-size:0.7rem; padding:4px 10px;"><i class="fa-solid fa-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="dash-stock-pill low-stock" style="font-size:0.7rem; padding:4px 10px;">Pending</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 class="dash-card-title">Store Operations</h2>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(0,0,0,0.04); padding-bottom:16px; align-items:center;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Seller Plan</div>
                                    <div style="font-size:0.75rem; color:var(--dash-text-muted); margin-top:4px;">You are currently on the <?= ucfirst($activeSellerTier) ?> plan.</div>
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                                    <span class="dash-stock-pill in-stock" style="font-size:0.75rem; background:rgba(108,71,255,0.1); color:var(--dash-primary);"><?= strtoupper($activeSellerTier) ?></span>
                                    <button class="dash-btn dash-btn-outline" style="padding:4px 10px; font-size:0.7rem;" onclick="document.getElementById('upgradeModalOverlay').classList.add('open')">Manage Plan</button>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">Vacation Mode</div>
                                    <div style="font-size:0.75rem; color:var(--dash-text-muted); margin-top:4px;">Hide all your listings temporarily.</div>
                                </div>
                                <div>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="toggle_vacation">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dash-btn <?= $user['vacation_mode'] ? 'dash-btn-outline' : 'dash-btn-primary' ?>" <?= ($isTierExpired && $user['vacation_mode']) ? 'disabled' : '' ?>>
                                            <?= $user['vacation_mode'] ? 'Turn Off' : 'Turn On' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div> <!-- /.dash-content -->
    </main>

</div>

<!-- UPGRADE PLAN MODAL -->
<div class="dash-modal-overlay" id="upgradeModalOverlay" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="dash-modal" style="max-width:800px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h2 class="dash-card-title" style="font-size:1.3rem;">Manage Plan</h2>
            <button onclick="document.getElementById('upgradeModalOverlay').classList.remove('open')" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--dash-text-muted);"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
            <?php foreach ($tiers as $tier): ?>
                <?php
                $isActiveTier = $activeSellerTier === (string)$tier['tier_name'];
                $price = (float)($tier['price'] ?? 0);
                ?>
                <div class="dash-card" style="border:2px solid <?= $isActiveTier ? 'var(--dash-primary)' : 'var(--dash-border)' ?>; position:relative;">
                    <?php if($isActiveTier): ?>
                        <div style="position:absolute; top:-12px; right:16px; background:var(--dash-primary); color:#fff; font-size:0.7rem; font-weight:700; padding:4px 12px; border-radius:999px;">CURRENT</div>
                    <?php endif; ?>
                    <h3 style="font-size:1.1rem; margin:0 0 8px; text-transform:uppercase;"><?= htmlspecialchars((string)$tier['tier_name']) ?></h3>
                    <div style="font-size:1.8rem; font-weight:800; color:var(--dash-text); margin-bottom:16px;">
                        <?= $price > 0 ? 'GHS ' . number_format($price, $price === floor($price) ? 0 : 2) : 'Free' ?>
                        <span style="font-size:0.8rem; font-weight:600; color:var(--dash-text-muted);">/ <?= htmlspecialchars((string)$tier['duration_label']) ?></span>
                    </div>
                    <ul style="list-style:none; padding:0; margin:0 0 24px; font-size:0.85rem; color:var(--dash-text-muted); display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($tier['feature_list'] as $feature): ?>
                            <li><i class="fa-solid fa-check" style="color:var(--dash-green); margin-right:8px;"></i> <?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($isActiveTier): ?>
                        <button class="dash-btn dash-btn-outline" style="width:100%; justify-content:center;" disabled>Active Plan</button>
                    <?php elseif ($price > 0): ?>
                        <button type="button" class="dash-btn dash-btn-primary" style="width:100%; justify-content:center;" onclick="payWithPaystack('<?= htmlspecialchars((string)$tier['tier_name']) ?>')">Upgrade Now</button>
                    <?php else: ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="request_<?= htmlspecialchars((string)$tier['tier_name']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dash-btn dash-btn-outline" style="width:100%; justify-content:center;">Select Plan</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
    const tierPricingConfig = <?= json_encode($tierPricingConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const paystackCsrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const paystackPublicKey = <?= json_encode((string)get_env_var('PAYSTACK_PUBLIC_KEY', '')) ?>;
    const paystackCustomerEmail = <?= json_encode((string)($user['email'] ?? '')) ?>;
    const paystackUserId = <?= json_encode((string)($user['id'] ?? '')) ?>;

    window.payWithPaystack = function (tier) {
        const tierConfig = tierPricingConfig[tier];
        if (!tierConfig) {
            alert('Tier configuration could not be found. Please refresh and try again.');
            return;
        }
        const amount = Number(tierConfig.price || 0);
        if (!Number.isFinite(amount) || amount <= 0) {
            alert('This plan does not require payment.');
            return;
        }
        if (!paystackPublicKey) {
            alert('Paystack is not configured yet. Please contact support.');
            return;
        }
        if (!paystackCustomerEmail) {
            alert('Your account email is missing. Please update your profile before upgrading.');
            return;
        }
        if (typeof window.PaystackPop === 'undefined') {
            alert('The Paystack checkout script did not load. Please refresh and try again.');
            return;
        }

        const handler = window.PaystackPop.setup({
            key: paystackPublicKey,
            email: paystackCustomerEmail,
            amount: Math.round(amount * 100),
            currency: 'GHS',
            label: tierConfig.duration_label ? `${tier.toUpperCase()} - ${tierConfig.duration_label}` : tier.toUpperCase(),
            metadata: {
                tier: tier,
                user_id: paystackUserId,
                dashboard_flow: 'seller-workspace'
            },
            callback: function (response) {
                verifyPayment(response.reference, tier);
            },
            onClose: function () {
                alert('Transaction was not completed.');
            }
        });
        handler.openIframe();
    };

    async function verifyPayment(reference, tier) {
        try {
            const response = await fetch('api/paystack_verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ reference: reference, tier: tier, csrf_token: paystackCsrfToken })
            });
            const data = await response.json();
            if (data.status === 'success') {
                alert(data.message);
                window.location.reload();
                return;
            }
            alert('Error: ' + data.message);
        } catch (error) {
            alert('CRITICAL PAYMENT ERROR: Could not verify transaction.');
        }
    }

    // Order Overview filters
    const orderOverviewFilters = document.querySelectorAll('.dash-order-tabs .dash-order-tab');
    const overviewOrderItems = document.querySelectorAll('#overviewOrderList .dash-order-item');
    
    orderOverviewFilters.forEach(btn => {
        btn.addEventListener('click', () => {
            orderOverviewFilters.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.dataset.filter;
            
            overviewOrderItems.forEach(item => {
                if (filter === 'all' || item.dataset.status === filter) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Inventory Search & Filters
    const invSearch = document.getElementById('invSearch');
    const invFilters = document.querySelectorAll('#invFilters .dash-filter-pill');
    const invRows = document.querySelectorAll('#invTable tbody tr');
    const invCount = document.getElementById('invResultCount');

    function filterInventory() {
        if (!invSearch) return;
        const query = invSearch.value.toLowerCase();
        let visibleCount = 0;

        invRows.forEach(row => {
            const title = row.querySelector('.inv-title').textContent.toLowerCase();
            if (title.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        if (invCount) invCount.textContent = visibleCount + ' results';
    }

    if (invSearch) invSearch.addEventListener('input', filterInventory);

    // Initialize Chart.js
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('overviewChartCanvas');
        if (ctx && typeof Chart !== 'undefined') {
            const labels = <?= json_encode($chart_labels ?? []) ?>;
            const salesData = <?= json_encode($chart_revenue ?? []) ?>;
            const viewsData = <?= json_encode($chart_views ?? []) ?>;

            window.overviewChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue (GHS)',
                            data: salesData,
                            borderColor: '#6c47ff',
                            backgroundColor: 'rgba(108,71,255,0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Views',
                            data: viewsData,
                            borderColor: '#00c9a7',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#00c9a7',
                            pointRadius: 4,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f0f1a',
                            titleFont: { family: 'Inter', size: 13 },
                            bodyFont: { family: 'Inter', size: 13 },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter' }, color: '#6b7280' }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { borderDash: [4, 4], color: '#e5e7eb' },
                            ticks: { font: { family: 'Inter' }, color: '#6b7280' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { display: false },
                            ticks: { font: { family: 'Inter' }, color: '#6b7280' }
                        }
                    }
                }
            });
        }
    });
</script>

<script>
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('active');
}

document.querySelector('.sidebar-overlay')
  .addEventListener('click', function() {
    document.querySelector('.sidebar').classList.remove('open');
    this.classList.remove('active');
  });
</script>

<!-- ═══════ NOTIFICATION DROPDOWN + SLOT COUNTER LOGIC ═══════ -->
<script>
(function() {
  'use strict';
  const POLL_INTERVAL = 30000;
  const MAX_DROPDOWN_ITEMS = 15;
  let notifCache = [];
  let dropdownOpen = false;

  // ─── Toggle Dropdown ───
  window.toggleNotifDropdown = function() {
    const dd = document.getElementById('notifDropdown');
    dropdownOpen = !dropdownOpen;
    dd.classList.toggle('open', dropdownOpen);
    if (dropdownOpen) {
      fetchNotifications();
    }
  };

  // ─── Close on outside click ───
  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      const dd = document.getElementById('notifDropdown');
      if (dd) { dd.classList.remove('open'); dropdownOpen = false; }
    }
  });

  // ─── Fetch Notifications ───
  function fetchNotifications() {
    fetch('backend/index.php?route=notifications', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.error) return;
        notifCache = (data.notifications || []).sort(function(a, b) { return b.id - a.id; }).slice(0, MAX_DROPDOWN_ITEMS);
        renderNotifList();
        updateBadge(data.unread_count || 0);
      })
      .catch(function() {});
  }

  // ─── Poll for count updates ───
  function pollCounts() {
    fetch('backend/index.php?route=notifications/unread-count', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.error) {
          updateBadge(data.unread_count || 0);
        }
      })
      .catch(function() {});
  }

  // ─── Update Badge Count ───
  function updateBadge(count) {
    var badge = document.getElementById('notifBadge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = '';
      var bell = document.querySelector('#notifBellBtn i');
      if (bell) { bell.style.color = 'var(--dash-primary)'; }
    } else {
      badge.style.display = 'none';
      var bell2 = document.querySelector('#notifBellBtn i');
      if (bell2) { bell2.style.color = '#6b7280'; }
    }
  }

  window.handleNotifClick = function(e, id, path) {
    e.preventDefault();
    var item = notifCache.find(function(n) { return n.id === id; });
    if (item && isUnread(item)) {
        item.is_read = 1;
        var currentBadge = parseInt(document.getElementById('notifBadge').textContent) || 0;
        updateBadge(Math.max(0, currentBadge - 1));
        fetch('backend/index.php?route=notifications/' + id + '/read', { 
            method: 'PATCH',
            credentials: 'same-origin' 
        });
    }
    document.getElementById('notifDropdown').classList.remove('open');
    dropdownOpen = false;
    window.location.href = path || 'dashboard.php';
  };

  // ─── Render Notification List ───
  function renderNotifList() {
    var list = document.getElementById('notifList');
    if (!list) return;

    if (notifCache.length === 0) {
      list.innerHTML = '';
      list.appendChild(createEmptyState());
      return;
    }

    list.innerHTML = '';
    notifCache.forEach(function(n) {
      var item = document.createElement('a');
      item.href = n.redirect_path || n.link_url || 'dashboard.php';
      item.className = 'dash-notif-item' + (isUnread(n) ? ' unread' : '');
      item.onclick = function(e) { handleNotifClick(e, n.id, item.href); };
      item.innerHTML =
        '<div class="dash-notif-item-icon ' + notifIconClass(n.type) + '">' +
          '<i class="' + notifIcon(n.type) + '"></i>' +
        '</div>' +
        '<div class="dash-notif-item-body">' +
          '<div class="dash-notif-item-title">' + escHtml(n.title || notifFallbackTitle(n.type)) + '</div>' +
          '<div class="dash-notif-item-msg">' + escHtml(truncate(n.message, 80)) + '</div>' +
          '<div class="dash-notif-item-time">' + relTime(n.created_at) + '</div>' +
        '</div>' +
        (isUnread(n) ? '<span class="dash-notif-dot"></span>' : '');
      list.appendChild(item);
    });
  }

  function createEmptyState() {
    var div = document.createElement('div');
    div.className = 'dash-notif-empty';
    div.innerHTML = '<i class="fa-regular fa-bell-slash" style="font-size:1.5rem;margin-bottom:8px;opacity:0.4;"></i><div>No notifications yet</div>';
    return div;
  }

  // ─── Mark All Read ───
  window.markAllNotifRead = function() {
    fetch('backend/index.php?route=notifications/read-all', { method: 'PATCH', credentials: 'same-origin' })
      .catch(function() {});
    // Optimistically update UI
    notifCache.forEach(function(n) { n.is_read = 1; });
    renderNotifList();
    updateBadge(0);
  };

  // ─── Helpers ───
  function isUnread(n) { return !n.is_read || n.is_read === '0' || n.is_read === 0 || n.is_read === false; }

  function notifIcon(type) {
    var map = {
      'message': 'fa-regular fa-comment-dots',
      'new_message': 'fa-regular fa-comment-dots',
      'order': 'fa-solid fa-bag-shopping',
      'new_order': 'fa-solid fa-bag-shopping',
      'order_received': 'fa-solid fa-bag-shopping',
      'order_update': 'fa-solid fa-truck',
      'approval': 'fa-solid fa-check-circle',
      'rejection': 'fa-solid fa-xmark-circle',
      'subscription': 'fa-solid fa-star',
      'slot': 'fa-solid fa-layer-group',
      'payment': 'fa-solid fa-credit-card',
      'system': 'fa-solid fa-gear'
    };
    return map[type] || 'fa-solid fa-bell';
  }

  function notifIconClass(type) {
    if (['order','new_order','order_received','approval'].indexOf(type) >= 0) return 'green';
    if (['rejection','order_rejected','order_cancelled'].indexOf(type) >= 0) return 'red';
    if (['subscription', 'slot'].indexOf(type) >= 0) return 'amber';
    if (['message','new_message'].indexOf(type) >= 0) return 'purple';
    if (['payment'].indexOf(type) >= 0) return 'blue';
    return 'blue';
  }

  function notifFallbackTitle(type) {
    var map = { 'message':'New Message','order':'New Order','approval':'Product Approved','rejection':'Product Rejected','subscription':'Subscription Update','payment':'Payment Confirmed' };
    return map[type] || 'Notification';
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function truncate(s, n) {
    if (!s) return '';
    return s.length > n ? s.substring(0, n) + '\u2026' : s;
  }

  function relTime(dateStr) {
    if (!dateStr) return '';
    var now = Date.now();
    var ts = new Date(dateStr.replace(' ', 'T') + (dateStr.indexOf('+') >= 0 || dateStr.indexOf('Z') >= 0 ? '' : 'Z')).getTime();
    var diff = Math.floor((now - ts) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(ts).toLocaleDateString('en-GB', { day:'numeric', month:'short' });
  }

  // ─── Slot Counter Logic ───
  function fetchSlotCount() {
    fetch('backend/index.php?route=users/slot-count', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.error) return;
        updateSlotUI(data.available_slots, data.used_slots, data.total_slots);
      })
      .catch(function() {});
  }

  function updateSlotUI(available, used, total) {
    var textStr = available + ' left';
    
    var addBtns = [document.getElementById('topAddProductBtn'), document.getElementById('invAddProductBtn')];
    var counters = [document.getElementById('topSlotCounter'), document.getElementById('invSlotCounter')];
    var texts = [document.getElementById('topSlotText'), document.getElementById('invSlotText')];
    
    addBtns.forEach(function(btn) {
      if (!btn) return;
      if (available <= 0) {
        btn.style.opacity = '0.5';
        btn.style.filter = 'grayscale(100%)';
        btn.onclick = function(e) {
            e.preventDefault();
            alert('No slots available. Please upgrade your tier to add more products.');
            window.location.href = 'dashboard.php?tab=wallet';
        };
      } else {
        btn.style.opacity = '1';
        btn.style.filter = 'none';
        btn.onclick = null;
      }
    });

    counters.forEach(function(counter) {
      if (!counter) return;
      counter.className = 'dash-slot-counter';
      if (available <= 0) {
        counter.classList.add('dash-slot-full');
        counter.title = 'No slots available';
        var icon = counter.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-lock';
      } else if (available === 1) {
        counter.classList.add('dash-slot-warning');
        counter.title = 'Low slots warning';
        var icon2 = counter.querySelector('i');
        if (icon2) icon2.className = 'fa-solid fa-triangle-exclamation';
      } else {
        counter.title = 'Product slots used';
        var icon3 = counter.querySelector('i');
        if (icon3) icon3.className = 'fa-solid fa-layer-group';
      }
    });

    texts.forEach(function(textElem) {
      if (!textElem) return;
      textElem.textContent = available <= 0 ? 'Full (' + used + '/' + total + ')' : textStr;
    });
  }

  // ─── Init ───
  fetchNotifications();
  fetchSlotCount();
  setInterval(pollCounts, POLL_INTERVAL);
})();
</script>
