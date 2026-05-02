<?php
require_once 'includes/db.php';
require_once 'includes/ai_recommendations.php';
if (!isset($_GET['id'])) redirect('index.php');

      $pid = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT p.*, u.username as seller, u.id as seller_id, u.seller_tier, u.profile_pic as seller_pic, u.department, u.level, u.hall, u.phone, u.whatsapp, u.verified, u.last_seen, u.vacation_mode
    FROM products p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$pid]);
$product = $stmt->fetch();
if (!$product) redirect('index.php');

// Define $isOwner BEFORE any usage
$isOwner = isLoggedIn() && $_SESSION['user_id'] == $product['seller_id'];

$canReview = false;
$hasActiveOrder = false;
$canContactSeller = false;
if (isLoggedIn() && !$isOwner) {
    $reviewAccessStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND buyer_id = ? AND status = 'completed'");
    $reviewAccessStmt->execute([$pid, $_SESSION['user_id']]);
    $canReview = ((int)$reviewAccessStmt->fetchColumn() > 0);

    $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND buyer_id = ? AND status IN ('ordered','seller_seen','delivered')");
    $activeCheckStmt->execute([$pid, $_SESSION['user_id']]);
    $hasActiveOrder = ((int)$activeCheckStmt->fetchColumn() > 0);

    $canContactSeller = $hasActiveOrder || $canReview;
}

// 1. FORCED REVIEW BARRIER: Rule 10
if (isLoggedIn() && hasUnreviewedOrders($pdo, $_SESSION['user_id'])) {
    if (!$isOwner && !$canReview) {
        $_SESSION['flash'] = "🔒 REVIEW REQUIRED: You must submit a review for your previously completed order before viewing more products.";
        header("Location: dashboard.php#buyer_orders");
        exit;
    }
}
if ($product['status'] !== 'approved' && !isAdmin() && !$isOwner) {
    require_once 'includes/header.php';
    echo "<div class='text-center' style='padding:4rem;'><h2>This product is not currently available.</h2><a href='index.php' class='btn btn-primary mt-3'>Browse Products</a></div>";
    require_once 'includes/footer.php'; exit;
}

// Increment views
if (!$isOwner) { $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$pid]); }

$images = getProductImages($pdo, $pid);
$rating = getAvgRating($pdo, $pid);
$reviewCount = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ?"); $reviewCount->execute([$pid]); $reviewCount = $reviewCount->fetchColumn();

// Handle review submission
$reviewMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review']) && $canReview) {
    check_csrf();
    $r_rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $r_comment = trim($_POST['comment'] ?? '');
    try {
        $updateReview = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, created_at = NOW() WHERE product_id = ? AND user_id = ?");
        $updateReview->execute([$r_rating, $r_comment, $pid, $_SESSION['user_id']]);

        if ($updateReview->rowCount() === 0) {
            $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)")
                ->execute([$pid, $_SESSION['user_id'], $r_rating, $r_comment]);
        }
        $reviewMsg = "Review submitted!";
        $rating = getAvgRating($pdo, $pid);
    } catch(Exception $e) {
        error_log('legacy/product.php review save failed: ' . $e->getMessage());
        $reviewMsg = "Could not save review.";
    }
}

// Handle purchase
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy']) && isLoggedIn() && $product['status'] === 'approved' && !$isOwner) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$_SESSION['user_id']]);
        $buyer = $stmt->fetch();
        if (!$buyer || $buyer['balance'] < $product['price']) throw new Exception("Insufficient wallet balance.");

        $price = $product['price'];
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$price, $_SESSION['user_id']]);
        $pdo->prepare("INSERT INTO transactions (user_id,type,amount,status,reference,description) VALUES (?,'purchase',?,'completed',?,?)")->execute([$_SESSION['user_id'], $price, generateRef('PUR'), "Purchased: ".$product['title']]);
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$price, $product['seller_id']]);
        $pdo->prepare("INSERT INTO transactions (user_id,type,amount,status,reference,description) VALUES (?,'sale',?,'completed',?,?)")->execute([$product['seller_id'], $price, generateRef('SAL'), "Sold: ".$product['title']]);

        // Reduce quantity
        $pdo->prepare("UPDATE products SET quantity = quantity - 1, clicks = clicks + 1 WHERE id = ?")->execute([$pid]);
        $newQty = $pdo->prepare("SELECT quantity FROM products WHERE id = ?"); $newQty->execute([$pid]); $q = $newQty->fetchColumn();
        if ($q <= 0) { $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$pid]); }

        $pdo->commit();
        echo "<script>alert('Purchase successful!');window.location='dashboard.php';</script>"; exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch reviews
$reviews = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 10");
$reviews->execute([$pid]); $reviews = $reviews->fetchAll();

$isOnline = $product['last_seen'] && (time() - strtotime($product['last_seen'])) < 300;

// ── Seller Trust Panel Data ──
$sellerProductCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status='approved'");
$sellerProductCount->execute([$product['seller_id']]); $sellerTotalProducts = $sellerProductCount->fetchColumn();
$sellerAvgRating = $pdo->prepare("SELECT ROUND(AVG(r.rating),1) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.user_id = ?");
$sellerAvgRating->execute([$product['seller_id']]); $sellerRating = $sellerAvgRating->fetchColumn() ?: 0;
$sellerSalesCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND type='sale'");
$sellerSalesCount->execute([$product['seller_id']]); $sellerSales = $sellerSalesCount->fetchColumn();

// ── Activity Indicator Data ──
$viewingNow = max(1, $product['views'] % 7 + rand(1,4)); // Simulated from real views
$soldLast24 = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE description LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$soldLast24->execute(['%' . $product['title'] . '%']); $sold24h = $soldLast24->fetchColumn();

// ── Trust Badges Logic ──
$badges = [];
if ($product['verified']) $badges[] = ['icon' => '✓', 'label' => 'Verified Seller', 'color' => '#34c759'];
if ($sellerRating >= 4.0) $badges[] = ['icon' => '⭐', 'label' => 'Top Rated', 'color' => '#ff9500'];
if ($sellerSales >= 5) $badges[] = ['icon' => '⚡', 'label' => 'Fast Seller', 'color' => '#7c3aed'];
if ($product['seller_tier'] === 'premium') $badges[] = ['icon' => '💎', 'label' => 'Premium', 'color' => '#af52de'];

// Fetch more from seller
$more_stmt = $pdo->prepare("SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image FROM products p WHERE p.user_id = ? AND p.status = 'approved' AND p.id != ? LIMIT 6");
$more_stmt->execute([$product['seller_id'], $pid]);
$more_products = $more_stmt->fetchAll();

// Track recent views for AI Session context
if (!isset($_SESSION['recent_views'])) $_SESSION['recent_views'] = [];
$recent_product = ['id' => $product['id'], 'title' => $product['title'], 'category' => $product['category']];
$_SESSION['recent_views'] = array_filter($_SESSION['recent_views'], function($v) use($pid) { return $v['id'] !== $pid; });
array_unshift($_SESSION['recent_views'], $recent_product);
if (count($_SESSION['recent_views']) > 15) array_pop($_SESSION['recent_views']);

// Get AI Smart Suggestions
$ai_suggestions = get_smart_suggestions($pdo, 'product', $product, 4);

// Fetch Product-Specific Ads
$product_ads = [];
try {
    $stmt_ads = $pdo->prepare("SELECT * FROM ad_placements WHERE placement = 'product' AND is_active = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt_ads->execute();
    $product_ads = $stmt_ads->fetchAll();
    
    if (count($product_ads) > 0) {
        $ad_ids = array_column($product_ads, 'id');
        $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
        $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id IN ($placeholders)")->execute($ad_ids);
    }
} catch(Exception $e) {}

require_once 'includes/header.php';
?>

<div class="fade-in" style="max-width:1000px; margin:0 auto;">
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($product['status'] !== 'approved'): ?>
        <div class="alert alert-warning">Status: <strong><?= strtoupper($product['status']) ?></strong> — This product is not visible to buyers.</div>
    <?php endif; ?>

    <!-- FIXED: Responsive grid — stacked on mobile, side-by-side on desktop -->
    <div class="glass" style="padding:2rem; display:grid; grid-template-columns:1fr; gap:2rem;">
        <style>
            @media(min-width:768px) { .product-detail-grid { grid-template-columns:1fr 1fr !important; } }
            .btn-add-cart { background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:0.9rem 1.5rem; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; text-align:center; transition:all 0.3s; box-shadow:0 4px 15px rgba(16,185,129,0.3); }
            .btn-add-cart:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.4); }
            .btn-wishlist { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.9rem 1.5rem; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; width:100%; text-align:center; transition:all 0.3s; }
            .btn-wishlist:hover { background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.3); }
            .btn-wishlist.wishlist-active { background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); }
        </style>
        <!-- Images -->
        <div class="product-detail-grid" style="display:grid; grid-template-columns:1fr; gap:2.5rem; align-items:start;">
        <div style="display:flex; flex-direction:column; align-items:center;">
            <?php if(count($images) > 0): ?>
                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($images[0]['image_path'])) ?>" id="mainImg" alt="<?= htmlspecialchars($product['title']) ?>" style="width:100%; max-width:450px; height:340px; object-fit:fill; border-radius:18px; box-shadow:0 12px 32px rgba(0,0,0,0.08); cursor:pointer;" onclick="toggleAutoPlay()">
                <?php if(count($images) > 1): ?>
                <div class="flex gap-1 mt-3" style="overflow-x:auto; max-width:480px; padding-bottom:0.5rem;">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($img['image_path'])) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid var(--border);transition:all 0.2s;" onclick="changeImg(<?= $idx ?>, this.src)" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                    <?php endforeach; ?>
                </div>
                <script>
                    let currentImageIdx = 0;
                    const imagesArr = <?= json_encode(array_column($images, 'image_path')) ?>;
                    let autoPlayInterval = null;
                    function changeImg(idx, src) {
                        currentImageIdx = idx;
                        document.getElementById('mainImg').src = src || ('uploads/' + imagesArr[idx]);
                    }
                    function toggleAutoPlay() {
                        if(autoPlayInterval) {
                            clearInterval(autoPlayInterval);
                            autoPlayInterval = null;
                        } else {
                            autoPlayInterval = setInterval(() => {
                                currentImageIdx = (currentImageIdx + 1) % imagesArr.length;
                                changeImg(currentImageIdx);
                            }, 1000);
                        }
                    }
                </script>
                <?php endif; ?>
            <?php else: ?>
                <div style="width:100%;height:300px;background:rgba(0,0,0,0.3);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#555;">No Image</div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div style="display:flex; flex-direction:column;">
            <div style="flex:1;">
                <h1 style="font-size:1.4rem; font-weight:800; line-height:1.2; letter-spacing:-0.03em; margin-bottom:0.4rem;"><?= htmlspecialchars($product['title']) ?></h1>
                
                <!-- PRICE EMPHASIS -->
                <div style="margin:0.6rem 0;">
                    <?php if(!empty($product['original_price_before_discount']) && $product['original_price_before_discount'] > $product['price']): ?>
                        <span style="text-decoration:line-through; color:#86868b; font-size:1rem; font-weight:400;">₵<?= number_format($product['original_price_before_discount'], 2) ?></span>
                        <span style="color:var(--primary); font-size:2rem; font-weight:900; margin-left:0.5rem; letter-spacing:-0.03em;">₵<?= number_format($product['price'], 2) ?></span>
                        <span style="background:rgba(239,68,68,0.12); color:#ef4444; font-size:0.7rem; font-weight:700; padding:0.2rem 0.5rem; border-radius:6px; margin-left:0.4rem;">
                            -<?= round(100 - ($product['price'] / $product['original_price_before_discount'] * 100)) ?>% OFF
                        </span>
                    <?php else: ?>
                        <span style="color:var(--primary); font-size:2rem; font-weight:900; letter-spacing:-0.03em;">₵<?= number_format($product['price'], 2) ?></span>
                    <?php endif; ?>
                </div>

                <!-- LOW STOCK URGENCY -->
                <?php if($product['quantity'] <= 0): ?>
                    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:0.6rem 1rem; margin:0.5rem 0; display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1rem;">🚫</span>
                        <span style="color:#ef4444; font-weight:700; font-size:0.85rem;">Out of Stock</span>
                    </div>
                <?php elseif($product['quantity'] <= 5): ?>
                    <div style="background:rgba(255,149,0,0.08); border:1px solid rgba(255,149,0,0.2); border-radius:10px; padding:0.6rem 1rem; margin:0.5rem 0; display:flex; align-items:center; gap:0.5rem; animation: urgencyPulse 2s infinite;">
                        <span style="font-size:1rem;">🔥</span>
                        <span style="color:#ff9500; font-weight:700; font-size:0.85rem;">Only <?= $product['quantity'] ?> left in stock — order soon!</span>
                    </div>
                <?php endif; ?>

                <!-- ACTIVITY INDICATOR -->
                <div style="display:flex; gap:1rem; flex-wrap:wrap; margin:0.5rem 0 0.8rem;">
                    <span style="font-size:0.78rem; color:#86868b; display:flex; align-items:center; gap:4px;">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#34c759; animation: blink 1.5s infinite;"></span>
                        <?= $viewingNow ?> people viewing now
                    </span>
                    <?php if($sold24h > 0): ?>
                        <span style="font-size:0.78rem; color:#86868b;">⚡ <?= $sold24h ?> sold in last 24h</span>
                    <?php endif; ?>
                    <span style="font-size:0.78rem; color:#86868b;">👁 <?= $product['views'] ?> views</span>
                </div>

                <div class="flex gap-1 mb-2" style="flex-wrap:wrap;">
                    <span class="badge badge-blue"><?= htmlspecialchars($product['category']) ?></span>
                    <?php if($rating > 0): ?><span class="badge badge-gold">⭐ <?= $rating ?>/5 (<?= $reviewCount ?>)</span><?php endif; ?>
                </div>

                <!-- TRUST BADGES -->
                <?php if(count($badges) > 0): ?>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin:0.6rem 0;">
                    <?php foreach($badges as $b): ?>
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:0.7rem; font-weight:600; padding:0.3rem 0.65rem; border-radius:8px; background:<?= $b['color'] ?>15; color:<?= $b['color'] ?>; border:1px solid <?= $b['color'] ?>30;">
                            <?= $b['icon'] ?> <?= $b['label'] ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- DELIVERY INFO -->
                <?php
                    $del_meth = isset($product['delivery_method']) ? $product['delivery_method'] : 'Pickup';
                    $pay_agree = isset($product['payment_agreement']) ? $product['payment_agreement'] : 'Pay on delivery';
                ?>
                <div style="display:flex; flex-direction:column; gap:0.4rem; padding:0.8rem 0; border-top:1px solid rgba(0,0,0,0.05); border-bottom:1px solid rgba(0,0,0,0.05); margin:0.5rem 0;">
                   <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1rem;">🚚</span>
                        <span style="font-size:0.85rem; color:var(--text-muted);">Delivery Method: <strong style="color:var(--text-main);"><?= htmlspecialchars($del_meth) ?></strong></span>
                   </div>
                   <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1rem;">💳</span>
                        <span style="font-size:0.85rem; color:var(--text-muted);">Payment Agreement: <strong style="color:var(--text-main);"><?= htmlspecialchars($pay_agree) ?></strong></span>
                   </div>
                </div>

                <!-- SELLER TRUST PANEL -->
                <div class="glass" style="padding:1.25rem; border-radius:16px; margin:0.8rem 0;">
                    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.8rem;">
                        <?php if($product['seller_pic']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($product['seller_pic'])) ?>" class="profile-pic" style="width:44px;height:44px;" alt="">
                        <?php else: ?>
                            <div class="profile-pic" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:rgba(124,58,237,0.1);color:var(--primary);font-weight:700;font-size:1.1rem;"><?= strtoupper(substr($product['seller'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div style="flex:1;">
                            <strong style="font-size:0.95rem;"><?= htmlspecialchars($product['seller']) ?></strong>
                            <?php if($product['verified']): ?><span style="color:var(--success); font-size:0.85rem;"> ✓</span><?php endif; ?>
                            <div style="font-size:0.75rem; color:#86868b; display:flex; gap:0.5rem; margin-top:2px;">
                                <span class="online-dot" style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= $isOnline ? 'var(--success)' : '#999' ?>;"></span>
                                <?= $isOnline ? 'Online now' : 'Offline' ?>
                            </div>
                        </div>
                    </div>
                    <!-- Seller Stats Row -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem; text-align:center; padding:0.6rem 0; border-top:1px solid rgba(0,0,0,0.05); border-bottom:1px solid rgba(0,0,0,0.05); margin-bottom:0.8rem;">
                        <div><div style="font-weight:800; font-size:1rem; color:var(--primary);"><?= $sellerTotalProducts ?></div><div style="font-size:0.65rem; color:#86868b;">Products</div></div>
                        <div><div style="font-weight:800; font-size:1rem; color:#ff9500;"><?= $sellerRating ?: 'N/A' ?></div><div style="font-size:0.65rem; color:#86868b;">Rating</div></div>
                        <div><div style="font-weight:800; font-size:1rem; color:var(--success);"><?= $sellerSales ?></div><div style="font-size:0.65rem; color:#86868b;">Sales</div></div>
                    </div>
                    <!-- Contact Buttons -->
                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                        <?php if(!empty($product['phone'])): ?>
                            <?php if($canContactSeller): ?>
                                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $product['phone'])) ?>" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; background:#fff; border:1px solid rgba(0,0,0,0.1); color:#1d1d1f; font-weight:600; padding:0.55rem 0.7rem; border-radius:10px; font-size:0.8rem; text-decoration:none; transition:all 0.2s;">
                                    📱 Call
                                </a>
                            <?php else: ?>
                                <span style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; background:#fff; border:1px solid rgba(0,0,0,0.1); color:#aaa; font-weight:600; padding:0.55rem 0.7rem; border-radius:10px; font-size:0.8rem; cursor:not-allowed; opacity:0.5;" title="Confirm order first">
                                    🔒 Call
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(!empty($product['whatsapp'])): ?>
                            <?php if($canContactSeller): ?>
                                <a href="<?= formatWhatsAppLink($product['whatsapp']) ?>" target="_blank" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; background:#248a3d; color:#fff; font-weight:600; padding:0.55rem 0.7rem; border-radius:10px; font-size:0.8rem; text-decoration:none; border:none; transition:all 0.2s;">
                                    💬 WhatsApp
                                </a>
                            <?php else: ?>
                                <span style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; background:#248a3d; color:#fff; font-weight:600; padding:0.55rem 0.7rem; border-radius:10px; font-size:0.8rem; border:none; cursor:not-allowed; opacity:0.5;" title="Confirm order first">
                                    🔒 WhatsApp
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(isLoggedIn() && !$isOwner): ?>
                            <a href="chat.php?user=<?= $product['seller_id'] ?>" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; background:#7c3aed; color:#fff; font-weight:600; padding:0.55rem 0.7rem; border-radius:10px; font-size:0.8rem; text-decoration:none; border:none; transition:all 0.2s;">
                                ✉️ Message
                            </a>
                        <?php endif; ?>
                    </div>
                        <?php if(count($more_products) > 0): ?>
                        <a href="index.php?seller=<?= urlencode($product['seller']) ?>" style="display:block; text-align:center; font-size:0.78rem; color:var(--primary); font-weight:600; margin-top:0.6rem; text-decoration:none;">View more from this seller →</a>
                    <?php endif; ?>
                </div>

                <h4 class="mb-1" style="font-size:0.95rem;">Description</h4>
                <p style="line-height:1.7; white-space:pre-wrap; font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($product['description']) ?></p>
            </div>

            <!-- ADDED: Cart + Wishlist + Buy Buttons -->
            <?php if($product['status'] === 'approved' && !$isOwner): ?>
                <?php
                    $productMainImage = count($images) > 0 ? getAssetUrl('uploads/' . htmlspecialchars($images[0]['image_path'])) : '';
                    $jsName = json_encode($product['title']);
                    $jsPrice = $product['price'];
                    $jsId = $product['id'];
                ?>
                <div style="display:flex; flex-direction:column; gap:0.75rem; margin-top:1.5rem;">
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button 
                            type="button" 
                            class="btn-add-cart"
                            style="flex:1;"
                            onclick='cmCart.add(<?= $jsId ?>, <?= $jsName ?>, <?= $jsPrice ?>, "<?= $productMainImage ?>")'
                        >
                            🛒 Add to Cart
                        </button>
                        
                        <?php if(isLoggedIn()): ?>
                            <a href="chat.php?user=<?= $product['seller_id'] ?>" style="flex:1; background:#7c3aed; color:#fff; padding:0.9rem; border-radius:12px; font-weight:700; text-align:center; text-decoration:none; transition:all 0.3s;">✉️ Message Seller</a>
                        <?php else: ?>
                            <a href="login.php" style="flex:1; background:#7c3aed; color:#fff; padding:0.9rem; border-radius:12px; font-weight:700; text-align:center; text-decoration:none; transition:all 0.3s;">Login to Message</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(isLoggedIn()): ?>
                        <a href="tel:<?= htmlspecialchars($product['phone'] ?? '#') ?>" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1.05rem;padding:0.9rem;" onclick="return confirm('Do you want to dial the seller\'s number now?')">📞 Buy Now (Call Seller)</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary" style="width:100%;justify-content:center;text-align:center;">Login to Buy</a>
                    <?php endif; ?>
                </div>

                <!-- ADDED: Initialize wishlist heart state on page load -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (window.cmWishlist && cmWishlist.has(<?= $jsId ?>)) {
                            cmWishlist.updateHearts(<?= $jsId ?>, true);
                        }
                    });
                </script>
            <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- STICKY MOBILE ADD TO CART -->
    <?php if($product['status'] === 'approved' && !$isOwner && $product['quantity'] > 0): ?>
    <div id="stickyMobileCta" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999; background:rgba(255,255,255,0.92); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border-top:1px solid rgba(0,0,0,0.08); padding:0.6rem 1rem; box-shadow:0 -4px 20px rgba(0,0,0,0.06);">
        <div style="display:flex; align-items:center; gap:0.6rem; max-width:600px; margin:0 auto;">
            <div style="flex:1;">
                <p style="font-weight:800; font-size:1.1rem; color:var(--primary); margin:0;">₵<?= number_format($product['price'], 2) ?></p>
                <p style="font-size:0.65rem; color:#86868b; margin:0;"><?= $product['quantity'] ?> in stock</p>
            </div>
            <button
                type="button"
                class="btn-add-cart"
                style="flex:2; padding:0.75rem 1rem; font-size:0.95rem;"
                onclick='cmCart.add(<?= $jsId ?>, <?= $jsName ?>, <?= $jsPrice ?>, "<?= $productMainImage ?>")'
            >
                🛒 Add to Cart
            </button>
        </div>
    </div>
    <script>
        (function() {
            if (window.innerWidth > 768) return;
            var sticky = document.getElementById('stickyMobileCta');
            window.addEventListener('scroll', function() {
                sticky.style.display = window.scrollY > 350 ? 'block' : 'none';
            });
        })();
    </script>
    <?php endif; ?>
    
    <style>
        @keyframes urgencyPulse { 0%,100%{ opacity:1; } 50%{ opacity:0.7; } }
        @keyframes blink { 0%,100%{ opacity:1; } 50%{ opacity:0.3; } }
        
        /* AD RESPONSIVENESS */
        .ad-banner-img-prod { height: 160px; object-fit: cover !important; }
        @media (min-width: 768px) {
            .ad-banner-img-prod { height: 320px !important; }
            .ad-image-container:hover { transform: translateY(-3px) scale(1.002); }
        }
    </style>

    <!-- Reviews Section -->
    <?php if(isset($_GET['review_required'])): ?>
    <div class="alert alert-warning fade-in" style="font-size:1.1rem; padding:1.5rem; border:2px solid #ff9500; text-align:center; animation:urgencyPulse 2s infinite;">
        ⚠️ <strong>ACTION REQUIRED:</strong> You must review your purchase of this product to continue using the marketplace.
    </div>
    <?php endif; ?>
    <div id="review" class="glass mt-3" style="padding:2rem;">
        <h3 class="mb-2">Reviews & Ratings</h3>

        <?php if($reviewMsg): ?><div class="alert alert-success"><?= htmlspecialchars($reviewMsg) ?></div><?php endif; ?>

        <?php if(isLoggedIn() && !$isOwner): ?>
        <form method="POST" style="margin-bottom:1.5rem; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label for="rating">Rating</label>
                <select name="rating" id="rating" class="form-control" style="width:80px;">
                    <option value="5">⭐ 5</option><option value="4">⭐ 4</option><option value="3">⭐ 3</option><option value="2">⭐ 2</option><option value="1">⭐ 1</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; flex:1;">
                <label for="comment">Comment</label>
                <input type="text" name="comment" id="comment" class="form-control" placeholder="Write a short review...">
            </div>
            <button type="submit" name="review" class="btn btn-primary btn-sm">Submit</button>
        </form>
        <?php endif; ?>

        <?php if(count($reviews) > 0): ?>
            <?php foreach($reviews as $rv): ?>
            <div style="padding:0.75rem 0; border-bottom:1px solid var(--border);">
                <div class="flex-between">
                    <strong style="font-size:0.9rem;"><?= htmlspecialchars($rv['username']) ?></strong>
                    <span style="color:var(--gold);"><?= str_repeat('⭐', $rv['rating']) ?></span>
                </div>
                <?php if($rv['comment']): ?><p class="text-muted" style="font-size:0.85rem; margin-top:0.3rem;"><?= htmlspecialchars($rv['comment']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No reviews yet.</p>
        <?php endif; ?>
    </div>
</div>

    <!-- Recommendations -->
    <?php if(!empty($ai_suggestions) && count($ai_suggestions) > 0): ?>
    <div class="glass mt-3" style="padding:1.5rem;">
        <h3 class="mb-3" style="font-size:1.1rem; font-weight:700; display:flex; align-items:center; justify-content:space-between;">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                </svg>
                Recommendations
            </span>
            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:500;">Swipe for more →</span>
        </h3>
        
        <!-- AD CAROUSEL (Product Page) -->
        <?php if(count($product_ads) > 0): ?>
        <div style="margin-bottom:1.5rem; position:relative;">
            <div id="adCarouselProduct" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 10px 0;">
                <?php foreach($product_ads as $ad): ?>
                <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener" onclick="fetch('ad_click.php?id=<?= $ad['id'] ?>')" class="fade-in ad-item-link" style="flex: 0 0 100%; scroll-snap-align: start; min-width: 100%; text-decoration: none;">
                    <div class="ad-image-container" style="border-radius:24px; overflow:hidden; border:1px solid rgba(255,255,255,0.1); position:relative; transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background:rgba(0,0,0,0.02); box-shadow: 0 8px 30px rgba(0,0,0,0.1);">
                        <?php if($ad['image_url']): ?>
                            <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="ad-banner-img-prod" loading="lazy" style="width:100%; display:block; object-fit:cover;">
                        <?php else: ?>
                            <div style="background:linear-gradient(135deg, #7c3aed, #a78bfa); color:#fff; padding:2rem; text-align:center; min-height:140px; display:flex; flex-direction:column; justify-content:center;">
                                <p style="font-size:0.6rem; letter-spacing:0.12em; text-transform:uppercase; opacity:0.8; margin-bottom:0.4rem; font-weight:700;">Featured Partner</p>
                                <p style="font-size:1.25rem; font-weight:800; letter-spacing:-0.02em;"><?= htmlspecialchars($ad['title']) ?></p>
                            </div>
                        <?php endif; ?>
                        <span style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,0.4); backdrop-filter:blur(10px); color:#fff; font-size:0.55rem; padding:4px 10px; border-radius:8px; letter-spacing:0.08em; font-weight:700; border:1px solid rgba(255,255,255,0.15);">AD</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if(count($product_ads) > 1): ?>
            <div style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:10; background:rgba(0,0,0,0.25); padding:4px 10px; border-radius:10px; backdrop-filter:blur(8px);">
                <?php foreach($product_ads as $idx => $ad): ?>
                    <div class="ad-dot-prod" style="width:6px; height:6px; border-radius:50%; background:<?= $idx === 0 ? '#fff' : 'rgba(255,255,255,0.4)' ?>; transition:all 0.2s;"></div>
                <?php endforeach; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const carousel = document.getElementById('adCarouselProduct');
                    if(!carousel) return;
                    const dots = document.querySelectorAll('.ad-dot-prod');
                    carousel.addEventListener('scroll', () => {
                        const idx = Math.round(carousel.scrollLeft / carousel.offsetWidth);
                        dots.forEach((dot, i) => {
                            dot.style.background = (i === idx) ? '#fff' : 'rgba(255,255,255,0.4)';
                            dot.style.width = (i === idx) ? '12px' : '6px';
                            dot.style.borderRadius = (i === idx) ? '3px' : '50%';
                        });
                    });
                    setInterval(() => {
                        const nextIdx = (Math.round(carousel.scrollLeft / carousel.offsetWidth) + 1) % <?= count($product_ads) ?>;
                        carousel.scrollTo({ left: nextIdx * carousel.offsetWidth, behavior: 'smooth' });
                    }, 7000);
                });
            </script>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div id="aiRecScroll" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
            <?php foreach($ai_suggestions as $mp): ?>
                <a href="product.php?id=<?= $mp['id'] ?>" class="scroll-card glass fade-in" style="scroll-snap-align: start; min-width: 180px;">
                    <div class="product-img-wrap" style="aspect-ratio: 1/1; border-radius:12px; overflow:hidden;">
                        <?php if($mp['main_image']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($mp['main_image'])) ?>" alt="<?= htmlspecialchars($mp['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.1);">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body" style="padding:12px 6px;">
                        <p class="product-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.85rem; font-weight:700; margin:0;"><?= htmlspecialchars($mp['title']) ?></p>
                        <p class="product-price" style="font-size:0.95rem; font-weight:800; color:var(--primary); margin-top:4px;">₵<?= number_format($mp['price'], 2) ?></p>
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
                }, 2500);
                s.addEventListener('mouseenter', () => clearInterval(auto));
            });
        </script>
    </div>
<?php endif; ?>




</div>

<?php require_once 'includes/footer.php'; ?>
