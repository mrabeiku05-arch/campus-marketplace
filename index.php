<?php
// ── Render Health Check (must be first) ──
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($ua, 'Render/1.0') !== false || strpos($ua, 'Go-http-client') !== false) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo '{"status":"ok"}';
    exit;
}

// ── API Bridge: route REST API calls to backend ──
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$api_routes = ['/api', '/auth', '/products', '/orders', '/messages', '/reviews', '/users', '/payments', '/search', '/recommendations', '/leaderboard', '/ads', '/announcements', '/notifications', '/ai', '/upload'];

$is_api = false;
foreach ($api_routes as $route) {
    if (strpos($uri, $route) === 0) {
        $is_api = true;
        break;
    }
}

if ($is_api) {
    require_once __DIR__ . '/backend/index.php';
    exit;
}

require_once 'includes/db.php';

// Promo tag icon helper
function getPromoTagIcon($tag) {
    $icons = [
        'Hot Deal' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-1.072-5.714-1-7 0 0-2.5 1.5-2.5 5 0 2.5 1 3.5 1.5 4.5.5 1 1.5 2.5 1.5 4.5a2.5 2.5 0 0 0 2.5 2.5c1.5 0 2.5-1 2.5-2.5 0-1.5-1-2.5-1.5-3.5-.5-1-1-2-1-3.5 0-1.5.5-2.5 1-3.5.5-1 1-2 1-3.5a2.5 2.5 0 0 0-2.5-2.5c-1.5 0-2.5 1-3 2.5-.5 1.5-.5 3-.5 4.5"/></svg>',
        'Flash Sale' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'Limited Offer' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/></svg>',
        'Student Special' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
        'Bundle Deal' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        'Clearance' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>'
    ];
    return $icons[$tag] ?? '';
}

// 1. FORCED REVIEW BARRIER: Rule 10
if (isLoggedIn() && hasUnreviewedOrders($pdo, $_SESSION['user_id'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php') {
        $_SESSION['flash'] = "Review required: please submit a review for your recent purchase before you continue browsing.";
        $reviewProductId = getFirstUnreviewedProductId($pdo, (int) $_SESSION['user_id']);
        if ($reviewProductId) {
            redirect('product.php?id=' . $reviewProductId . '&review_required=1#review');
        }
        redirect('dashboard.php#buyer_orders');
    }
}

// ── Full PHP Landing Page ──
require_once 'includes/header.php';
require_once 'includes/ai_recommendations.php';

if (!isset($pdo)) {
    echo "<div class='glass form-container text-center'><h2>Run Setup</h2><a href='setup.php' class='btn btn-primary mt-2'>Initialize Database</a></div>";
    require_once 'includes/footer.php'; exit;
}

// Database migrations moved to migrate.php for performance


// Fetch Context-Aware Ads (Multiple)
$homepage_ads = [];
$ad_loc = (!empty($category)) ? 'category' : 'homepage';
$isPg = ($db_type ?? '') === 'pgsql';
try {
    $boolTrue = $isPg ? 'true' : '1';
    $stmt_ads = $pdo->prepare("SELECT * FROM ad_placements WHERE placement = ? AND is_active = $boolTrue ORDER BY created_at DESC LIMIT 5");
    $stmt_ads->execute([$ad_loc]);
    $homepage_ads = $stmt_ads->fetchAll();
    
    if (count($homepage_ads) > 0) {
        $ad_ids = array_column($homepage_ads, 'id');
        $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
        $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id IN ($placeholders)")->execute($ad_ids);
    }
} catch(Exception $e) {}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$category = $_GET['category'] ?? '';

// Base query - only approved + not on vacation
$boolFalse = $isPg ? 'false' : '0';
if ($search) {
    // PostgreSQL doesn't have FIELD() — use a CASE-based sort
    if ($isPg) {
        $p_join = " JOIN users u ON p.user_id = u.id ";
        $p_order = " ORDER BY CASE u.seller_tier WHEN 'premium' THEN 1 WHEN 'pro' THEN 2 ELSE 3 END, p.boosted_until DESC NULLS LAST, p.created_at DESC ";
    } else {
        $p_join = " JOIN users u ON p.user_id = u.id ";
        $p_order = " ORDER BY FIELD(u.seller_tier, 'premium', 'pro', 'basic'), p.boosted_until DESC, p.created_at DESC ";
    }
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.seller_tier, u.verified FROM products p $p_join WHERE p.status='approved' AND (p.title LIKE ? OR p.category LIKE ? OR p.description LIKE ?) $p_order");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $products = $stmt->fetchAll();
    
    // TYPO-TOLERANT FALLBACK (skip SOUNDEX on PostgreSQL)
    if (count($products) === 0 && strlen($search) > 3) {
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.seller_tier, u.verified FROM products p $p_join WHERE p.status='approved' AND p.title ILIKE ? $p_order LIMIT 10");
        $stmt->execute(['%' . substr($search, 0, 4) . '%']);
        $products = $stmt->fetchAll();
    }
    $total = count($products);
    $total_pages = 1;
} else {
    $seller_filter = trim($_GET['seller'] ?? '');

    // Base query - only approved + not on vacation
    $vacFalse = $isPg ? 'false' : '0';
    $query = "SELECT p.*, u.username, u.seller_tier, u.verified, u.profile_pic as seller_pic,
              (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
              (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
              FROM products p
              JOIN users u ON p.user_id = u.id
              WHERE p.status = 'approved' AND u.vacation_mode = $vacFalse";
    $count_query = "SELECT COUNT(*) FROM products p JOIN users u ON p.user_id = u.id WHERE p.status = 'approved' AND u.vacation_mode = $vacFalse";
    $params = [];

    if ($min_price !== '') { $query .= " AND p.price >= ?"; $count_query .= " AND p.price >= ?"; $params[] = $min_price; }
    if ($max_price !== '') { $query .= " AND p.price <= ?"; $count_query .= " AND p.price <= ?"; $params[] = $max_price; }
    if ($category)         { $query .= " AND p.category = ?"; $count_query .= " AND p.category = ?"; $params[] = $category; }
    if ($seller_filter)    { $query .= " AND u.username = ?"; $count_query .= " AND u.username = ?"; $params[] = $seller_filter; }

    if ($isPg) {
        $query .= " ORDER BY CASE u.seller_tier WHEN 'premium' THEN 1 WHEN 'pro' THEN 2 ELSE 3 END, (p.boosted_until > NOW()) DESC, p.created_at DESC LIMIT $per_page OFFSET $offset";
    } else {
        $query .= " ORDER BY FIELD(u.seller_tier, 'premium', 'pro', 'basic'), (p.boosted_until > NOW()) DESC, p.created_at DESC LIMIT $per_page OFFSET $offset";
    }

    $stmt = $pdo->prepare($count_query); $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));

    $stmt = $pdo->prepare($query); $stmt->execute($params);
    $products = $stmt->fetchAll();
}

// Categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status='approved' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<?php 
$cat_images = [
    'Computer & Accessories' => 'IMG_5825.webp',
    'Phone & Accessories' => 'IMG_5822.webp',
    'Electrical Appliances' => 'IMG_5827.webp',
    'Fashion' => 'IMG_5828.webp',
    'Food & Groceries' => 'IMG_5830.webp',
    'Education & Books' => 'IMG_5831.webp',
    'Hostels for Rent' => 'IMG_5833.webp'
];
$cat_descriptions = [
    'Computer & Accessories' => 'Laptops, monitors, and all computing essentials.',
    'Phone & Accessories' => 'Smartphones, covers, screen protectors, and mobile gear.',
    'Electrical Appliances' => 'Home and dorm gadgets, microwaves, fans, and blenders.',
    'Fashion' => 'Trendy clothing, shoes, bags, and stylish accessories.',
    'Food & Groceries' => 'Snacks, beverages, fresh produce, and daily provisions.',
    'Education & Books' => 'Textbooks, notebooks, stationery, and study materials.',
    'Hostels for Rent' => 'Accommodation, shared rooms, apartments, and living spaces.'
];
$cat_img = ($category && isset($cat_images[$category])) ? $cat_images[$category] : null;
$cat_desc = ($category && isset($cat_descriptions[$category])) ? $cat_descriptions[$category] : 'Explore what this category comprises of.';
?>
<?php if (empty($search) && empty($category) && empty($min_price) && empty($max_price) && $page == 1): ?>
    </div><!-- End container for full bleed -->
    <!-- REACT HERO (Only show on default home) -->
    <div id="react-hero-root"></div>
    <div class="container"><!-- Reopen container for the rest of the page -->
<?php else: ?>
    <?php if($cat_img): ?>
        </div>
        <!-- Full Width Category Banner -->
        <div style="position:relative; width:100%; height:40vh; min-height:300px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:2rem;">
            <img src="<?= getAssetUrl(htmlspecialchars($cat_img)) ?>" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center top; z-index:0;">
            <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5); z-index:1;"></div>
            <div style="text-align:center; z-index:2; padding:0 20px;">
                <h1 style="font-size:3.5rem; font-weight:800; color:#fff; letter-spacing:-0.04em; text-shadow:0 12px 40px rgba(0,0,0,0.8);"><?= htmlspecialchars($category) ?></h1>
                <p style="color:#f5f5f7; font-size:1.2rem; margin-top:0.5rem; font-weight:500;"><?= htmlspecialchars($cat_desc) ?></p>
                <p style="color:#f5f5f7; font-size:1rem; margin-top:0.5rem; font-weight:400;">Found <?= $total ?> products</p>
            </div>
        </div>
        <div class="container">
    <?php else: ?>
        <!-- Search Results Header (Apple Style) -->
        <div class="search-header" style="padding:5rem 5% 3rem; background:linear-gradient(to bottom, rgba(124,58,237,0.06), transparent); border-bottom:1px solid rgba(0,0,0,0.05); text-align:center; margin-bottom:2rem;">
            <h1 style="font-size:3.5rem; font-weight:800; color:var(--text-main); letter-spacing:-0.04em;">
                <?php 
                    if ($search) echo 'Search: "'.htmlspecialchars($search).'"';
                    else echo 'All Products';
                ?>
            </h1>
            <p style="color:var(--text-muted); font-size:1.2rem; margin-top:0.5rem; font-weight:500;">Found <?= $total ?> items</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- FRAUD NOTICE -->
<div class="notice-box-inline mb-4" style="max-width:800px; margin-left:auto; margin-right:auto; background:rgba(255,204,0,0.15); border:1px solid rgba(255,204,0,0.5); padding:15px; border-radius:12px; text-align:center; margin-top:20px;">
    <strong><span style="font-size:1.1rem; vertical-align:middle; margin-right:5px;">⚠️</span> SAFETY NOTICE:</strong>
    <span>All transactions should be made in person. Sending money online without meeting the seller is at your own risk. Campus Marketplace will not be held accountable for online transactions.</span>
</div>

<!-- FILTERS -->
<div class="glass filter-bar">
    <form action="index.php" method="GET" class="search-filter-row" style="display:flex; gap:0.75rem; width:100%; flex-wrap:wrap; align-items:center;" id="searchForm">
        <div style="flex:1; min-width:180px; position:relative;">
            <input type="text" name="search" id="searchInput" class="form-control" autocomplete="off" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" style="width:100%;">
            <div id="searchSuggestions" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:9999; max-height:200px; overflow-y:auto;"></div>
        </div>
        <select name="category" class="form-control" style="max-width:160px;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="min_price" class="form-control" placeholder="Min ₵" value="<?= htmlspecialchars($min_price) ?>" style="width:100px;">
        <input type="number" name="max_price" class="form-control" placeholder="Max ₵" value="<?= htmlspecialchars($max_price) ?>" style="width:100px;">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<!-- AD CAROUSEL (Context Aware) -->
<?php if(count($homepage_ads) > 0): ?>
<div style="margin-top:2rem; margin-bottom:1.5rem; position:relative;">
    <div id="adCarousel" class="horizontal-scroll-container legacy-home-ad-carousel" style="display:flex; gap:1rem; overflow-x:auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 10px 0; scrollbar-width:none;">
        <?php foreach($homepage_ads as $ad): ?>
        <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener" onclick="fetch('ad_click.php?id=<?= $ad['id'] ?>')" class="fade-in ad-item-link legacy-home-ad-card" data-ad-card="true" style="scroll-snap-align: start; text-decoration: none;">
            <div class="ad-image-container" style="border-radius:24px; overflow:hidden; border:1px solid rgba(0,0,0,0.05); position:relative; transition:all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
                <?php $ad_img = $ad['image_url'] ?? $ad['image_path'] ?? ''; ?>
                <?php if($ad_img): ?>
                    <img src="<?= htmlspecialchars($ad_img) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="legacy-home-ad-banner" loading="lazy" style="width:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    <div style="background:linear-gradient(135deg, #7c3aed, #a78bfa); color:#fff; padding:2.5rem; text-align:center; min-height:160px; display:flex; flex-direction:column; justify-content:center;">
                        <p style="font-size:0.7rem; letter-spacing:0.15em; text-transform:uppercase; opacity:0.8; margin-bottom:0.5rem; font-weight:700;">Sponsored Content</p>
                        <p style="font-size:1.5rem; font-weight:800; letter-spacing:-0.02em;"><?= htmlspecialchars($ad['title']) ?></p>
                    </div>
                <?php endif; ?>
                <div style="position:absolute; inset:auto 0 0 0; padding:1rem 1.1rem; background:linear-gradient(to top, rgba(0,0,0,0.68), rgba(0,0,0,0.08)); color:#fff;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem;">
                        <div style="min-width:0;">
                            <p style="font-size:0.68rem; letter-spacing:0.14em; text-transform:uppercase; opacity:0.78; margin:0 0 0.35rem; font-weight:700;">Sponsored</p>
                            <p style="font-size:1rem; font-weight:800; letter-spacing:-0.02em; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($ad['title']) ?></p>
                        </div>
                        <span style="background:rgba(255,255,255,0.14); color:#fff; font-size:0.62rem; padding:4px 10px; border-radius:999px; letter-spacing:0.08em; font-weight:700; border:1px solid rgba(255,255,255,0.15); flex-shrink:0;">AD</span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if(count($homepage_ads) > 1): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const carousel = document.getElementById('adCarousel');
            if(!carousel) return;
            const firstCard = carousel.querySelector('[data-ad-card]');
            if(!firstCard) return;

            const getStep = () => {
                const gap = parseFloat(getComputedStyle(carousel).gap || '0');
                return firstCard.getBoundingClientRect().width + gap;
            };

            const advance = () => {
                const maxScroll = carousel.scrollWidth - carousel.clientWidth;
                if (maxScroll <= 0) return;
                const nextScroll = carousel.scrollLeft + getStep();
                carousel.scrollTo({
                    left: nextScroll >= maxScroll - 10 ? 0 : Math.min(nextScroll, maxScroll),
                    behavior: 'smooth'
                });
            };

            let scrollInterval = setInterval(advance, 5000);
            carousel.addEventListener('mouseenter', () => clearInterval(scrollInterval));
            carousel.addEventListener('mouseleave', () => {
                scrollInterval = setInterval(advance, 5000);
            });
        });
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    let timeoutId;
    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim();
        clearTimeout(timeoutId);
        if (q.length < 2) { searchSuggestions.style.display = 'none'; return; }
        timeoutId = setTimeout(() => {
            fetch(`${window.MARKETPLACE_BASE_URL || '/'}api/search_suggest.php?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        searchSuggestions.innerHTML = '';
                        data.forEach(word => {
                            const wordDiv = document.createElement('div');
                            wordDiv.textContent = word;
                            wordDiv.style.cssText = 'padding:10px 16px; cursor:pointer; font-weight:500; border-bottom:1px solid rgba(0,0,0,0.05); transition:background 0.2s;';
                            wordDiv.onmouseover = () => wordDiv.style.background = 'rgba(124,58,237,0.06)';
                            wordDiv.onmouseout = () => wordDiv.style.background = 'transparent';
                            wordDiv.onclick = () => { searchInput.value = word; searchSuggestions.style.display = 'none'; document.getElementById('searchForm').submit(); };
                            searchSuggestions.appendChild(wordDiv);
                        });
                        searchSuggestions.style.display = 'block';
                    } else { searchSuggestions.style.display = 'none'; }
                }).catch(err => { searchSuggestions.style.display = 'none'; });
        }, 300);
    });
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });
});
</script>

<style>
    .legacy-home-ad-carousel::-webkit-scrollbar { display:none; }
    .legacy-home-ad-card { flex: 0 0 86%; max-width: 86%; }
    .legacy-home-ad-banner { height: 200px; object-fit: cover !important; }
    .listing-card {
        min-height: 100%;
    }
    .listing-card .product-img-wrap {
        aspect-ratio: 1 / 1;
    }
    .listing-card .product-body {
        gap: 0.45rem;
        align-items: stretch;
    }
    .listing-card .product-title {
        min-height: 2.7rem;
        margin: 0;
    }
    .listing-card .product-price {
        margin: 0;
    }
    .seller-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .seller-name {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        font-weight: 700;
        color: var(--text-main);
    }
    .seller-name-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .verified-pill,
    .tier-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        padding: 0.24rem 0.55rem;
        line-height: 1;
        white-space: nowrap;
    }
    .verified-pill {
        background: rgba(52, 199, 89, 0.14);
        color: #1f9d55;
        border: 1px solid rgba(52, 199, 89, 0.2);
    }
    .tier-pill {
        background: rgba(124, 58, 237, 0.12);
        color: var(--primary);
        border: 1px solid rgba(124, 58, 237, 0.2);
    }
    .seller-badges {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }
    .listing-meta {
        display: grid;
        gap: 0.35rem;
    }
    .stock-note {
        display: block;
        font-size: 0.65rem;
        font-weight: 700;
        margin-top: 0.1rem;
    }
    .stock-note.out {
        color: #ef4444;
    }
    .stock-note.low {
        color: #ff9500;
    }
    .listing-card .quick-add-btn {
        margin-top: auto;
    }
    @media (min-width: 768px) {
        .legacy-home-ad-card { flex-basis: calc(50% - 0.5rem); max-width: calc(50% - 0.5rem); }
        .legacy-home-ad-banner { height: 280px !important; }
        .ad-image-container:hover { transform: scale(1.005); filter: brightness(1.05); }
    }
    @media (max-width: 768px) {
        .listing-card {
            border-radius: 12px;
        }
        .listing-card .product-body {
            padding: 0.55rem;
            gap: 0.38rem;
        }
        .seller-row {
            align-items: flex-start;
        }
        .seller-name {
            max-width: 100%;
        }
        .verified-pill,
        .tier-pill {
            font-size: 0.56rem;
            padding: 0.22rem 0.45rem;
        }
    }
</style>

<h2 class="mb-2" style="font-size:1.3rem;">Approved Listings <span class="text-muted" style="font-size:0.9rem;">(<?= $total ?> items)</span></h2>

<?php if (empty($search) && empty($category) && $page == 1): ?>
    <?php 
    $ai_suggestions = get_smart_suggestions($pdo, 'home', $_SESSION['recent_views'] ?? [], 6, true); 
    ?>

    <?php if(count($ai_suggestions) > 0): ?>
    <div style="margin-bottom:2rem; position:relative;">
        <h3 class="mb-3" style="font-size:1.4rem; font-weight:800; display:flex; align-items:center; justify-content:space-between;">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#ai-grad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <defs><linearGradient id="ai-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#a78bfa"/></linearGradient></defs>
                    <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                </svg>
                Recommendations
            </span>
            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Swipe →</span>
        </h3>
        
        <div id="aiRecScroll" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
            <?php foreach($ai_suggestions as $mp): ?>
                <a href="product.php?id=<?= $mp['id'] ?>" class="scroll-card glass fade-in listing-card" style="scroll-snap-align: start; min-width: 160px;">
                    <div class="product-img-wrap" style="aspect-ratio: 4/3; max-height: 140px; border-radius: 12px; overflow:hidden;">
                        <?php if($mp['main_image']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($mp['main_image'])) ?>" alt="<?= htmlspecialchars($mp['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.1);">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body" style="padding:10px 6px;">
                        <p class="product-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.8rem; font-weight:700; margin:0;"><?= htmlspecialchars($mp['title']) ?></p>
                        <p class="product-price" style="font-size:0.9rem; font-weight:800; color:var(--primary); margin-top:2px;">₵<?= number_format($mp['price'], 2) ?></p>
                        <div class="listing-meta">
                            <div class="seller-row">
                                <span class="seller-name">
                                    <span class="seller-name-text"><?= htmlspecialchars($mp['username'] ?? 'Seller') ?></span>
                                </span>
                                <div class="seller-badges">
                                    <?php if (!empty($mp['verified'])): ?>
                                        <span class="verified-pill">Verified</span>
                                    <?php endif; ?>
                                    <span class="tier-pill"><?= strtoupper(htmlspecialchars($mp['seller_tier'] ?? 'basic')) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>


<div class="product-grid">
    <?php if (count($products) > 0): ?>
        <?php foreach($products as $p): ?>
            <a href="product.php?id=<?= $p['id'] ?>" class="glass product-card fade-in listing-card" style="flex-direction:column;">
                <div class="product-img-wrap" style="aspect-ratio: 1 / 1; border-radius: 14px; overflow:hidden;">
                    <?php if($p['main_image']): ?>
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.3);">No Image</div>
                    <?php endif; ?>

                    <?php if($p['boosted_until'] && strtotime($p['boosted_until']) > time()): ?>
                        <span class="boosted-badge" style="display:inline-flex; align-items:center; gap:0.25rem;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Boosted</span>
                        <span class="featured-badge" style="display:inline-flex; align-items:center; gap:0.25rem;"><svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Featured</span>
                    <?php endif; ?>
                    <?php $promo = isset($p['promo_tag']) ? trim($p['promo_tag']) : ''; ?>
                    <?php if($promo): ?>
                    <span class="boosted-badge" style="top:8px; bottom:auto; left:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:0.6rem; padding:0.3rem 0.6rem; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:0.25rem;">
                        <?= getPromoTagIcon($promo) ?> <?= htmlspecialchars($promo) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <p class="product-title" style="font-size:0.75rem;"><?= htmlspecialchars($p['title']) ?></p>
                    <?php if(!empty($p['original_price_before_discount']) && $p['original_price_before_discount'] > $p['price']): ?>
                        <p class="product-price">
                            <span style="text-decoration:line-through; opacity:0.5; font-size:0.75rem;">₵<?= number_format($p['original_price_before_discount'], 2) ?></span>
                            ₵<?= number_format($p['price'], 2) ?>
                            <span style="background:rgba(239,68,68,0.12); color:#ef4444; font-size:0.55rem; font-weight:700; padding:0.15rem 0.35rem; border-radius:4px;">
                                −<?= round(100 - ($p['price'] / $p['original_price_before_discount'] * 100)) ?>%
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="product-price">₵<?= number_format($p['price'], 2) ?></p>
                    <?php endif; ?>
                    <div class="listing-meta">
                        <div class="seller-row">
                            <span class="seller-name">
                                <span class="seller-name-text"><?= htmlspecialchars($p['username']) ?></span>
                            </span>
                            <div class="seller-badges">
                                <?php if (!empty($p['verified'])): ?>
                                    <span class="verified-pill">Verified</span>
                                <?php endif; ?>
                                <span class="tier-pill"><?= strtoupper(htmlspecialchars($p['seller_tier'] ?? 'basic')) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if($p['quantity'] <= 0): ?>
                        <span class="stock-note out">Out of Stock</span>
                    <?php elseif($p['quantity'] <= 5): ?>
                        <span class="stock-note low">Only <?= $p['quantity'] ?> left!</span>
                    <?php endif; ?>
                    <?php if($p['quantity'] > 0): ?>
                    <?php 
                        $cardImg = $p['main_image'] ? getAssetUrl('uploads/'.htmlspecialchars($p['main_image'])) : '';
                        $cardJsName = json_encode($p['title']);
                    ?>
                    <button type="button" class="quick-add-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); cmCart.add(<?= $p['id'] ?>, <?= $cardJsName ?>, <?= $p['price'] ?>, '<?= $cardImg ?>'); var _b=this; _b.textContent='Added'; setTimeout(function(){_b.textContent='+ Add';}, 1500);"
                    >+ Add</button>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No products found matching your criteria.</p>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if($total_pages > 1): ?>
<div class="flex gap-1 mt-3" style="justify-content:center;">
    <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>"
           class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
