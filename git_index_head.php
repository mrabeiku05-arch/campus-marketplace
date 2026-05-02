<?php
require_once 'includes/header.php';
require_once 'includes/ai_recommendations.php';

if (!isset($pdo)) {
    echo "<div class='glass form-container text-center'><h2>Run Setup</h2><a href='setup.php' class='btn btn-primary mt-2'>Initialize Database</a></div>";
    require_once 'includes/footer.php'; exit;
}

// 1. FORCED REVIEW BARRIER: Rule 10
if (isLoggedIn() && hasUnreviewedOrders($pdo, $_SESSION['user_id'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php') {
        $_SESSION['flash'] = "≡ƒöÆ REVIEW REQUIRED: Please submit a review for your recent purchase before you continue browsing.";
        header("Location: dashboard.php#buyer_orders");
        exit;
    }
}


// Database migrations moved to migrate.php for performance


// Fetch Context-Aware Ads (Multiple)
$homepage_ads = [];
$ad_loc = (!empty($category)) ? 'category' : 'homepage';
try {
    $stmt_ads = $pdo->prepare("SELECT * FROM ad_placements WHERE placement = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 5");
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
// Base query
if ($search) {
    $p_join = " JOIN users u ON p.user_id = u.id ";
    $p_order = " ORDER BY FIELD(u.seller_tier, 'premium', 'pro', 'basic'), p.boosted_until DESC, p.created_at DESC ";
    $stmt = $pdo->prepare("SELECT p.* FROM products p $p_join WHERE p.status='approved' AND (p.title LIKE ? OR p.category LIKE ? OR p.description LIKE ?) $p_order");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $products = $stmt->fetchAll();
    
    // TYPO-TOLERANT FALLBACK
    if (count($products) === 0 && strlen($search) > 3) {
        $stmt = $pdo->prepare("SELECT p.* FROM products p $p_join WHERE p.status='approved' AND (SOUNDEX(p.title) = SOUNDEX(?) OR p.title LIKE ?) $p_order LIMIT 10");
        $stmt->execute([$search, substr($search, 0, 4) . "%"]);
        $products = $stmt->fetchAll();
    }
    $total = count($products);
    $total_pages = 1;
} else {
    $seller_filter = trim($_GET['seller'] ?? '');

    // Base query - only approved + not on vacation
    $query = "SELECT p.*, u.username, u.seller_tier, u.profile_pic as seller_pic,
              (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
              (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
              FROM products p
              JOIN users u ON p.user_id = u.id
              WHERE p.status = 'approved' AND u.vacation_mode = 0";
    $count_query = "SELECT COUNT(*) FROM products p JOIN users u ON p.user_id = u.id WHERE p.status = 'approved' AND u.vacation_mode = 0";
    $params = [];

    if ($min_price !== '') { $query .= " AND p.price >= ?"; $count_query .= " AND p.price >= ?"; $params[] = $min_price; }
    if ($max_price !== '') { $query .= " AND p.price <= ?"; $count_query .= " AND p.price <= ?"; $params[] = $max_price; }
    if ($category)         { $query .= " AND p.category = ?"; $count_query .= " AND p.category = ?"; $params[] = $category; }
    if ($seller_filter)    { $query .= " AND u.username = ?"; $count_query .= " AND u.username = ?"; $params[] = $seller_filter; }

    $query .= " ORDER BY FIELD(u.seller_tier, 'premium', 'pro', 'basic'), (p.boosted_until > NOW()) DESC, p.created_at DESC LIMIT $per_page OFFSET $offset";

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
        <div style="padding:5rem 5% 3rem; background:linear-gradient(to bottom, rgba(124,58,237,0.06), transparent); border-bottom:1px solid rgba(0,0,0,0.05); text-align:center; margin-bottom:2rem;">
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
    <strong><span style="font-size:1.1rem; vertical-align:middle; margin-right:5px;">ΓÜá∩╕Å</span> SAFETY NOTICE:</strong>
    <span>All transactions should be made in person. Sending money online without meeting the seller is at your own risk. CampusMarketplace will not be held accountable for online transactions.</span>
</div>

<!-- FILTERS -->
<div class="glass filter-bar">
    <form action="index.php" method="GET" style="display:flex; gap:0.75rem; width:100%; flex-wrap:wrap; align-items:center;" id="searchForm">
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
        <input type="number" name="min_price" class="form-control" placeholder="Min Γé╡" value="<?= htmlspecialchars($min_price) ?>" style="width:100px;">
        <input type="number" name="max_price" class="form-control" placeholder="Max Γé╡" value="<?= htmlspecialchars($max_price) ?>" style="width:100px;">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<!-- AD CAROUSEL (Context Aware) -->
<?php if(count($homepage_ads) > 0): ?>
<div style="margin-top:2rem; margin-bottom:1.5rem; position:relative;">
    <div id="adCarousel" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 10px 0;">
        <?php foreach($homepage_ads as $ad): ?>
        <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener" onclick="fetch('ad_click.php?id=<?= $ad['id'] ?>')" class="fade-in ad-item-link" style="flex: 0 0 100%; scroll-snap-align: start; min-width: 100%; text-decoration: none;">
            <div class="ad-image-container" style="border-radius:24px; overflow:hidden; border:1px solid rgba(0,0,0,0.05); position:relative; transition:all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
                <?php if($ad['image_url']): ?>
                    <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="ad-banner-img" loading="lazy" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    <div style="background:linear-gradient(135deg, #7c3aed, #a78bfa); color:#fff; padding:2.5rem; text-align:center; min-height:160px; display:flex; flex-direction:column; justify-content:center;">
                        <p style="font-size:0.7rem; letter-spacing:0.15em; text-transform:uppercase; opacity:0.8; margin-bottom:0.5rem; font-weight:700;">Sponsored Content</p>
                        <p style="font-size:1.5rem; font-weight:800; letter-spacing:-0.02em;"><?= htmlspecialchars($ad['title']) ?></p>
                    </div>
                <?php endif; ?>
                <span style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,0.4); color:#fff; font-size:0.6rem; padding:4px 10px; border-radius:8px; letter-spacing:0.08em; font-weight:700; border:1px solid rgba(255,255,255,0.1);">AD</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Navigation dots for ads if more than 1 -->
    <?php if(count($homepage_ads) > 1): ?>
    <div style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:10; background:rgba(0,0,0,0.5); padding:4px 10px; border-radius:10px;">
        <?php foreach($homepage_ads as $idx => $ad): ?>
            <div class="ad-dot" style="width:6px; height:6px; border-radius:50%; background:<?= $idx === 0 ? '#fff' : 'rgba(255,255,255,0.4)' ?>; transition:all 0.2s;"></div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const carousel = document.getElementById('adCarousel');
            if(!carousel) return;
            const dots = document.querySelectorAll('.ad-dot');
            
            carousel.addEventListener('scroll', () => {
                const idx = Math.round(carousel.scrollLeft / carousel.offsetWidth);
                dots.forEach((dot, i) => {
                    dot.style.background = (i === idx) ? '#fff' : 'rgba(255,255,255,0.4)';
                    dot.style.width = (i === idx) ? '12px' : '6px';
                    dot.style.borderRadius = (i === idx) ? '3px' : '50%';
                });
            });

            // Auto-scroll logic
            let scrollInterval = setInterval(() => {
                const nextIdx = (Math.round(carousel.scrollLeft / carousel.offsetWidth) + 1) % <?= count($homepage_ads) ?>;
                carousel.scrollTo({
                    left: nextIdx * carousel.offsetWidth,
                    behavior: 'smooth'
                });
            }, 6000); // 6 seconds per ad

            carousel.addEventListener('mouseenter', () => clearInterval(scrollInterval));
            carousel.addEventListener('mouseleave', () => {
                scrollInterval = setInterval(() => {
                    const nextIdx = (Math.round(carousel.scrollLeft / carousel.offsetWidth) + 1) % <?= count($homepage_ads) ?>;
                    carousel.scrollTo({
                        left: nextIdx * carousel.offsetWidth,
                        behavior: 'smooth'
                    });
                }, 6000);
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
        
        if (q.length < 2) {
            searchSuggestions.style.display = 'none';
            return;
        }

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
                            wordDiv.onclick = () => {
                                searchInput.value = word;
                                searchSuggestions.style.display = 'none';
                                document.getElementById('searchForm').submit();
                            };
                            searchSuggestions.appendChild(wordDiv);
                        });
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                }).catch(err => { console.error('Suggestion Error:', err); searchSuggestions.style.display = 'none'; });
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
    .ad-banner-img { height: 180px; object-fit: cover !important; }
    @media (min-width: 768px) {
        .ad-banner-img { height: 420px !important; }
        .ad-image-container:hover { transform: scale(1.005); filter: brightness(1.05); }
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
            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Swipe ΓåÆ</span>
        </h3>
        
        <div id="aiRecScroll" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
            <?php foreach($ai_suggestions as $mp): ?>
                <a href="product.php?id=<?= $mp['id'] ?>" class="scroll-card glass fade-in" style="scroll-snap-align: start; min-width: 160px;">
                    <div class="product-img-wrap" style="aspect-ratio: 4/3; max-height: 140px; border-radius: 12px; overflow:hidden;">
                        <?php if($mp['main_image']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($mp['main_image'])) ?>" alt="<?= htmlspecialchars($mp['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.1);">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body" style="padding:10px 6px;">
                        <p class="product-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.8rem; font-weight:700; margin:0;"><?= htmlspecialchars($mp['title']) ?></p>
                        <p class="product-price" style="font-size:0.9rem; font-weight:800; color:var(--primary); margin-top:2px;">Γé╡<?= number_format($mp['price'], 2) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const s = document.getElementById('aiRecScroll');
                if(!s) return;
                let auto = setInterval(() => {
                    if(s.scrollLeft + s.clientWidth >= s.scrollWidth - 10) s.scrollTo({left:0, behavior:'smooth'});
                    else s.scrollBy({left:188, behavior:'smooth'});
                }, 2000);
                s.addEventListener('mouseenter', () => clearInterval(auto));
                s.addEventListener('mouseleave', () => auto = setInterval(() => {
                    if(s.scrollLeft + s.clientWidth >= s.scrollWidth - 10) s.scrollTo({left:0, behavior:'smooth'});
                    else s.scrollBy({left:188, behavior:'smooth'});
                }, 2000));
            });
        </script>
    </div>
    
    <?php endif; ?>
<?php endif; ?>


<div class="product-grid">
    <?php if (count($products) > 0): ?>
        <?php foreach($products as $p): ?>
            <a href="product.php?id=<?= $p['id'] ?>" class="glass product-card fade-in" style="flex-direction:column;">
                <div class="product-img-wrap" style="aspect-ratio: 1 / 1; border-radius: 14px; overflow:hidden;">
                    <?php if($p['main_image']): ?>
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.3);">No Image</div>
                    <?php endif; ?>

                    <?php if($p['boosted_until'] && strtotime($p['boosted_until']) > time()): ?>
                        <span class="boosted-badge">ΓÜí Boosted</span>
                        <span class="featured-badge">Γ¡É Featured</span>
                    <?php endif; ?>
                    <?php
                        $promo = isset($p['promo_tag']) ? trim($p['promo_tag']) : '';
                    ?>
                    <?php if($promo): ?>
                    <span class="boosted-badge" style="top:8px; bottom:auto; left:8px; background:rgba(0,0,0,0.85); color:#fff; font-size:0.6rem; padding:0.3rem 0.6rem; border-radius:8px; font-weight:600; box-shadow:0 2px 8px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.15); display:flex; align-items:center; gap:4px; letter-spacing:0.02em;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        <?= htmlspecialchars(str_replace(['ΓÜí ', 'ΓÅ│ ', '≡ƒÄô ', '≡ƒôª ', '≡ƒÅ╖∩╕Å '], '', $promo)) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <p class="product-title" style="font-size:0.75rem;"><?= htmlspecialchars($p['title']) ?></p>
                    <?php if(!empty($p['original_price_before_discount']) && $p['original_price_before_discount'] > $p['price']): ?>
                        <p class="product-price">
                            <span style="text-decoration:line-through; opacity:0.5; font-size:0.75rem; font-weight:400;">Γé╡<?= number_format($p['original_price_before_discount'], 2) ?></span>
                            Γé╡<?= number_format($p['price'], 2) ?>
                            <span style="background:rgba(239,68,68,0.12); color:#ef4444; font-size:0.55rem; font-weight:700; padding:0.15rem 0.35rem; border-radius:4px; margin-left:2px;">
                                ΓêÆ<?= round(100 - ($p['price'] / $p['original_price_before_discount'] * 100)) ?>%
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="product-price">Γé╡<?= number_format($p['price'], 2) ?></p>
                    <?php endif; ?>
                    <p class="product-meta">
                        By <?= htmlspecialchars($p['username']) ?>
                        <?= getBadgeHtml($pdo, $p['seller_tier'] ?? 'basic') ?>
                    </p>

                    <?php if($p['quantity'] <= 0): ?>
                        <span style="display:block; font-size:0.65rem; color:#ef4444; font-weight:700; margin-top:0.2rem;">≡ƒÜ½ Out of Stock</span>
                    <?php elseif($p['quantity'] <= 5): ?>
                        <span style="display:block; font-size:0.65rem; color:#ff9500; font-weight:700; margin-top:0.2rem;">≡ƒöÑ Only <?= $p['quantity'] ?> left!</span>
                    <?php endif; ?>
                    <?php if($p['quantity'] > 0): ?>
                    <?php 
                        $cardImg = $p['main_image'] ? getAssetUrl('uploads/'.htmlspecialchars($p['main_image'])) : '';
                        $cardJsName = json_encode($p['title']);
                    ?>
                    <button
                        type="button"
                        class="quick-add-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); cmCart.add(<?= $p['id'] ?>, <?= $cardJsName ?>, <?= $p['price'] ?>, '<?= $cardImg ?>'); var _b=this; _b.textContent='Γ£ô Added'; setTimeout(function(){_b.textContent='+ Add';}, 1500);"
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
