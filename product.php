<?php
require_once 'includes/db.php';
require_once 'includes/ai_recommendations.php';
if (!isset($_GET['id'])) redirect('index.php');

      $pid = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT p.*, u.username as seller, u.id as seller_id, u.seller_tier, u.profile_pic as seller_pic, u.department, u.faculty, u.level, u.hall, u.phone, u.whatsapp, u.verified, u.last_seen, u.vacation_mode
    FROM products p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$pid]);
$product = $stmt->fetch();
if (!$product) redirect('index.php');

// Define $isOwner BEFORE any usage
$isOwner = isLoggedIn() && $_SESSION['user_id'] == $product['seller_id'];

$canReview = false;
$hasActiveOrder = false;        // Active order in progress — blocks re-order
$canContactSeller = false;      // Has any active or completed order — unlocks Phone/WhatsApp
if (isLoggedIn() && !$isOwner) {
    $reviewAccessStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND buyer_id = ? AND status = 'completed'");
    $reviewAccessStmt->execute([$pid, $_SESSION['user_id']]);
    $canReview = ((int)$reviewAccessStmt->fetchColumn() > 0);

    // Active order (in progress) — shows "Order Confirmed" state, blocks re-ordering
    $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND buyer_id = ? AND status IN ('ordered','seller_seen','delivered')");
    $activeCheckStmt->execute([$pid, $_SESSION['user_id']]);
    $hasActiveOrder = ((int)$activeCheckStmt->fetchColumn() > 0);

    // Any active OR completed order — unlocks Phone/WhatsApp (buyer has a legitimate relationship)
    $canContactSeller = $hasActiveOrder || $canReview;
}

// 1. FORCED REVIEW BARRIER: Rule 10
if (isLoggedIn() && hasUnreviewedOrders($pdo, $_SESSION['user_id'])) {
    if (!$isOwner && !$canReview) {
        $_SESSION['flash'] = "REVIEW REQUIRED: You must submit a review for your previously completed order before viewing more products.";
        $reviewProductId = getFirstUnreviewedProductId($pdo, (int) $_SESSION['user_id']);
        if ($reviewProductId) {
            header("Location: product.php?id={$reviewProductId}&review_required=1#review");
            exit;
        }
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
        error_log('product.php review save failed: ' . $e->getMessage());
        $reviewMsg = "Could not save review.";
    }
}

// Handle purchase
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy']) && isLoggedIn() && $product['status'] === 'approved' && !$isOwner) {
    check_csrf();
    try {
        $pdo->beginTransaction();
        
        $prodStmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ? FOR UPDATE");
        $prodStmt->execute([$pid]);
        $lockedProd = $prodStmt->fetch();
        if (!$lockedProd || $lockedProd['quantity'] <= 0) {
            throw new Exception("Product is currently out of stock.");
        }

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
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'pgsql') {
    $soldLast24 = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE description LIKE ? AND created_at > NOW() - INTERVAL '24 HOURS'");
    $recent = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ? AND session_id = ? AND created_at > NOW() - INTERVAL '24 HOURS'");
} else {
    $soldLast24 = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE description LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recent = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ? AND session_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}
$soldLast24->execute(['%' . $product['title'] . '%']); $sold24h = $soldLast24->fetchColumn();

// ── Trust Badges Logic ──
$badges = [];
if ($product['verified']) $badges[] = ['icon' => '', 'label' => 'Verified Seller', 'color' => '#34c759'];
if ($sellerRating >= 4.0) $badges[] = ['icon' => '', 'label' => 'Top Rated', 'color' => '#ff9500'];
if ($sellerSales >= 5) $badges[] = ['icon' => '', 'label' => 'Fast Seller', 'color' => '#7c3aed'];
if ($product['seller_tier'] === 'premium') $badges[] = ['icon' => '', 'label' => 'Premium', 'color' => '#af52de'];

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
    $is_active_check = sqlBool(true, $pdo);
    $stmt_ads = $pdo->prepare("SELECT * FROM ad_placements WHERE placement = 'product' AND is_active = $is_active_check ORDER BY created_at DESC LIMIT 5");
    $stmt_ads->execute();
    $product_ads = $stmt_ads->fetchAll();
    
    if (count($product_ads) > 0) {
        $ad_ids = array_column($product_ads, 'id');
        $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
        $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id IN ($placeholders)")->execute($ad_ids);
    }
} catch(Exception $e) {}

// ── Handle Confirm Order POST ──
$orderSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order']) && isLoggedIn() && !$isOwner) {
    check_csrf();
    $buyer_id = (int) $_SESSION['user_id'];
    $buyer_name = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Prevent duplicate orders
    $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND buyer_id = ? AND status IN ('ordered','seller_seen','delivered')");
    $dupCheck->execute([$pid, $buyer_id]);
    if ((int)$dupCheck->fetchColumn() > 0) {
        $error = "You already have an active order for this product.";
    } elseif ($product['status'] !== 'approved') {
        $error = "This product is no longer available.";
    } elseif ((int)($product['quantity'] ?? 0) < 1) {
        $error = "This product is out of stock.";
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE products SET quantity = quantity - 1 WHERE id = ?")->execute([$pid]);
            $pdo->prepare("UPDATE products SET status='sold' WHERE id = ? AND quantity <= 0")->execute([$pid]);

            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $istmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, product_id, price) VALUES (?, ?, ?, ?) RETURNING id");
                $istmt->execute([$buyer_id, $product['seller_id'], $pid, $product['price']]);
                $order_id = (int)$istmt->fetchColumn();
            } else {
                $istmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, product_id, price) VALUES (?, ?, ?, ?)");
                $istmt->execute([$buyer_id, $product['seller_id'], $pid, $product['price']]);
                $order_id = (int)$pdo->lastInsertId();
            }

            // Send in-app chat message to seller
            $chatMsg = "Hi! I just confirmed an order for \"{$product['title']}\" (₵" . number_format($product['price'], 2) . "). Order #$order_id. Please let me know the next steps for delivery!";
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)")
                ->execute([$buyer_id, $product['seller_id'], $chatMsg]);

            // Notify seller
            $notifMsg = '"' . $product['title'] . '" has been ordered by ' . $buyer_name . '.';
            createNotification($pdo, (int) $product['seller_id'], 'order_received', $notifMsg, $order_id, [
                'title' => 'New order received',
                'link_url' => 'dashboard.php#seller_orders',
            ]);

            $pdo->commit();
            $hasActiveOrder = true;
            $canContactSeller = true;
            $orderSuccess = "Order confirmed! The seller has been notified and messaged. You can now contact them via Phone or WhatsApp.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Could not place order. Please try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="fade-in" style="max-width:1000px; margin:0 auto;">
    <?php if(!empty($orderSuccess)): ?><div class="alert alert-success"><?= htmlspecialchars($orderSuccess) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($product['status'] !== 'approved'): ?>
        <div class="alert alert-warning">Status: <strong><?= strtoupper($product['status']) ?></strong> — This product is not visible to buyers.</div>
    <?php endif; ?>

    <!-- FIXED: Responsive grid — stacked on mobile, side-by-side on desktop -->
    <div class="glass" style="padding:1.25rem; display:grid; grid-template-columns:1fr; gap:1.25rem;">
        <style>
            .product-detail-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
                align-items: start;
            }
            .product-media-column,
            .product-info-column {
                min-width: 0;
            }
            .product-media-column {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0.85rem;
            }
            .product-preview-frame {
                width: 100%;
                min-height: 320px;
                background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(245,245,247,0.92));
                border: 1px solid rgba(0,0,0,0.06);
                border-radius: 22px;
                padding: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }
            .product-preview-image {
                width: 100%;
                height: min(60vh, 460px);
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }
            .product-preview-empty {
                width: 100%;
                min-height: 280px;
                border-radius: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0,0,0,0.04);
                color: #666;
            }
            .product-thumb-strip {
                display: flex;
                gap: 0.6rem;
                overflow-x: auto;
                padding-bottom: 0.35rem;
            }
            .product-thumb-strip img {
                width: 68px;
                height: 68px;
                object-fit: cover;
                border-radius: 10px;
                cursor: pointer;
                border: 2px solid var(--border);
                transition: all 0.2s;
                flex: 0 0 auto;
            }
            .seller-identity {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 0.8rem;
            }
            .seller-identity-copy {
                min-width: 0;
                flex: 1;
            }
            .seller-name-line {
                display: flex;
                align-items: center;
                gap: 0.45rem;
                flex-wrap: wrap;
                margin-bottom: 0.2rem;
            }
            .seller-tier-chip,
            .seller-verified-chip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.25rem 0.6rem;
                border-radius: 999px;
                font-size: 0.66rem;
                font-weight: 800;
                letter-spacing: 0.03em;
                line-height: 1;
            }
            .seller-tier-chip {
                background: rgba(124,58,237,0.12);
                color: var(--primary);
                border: 1px solid rgba(124,58,237,0.18);
            }
            .seller-verified-chip {
                background: rgba(52,199,89,0.12);
                color: #1f9d55;
                border: 1px solid rgba(52,199,89,0.18);
            }
            .seller-credentials-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.6rem;
                margin: 0.8rem 0 1rem;
            }
            .seller-credential {
                background: rgba(0,0,0,0.03);
                border: 1px solid rgba(0,0,0,0.05);
                border-radius: 12px;
                padding: 0.75rem 0.8rem;
                min-width: 0;
            }
            .seller-credential-label {
                display: block;
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
                margin-bottom: 0.28rem;
                font-weight: 700;
            }
            .seller-credential-value {
                display: block;
                font-size: 0.86rem;
                color: var(--text-main);
                font-weight: 600;
                overflow-wrap: anywhere;
            }
            .seller-contact-actions {
                display: flex;
                gap: 0.45rem;
                flex-wrap: wrap;
            }
            .seller-contact-actions a {
                flex: 1 1 160px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
                padding: 0.65rem 0.8rem;
                border-radius: 10px;
                font-size: 0.8rem;
                font-weight: 700;
                text-decoration: none;
                transition: all 0.2s;
            }
            .btn-add-cart { background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:0.9rem 1.5rem; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; text-align:center; transition:all 0.3s; box-shadow:0 4px 15px rgba(16,185,129,0.3); }
            .btn-add-cart:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.4); }
            .btn-wishlist { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.9rem 1.5rem; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; width:100%; text-align:center; transition:all 0.3s; }
            .btn-wishlist:hover { background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.3); }
            .btn-wishlist.wishlist-active { background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); }
            @media(min-width:768px) {
                .product-detail-grid {
                    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr) !important;
                }
                .product-preview-frame {
                    min-height: 420px;
                }
            }
            @media(max-width:767px) {
                .product-preview-frame {
                    padding: 0.75rem;
                    min-height: 260px;
                }
                .product-preview-image {
                    height: min(46vh, 340px);
                }
                .seller-credentials-grid {
                    grid-template-columns: 1fr;
                }
                .seller-contact-actions a {
                    flex-basis: 100%;
                }
            }
        </style>
        <!-- Images -->
        <div class="product-detail-grid">
        <div class="product-media-column">
            <?php if(count($images) > 0): ?>
                <div class="product-preview-frame">
                    <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($images[0]['image_path'])) ?>" id="mainImg" alt="<?= htmlspecialchars($product['title']) ?>" class="product-preview-image">
                </div>
                <?php if(count($images) > 1): ?>
                <div class="product-thumb-strip" id="productImageThumbs">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($img['image_path'])) ?>" onclick="changeImg(<?= $idx ?>, this.src)" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                    <?php endforeach; ?>
                </div>
                <script>
                    let currentImageIdx = 0;
                    const imagesArr = <?= json_encode(array_map(static fn($imagePath) => getAssetUrl('uploads/' . $imagePath), array_column($images, 'image_path'))) ?>;
                    let autoPlayInterval = null;
                    function changeImg(idx, src) {
                        currentImageIdx = idx;
                        document.getElementById('mainImg').src = src || imagesArr[idx];
                    }

                    function startAutoPlay() {
                        if (autoPlayInterval || imagesArr.length < 2) return;
                        autoPlayInterval = setInterval(() => {
                            currentImageIdx = (currentImageIdx + 1) % imagesArr.length;
                            changeImg(currentImageIdx);
                        }, 2000);
                    }

                    function stopAutoPlay() {
                        if (!autoPlayInterval) return;
                        clearInterval(autoPlayInterval);
                        autoPlayInterval = null;
                    }

                    document.addEventListener('DOMContentLoaded', () => {
                        startAutoPlay();

                        const mainImg = document.getElementById('mainImg');
                        const thumbs = document.getElementById('productImageThumbs');

                        [mainImg, thumbs].forEach((el) => {
                            if (!el) return;
                            el.addEventListener('mouseenter', stopAutoPlay);
                            el.addEventListener('mouseleave', startAutoPlay);
                        });
                    });
                </script>
                <?php endif; ?>
            <?php else: ?>
                <div class="product-preview-empty">No Image</div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="product-info-column" style="display:flex; flex-direction:column;">
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
                        <span style="color:#ef4444; font-weight:700; font-size:0.85rem;">Out of Stock</span>
                    </div>
                <?php elseif($product['quantity'] <= 5): ?>
                    <div style="background:rgba(255,149,0,0.08); border:1px solid rgba(255,149,0,0.2); border-radius:10px; padding:0.6rem 1rem; margin:0.5rem 0; display:flex; align-items:center; gap:0.5rem; animation: urgencyPulse 2s infinite;">
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
                        <span style="font-size:0.78rem; color:#86868b;"><?= $sold24h ?> sold in last 24h</span>
                    <?php endif; ?>
                    <span style="font-size:0.78rem; color:#86868b;"><?= $product['views'] ?> views</span>
                </div>

                <div class="flex gap-1 mb-2" style="flex-wrap:wrap;">
                    <span class="badge badge-blue"><?= htmlspecialchars($product['category']) ?></span>
                    <?php if($rating > 0): ?><span class="badge badge-gold"><?= $rating ?>/5 (<?= $reviewCount ?>)</span><?php endif; ?>
                </div>

                <!-- TRUST BADGES -->
                <?php if(count($badges) > 0): ?>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin:0.6rem 0;">
                    <?php foreach($badges as $b): ?>
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:0.7rem; font-weight:600; padding:0.3rem 0.65rem; border-radius:8px; background:<?= $b['color'] ?>15; color:<?= $b['color'] ?>; border:1px solid <?= $b['color'] ?>30;">
                            <?= $b['label'] ?>
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
                        <span style="font-size:0.85rem; color:var(--text-muted);">Delivery Method: <strong style="color:var(--text-main);"><?= htmlspecialchars($del_meth) ?></strong></span>
                   </div>
                   <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:0.85rem; color:var(--text-muted);">Payment Agreement: <strong style="color:var(--text-main);"><?= htmlspecialchars($pay_agree) ?></strong></span>
                   </div>
                </div>

                <!-- SELLER TRUST PANEL -->
                <div class="glass" style="padding:1rem; border-radius:16px; margin:0.5rem 0;">
                    <div class="seller-identity">
                        <?php $sellerTierClass = 'profile-pic-' . ($product['seller_tier'] ?: 'basic'); ?>
                        <?php if($product['seller_pic']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($product['seller_pic'])) ?>" class="profile-pic profile-pic-previewable <?= $sellerTierClass ?>" style="width:44px;height:44px;cursor:pointer;" alt="<?= htmlspecialchars($product['seller']) ?>">
                        <?php else: ?>
                            <div class="profile-pic <?= $sellerTierClass ?>" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:rgba(124,58,237,0.1);color:var(--primary);font-weight:700;font-size:1.1rem;"><?= strtoupper(substr($product['seller'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="seller-identity-copy">
                            <div class="seller-name-line">
                                <strong style="font-size:0.95rem;"><?= htmlspecialchars($product['seller']) ?></strong>
                                <?php if($product['verified']): ?><span class="seller-verified-chip">Verified</span><?php endif; ?>
                                <span class="seller-tier-chip"><?= strtoupper(htmlspecialchars($product['seller_tier'] ?: 'basic')) ?></span>
                            </div>
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
                    <div class="seller-credentials-grid">
                        <div class="seller-credential">
                            <span class="seller-credential-label">Faculty</span>
                            <span class="seller-credential-value"><?= htmlspecialchars($product['faculty'] ?: 'Not provided') ?></span>
                        </div>
                        <div class="seller-credential">
                            <span class="seller-credential-label">Department</span>
                            <span class="seller-credential-value"><?= htmlspecialchars($product['department'] ?: 'Not provided') ?></span>
                        </div>
                        <div class="seller-credential">
                            <span class="seller-credential-label">Level</span>
                            <span class="seller-credential-value"><?= htmlspecialchars($product['level'] ?: 'Not provided') ?></span>
                        </div>
                        <div class="seller-credential">
                            <span class="seller-credential-label">Hall</span>
                            <span class="seller-credential-value"><?= htmlspecialchars($product['hall'] ?: 'Not provided') ?></span>
                        </div>
                    </div>
                    <!-- Contact Buttons -->
                    <div class="seller-contact-actions">
                        <?php if(!empty($product['phone'])): ?>
                            <?php if($canContactSeller): ?>
                                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $product['phone'])) ?>" style="background:#fff; border:1px solid rgba(0,0,0,0.1); color:#1d1d1f;">
                                    Phone
                                </a>
                            <?php else: ?>
                                <span style="background:#fff; border:1px solid rgba(0,0,0,0.1); color:#aaa; cursor:not-allowed; opacity:0.5;" title="Confirm order first to unlock">
                                    Phone
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(!empty($product['whatsapp'])): ?>
                            <?php if($canContactSeller): ?>
                                <a href="<?= formatWhatsAppLink($product['whatsapp']) ?>" target="_blank" style="background:#248a3d; color:#fff; border:none;">
                                    WhatsApp
                                </a>
                            <?php else: ?>
                                <span style="background:#248a3d; color:#fff; border:none; cursor:not-allowed; opacity:0.5;" title="Confirm order first to unlock">
                                    WhatsApp
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(isLoggedIn() && !$isOwner): ?>
                            <a href="chat.php?user=<?= $product['seller_id'] ?>" style="background:#7c3aed; color:#fff; border:none;">
                                Message
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
                <div style="display:flex; flex-direction:column; gap:0.75rem; margin-top:1rem;">
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button 
                            type="button" 
                            class="btn-add-cart"
                            style="flex:1;"
                            onclick='cmCart.add(<?= $jsId ?>, <?= $jsName ?>, <?= $jsPrice ?>, "<?= $productMainImage ?>")'
                        >
                            Add to Cart
                        </button>
                        
                        <?php if(isLoggedIn()): ?>
                            <a href="chat.php?user=<?= $product['seller_id'] ?>" style="flex:1; background:#7c3aed; color:#fff; padding:0.9rem; border-radius:12px; font-weight:700; text-align:center; text-decoration:none; transition:all 0.3s;">Message Seller</a>
                        <?php else: ?>
                            <a href="login.php" style="flex:1; background:#7c3aed; color:#fff; padding:0.9rem; border-radius:12px; font-weight:700; text-align:center; text-decoration:none; transition:all 0.3s;">Login to Message</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(isLoggedIn()): ?>
                        <?php if($hasActiveOrder): ?>
                            <div style="width:100%; text-align:center; padding:0.9rem; border-radius:12px; background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); color:#22c55e; font-weight:700; font-size:1.05rem;">
                                Order In Progress — Contact seller below
                            </div>
                        <?php else: ?>
                            <form method="POST" style="width:100%;">
                                <?= csrf_field() ?>
                                <button type="submit" name="confirm_order" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1.05rem;padding:0.9rem;" onclick="return confirm('Confirm this order? The seller will be notified and you can then contact them via Phone or WhatsApp.')"><?= $canReview ? 'Order Again' : 'Confirm Order' ?></button>
                            </form>
                        <?php endif; ?>
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
    <div id="stickyMobileCta" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999; background:rgb(255,255,255); border-top:1px solid rgba(0,0,0,0.08); padding:0.6rem 1rem; box-shadow:0 -4px 20px rgba(0,0,0,0.06);">
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
                Add to Cart
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
        .legacy-product-ad-carousel::-webkit-scrollbar { display:none; }
        .legacy-product-ad-card { flex: 0 0 86%; max-width: 86%; }
        .ad-banner-img-prod { height: 180px; object-fit: cover !important; }
        @media (min-width: 768px) {
            .legacy-product-ad-card { flex-basis: calc(50% - 0.5rem); max-width: calc(50% - 0.5rem); }
            .ad-banner-img-prod { height: 240px !important; }
            .ad-image-container:hover { transform: translateY(-3px) scale(1.002); }
        }
    </style>

    <!-- Reviews Section -->
    <?php if(isset($_GET['review_required'])): ?>
    <div class="alert alert-warning fade-in" style="font-size:1.1rem; padding:1.5rem; border:2px solid #ff9500; text-align:center; animation:urgencyPulse 2s infinite; display:flex; align-items:center; justify-content:center; gap:0.5rem;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff9500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        <strong>ACTION REQUIRED:</strong> You must review your purchase of this product to continue using the marketplace.
    </div>
    <?php endif; ?>
    <div id="review" class="glass mt-3" style="padding:2rem;">
        <h3 class="mb-2">Reviews & Ratings</h3>

        <?php if($reviewMsg): ?><div class="alert alert-success"><?= htmlspecialchars($reviewMsg) ?></div><?php endif; ?>

        <?php if($canReview): ?>
        <form method="POST" style="margin-bottom:1.5rem; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
            <?= csrf_field() ?>
            <div class="form-group" style="margin:0;">
                <label for="reviewRating">Rating</label>
                <select name="rating" id="reviewRating" class="form-control" style="width:80px;">
                    <option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; flex:1;">
                <label for="reviewComment">Comment</label>
                <input type="text" name="comment" id="reviewComment" class="form-control" placeholder="Write a short review...">
            </div>
            <button type="submit" name="review" class="btn btn-primary btn-sm">Submit</button>
        </form>
        <?php endif; ?>

        <?php if(count($reviews) > 0): ?>
            <?php foreach($reviews as $rv): ?>
            <div style="padding:0.75rem 0; border-bottom:1px solid var(--border);">
                <div class="flex-between">
                    <strong style="font-size:0.9rem;"><?= htmlspecialchars($rv['username']) ?></strong>
                    <span style="color:var(--gold); display:inline-flex; gap:2px;"><?= str_repeat('<svg width="14" height="14" viewBox="0 0 24 24" fill="#ffcc00" stroke="#ffcc00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $rv['rating']) ?></span>
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
            <div id="adCarouselProduct" class="horizontal-scroll-container legacy-product-ad-carousel" style="display:flex; gap:1rem; overflow-x:auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 10px 0; scrollbar-width:none;">
                <?php foreach($product_ads as $ad): ?>
                <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener" onclick="fetch('ad_click.php?id=<?= $ad['id'] ?>')" class="fade-in ad-item-link legacy-product-ad-card" data-product-ad-card="true" style="scroll-snap-align: start; text-decoration: none;">
                    <div class="ad-image-container" style="border-radius:24px; overflow:hidden; border:1px solid rgba(255,255,255,0.1); position:relative; transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background:rgba(0,0,0,0.02); box-shadow: 0 8px 30px rgba(0,0,0,0.1);">
                        <?php if($ad['image_url']): ?>
                            <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="ad-banner-img-prod" loading="lazy" style="width:100%; display:block; object-fit:cover;">
                        <?php else: ?>
                            <div style="background:linear-gradient(135deg, #7c3aed, #a78bfa); color:#fff; padding:2rem; text-align:center; min-height:140px; display:flex; flex-direction:column; justify-content:center;">
                                <p style="font-size:0.6rem; letter-spacing:0.12em; text-transform:uppercase; opacity:0.8; margin-bottom:0.4rem; font-weight:700;">Featured Partner</p>
                                <p style="font-size:1.25rem; font-weight:800; letter-spacing:-0.02em;"><?= htmlspecialchars($ad['title']) ?></p>
                            </div>
                        <?php endif; ?>
                        <div style="position:absolute; inset:auto 0 0 0; padding:1rem 1.1rem; background:linear-gradient(to top, rgba(0,0,0,0.68), rgba(0,0,0,0.08)); color:#fff;">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem;">
                                <div style="min-width:0;">
                                    <p style="font-size:0.68rem; letter-spacing:0.14em; text-transform:uppercase; opacity:0.78; margin:0 0 0.35rem; font-weight:700;">Featured Ad</p>
                                    <p style="font-size:1rem; font-weight:800; letter-spacing:-0.02em; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($ad['title']) ?></p>
                                </div>
                                <span style="background:rgba(255,255,255,0.14); color:#fff; font-size:0.62rem; padding:4px 10px; border-radius:999px; letter-spacing:0.08em; font-weight:700; border:1px solid rgba(255,255,255,0.15); flex-shrink:0;">AD</span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if(count($product_ads) > 1): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const carousel = document.getElementById('adCarouselProduct');
                    if(!carousel) return;
                    const firstCard = carousel.querySelector('[data-product-ad-card]');
                    if(!firstCard) return;

                    const getStep = () => {
                        const gap = parseFloat(getComputedStyle(carousel).gap || '0');
                        return firstCard.getBoundingClientRect().width + gap;
                    };

                    setInterval(() => {
                        const maxScroll = carousel.scrollWidth - carousel.clientWidth;
                        if (maxScroll <= 0) return;
                        const nextScroll = carousel.scrollLeft + getStep();
                        carousel.scrollTo({
                            left: nextScroll >= maxScroll - 10 ? 0 : Math.min(nextScroll, maxScroll),
                            behavior: 'smooth'
                        });
                    }, 5000);
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
