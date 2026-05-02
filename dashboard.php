<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

// --- STORE SELLER VARIABLES ---
$seller_id = $user['id'];
$seller_name = $user['username'];
$seller_avatar = $user['profile_pic'] ?? '';
$seller_plan = $user['seller_tier'] ?: 'basic';
$seller_verified = !empty($user['verified']);
$wallet_balance = (float)($user['balance'] ?? 0);

// --- TAB ROUTING ---
$tabWhitelist = ['overview','inventory','orders','analytics','wallet','messages','settings'];
$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, $tabWhitelist, true)) {
    $tab = 'overview';
}
$supportAdminId = getPrimaryAdminId($pdo);
$isAdminSellerView = ($user['role'] === 'admin' && (($_GET['view'] ?? '') === 'seller' || ($_POST['dashboard_view'] ?? '') === 'seller'));
$dashboardRole = $isAdminSellerView ? 'seller' : $user['role'];
$hasSellerDashboardAccess = ($dashboardRole === 'seller');
$hasBuyerDashboardAccess = ($dashboardRole === 'buyer');
try {
    $boolTrue = sqlBool(true, $pdo);
    $pdo->prepare("UPDATE notifications SET is_read = {$boolTrue} WHERE user_id = ?")->execute([$user['id']]);
} catch (PDOException $e) {}

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf();
    $pid = (int)($_POST['pid'] ?? 0);
    $action = $_POST['action'];
    switch ($action) {
        case 'request_delete':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='deleted' WHERE id=? AND user_id=? AND status NOT IN ('deleted')")->execute([$pid, $user['id']]);
                $_SESSION['flash'] = 'Product removed successfully.';
                header('Location: dashboard.php?tab=inventory');
                exit;
            }
            break;
        case 'pause':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='paused' WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                $msg = "Product paused.";
            }
            break;
        case 'unpause':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=? AND user_id=? AND status='paused'")->execute([$pid, $user['id']]);
                $msg = "Product resumed.";
            }
            break;
        case 'mark_sold':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET quantity=0, status='sold' WHERE id=? AND user_id=?")->execute([$pid, $user['id']]);
                $msg = "Product marked as sold out.";
            }
            break;
        case 'restock_qty':
            $qty = (int)($_POST['restock_amount'] ?? 0);
            $restock_pid = (int)($_POST['pid'] ?? 0);
            if ($qty > 0 && $restock_pid > 0) {
                // Update quantity
                $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id=? AND user_id=?")->execute([$qty, $restock_pid, $user['id']]);
                // If product was sold out, re-approve it
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=? AND user_id=? AND status='sold'")->execute([$restock_pid, $user['id']]);
                $msg = "Product successfully restocked with +$qty items!";
            }
            break;
        case 'toggle_vacation':
            // ── VACATION MODE HARDENING (Phase 2) ──
            $isTierExpired = ($user['seller_tier'] === 'basic' && !empty($user['tier_expires_at']) && strtotime($user['tier_expires_at']) < time());
            if ($isTierExpired && $user['vacation_mode']) {
                $msg = "❌ Cannot disable vacation mode while your subscription is expired. Please subscribe to a tier first.";
                break;
            }

            if ($user['vacation_mode']) {
                $stmt = $pdo->prepare("UPDATE users SET vacation_mode=? WHERE id=?");
                $stmt->bindValue(1, false, PDO::PARAM_BOOL);
                $stmt->bindValue(2, $user['id'], PDO::PARAM_INT);
                $stmt->execute();
                $user['vacation_mode'] = false;
                $msg = "Welcome back! Your listings are visible again.";
            } else {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS vacation_requests (
                        id SERIAL PRIMARY KEY,
                        seller_id INT NOT NULL,
                        status VARCHAR(20) DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                } catch(PDOException $e) {}
                $pdo->prepare("INSERT INTO vacation_requests (seller_id, status) VALUES (?, 'pending')")->execute([$user['id']]);
                $msg = "Vacation request submitted for admin approval. Your listings will be hidden once approved.";
            }
            break;

        case 'boost':
            if ($pid > 0) {
                try {
                    $pdo->beginTransaction();
                    $isPremium = ($user['seller_tier'] === 'premium');
                    $boostPrice = $isPremium ? 0.00 : (float)getSetting($pdo, 'ad_boost_price', '10');

                    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$user['id']]);
                    $u = $stmt->fetch();

                    if (!$u || $u['balance'] < $boostPrice) throw new Exception("Insufficient funds (GHS $boostPrice required) to boost.");

                    if ($boostPrice > 0) {
                        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$boostPrice, $user['id']]);
                    }

                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                        $pdo->prepare("UPDATE products SET boosted_until = CURRENT_TIMESTAMP + INTERVAL '24 hours' WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                    } else {
                        $pdo->prepare("UPDATE products SET boosted_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                    }
                    $pdo->prepare("INSERT INTO transactions (user_id,type,amount,status,reference,description) VALUES (?,'boost',?,?,?,?)")->execute([$user['id'], $boostPrice, 'completed', generateRef('BST'), "Boosted Product #$pid" . ($isPremium ? " (Free Premium Benefit)" : "")]);

                    $pdo->commit();
                    $msg = $isPremium ? "Product successfully boosted (free Premium benefit)." : "Product successfully boosted for 24 hours.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error: " . $e->getMessage();
                }
            }
            break;

        case 'request_premium':
            if ($supportAdminId > 0) {
                $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $supportAdminId, "Hello Admin! I am requesting a Platinum/Premium Badge upgrade for my seller account. I agree to pay the premium fee."]);
                createMessageNotification($pdo, $supportAdminId, (int) $user['id'], 'Premium badge upgrade request');
                $msg = "Premium request sent to the administrator.";
            } else {
                $msg = "No administrator account is available right now.";
            }
            break;
        case 'request_pro':
            if ($supportAdminId > 0) {
                $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $supportAdminId, "Hello Admin! I am requesting a Pro Badge upgrade for my seller account. I agree to pay the pro fee."]);
                createMessageNotification($pdo, $supportAdminId, (int) $user['id'], 'Pro badge upgrade request');
                $msg = "Pro request sent to the administrator.";
            } else {
                $msg = "No administrator account is available right now.";
            }
            break;
        case 'cancel_tier':
            // Check for existing pending request to avoid duplicates
            $check = $pdo->prepare("SELECT COUNT(*) FROM profile_edit_requests WHERE user_id=? AND field_name='seller_tier' AND status='pending'");
            $check->execute([$user['id']]);
            if($check->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?, 'seller_tier', ?, 'basic')")
                    ->execute([$user['id'], $user['seller_tier']]);
                $msg = "Tier cancellation request submitted for admin approval.";
            } else {
                $msg = "You already have a pending cancellation request.";
            }
            break;

        // ADDED: Submit discount for admin approval
        case 'submit_discount':
            $discount_pct = (int)($_GET['disc'] ?? 0);
            if ($pid > 0 && $discount_pct > 0 && $discount_pct <= 90) {
                $prod_check = $pdo->prepare("SELECT id, title, price FROM products WHERE id = ? AND user_id = ?");
                $prod_check->execute([$pid, $user['id']]);
                $prod_data = $prod_check->fetch(PDO::FETCH_ASSOC);
                if ($prod_data) {
                    $discounted = $prod_data['price'] * (1 - $discount_pct / 100);
                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                            id SERIAL PRIMARY KEY, product_id INT NOT NULL, seller_id INT NOT NULL,
                            original_price DECIMAL(10,2) NOT NULL, discount_percent INT NOT NULL,
                            discounted_price DECIMAL(10,2) NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                    } else {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                            id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, seller_id INT NOT NULL,
                            original_price DECIMAL(10,2) NOT NULL, discount_percent INT NOT NULL,
                            discounted_price DECIMAL(10,2) NOT NULL,
                            status ENUM('pending','approved','rejected') DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB");
                    }
                    $pdo->prepare("INSERT INTO discount_requests (product_id, seller_id, original_price, discount_percent, discounted_price) VALUES (?,?,?,?,?)")
                        ->execute([$pid, $user['id'], $prod_data['price'], $discount_pct, $discounted]);
                    $msg = "Discount request ({$discount_pct}% off \"{$prod_data['title']}\") submitted for admin approval.";
                }
            } else {
                $msg = "Enter a valid discount (1-90%).";
            }
            break;
        case 'accept_order':
            $oid = (int)($_POST['oid'] ?? $_GET['oid'] ?? 0);
            $note = htmlspecialchars(trim($_POST['delivery_note'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($oid > 0) {
                $note = $note ?: 'Seller has acknowledged your order and will update you shortly.';
                $o = $pdo->prepare("SELECT o.*, p.title FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=? AND o.seller_id=?");
                $o->execute([$oid, $user['id']]);
                $ord = $o->fetch(PDO::FETCH_ASSOC);
                if (!$ord) {
                    $msg = "Order not found.";
                    break;
                }
                if (($ord['status'] ?? '') !== 'ordered') {
                    $msg = "This order has already been acknowledged.";
                    break;
                }

                $pdo->prepare("UPDATE orders SET status='seller_seen', delivery_note=? WHERE id=? AND seller_id=?")
                    ->execute([$note, $oid, $user['id']]);
                createNotification($pdo, (int) $ord['buyer_id'], 'order_update', "Seller acknowledged your order: " . $note, $oid, [
                    'title' => 'Seller acknowledged your order',
                    'link_url' => 'dashboard.php#buyer_orders',
                ]);
                $msg = "Order acknowledged. Buyer has been notified.";
            }
            break;
        case 'reject_order':
            $oid = (int)($_POST['oid'] ?? $_GET['oid'] ?? 0);
            if ($oid > 0) {
                $o = $pdo->prepare("SELECT * FROM orders WHERE id=? AND seller_id=?");
                $o->execute([$oid, $user['id']]);
                $ord = $o->fetch(PDO::FETCH_ASSOC);
                if (!$ord) {
                    $msg = "Order not found.";
                    break;
                }
                if (!in_array($ord['status'] ?? '', ['ordered', 'seller_seen'], true)) {
                    $msg = "This order can no longer be rejected.";
                    break;
                }

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM orders WHERE id=? AND seller_id=?")->execute([$oid, $user['id']]);
                    dashboardRestoreOrderStock($pdo, $ord);
                    createNotification($pdo, (int) $ord['buyer_id'], 'order_update', "Seller declined your order.", $oid, [
                        'title' => 'Order declined',
                        'link_url' => 'dashboard.php#buyer_orders',
                    ]);
                    $pdo->commit();
                    $msg = "Order declined and stock restored.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $msg = "Failed to reject order.";
                }
            }
            break;
        case 'deliver_order':
            $oid = (int)($_POST['oid'] ?? $_GET['oid'] ?? 0);
            if ($oid > 0) {
                $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND seller_id=?");
                $orderStmt->execute([$oid, $user['id']]);
                $ordData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ordData) {
                    $msg = "Order not found.";
                    break;
                }
                if (($ordData['status'] ?? '') === 'ordered') {
                    $msg = "Please acknowledge the order first before confirming item sold.";
                    break;
                }
                if (($ordData['status'] ?? '') !== 'seller_seen' && ($ordData['status'] ?? '') !== 'delivered') {
                    $msg = "This order cannot be marked as sold right now.";
                    break;
                }
                if (!empty($ordData['seller_confirmed'])) {
                    $msg = "Item sold has already been confirmed for this order.";
                    break;
                }
                $boolT = sqlBool(true, $pdo);
                $status_sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
                    ? "CASE WHEN buyer_confirmed = $boolT THEN 'completed' ELSE 'delivered' END"
                    : "IF(buyer_confirmed = 1, 'completed', 'delivered')";
                $pdo->prepare("UPDATE orders SET seller_confirmed=$boolT, status=$status_sql WHERE id=? AND seller_id=?")->execute([$oid, $user['id']]);
                if (!empty($ordData['buyer_confirmed'])) {
                    dashboardCreateSaleTransaction($pdo, $ordData, $oid);
                }
                // Notify admin/buyer
                $o = $pdo->prepare("SELECT buyer_id, p.id as pid FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=?"); $o->execute([$oid]); $ord = $o->fetch();
                if ($ord) {
                    createNotification($pdo, (int) $ord['buyer_id'], 'order_update', "Seller confirmed Item Sold. Please confirm when received.", $oid, [
                        'title' => 'Seller confirmed item sold',
                        'link_url' => 'dashboard.php#buyer_orders',
                    ]);
                    if ($supportAdminId > 0) {
                        createNotification($pdo, $supportAdminId, 'admin_alert', "Seller confirmed order #$oid as Item Sold.", $oid, [
                            'title' => 'Seller confirmed an order',
                            'link_url' => 'admin/',
                        ]);
                    }
                }
                $msg = "Item Sold confirmed successfully.";
            }
            break;
        case 'ship_order':
            $oid = (int)($_POST['oid'] ?? 0);
            if ($oid > 0) {
                $o = $pdo->prepare("SELECT * FROM orders WHERE id=? AND seller_id=?");
                $o->execute([$oid, $user['id']]);
                $ordData = $o->fetch(PDO::FETCH_ASSOC);
                if (!$ordData) { $msg = "Order not found."; break; }
                if (($ordData['status'] ?? '') !== 'seller_seen') { $msg = "Order must be acknowledged before shipping."; break; }
                $pdo->prepare("UPDATE orders SET status='delivered' WHERE id=? AND seller_id=?")->execute([$oid, $user['id']]);
                createNotification($pdo, (int)$ordData['buyer_id'], 'order_update', "Your order #$oid has been shipped!", $oid, [
                    'title' => 'Order shipped',
                    'link_url' => 'dashboard.php#buyer_orders',
                ]);
                $msg = "Order marked as shipped.";
            }
            break;
        case 'complete_order':
            $oid = (int)($_POST['oid'] ?? 0);
            if ($oid > 0) {
                $o = $pdo->prepare("SELECT * FROM orders WHERE id=? AND seller_id=?");
                $o->execute([$oid, $user['id']]);
                $ordData = $o->fetch(PDO::FETCH_ASSOC);
                if (!$ordData) { $msg = "Order not found."; break; }
                if (!in_array($ordData['status'] ?? '', ['delivered', 'seller_seen'], true)) { $msg = "Order cannot be completed from current state."; break; }
                $pdo->prepare("UPDATE orders SET status='completed' WHERE id=? AND seller_id=?")->execute([$oid, $user['id']]);
                dashboardCreateSaleTransaction($pdo, $ordData, $oid);
                createNotification($pdo, (int)$ordData['buyer_id'], 'order_update', "Your order #$oid is completed!", $oid, [
                    'title' => 'Order completed',
                    'link_url' => 'dashboard.php#buyer_orders',
                ]);
                $msg = "Order marked as completed.";
            }
            break;
        case 'receive_order':
            $oid = (int)($_POST['oid'] ?? $_GET['oid'] ?? 0);
            if ($oid > 0) {
                try {
                    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND buyer_id=?");
                    $orderStmt->execute([$oid, $user['id']]);
                    $ordData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$ordData) {
                        $msg = "Order not found.";
                        break;
                    }
                    if (($ordData['status'] ?? '') !== 'delivered' || empty($ordData['seller_confirmed'])) {
                        $msg = "The seller must confirm item sold before you can confirm receipt.";
                        break;
                    }
                    if (!empty($ordData['buyer_confirmed'])) {
                        $msg = "Receipt already confirmed for this order.";
                        break;
                    }
                    $boolT = sqlBool(true, $pdo);
                    $status_sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                        ? "CASE WHEN seller_confirmed = $boolT THEN 'completed' ELSE 'delivered' END"
                        : "IF(seller_confirmed = 1, 'completed', 'delivered')";

                    // CORE ACTION: update order status (must succeed)
                    $pdo->prepare("UPDATE orders SET buyer_confirmed=$boolT, status=$status_sql WHERE id=? AND buyer_id=?")
                        ->execute([$oid, $user['id']]);

                    // SIDE EFFECTS: wrap each in try/catch so email/notification/transaction failures don't 500
                    try {
                        if (!empty($ordData['seller_confirmed'])) {
                            dashboardCreateSaleTransaction($pdo, $ordData, $oid);
                        }
                    } catch (Throwable $txErr) {
                        error_log("receive_order transaction creation failed for order #$oid: " . $txErr->getMessage());
                    }

                    try {
                        $o = $pdo->prepare("SELECT seller_id, p.id as pid FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=?");
                        $o->execute([$oid]);
                        $ord = $o->fetch();
                        if ($ord) {
                            try {
                                createNotification($pdo, (int) $ord['seller_id'], 'order_update', "Buyer confirmed Item Received. Transaction complete.", $oid, [
                                    'title' => 'Buyer confirmed receipt',
                                    'link_url' => 'dashboard.php#seller_orders',
                                ]);
                            } catch (Throwable $nErr) {
                                error_log("receive_order seller notification failed for order #$oid: " . $nErr->getMessage());
                            }
                            if ($supportAdminId > 0) {
                                try {
                                    createNotification($pdo, $supportAdminId, 'admin_alert', "Buyer confirmed receipt of order #$oid. Transaction complete.", $oid, [
                                        'title' => 'Buyer confirmed an order',
                                        'link_url' => 'admin/',
                                    ]);
                                } catch (Throwable $aErr) {
                                    error_log("receive_order admin notification failed for order #$oid: " . $aErr->getMessage());
                                }
                            }
                        }
                    } catch (Throwable $notifErr) {
                        error_log("receive_order notification block failed for order #$oid: " . $notifErr->getMessage());
                    }

                    $msg = "Item Received confirmed. Transaction complete.";
                } catch (Throwable $e) {
                    error_log("receive_order failed for order #$oid, buyer " . ($user['id'] ?? '?') . ": " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $msg = "Could not confirm receipt. Please try again or contact support.";
                }
            }
            break;
        case 'submit_dispute':
            $oid = (int)($_POST['oid'] ?? $_GET['oid'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if($oid > 0 && $reason) {
                $o = $pdo->prepare("SELECT seller_id FROM orders WHERE id=? AND buyer_id=?");
                $o->execute([$oid, $user['id']]);
                $ord = $o->fetch();
                if($ord) {
                    $pdo->prepare("INSERT INTO disputes (order_id, complainant_id, target_id, reason) VALUES (?,?,?,?)")
                        ->execute([$oid, $user['id'], $ord['seller_id'], $reason]);
                    $msg = "Dispute reported successfully. Admin will review this shortly.";
                }
            }
            break;
        case 'request_withdrawal':
            $amount = (float)($_POST['withdraw_amount'] ?? 0);
            $network = trim($_POST['momo_network'] ?? '');
            $number = trim($_POST['momo_number'] ?? '');
            if ($amount <= 0) { $msg = "Please enter a valid amount."; break; }
            if (!$network || !$number) { $msg = "Please select a network and enter your mobile number."; break; }
            if (!preg_match('/^[0-9]{10}$/', $number)) { $msg = "Please enter a valid 10-digit phone number."; break; }
            if (!\in_array($network, ['MTN', 'Vodafone', 'AirtelTigo'], true)) { $msg = "Invalid network selected."; break; }
            // Balance validation + atomic withdrawal
            $pdo->beginTransaction();
            try {
                $bal = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                $bal->execute([$user['id']]);
                $currentBalance = (float)$bal->fetchColumn();
                if ($amount > $currentBalance) {
                    throw new Exception("Insufficient funds. Your balance is GHS " . number_format($currentBalance, 2));
                }
                $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user['id']]);
                $ref = 'WD-' . strtoupper(bin2hex(random_bytes(6)));
                $desc = "Withdrawal to $network $number";
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'withdrawal', ?, 'pending', ?, ?)")
                    ->execute([$user['id'], $amount, $ref, $desc]);
                $pdo->commit();
                $_SESSION['flash'] = "Withdrawal of GHS " . number_format($amount, 2) . " submitted successfully (Ref: $ref). Processing may take 24-48 hours.";
                header('Location: dashboard.php?tab=wallet');
// Stats
try {
    $productCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?"); 
    $productCount->execute([$user['id']]); 
    $totalProducts = $productCount->fetchColumn();

    $approvedCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status='approved'"); 
    $approvedCount->execute([$user['id']]); 
    $totalApproved = $approvedCount->fetchColumn();
    $active_listings = (int)$totalApproved;

    $pendingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status='pending'");
    $pendingCountStmt->execute([$user['id']]);
    $totalPending = $pendingCountStmt->fetchColumn();

    $totalSales = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type='sale'"); 
    $totalSales->execute([$user['id']]); 
    $salesAmount = $totalSales->fetchColumn();

    $totalViews = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM products WHERE user_id = ?"); 
    $totalViews->execute([$user['id']]); 
    $viewsTotal = $totalViews->fetchColumn();
    $total_views = (int)$viewsTotal;

    // Show seller products (with optional filter + search)
    $invFilter = $_GET['filter'] ?? 'all';
    $invSearch = trim($_GET['search'] ?? '');
    $productSql = "SELECT p.*, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
        (SELECT COUNT(*) FROM discount_requests WHERE product_id = p.id AND status = 'pending') as has_pending_discount
        FROM products p WHERE p.user_id = ? AND p.status != 'deleted'";
    $productParams = [$user['id']];

    if ($invFilter === 'approved') {
        $productSql .= " AND p.status = 'approved'";
    } elseif ($invFilter === 'pending') {
        $productSql .= " AND p.status = 'pending'";
    } elseif ($invFilter === 'paused') {
        $productSql .= " AND p.status = 'paused'";
    } elseif ($invFilter === 'low') {
        $productSql .= " AND p.quantity > 0 AND p.quantity <= 5 AND p.status = 'approved'";
    }

    if ($invSearch !== '') {
        $productSql .= " AND p.title LIKE ?";
        $productParams[] = '%' . $invSearch . '%';
    }

    $productSql .= " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($productSql);
    $stmt->execute($productParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('dashboard.php DB error: ' . $e->getMessage());
    $msg = "Temporary database issue. Please refresh in a moment.";
    $products = [];
    $totalProducts = 0;
    $totalApproved = 0;
    $totalPending = 0;
    $salesAmount = 0;
    $viewsTotal = 0;
}

// Transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// --- RECENT ACTIVITY (UNIFIED UNION ALL) ---
$sqlActivity = "
    SELECT 'transaction' AS type, CONCAT('Transaction: ', type) AS title, description AS subtitle, created_at
    FROM transactions WHERE user_id = ?
    UNION ALL
    SELECT 'order' AS type, CONCAT('Order #', id) AS title, CONCAT('Status: ', status) AS subtitle, created_at
    FROM orders WHERE seller_id = ?
    UNION ALL
    SELECT 'message' AS type, 'New Message' AS title, message AS subtitle, created_at
    FROM messages WHERE receiver_id = ?
    UNION ALL
    SELECT 'product' AS type, 'Low Stock Alert' AS title, title AS subtitle, COALESCE(updated_at, created_at) AS created_at
    FROM products WHERE user_id = ? AND quantity > 0 AND quantity <= 5 AND status = 'approved'
    ORDER BY created_at DESC LIMIT 5
";
$stmtAct = $pdo->prepare($sqlActivity);
$stmtAct->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$activityFeed = $stmtAct->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Announcements
$announcement_active = sqlBool(true, $pdo);
$stmt = $pdo->prepare("SELECT a.*, u.username as admin_name FROM announcements a JOIN users u ON a.admin_id = u.id WHERE a.is_active = $announcement_active ORDER BY a.created_at DESC LIMIT 5");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// REAL ANALYTICS DATA

// --- BUYER ANALYTICS ---
$buyerItemsBought = 0; $buyerTotalSpent = 0; $buyerRecentPurchases = []; $buyerFavCategories = [];
if ($hasBuyerDashboardAccess) {
    try {
        // Total items bought (from orders table)
        $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status='completed'"); $s->execute([$user['id']]); $buyerItemsBought = (int)$s->fetchColumn();
    } catch(PDOException $e) { /* orders table may not exist */ }
    try {
        // Total money spent
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type IN ('purchase','order')"); $s->execute([$user['id']]); $buyerTotalSpent = (float)$s->fetchColumn();
    } catch(PDOException $e) {}
    try {
        // Recent purchases
        $s = $pdo->prepare("SELECT o.*, p.title, (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) as img FROM orders o JOIN products p ON o.product_id=p.id WHERE o.buyer_id=? ORDER BY o.created_at DESC LIMIT 5");
        $s->execute([$user['id']]); $buyerRecentPurchases = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(PDOException $e) {}
    try {
        // Favorite categories
        $s = $pdo->prepare("SELECT p.category, COUNT(*) as cnt FROM orders o JOIN products p ON o.product_id=p.id WHERE o.buyer_id=? GROUP BY p.category ORDER BY cnt DESC LIMIT 4");
        $s->execute([$user['id']]); $buyerFavCategories = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(PDOException $e) {}
}

// --- SELLER ANALYTICS ---
$sellerTotalSold = 0; $sellerRevenue = 0; $sellerLowStock = 0; $sellerTopProduct = null; $sellerWeeklySales = [];
if ($hasSellerDashboardAccess) {
    try {
        // Total items sold
        $s = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id=p.id WHERE p.user_id=? AND o.status='completed'"); $s->execute([$user['id']]); $sellerTotalSold = (int)$s->fetchColumn();
        $items_sold = $sellerTotalSold;
    } catch(PDOException $e) {}
    try {
        // Total revenue
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND status='completed'"); $s->execute([$user['id']]); $sellerRevenue = (float)$s->fetchColumn();
        $total_revenue = $sellerRevenue;
    } catch(PDOException $e) {}
    // Low stock products
    $sellerLowStock = 0;
    foreach ($products as $p) { if ($p['quantity'] > 0 && $p['quantity'] <= 5 && $p['status'] === 'approved') $sellerLowStock++; }
    $low_stock_count = $sellerLowStock;
    
    // Add pending orders and unread messages count
    try {
        $po = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id=? AND status NOT IN ('completed', 'delivered', 'cancelled', 'refunded')");
        $po->execute([$user['id']]);
        $pending_orders = (int)$po->fetchColumn();
    } catch(PDOException $e) { $pending_orders = 0; }
    
    $unread_messages = function_exists('getUnreadCount') ? getUnreadCount($pdo, $user['id']) : 0;
    try {
        // Top performing product
        $s = $pdo->prepare("SELECT title, views, quantity FROM products WHERE user_id=? AND status='approved' ORDER BY views DESC LIMIT 1"); $s->execute([$user['id']]); $sellerTopProduct = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch(PDOException $e) {}
    // PERCENTAGE CHANGES (7-day vs previous 7-day)
    $changeHelper = function($curr, $prev) { return $prev > 0 ? round(($curr - $prev) / $prev * 100, 1) : 0; };
    $p7d = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "NOW() - INTERVAL '7 days'" : "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $p14d = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "NOW() - INTERVAL '14 days'" : "DATE_SUB(NOW(), INTERVAL 14 DAY)";

    // Revenue Change
    $sqlC = "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND status='completed' AND created_at >= $p7d";
    $sqlP = "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND status='completed' AND created_at >= $p14d AND created_at < $p7d";
    $sC = $pdo->prepare($sqlC); $sC->execute([$user['id']]); $currRev = (float)$sC->fetchColumn();
    $sP = $pdo->prepare($sqlP); $sP->execute([$user['id']]); $prevRev = (float)$sP->fetchColumn();
    $revenue_change = $changeHelper($currRev, $prevRev);

    // Items Sold Change
    $sqlC = "SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id=p.id WHERE p.user_id=? AND o.status='completed' AND o.created_at >= $p7d";
    $sqlP = "SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id=p.id WHERE p.user_id=? AND o.status='completed' AND o.created_at >= $p14d AND o.created_at < $p7d";
    $sC = $pdo->prepare($sqlC); $sC->execute([$user['id']]); $currSold = (float)$sC->fetchColumn();
    $sP = $pdo->prepare($sqlP); $sP->execute([$user['id']]); $prevSold = (float)$sP->fetchColumn();
    $sold_change = $changeHelper($currSold, $prevSold);

    // Views Change
    $sqlC = "SELECT COALESCE(SUM(views),0) FROM products WHERE user_id=? AND updated_at >= $p7d";
    $sqlP = "SELECT COALESCE(SUM(views),0) FROM products WHERE user_id=? AND updated_at >= $p14d AND updated_at < $p7d";
    $sC = $pdo->prepare($sqlC); $sC->execute([$user['id']]); $currView = (float)$sC->fetchColumn();
    $sP = $pdo->prepare($sqlP); $sP->execute([$user['id']]); $prevView = (float)$sP->fetchColumn();
    $views_change = $changeHelper($currView, $prevView);

    // Listings Change
    $sqlC = "SELECT COUNT(*) FROM products WHERE user_id=? AND status='approved' AND created_at >= $p7d";
    $sqlP = "SELECT COUNT(*) FROM products WHERE user_id=? AND status='approved' AND created_at >= $p14d AND created_at < $p7d";
    $sC = $pdo->prepare($sqlC); $sC->execute([$user['id']]); $currList = (float)$sC->fetchColumn();
    $sP = $pdo->prepare($sqlP); $sP->execute([$user['id']]); $prevList = (float)$sP->fetchColumn();
    $listings_change = $changeHelper($currList, $prevList);

    // CHART DATA GROUPING
    $chart_range = max(7, (int)($_GET['range'] ?? 7));
    $chart_labels_map = [];
    $chart_revenue_map = [];
    $chart_views_map = [];
    
    for ($i = $chart_range - 1; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime("-$i days"));
        $chart_labels_map[$dateKey] = date('M d', strtotime("-$i days"));
        $chart_revenue_map[$dateKey] = 0;
        $chart_views_map[$dateKey] = 0;
    }

    $rangeInterval = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "INTERVAL '$chart_range days'" : "INTERVAL $chart_range DAY";
    
    try {
        $sqlR = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
            ? "SELECT created_at::DATE as d, SUM(amount) as amt FROM transactions WHERE user_id=? AND type='sale' AND status='completed' AND created_at >= NOW() - $rangeInterval GROUP BY created_at::DATE"
            : "SELECT DATE(created_at) as d, SUM(amount) as amt FROM transactions WHERE user_id=? AND type='sale' AND status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL $chart_range DAY) GROUP BY DATE(created_at)";
        $sR = $pdo->prepare($sqlR); $sR->execute([$user['id']]);
        while ($row = $sR->fetch(PDO::FETCH_ASSOC)) {
            if (isset($chart_revenue_map[$row['d']])) $chart_revenue_map[$row['d']] = (float)$row['amt'];
        }
    } catch(PDOException $e) {}

    try {
        $sqlV = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
            ? "SELECT updated_at::DATE as d, SUM(views) as vw FROM products WHERE user_id=? AND updated_at >= NOW() - $rangeInterval GROUP BY updated_at::DATE"
            : "SELECT DATE(updated_at) as d, SUM(views) as vw FROM products WHERE user_id=? AND updated_at >= DATE_SUB(NOW(), INTERVAL $chart_range DAY) GROUP BY DATE(updated_at)";
        $sV = $pdo->prepare($sqlV); $sV->execute([$user['id']]);
        while ($row = $sV->fetch(PDO::FETCH_ASSOC)) {
            if (isset($chart_views_map[$row['d']])) $chart_views_map[$row['d']] = (int)$row['vw'];
        }
    } catch(PDOException $e) {}

    $chart_labels = array_values($chart_labels_map);
    $chart_revenue = array_values($chart_revenue_map);
    $chart_views = array_values($chart_views_map);
}

if (!isset($chart_labels) || empty($chart_labels)) {
    $chart_labels = [];
    $chart_revenue = [];
    $chart_views = [];
    $chart_range = max(7, (int)($_GET['range'] ?? 7));
    for ($i = $chart_range - 1; $i >= 0; $i--) {
        $chart_labels[] = date('M d', strtotime("-$i days"));
        $chart_revenue[] = 0;
        $chart_views[] = 0;
    }
}

function dashboardTierVisual(string $tierName): array {
    $tierName = strtolower(trim($tierName));

    $visual = match ($tierName) {
        'premium' => [
            'accent' => '#ff9f0a',
            'background' => 'linear-gradient(180deg, rgba(255,159,10,0.96), rgba(255,159,10,0.78))',
            'border' => 'rgba(255,210,120,0.45)',
            'button_text' => '#111111',
            'text' => '#111111',
            'label' => 'rgba(17,17,17,0.74)',
            'soft' => 'rgba(255,248,235,0.24)',
            'shadow' => 'rgba(255,159,10,0.28)',
        ],
        'pro' => [
            'accent' => '#8e8e93',
            'background' => 'linear-gradient(180deg, rgba(10,10,14,0.9), rgba(10,10,14,0.68))',
            'border' => 'rgba(142,142,147,0.24)',
            'button_text' => '#ffffff',
            'text' => '#ffffff',
            'label' => 'rgba(255,255,255,0.72)',
            'soft' => 'rgba(142,142,147,0.16)',
            'shadow' => 'rgba(142,142,147,0.18)',
        ],
        default => [
            'accent' => '#7c3aed',
            'background' => 'linear-gradient(180deg, rgba(10,10,14,0.9), rgba(10,10,14,0.68))',
            'border' => 'rgba(124,58,237,0.24)',
            'button_text' => '#ffffff',
            'text' => '#ffffff',
            'label' => 'rgba(255,255,255,0.72)',
            'soft' => 'rgba(124,58,237,0.16)',
            'shadow' => 'rgba(124,58,237,0.18)',
        ],
    };

    $visual['color'] = $visual['accent'];
    $visual['panel'] = $visual['background'];
    $visual['subtleText'] = $visual['label'];

    return $visual;
}

function dashboardTierDurationLabel($duration): string {
    $duration = strtolower(trim((string)$duration));

    if ($duration === '' || $duration === '0' || $duration === 'forever') {
        return 'lifetime';
    }
    if ($duration === 'weekly') {
        return 'week';
    }
    if (preg_match('/^(\d+)_weeks?$/', $duration, $matches)) {
        return $matches[1] . ' week' . ((int)$matches[1] === 1 ? '' : 's');
    }
    if (preg_match('/^(\d+)_months?$/', $duration, $matches)) {
        return $matches[1] . ' month' . ((int)$matches[1] === 1 ? '' : 's');
    }
    if (ctype_digit($duration)) {
        return $duration . ' month' . ((int)$duration === 1 ? '' : 's');
    }

    return $duration;
}

function dashboardTierFeatures(array $tier): array {
    $features = [
        (int)($tier['product_limit'] ?? 0) . ' active listings',
        (int)($tier['images_per_product'] ?? 0) . ' images per product',
        !empty($tier['ads_boost']) ? 'Ads boost enabled' : 'Standard listing rank',
    ];

    if (!empty($tier['priority'])) {
        $features[] = ucfirst((string)$tier['priority']) . ' search priority';
    }

    if (($tier['tier_name'] ?? 'basic') !== 'basic') {
        $features[] = 'Verified seller badge styling';
    }

    $benefits = json_decode((string)($tier['benefits'] ?? '[]'), true);
    if (is_array($benefits)) {
        foreach ($benefits as $benefit) {
            if (is_string($benefit) && trim($benefit) !== '') {
                $features[] = trim($benefit);
            }
        }
    }

    return $features;
}

function dashboardSortTiers(array $tiers): array {
    $order = ['basic' => 0, 'premium' => 1, 'pro' => 2];
    usort($tiers, function (array $left, array $right) use ($order): int {
        return ($order[$left['tier_name']] ?? 99) <=> ($order[$right['tier_name']] ?? 99);
    });
    return $tiers;
}

if (!function_exists('dashboardOrderQuantity')) {
    function dashboardOrderQuantity(array $order): int {
        return max(1, (int)($order['quantity'] ?? 1));
    }
}

if (!function_exists('dashboardRestoreOrderStock')) {
    function dashboardRestoreOrderStock(PDO $pdo, array $order): void {
        $qty = dashboardOrderQuantity($order);
        $pdo->prepare("
            UPDATE products
            SET quantity = quantity + ?,
                status = CASE WHEN status = 'sold' THEN 'approved' ELSE status END
            WHERE id = ?
        ")->execute([$qty, $order['product_id']]);
    }
}

if (!function_exists('dashboardSaleDescription')) {
    function dashboardSaleDescription(int $orderId): string {
        return "Sale of order #$orderId";
    }
}

if (!function_exists('dashboardCreateSaleTransaction')) {
    function dashboardCreateSaleTransaction(PDO $pdo, array $order, int $orderId): void {
        $sellerId = (int)($order['seller_id'] ?? 0);
        if ($sellerId <= 0) {
            return;
        }

        $check = $pdo->prepare("
            SELECT id
            FROM transactions
            WHERE user_id = ?
              AND type = 'sale'
              AND status = 'completed'
              AND description = ?
            LIMIT 1
        ");
        $check->execute([$sellerId, dashboardSaleDescription($orderId)]);

        if ($check->fetchColumn()) {
            return;
        }

        $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, status, reference, description)
            VALUES (?, 'sale', ?, 'completed', ?, ?)
        ")->execute([
            $sellerId,
            (float)($order['price'] ?? 0),
            generateRef('SAL'),
            dashboardSaleDescription($orderId),
        ]);
    }
}

// --- FETCH ORDERS ---
$buyer_orders = [];
$seller_orders = [];
try {
    // Always load purchases for any logged-in user — sellers can buy from other sellers
    $s = $pdo->prepare("SELECT o.*, p.title as product_title, p.price as product_price, s.username as seller_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users s ON o.seller_id=s.id WHERE o.buyer_id=? ORDER BY o.created_at DESC");
    $s->execute([$user['id']]); $buyer_orders = $s->fetchAll(PDO::FETCH_ASSOC);

    if ($hasSellerDashboardAccess) {
        $order_filter = $_GET['order_filter'] ?? 'all';
        $order_page = max(1, (int)($_GET['page'] ?? 1));
        $order_offset = ($order_page - 1) * 10;
        $orderSql = "SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users b ON o.buyer_id=b.id WHERE o.seller_id=?";
        $orderParams = [$user['id']];
        if ($order_filter !== 'all') {
            $orderSql .= " AND o.status=?";
            $orderParams[] = $order_filter;
        }
        // Count total for pagination
        $countSql = str_replace("SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name", "SELECT COUNT(*)", $orderSql);
        $cs = $pdo->prepare($countSql); $cs->execute($orderParams); $total_orders = (int)$cs->fetchColumn();
        $order_total_pages = max(1, (int)ceil($total_orders / 10));
        $orderSql .= " ORDER BY o.created_at DESC LIMIT 10 OFFSET $order_offset";
        $s = $pdo->prepare($orderSql);
        $s->execute($orderParams); $seller_orders = $s->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}

// Show buyer-orders UI to anyone who has at least one purchase (covers sellers buying from other sellers)
$showBuyerOrdersView = $hasBuyerDashboardAccess || count($buyer_orders) > 0;

require_once 'includes/ai_recommendations.php';

require_once 'includes/header.php';
?>
<style>
/* DASHBOARD ALIGNMENT FIXES */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    padding: 1.5rem 1rem;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 120px;
    border-radius: 16px;
}

.stat-card .stat-val {
    font-family: var(--font-heading, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
    margin-bottom: 0.5rem;
    color: var(--text-main);
}

.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    line-height: 1.2;
    margin: 0;
}

.glass {
    background: rgba(255, 255, 255, 0.95);
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.grid-2-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.grid-2-cols .glass {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 1.25rem;
    text-align: left;
}

.text-muted {
    color: var(--text-muted) !important;
    font-size: 0.8rem !important;
    line-height: 1.4 !important;
}

@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 1rem 0.75rem;
        min-height: 100px;
    }
    
    .stat-card .stat-val {
        font-size: 1.5rem;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
    }
    
    .grid-2-cols {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stat-grid {
        grid-template-columns: 1fr;
    }
}

/* FULL-SCREEN DASHBOARD LAYOUT */
/* layout sizing overrides */
.container { 
    max-width: none !important; 
    width: 96% !important; 
    padding-left: 2rem !important; 
    padding-right: 2rem !important; 
}
/* layout sizing overrides */
.container { 
    max-width: none !important; 
    width: 96% !important; 
    padding-left: 2rem !important; 
    padding-right: 2rem !important; 
}

/* SELLER DASHBOARD LAYOUT FIXES */
   SELLER DASHBOARD LAYOUT FIXES
/* seller product/list layout */
.product-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.03); /* Subtle backdrop */
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.product-row:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Ensure actions don't overflow the box */
.product-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    flex-shrink: 0;
    align-items: center;
}

@media (max-width: 900px) {
    .product-row {
        flex-direction: column;
        align-items: stretch;
        gap: 1.25rem;
    }
    .product-actions {
        justify-content: flex-start;
        width: 100%;
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }
}

/* Tooltip stabilization - anchor to the button relative box */
.boost-tooltip {
    position: relative;
    display: inline-block;
}
.boost-info {
    visibility: hidden;
    width: 240px;
    background: #1d1d1f; /* Clean Apple Dark */
    color: #fff;
    text-align: left;
    padding: 1rem;
    border-radius: 14px;
    position: absolute;
    z-index: 10000;
    bottom: 140%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: all 0.3s ease;
    font-size: 0.78rem;
    line-height: 1.5;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: var(--shadow-lg);
    pointer-events: none;
}
.boost-tooltip:hover .boost-info {
    visibility: visible;
    opacity: 1;
    bottom: 125%;
}

/* Discount Input Styling */
.discount-input-inline {
    width: 65px;
    padding: 0.45rem 0.75rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    transition: border-color 0.2s;
}
.discount-input-inline:focus {
    border-color: var(--primary);
    outline: none;
}

/* Responsive Dashboard Product Grid */
@media (max-width: 768px) {
    #detailed-product-grid {
        padding: 0.75rem !important;
        margin: 0 -0.5rem !important;
    }
    #detailed-product-grid .product-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    #detailed-product-grid .product-card {
        min-height: auto !important;
        border-radius: 12px !important;
    }
    #detailed-product-grid .product-body {
        padding: 0.6rem !important;
    }
    #detailed-product-grid h4 {
        font-size: 0.8rem !important;
        line-height: 1.25 !important;
        min-height: 2em !important;
    }
    .products_section_summary {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1.5rem !important;
    }
    .inventory-slots-card {
        width: 100% !important;
        text-align: left !important;
    }
    
    /* DASHBOARD MOBILE STACKING */
    .dashboard-grid {
        grid-template-columns: 1fr !important;
        gap: 1.5rem !important;
    }
    
    /* ORDERS & TRANSACTIONS MOBILE VIEW */
    .order-card, .transaction-card {
        display: block !important;
        width: 100% !important;
    }
    
    .order-item-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    
    .order-actions {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .order-actions .btn {
        width: 100% !important;
        justify-content: center !important;
    }
}

/* PROFILE PIC CENTERING FIX */
.profile-pic-lg {
    width: 110px;
    height: 110px;
    border-radius: 50% !important;
    object-fit: cover;
    display: block !important;
    margin: 0 auto 1.25rem !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 3px solid #fff;
    transition: transform 0.3s;
}

/* TOAST NOTIFICATION */
.toast-notify {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(29, 29, 31, 0.95);
    color: #fff;
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    z-index: 10000;
    transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1), opacity 0.5s;
    opacity: 0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 8px;
}
.toast-notify.visible {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}
</style>
<?php
if ($hasSellerDashboardAccess):
    require __DIR__ . '/includes/seller_dashboard_workspace.php';
else:
if($msg): ?><div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Global Announcements -->
<?php if(count($announcements) > 0): ?>
<div class="announcements-area mb-3">
    <?php foreach($announcements as $ann): ?>
        <div class="alert alert-<?= $ann['type'] ?> fade-in" style="margin-bottom:0.5rem; display:flex; align-items:center; gap:10px;">
            <span style="font-size:1.2rem;">&#128226;</span>
            <div style="flex:1;">
                <strong>Global Update:</strong> <?= nl2br(htmlspecialchars($ann['message'])) ?>
                <small style="display:block; font-size:0.7rem; opacity:0.7;"><?= date('M d, H:i', strtotime($ann['created_at'])) ?> by Admin</small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($user['vacation_mode']): ?>
<div class="alert alert-warning"><strong>Vacation Mode ON</strong> &mdash; Your listings are hidden from buyers.
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="toggle_vacation">
        <?= csrf_field() ?>
        <button type="submit" style="background:none; border:none; color:inherit; text-decoration:underline; cursor:pointer;">Disable</button>
    </form>
</div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- SIDEBAR -->
    <div>
        <!-- Profile Card -->
        <div class="glass fade-in" style="padding:2rem; text-align:center; margin-bottom:1.5rem;">
            <?php $tierClass = 'profile-pic-' . ($hasSellerDashboardAccess ? ($user['seller_tier'] ?: 'basic') : 'basic'); ?>
            <?php if($user['profile_pic']): ?>
                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>" class="profile-pic profile-pic-lg profile-pic-previewable <?= $tierClass ?> mb-2" style="cursor:pointer;" alt="<?= htmlspecialchars($user['username']) ?>">
            <?php else: ?>
                <div class="profile-pic profile-pic-lg <?= $tierClass ?> mb-2" style="display:flex;align-items:center;justify-content:center;background:rgba(99,102,241,0.2);color:var(--primary);font-size:2.5rem;font-weight:700;margin:0 auto;">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <h3><?= htmlspecialchars($user['username']) ?></h3>
            <div class="mb-1">
                <?php if($hasSellerDashboardAccess): ?>
                    <?= getBadgeHtml($pdo, $user['seller_tier'] ?: 'basic') ?>
                <?php elseif($user['role'] === 'admin'): ?>
                    <span class="badge badge-gold">Admin</span>
                <?php else: ?>
                    <span class="badge badge-blue">Buyer</span>
                <?php endif; ?>

                <?php if($user['verified']): ?><span class="badge badge-approved" style="margin-left:4px;">&#10003; Verified</span><?php endif; ?>
            </div>
            
            <?php if(!empty($user['faculty'])): ?><p class="text-muted" style="font-size:0.78rem; margin-top:0.3rem;">Faculty: <?= htmlspecialchars((string)$user['faculty']) ?></p><?php endif; ?>
            <?php if(!empty($user['department']) || !empty($user['level'])): ?>
                <p class="text-muted" style="font-size:0.85rem;">
                    <?php if(!empty($user['department'])): ?><?= htmlspecialchars((string)$user['department']) ?><?php endif; ?>
                    <?php if(!empty($user['department']) && !empty($user['level'])): ?> &middot; <?php endif; ?>
                    <?php if(!empty($user['level'])): ?>L<?= htmlspecialchars((string)$user['level']) ?><?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if(!empty($user['hall'])): ?><p class="text-muted" style="font-size:0.8rem;">Hall: <?= htmlspecialchars((string)$user['hall']) ?></p><?php endif; ?>
            <?php if(!empty($user['phone'])): ?>
                <p class="text-muted" style="font-size:0.8rem;">
                    Phone: <a href="<?= formatWhatsAppLink($user['phone']) ?>" target="_blank" style="color:inherit; text-decoration:none;"><?= htmlspecialchars((string)$user['phone']) ?></a>
                </p>
            <?php endif; ?>
            
            <!-- Social Links -->
            <div style="display:flex; flex-direction:column; align-items:center; gap:0.75rem; margin-top:1.5rem;">
                <?php if($user['whatsapp'] || !empty($user['phone'])): ?>
                    <a href="<?= formatWhatsAppLink($user['whatsapp'] ?: $user['phone']) ?>" target="_blank" class="btn" style="background:#25D366; color:#fff; width:140px; justify-content:center; border-radius:14px; font-weight:700; font-size:0.85rem; padding:0.6rem;">
                        <i class="fab fa-whatsapp" style="font-size:1.1rem; margin-right:6px;"></i> WhatsApp
                    </a>
                <?php endif; ?>
                
                <a href="edit_profile.php" class="react-liquid-btn" data-label="Edit Profile" style="display:inline-flex; align-items:center; justify-content:center; min-height:42px; width:140px; flex-shrink:0;"></a>
            </div>
        </div>

        <!-- Subscription Status Card -->
        <?php if($hasSellerDashboardAccess): ?>
        <div class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem; border: 1px solid <?= ($user['seller_tier'] !== 'basic') ? 'var(--gold)' : 'rgba(255,255,255,0.1)' ?>;">
            <h4 style="margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                Subscription Status
            </h4>
            
            <div style="background:rgba(255,255,255,0.05); padding:1rem; border-radius:12px; margin-bottom:1rem;">
                <p style="font-size:0.75rem; color:rgba(255,255,255,0.6); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Current Plan</p>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-weight:700; font-size:1.1rem; color:<?= ($user['seller_tier'] === 'premium') ? 'var(--gold)' : (($user['seller_tier'] === 'pro') ? '#8e8e93' : '#fff') ?>;">
                        <?= ucfirst($user['seller_tier'] ?: 'basic') ?>
                    </span>
                    <?= getBadgeHtml($pdo, $user['seller_tier'] ?: 'basic') ?>
                </div>
            </div>

            <?php 
            $isTierExpired = ($user['seller_tier'] === 'basic' && !empty($user['tier_expires_at']) && strtotime($user['tier_expires_at']) < time());
            $isLifetime = ($user['seller_tier'] !== 'basic' && empty($user['tier_expires_at']));
            ?>

            <?php if($isLifetime): ?>
                <div style="background:rgba(212,175,55,0.1); padding:1rem; border-radius:12px; border:1px solid rgba(212,175,55,0.2);">
                    <p style="font-size:0.75rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Subscription Status</p>
                    <div style="font-size:1.1rem; font-weight:700; color:#fff; display:flex; align-items:center; gap:0.5rem;">
                        <span>♾️ Lifetime Access</span>
                    </div>
                </div>
            <?php elseif($user['seller_tier'] !== 'basic' && !empty($user['tier_expires_at'])): ?>
                <div id="subscription-countdown" data-expiry="<?= $user['tier_expires_at'] ?>" style="background:rgba(124,58,237,0.1); padding:1rem; border-radius:12px; border:1px solid rgba(124,58,237,0.2);">
                    <p style="font-size:0.75rem; color:rgba(255,255,255,0.6); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Expires In</p>
                    <div id="countdown-timer" style="font-family:monospace; font-size:1.2rem; font-weight:700; color:#fff;">
                        --:--:--
                    </div>
                </div>
                <script>
                    (function() {
                        const expiryStr = document.getElementById('subscription-countdown').dataset.expiry;
                        if (!expiryStr) return;
                        const expiry = new Date(expiryStr).getTime();
                        const timerDisplay = document.getElementById('countdown-timer');
                        
                        function updateTimer() {
                            const now = new Date().getTime();
                            const distance = expiry - now;
                            
                            if (distance < 0) {
                                timerDisplay.innerHTML = "EXPIRED";
                                timerDisplay.style.color = "#ef4444";
                                return;
                            }
                            
                            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                            
                            let display = "";
                            if (days > 0) display += days + "d ";
                            display += String(hours).padStart(2, '0') + ":" + 
                                       String(minutes).padStart(2, '0') + ":" + 
                                       String(seconds).padStart(2, '0');
                            
                            timerDisplay.innerHTML = display;
                        }
                        
                        updateTimer();
                        setInterval(updateTimer, 1000);
                    })();
                </script>
            <?php elseif($isTierExpired): ?>
                <div style="background:rgba(239,68,68,0.1); padding:1rem; border-radius:12px; border:1px solid rgba(239,68,68,0.2); margin-bottom:1rem;">
                    <p style="font-size:0.75rem; color:#ef4444; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Subscription Expired</p>
                    <p style="font-size:0.8rem; color:rgba(255,255,255,0.7); margin:0;">Your account is in restricted mode. Renew to reactivate your listings.</p>
                </div>
                <button type="button" class="btn btn-gold btn-sm w-100" onclick="toggleUpgradeModal(true);">Renew Subscription</button>
            <?php else: ?>
                <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">Upgrade to Pro or Premium to unlock more features and boost your sales.</p>
                <button type="button" class="btn btn-gold btn-sm w-100" onclick="toggleUpgradeModal(true);">Get Premium Account</button>
            <?php endif; ?>

            <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,0.1);">
                <form method="POST" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <input type="hidden" name="action" value="toggle_vacation">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm" style="justify-content:center;" <?= ($isTierExpired && $user['vacation_mode']) ? 'disabled title="Cannot disable while subscription is expired"' : '' ?>>
                        <?= $user['vacation_mode'] ? 'End Vacation Mode' : 'Go on Vacation' ?>
                    </button>
                    <?php if($isTierExpired && $user['vacation_mode']): ?>
                        <p style="font-size:0.65rem; color:#ef4444; text-align:center; margin-top:0.25rem;">Locked: Subscription expired</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <style>
            body.modal-open {
                overflow: hidden;
                position: fixed;
                width: 100%;
            }
            .upgrade-card-grid {
                display: flex;
                flex-wrap: nowrap;
                justify-content: center;
                align-items: stretch;
                gap: 0.75rem;
                width: 100%;
                padding: 0.5rem 0;
            }
            .upgrade-card-grid::-webkit-scrollbar { display: none; }
            .upgrade-modal-content {
                width: 100%;
                max-width: 880px;
                padding: 2rem 1.25rem;
                border-radius: 32px;
                position: relative;
                background: rgba(10,10,15,0.95);
                color: var(--text-main);
                border: 1px solid rgba(255,255,255,0.1);
                box-shadow: 0 40px 100px rgba(0,0,0,0.6);
            }
            .tier-card {
                flex: 1 1 260px;
                max-width: 280px;
                min-height: 460px;
                padding: 1.5rem 1.25rem;
                border-radius: 24px;
                transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease;
                display: flex;
                flex-direction: column;
                position: relative;
                overflow: visible;
            }
            .tier-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            }
            .tier-card.featured {
                transform: scale(1.02);
                z-index: 2;
                border-width: 2px;
            }
            .tier-card.featured:hover {
                transform: translateY(-5px) scale(1.02);
            }
            .tier-floating-badge {
                position: absolute;
                top: -12px;
                left: 50%;
                transform: translateX(-50%);
                border-radius: 999px;
                padding: 0.35rem 1rem;
                font-size: 0.65rem;
                font-weight: 900;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                white-space: nowrap;
                box-shadow: 0 6px 15px rgba(0,0,0,0.3);
            }
            .tier-status-pill {
                position: absolute;
                top: 15px;
                right: 15px;
                border-radius: 999px;
                padding: 0.3rem 0.8rem;
                font-size: 0.6rem;
                font-weight: 800;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                z-index: 5;
            }
            .tier-price-row {
                display: flex;
                flex-direction: column;
                gap: 0.1rem;
                margin-bottom: 1.25rem;
            }
            .tier-feature-list {
                list-style: none;
                padding: 0;
                margin: 0 0 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                flex-grow: 1;
            }
            .tier-feature-item {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                font-size: 0.82rem;
                font-weight: 500;
            }
            .tier-feature-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                flex-shrink: 0;
                font-size: 0.75rem;
                font-weight: 900;
            }
            @media (max-width: 992px) {
                .upgrade-modal-content {
                    max-width: 650px;
                    padding: 1.5rem 0.75rem;
                }
                .upgrade-card-grid {
                    flex-wrap: wrap;
                    gap: 0.75rem;
                }
                .tier-card {
                    flex: 1 1 45%;
                    max-width: none;
                    min-height: 400px;
                }
            }
            @media (max-width: 640px) {
                .upgrade-modal-content {
                    padding: 1rem 0.7rem 1.2rem;
                    border-radius: 32px 32px 0 0;
                    margin-top: auto;
                    max-height: 90vh;
                    overflow-y: auto;
                }
                .upgrade-card-grid {
                    flex-direction: column;
                    flex-wrap: nowrap;
                    justify-content: flex-start;
                    align-items: stretch;
                    overflow-x: visible;
                    overflow-y: visible;
                    padding: 0 0 1rem;
                }
                .tier-card {
                    flex: 1 1 auto;
                    width: 100%;
                    min-height: 0;
                    padding: 0.9rem 0.85rem;
                }
                .tier-card:hover,
                .tier-card.featured,
                .tier-card.featured:hover { transform: none; }
                .tier-title { font-size: 0.98rem !important; }
                .tier-price { font-size: 1.5rem !important; }
                .tier-feature-item { font-size: 0.76rem; }
                .product-grid { 
                    grid-template-columns: 1fr !important; 
                    gap: 1rem !important;
                }
                .product-card { min-height: auto !important; }
                .dashboard-grid { grid-template-columns: 1fr !important; gap: 1rem !important; }
            }
        </style>

        <script>
            function toggleUpgradeModal(show) {
                const modal = document.getElementById('upgradeModal');
                if (show) {
                    modal.style.display = 'flex';
                    document.body.classList.add('modal-open');
                } else {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            }
        </script>

        <!-- Upgrade Modal -->
        <div id="upgradeModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999999; align-items:center; justify-content:center; overflow-y:auto; -webkit-overflow-scrolling: touch;">
            <?php
            $tiers = dashboardSortTiers(array_values(getAccountTiers($pdo)));
            $tierPricingConfig = [];
            foreach ($tiers as &$tier) {
                $tier['visual'] = array_replace([
                    'accent' => '#7c3aed',
                    'background' => 'linear-gradient(180deg, rgba(10,10,14,0.9), rgba(10,10,14,0.68))',
                    'border' => 'rgba(124,58,237,0.24)',
                    'button_text' => '#ffffff',
                    'text' => '#ffffff',
                    'label' => 'rgba(255,255,255,0.72)',
                    'soft' => 'rgba(124,58,237,0.16)',
                    'shadow' => 'rgba(124,58,237,0.18)',
                ], dashboardTierVisual((string)($tier['tier_name'] ?? 'basic')));
                $tier['duration_label'] = (string) dashboardTierDurationLabel($tier['duration'] ?? '');
                $tier['feature_list'] = array_values(array_filter(dashboardTierFeatures($tier), static fn($feature) => is_string($feature) && trim($feature) !== ''));
                $tierPricingConfig[(string)($tier['tier_name'] ?? 'basic')] = [
                    'price' => (float)($tier['price'] ?? 0),
                    'duration_label' => $tier['duration_label'],
                ];
            }
            unset($tier);
            ?>
            <div class="glass upgrade-modal-content fade-in" style="overflow-x: hidden;">
                 <button type="button" onclick="toggleUpgradeModal(false);" style="position:absolute; top:20px; right:20px; background:rgba(255,255,255,0.1); border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--text-main); cursor:pointer; z-index:100;">&times;</button>
                 
                 <div class="text-center mb-3">
                    <h2 style="font-weight:800; letter-spacing:-0.03em; font-size:1.6rem; margin-bottom:0.2rem;">Upgrade Plan</h2>
                    <p class="text-muted" style="font-size:0.8rem; margin:0;">Choose a plan to grow your campus business.</p>
                 </div>
                 
                 <div class="upgrade-card-grid">
                    <?php foreach ($tiers as $tier): ?>
                        <?php
                            $active = ($user['seller_tier'] ?: 'basic') === $tier['tier_name'];
                            $isFeatured = $tier['tier_name'] === 'premium';
                            $visual = $tier['visual'];
                            $price = (float)($tier['price'] ?? 0);
                            $buttonStyle = $price > 0
                                ? "width:100%; border-radius:16px; padding:1rem 1.2rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; background:{$visual['accent']}; color:{$visual['button_text']}; border:none; box-shadow:0 18px 36px {$visual['shadow']};"
                                : "width:100%; border-radius:16px; padding:1rem 1.2rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; background:transparent; color:{$visual['accent']}; border:1px solid {$visual['border']};";
                        ?>
                        <div class="tier-card <?= $isFeatured ? 'featured' : '' ?>" style="background:<?= htmlspecialchars($visual['background']) ?>; border:1px solid <?= htmlspecialchars($active ? $visual['accent'] : $visual['border']) ?>; box-shadow:0 24px 48px <?= htmlspecialchars($visual['shadow']) ?>;">
                            <?php if($isFeatured && !$active): ?>
                                <span class="tier-floating-badge" style="background:<?= htmlspecialchars($visual['accent']) ?>; color:<?= htmlspecialchars($visual['button_text']) ?>;">Best Deal</span>
                            <?php endif; ?>
                            <?php if($active): ?>
                                <span class="tier-status-pill" style="background:<?= htmlspecialchars($visual['soft']) ?>; color:<?= htmlspecialchars($visual['accent']) ?>; border:1px solid <?= htmlspecialchars($visual['border']) ?>;">Active Plan</span>
                            <?php elseif($isFeatured): ?>
                                <span class="tier-status-pill" style="background:rgba(255,255,255,0.12); color:#fff; border:1px solid rgba(255,255,255,0.22);">Featured</span>
                            <?php endif; ?>
                            
                            <div style="margin-bottom:1rem;">
                                <p style="margin:0 0 0.5rem; color:<?= htmlspecialchars($visual['label']) ?>; font-size:0.75rem; font-weight:900; letter-spacing:0.12em; text-transform:uppercase;">
                                    <?= htmlspecialchars(ucfirst($tier['tier_name'])) ?> Plan
                                </p>
                                <h3 class="tier-title" style="text-transform:capitalize; margin-bottom:0; font-weight:850; font-size:1.5rem; color:<?= htmlspecialchars($visual['text']) ?>;"><?= htmlspecialchars($tier['tier_name']) ?></h3>
                            </div>
                            <?php 
                                $price = (float)$tier['price'];
                                $originalPrice = isset($tier['original_price']) ? (float)$tier['original_price'] : 0.0;
                                $isDiscounted = ($originalPrice > $price);
                                // Contrast fix for Premium gold background
                                $priceColor = ($tier['tier_name'] === 'premium') ? '#000' : htmlspecialchars($visual['accent']);
                                $labelColor = ($tier['tier_name'] === 'premium') ? 'rgba(0,0,0,0.6)' : htmlspecialchars($visual['label']);
                                $oldPriceColor = ($tier['tier_name'] === 'premium') ? 'rgba(0,0,0,0.35)' : 'rgba(255,255,255,0.4)';
                                $badgeStyle = ($tier['tier_name'] === 'premium') 
                                    ? "background:#000; color:#fff; font-size:0.4em; padding:4px 10px; border-radius:99px; vertical-align:middle; margin-left:8px; font-weight:900; text-transform:uppercase;"
                                    : "background:var(--gold); color:#000; font-size:0.4em; padding:4px 10px; border-radius:99px; vertical-align:middle; margin-left:8px; font-weight:900; text-transform:uppercase;";
                            ?>
                            <div class="tier-price-row" style="margin-bottom: 1.5rem;">
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <?php if($isDiscounted): ?>
                                        <span style="text-decoration: line-through; color: <?= $oldPriceColor ?>; font-size: 1rem; font-weight: 700;">₵<?= number_format($originalPrice, 0) ?></span>
                                    <?php endif; ?>
                                    <div style="display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;">
                                        <span class="tier-price" style="font-size:2.6rem; font-weight:900; line-height:1; letter-spacing:-0.05em; color:<?= $priceColor ?>;">
                                            <?= $price > 0 ? '₵' . number_format($price, $price == floor($price) ? 0 : 2) : 'Free' ?>
                                        </span>
                                        <?php if($price > 0): ?>
                                            <span style="font-size:0.85rem; color:<?= $labelColor ?>; font-weight:700;">/ <?= htmlspecialchars($tier['duration_label']) ?></span>
                                        <?php endif; ?>
                                        <?php if($isDiscounted): ?>
                                            <span class="sale-badge" style="<?= $badgeStyle ?> margin-top: 2px;">-<?= round((1 - ($price/$originalPrice)) * 100) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <p style="margin:0 0 1rem; color:<?= htmlspecialchars($visual['label']) ?>; font-size:0.8rem; line-height:1.4;">
                                <?= $price > 0 ? 'Built for sellers who want more reach and stronger trust.' : 'A clean starting point for new sellers.' ?>
                            </p>
                            <?php if(false): ?>
                            <div class="tier-price-row">
                                GHS <?= number_format($tier['price'], 0) ?>
                                <p style="font-size:0.9rem; margin-top:0.3rem; margin-bottom:0; color:var(--text-muted); font-weight:600; letter-spacing:normal;">
                                    Valid for <?= htmlspecialchars($tier['duration']) ?> month<?= $tier['duration'] == 1 ? '' : 's' ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <ul class="tier-feature-list">
                                <?php foreach($tier['feature_list'] as $feature): ?>
                                <li class="tier-feature-item" style="color:<?= htmlspecialchars($visual['text']) ?>;">
                                    <span class="tier-feature-icon" style="background:<?= htmlspecialchars($visual['soft']) ?>; color:<?= htmlspecialchars($visual['accent']) ?>;">&#10003;</span>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if(false): ?>
                            <ul style="list-style:none; padding:0; margin-bottom:1.5rem; flex-grow:1;">
                                <?php
                                $d_bens = json_decode($tier['benefits'] ?? '[]', true) ?: [];
                                foreach($d_bens as $b): 
                                ?>
                                <li style="margin-bottom:0.8rem; font-size:0.92rem; display:flex; gap:10px; font-weight:500; color:<?= ($tier['tier_name'] === 'premium') ? '#000' : 'inherit' ?>;">
                                    <span style="color:<?= ($tier['tier_name'] === 'premium') ? '#000' : 'var(--primary)' ?>; font-weight:800;">&#10003;</span> <?= htmlspecialchars($b) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            
                            <?php if(!$active): ?>
                                    <?php if($price > 0): ?>
                                        <button type="button" onclick="payWithPaystack('<?= htmlspecialchars($tier['tier_name']) ?>')" class="btn" style="<?= $buttonStyle ?>; <?= ($tier['tier_name'] === 'premium') ? 'background:#000; color:#fff;' : '' ?> font-weight:800; border-radius:12px; text-transform:uppercase; letter-spacing:0.02em; padding: 0.8rem;">
                                            Upgrade
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="width:100%;">
                                            <input type="hidden" name="action" value="request_<?= htmlspecialchars($tier['tier_name']) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn" style="<?= $buttonStyle ?>; <?= ($tier['tier_name'] === 'premium') ? 'background:#000; color:#fff;' : '' ?> font-weight:800; border-radius:12px; text-transform:uppercase; letter-spacing:0.02em; padding: 0.8rem;">Select Free</button>
                                        </form>
                                    <?php endif; ?>
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <div style="width:100%; text-align:center; background:<?= ($tier['tier_name'] === 'premium') ? 'rgba(0,0,0,0.08)' : htmlspecialchars($visual['soft']) ?>; padding:1rem; border-radius:16px; font-weight:800; color:<?= ($tier['tier_name'] === 'premium') ? '#000' : htmlspecialchars($visual['accent']) ?>; border:1px solid <?= ($tier['tier_name'] === 'premium') ? 'rgba(0,0,0,0.1)' : htmlspecialchars($visual['border']) ?>;">Active Plan</div>
                                    <?php if($tier['tier_name'] !== 'basic'): ?>
                                        <form method="POST" style="width:100%;">
                                            <input type="hidden" name="action" value="cancel_tier">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.8rem; justify-content:center; color:<?= ($tier['tier_name'] === 'premium') ? '#000' : 'var(--danger)' ?>; border-color:<?= ($tier['tier_name'] === 'premium') ? 'rgba(0,0,0,0.2)' : 'rgba(255,59,48,0.2)' ?>; font-weight:700; width:100%;" onclick="return confirm('Request to downgrade to Basic? Your limits will be reduced.')">Downgrade to Basic</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                 </div>
            </div>
        </div>


        <!-- Wallet -->
        <div class="glass fade-in wallet-card" style="padding:1.5rem; margin-bottom:1.5rem; margin-top:1.5rem;">
            <p class="text-muted" style="font-size:0.8rem;">Wallet Balance</p>
            <h2 style="color:var(--success); font-size:2rem; font-weight:800;">GHS <?= number_format($user['balance'], 2) ?></h2>
        </div>

        <!-- Recent Transactions -->
        <div id="transactions_section" class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h4 class="mb-2">Recent Activity</h4>
            <?php if(count($transactions) > 0): ?>
                <?php foreach(array_slice($transactions, 0, 5) as $tx): ?>
                    <?php $is_credit = in_array($tx['type'], ['deposit', 'sale', 'referral']); ?>
                    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border);">
                        <div>
                            <a href="receipt.php?ref=<?= urlencode($tx['reference']) ?>" style="font-size:0.85rem; font-weight:600; color:var(--text-main); text-decoration:none;">
                                <?= htmlspecialchars($tx['description'] ?? ucfirst($tx['type'])) ?>
                            </a>
                            <p style="font-size:0.7rem; color:var(--text-muted);"><?= date('M d', strtotime($tx['created_at'])) ?></p>
                        </div>
                        <span style="color:<?= $is_credit ? 'var(--mint)' : 'var(--danger)' ?>; font-weight:700; font-size:0.85rem;">
                            <?= $is_credit ? '+' : '-' ?>GHS <?= number_format($tx['amount'], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div style="text-align:center; padding-top:1rem;">
                    <a href="transactions.php" class="text-muted" style="font-size:0.75rem; text-decoration:underline;">View All History</a>
                </div>
            <?php else: ?>
                <p class="text-muted" style="font-size:0.85rem;">No history yet.</p>
            <?php endif; ?>
        </div>

        <!-- Contact Admin -->
        <?php if(!isAdmin() && $supportAdminId > 0): ?>
        <div class="glass fade-in" style="padding:1.5rem; margin-top:1.5rem;">
            <h4 class="mb-2">Contact Admin</h4>
            <p class="text-muted" style="font-size:0.8rem; margin-bottom:1rem;">Need clarification? Chat directly with the platform administrator.</p>
            <form method="POST" action="chat.php?action=send_fast" class="flex-column gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="receiver_id" value="<?= (int) $supportAdminId ?>">
                <textarea name="message" placeholder="Type your question here..." class="form-control" style="font-size:0.85rem; min-height:80px; padding:0.75rem; border-radius:12px;" required></textarea>
                <button class="btn btn-primary btn-sm" style="width:100%; border-radius:10px;">Send Message</button>
            </form>
            <a href="chat.php?user=<?= (int) $supportAdminId ?>" class="btn btn-outline btn-sm mt-1" style="width:100%; justify-content:center; border-radius:10px;">Open Chat History</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- MAIN AREA -->
    <div>
        <!-- ROLE-SPECIFIC ANALYTICS -->
        <?php if($hasSellerDashboardAccess): ?>
        <!-- SELLER STATS -->
        <div class="stat-grid mb-3 fade-in">
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $totalProducts ?></div><div class="stat-label">Total Products</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);"><?= $totalApproved ?></div><div class="stat-label">Active Listings</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--mint);"><?= $sellerTotalSold ?></div><div class="stat-label">Items Sold</div></div></a>
            <a href="#transactions_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">GHS <?= number_format($sellerRevenue, 2) ?></div><div class="stat-label">Total Revenue</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--warning);"><?= $totalPending ?></div><div class="stat-label">Pending Approval</div></div></a>
            <a href="#analytics_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val"><?= $viewsTotal ?></div><div class="stat-label">Total Views</div></div></a>
        </div>

        <!-- Seller Insights Row -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;" class="fade-in grid-2-cols">
            <?php if($sellerLowStock > 0): ?>
            <div class="glass" style="padding:1.25rem; border-left:4px solid #ff9500;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">Warning</span>
                    <h4 style="font-size:0.9rem; margin:0;">Low Stock Alert</h4>
                </div>
                <p style="font-size:2rem; font-weight:800; color:#ff9500;"><?= $sellerLowStock ?></p>
                <p class="text-muted" style="font-size:0.78rem;">products with 5 or fewer units</p>
            </div>
            <?php else: ?>
            <div class="glass" style="padding:1.25rem; border-left:4px solid var(--success);">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">OK</span>
                    <h4 style="font-size:0.9rem; margin:0;">Stock Status</h4>
                </div>
                <p style="font-size:0.85rem; color:var(--success); font-weight:600;">All products well stocked</p>
            </div>
            <?php endif; ?>

            <div class="glass" style="padding:1.25rem; border-left:4px solid var(--primary);">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">Top</span>
                    <h4 style="font-size:0.9rem; margin:0;">Top Product</h4>
                </div>
                <?php if($sellerTopProduct): ?>
                    <p style="font-size:0.95rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($sellerTopProduct['title']) ?></p>
                    <p class="text-muted" style="font-size:0.78rem;">Views: <?= $sellerTopProduct['views'] ?> &middot; Stock: <?= $sellerTopProduct['quantity'] ?></p>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">No products yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weekly Revenue + Views Chart -->
        <div id="analytics_section" class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h4 class="mb-2">Weekly Performance</h4>
            <canvas id="analyticsChart" height="100"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('analyticsChart');
            if(ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chart_labels ?? []) ?>,
                        datasets: [
                            {
                                label: 'Revenue (GHS)',
                                data: <?= json_encode($chart_revenue ?? []) ?>,
                                backgroundColor: 'rgba(124,58,237,0.4)',
                                borderColor: '#7c3aed',
                                borderWidth: 2,
                                borderRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Views',
                                data: <?= json_encode($chart_views ?? []) ?>,
                                type: 'line',
                                borderColor: '#34c759',
                                backgroundColor: 'rgba(52,199,89,0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: true,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'left', ticks: { color: '#94a3b8' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                            y1: { beginAtZero: true, position: 'right', ticks: { color: '#34c759' }, grid: { drawOnChartArea: false } },
                            x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
                        },
                        plugins: { legend: { labels: { color: 'var(--text-main)', usePointStyle: true } } }
                    }
                });
            }
        });
        </script>
        <?php endif; ?>

        <?php if($hasBuyerDashboardAccess): ?>
        <!-- BUYER STATS -->
        <div class="stat-grid mb-3 fade-in">
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $buyerItemsBought ?></div><div class="stat-label">Items Bought</div></div></a>
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">GHS <?= number_format($buyerTotalSpent, 2) ?></div><div class="stat-label">Total Spent</div></div></a>
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);"><?= count($buyerRecentPurchases) ?></div><div class="stat-label">Recent Orders</div></div></a>
            <a href="javascript:void(0)" onclick="if(typeof openSideCart === 'function') openSideCart();" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--mint);" id="dash-cart-count">0</div><div class="stat-label">Cart Items</div></div></a>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const updateDashCart = () => {
                    let d = localStorage.getItem('cm_cart');
                    let c = d ? JSON.parse(d) : [];
                    let el = document.getElementById('dash-cart-count');
                    if(el) el.innerText = c.length;
                };
                updateDashCart();
                window.addEventListener('storage', updateDashCart);
                // Also hook into custom cart events if any
            });
        </script>

        <!-- Buyer Insights -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;" class="fade-in grid-2-cols">
            <!-- Recent Purchases -->
            <div id="buyer_recent_purchases" class="glass" style="padding:1.25rem;">
                <h4 style="font-size:0.95rem; margin-bottom:0.75rem;">Recent Purchases</h4>
                <?php if(count($buyerRecentPurchases) > 0): ?>
                    <?php foreach($buyerRecentPurchases as $bp): ?>
                    <div style="display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0; border-bottom:1px solid var(--border);">
                        <?php if(!empty($bp['img'])): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($bp['img'])) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;" alt="">
                        <?php else: ?>
                            <div style="width:36px;height:36px;background:rgba(0,0,0,0.1);border-radius:6px;display:flex;align-items:center;justify-content:center;">IMG</div>
                        <?php endif; ?>
                        <div style="flex:1; overflow:hidden;">
                            <p style="font-size:0.82rem; font-weight:600; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?= htmlspecialchars($bp['title'] ?? 'Product') ?></p>
                            <p class="text-muted" style="font-size:0.72rem;"><?= date('M d', strtotime($bp['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">No purchases yet. Start shopping!</p>
                <?php endif; ?>
            </div>

            <!-- Favorite Categories -->
            <div class="glass" style="padding:1.25rem;">
                <h4 style="font-size:0.95rem; margin-bottom:0.75rem;">Favorite Categories</h4>
                <?php if(count($buyerFavCategories) > 0): ?>
                    <?php foreach($buyerFavCategories as $fc): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; border-bottom:1px solid var(--border);">
                        <span class="badge badge-blue" style="font-size:0.75rem;"><?= htmlspecialchars($fc['category']) ?></span>
                        <span style="font-size:0.8rem; font-weight:700;"><?= $fc['cnt'] ?> orders</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">Buy products to discover your favorites!</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($hasSellerDashboardAccess): ?>
        <!-- Products Section (Consolidated Card) -->
        <div id="products_section" class="fade-in mb-3">
            <div class="glass" style="padding:2rem; border-radius:24px; position:relative; overflow:hidden; background:linear-gradient(135deg, rgba(124,58,237,0.1) 0%, rgba(20,20,20,0) 100%); border:1px solid rgba(124,58,237,0.2);">
                <div class="products_section_summary" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.5rem;">
                            <span style="font-size:1.8rem;">Inventory</span>
                            <h3 style="margin:0; font-size:1.5rem; font-weight:800;">My Inventory</h3>
                        </div>
                        <p class="text-muted" style="font-size:0.9rem;">You have <strong><?= count($products) ?></strong> products listed on the marketplace.</p>
                        <div style="margin-top:1.25rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn btn-primary" onclick="toggleDetailedProducts()" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700;">Manage Products</button>
                            <?php if(canAddProduct($pdo, $user['id'])): ?>
                                <a href="add_product.php" class="btn btn-outline" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700;">+ New Product</a>
                            <?php endif; ?>
                            <button class="btn btn-outline" onclick="copyProductLink()" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700; color:var(--primary); border-color:var(--primary);">Share My Shop</button>
                        </div>
                    </div>
                    
                    <div class="inventory-slots-card" style="text-align:right;">
                        <div style="padding:1.25rem; background:rgba(255,255,255,0.05); border-radius:20px; border:1px solid rgba(255,255,255,0.1); display:inline-block;">
                            <p class="text-muted" style="font-size:0.75rem; margin-bottom:0.2rem;">Inventory Slots</p>
                            <?php 
                                $tier = $user['seller_tier'] ?: 'basic';
                                $limit = getAccountTiers($pdo)[$tier]['product_limit'] ?? 2;
                                $usage_pct = ($count_prods = count($products)) / $limit * 100;
                            ?>
                            <div style="font-size:1.4rem; font-weight:900; color:var(--text-main); line-height:1;"><?= $count_prods ?> / <?= $limit ?></div>
                            <div style="width:100px; height:6px; background:rgba(0,0,0,0.1); border-radius:3px; margin-top:8px; overflow:hidden;">
                                <div style="width:<?= min(100, $usage_pct) ?>%; height:100%; background:var(--primary);"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Grid (Hidden by Default) -->
            <div id="detailed-product-grid" class="glass mt-2" style="display:none; padding:2rem; border-radius:24px; animation: slideDown 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);">
                <div class="flex-between mb-4">
                    <h4 style="font-size:1.2rem; margin:0;">Product Catalog</h4>
                    <button class="btn btn-outline btn-sm" onclick="toggleDetailedProducts()">Close View</button>
                </div>

                <?php if(count($products) > 0): ?>
                    <div class="product-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:1.25rem;">
                        <?php foreach($products as $p): ?>
                            <div class="glass product-card" style="display:flex; flex-direction:column; background:var(--bg); border:1px solid var(--border); border-radius:22px; overflow:hidden; transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); min-height:400px; position:relative;">
                                <div class="product-img-wrap" style="aspect-ratio: 1 / 1; position:relative; overflow:hidden;">
                                    <?php if($p['main_image']): ?>
                                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" class="product-img" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.05); color:var(--text-muted); font-size:0.75rem;">No Image</div>
                                    <?php endif; ?>
                                    
                                    <div style="position:absolute; top:12px; left:12px; display:flex; flex-direction:column; gap:6px;">
                                        <?php
                                            $statusColor = match($p['status']) {
                                                'approved' => '#34c759',
                                                'pending' => '#ff9500',
                                                'paused' => '#8e8e93',
                                                'sold' => '#ff3b30',
                                                default => '#7c3aed'
                                            };
                                        ?>
                                        <span class="badge" style="background:<?= $statusColor ?>; color:#fff; border:none; font-size:0.65rem; padding:4px 10px; font-weight:700;"><?= strtoupper($p['status']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="product-body" style="padding:1.25rem; flex-grow:1; display:flex; flex-direction:column;">
                                    <h4 style="font-size:1.05rem; font-weight:700; margin-bottom:0.4rem;"><?= htmlspecialchars($p['title']) ?></h4>
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                                        <p style="font-size:1.25rem; font-weight:800; color:var(--primary); margin:0;">GHS <?= number_format($p['price'], 2) ?></p>
                                        <span class="text-muted" style="font-size:0.75rem; font-weight:600;">Qty: <?= $p['quantity'] ?></span>
                                    </div>

                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.6rem; margin-top:auto;">
                                        <?php if($p['status'] === 'approved'): ?>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="mark_sold">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:0.6rem; justify-content:center; color:#ff3b30; border-color:rgba(255,59,48,0.1);" onclick="return confirm('Mark as Sold Out?')">Sold Out</button>
                                            </form>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="pause">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:0.6rem; justify-content:center;">Pause</button>
                                            </form>
                                            <a href="generate_flyer.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="grid-column: span 2; font-size:0.8rem; padding:0.6rem; justify-content:center; border-radius:12px;">Flyer / Promo</a>
                                            <?php if(!$p['boosted_until'] || strtotime($p['boosted_until']) < time()): ?>
                                                <form method="POST" style="display:contents;">
                                                    <input type="hidden" name="action" value="boost">
                                                    <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-gold btn-sm" style="grid-column: span 2; font-size:0.8rem; padding:0.6rem; justify-content:center; border-radius:12px;" onclick="return confirm('Boost for 24h?')">Boost Item</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif($p['status'] === 'paused'): ?>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="unpause">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-success btn-sm" style="grid-column: span 2; font-size:0.8rem; justify-content:center; border-radius:12px;">Resume Listing</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:contents;">
                                            <input type="hidden" name="action" value="request_delete">
                                            <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline btn-sm" style="grid-column: span 2; font-size:0.7rem; padding:0.4rem; color:var(--text-muted); justify-content:center; border-style:dashed; margin-top:0.5rem; opacity:0.6;" onclick="return confirm('Request Deletion?')">Remove listing</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted" style="padding:4rem;">No products found.</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function toggleDetailedProducts() {
                const grid = document.getElementById('detailed-product-grid');
                if (grid.style.display === 'none') {
                    grid.style.display = 'block';
                    grid.scrollIntoView({ behavior: 'smooth' });
                } else {
                    grid.style.display = 'none';
                }
            }

            function copyProductLink() {
                const url = window.location.origin + window.location.pathname.replace('dashboard.php', 'index.php') + '?seller=<?= urlencode($user['username']) ?>';
                
                // --- ADVANCED CLIPBOARD FALLBACK ---
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(() => {
                        showDashToast('Shop link copied! Share it on WhatsApp.');
                    }).catch(err => fallbackCopy(url));
                } else {
                    fallbackCopy(url);
                }
            }

            function fallbackCopy(text) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed"; top: 0; left: 0;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showDashToast('Shop link copied! Share it on WhatsApp.');
                } catch (err) {
                    alert('Could not copy link. Manually copy: ' + text);
                }
                document.body.removeChild(textArea);
            }
        </script>
        <?php endif; ?>

        <!-- ORDERS VIEW (SELLER) -->
        <?php if($hasSellerDashboardAccess): ?>
        <div id="seller_orders" class="glass fade-in" style="margin-bottom:2rem; padding:2rem;">
            <h3 class="mb-3">Order Management</h3>
            <?php if(count($seller_orders) > 0): ?>
                <div class="flex-column gap-1">
                    <?php foreach($seller_orders as $ord): ?>
                    <div class="order-card" style="background:rgba(0,0,0,0.2); border:1px solid var(--border); padding:1rem; border-radius:12px;">
                        <div class="order-item-header flex-between mb-1">
                            <strong>#ORDER-<?= $ord['id'] ?> &bull; <?= htmlspecialchars($ord['product_title']) ?></strong>
                            <span class="badge" style="background:#7c3aed;color:#fff;">GHS <?= number_format($ord['product_price'], 2) ?></span>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem;">Buyer: <strong><?= htmlspecialchars($ord['buyer_name']) ?></strong> &bull; Date: <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?></p>
                        
                        <div class="order-actions" style="margin-top:0.75rem;">
                            <?php if($ord['status'] === 'ordered'): ?>
                                <span class="badge badge-pending mb-1">Status: Pending</span>
                                <form method="POST" class="flex-column gap-1" style="margin-top:0.75rem;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="accept_order">
                                    <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                    <input type="text" name="delivery_note" class="form-control" placeholder="Optional note for the buyer" style="font-size:0.85rem;">
                                    <div class="flex gap-1" style="flex-wrap:wrap;">
                                        <button type="submit" class="btn btn-primary btn-sm mt-1">Acknowledge Order</button>
                                        <button type="submit" name="action" value="reject_order" class="btn btn-outline btn-sm mt-1" onclick="return confirm('Decline this order?');">Decline Order</button>
                                    </div>
                                </form>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">Message Buyer</a>
                            <?php elseif($ord['status'] === 'seller_seen'): ?>
                                <span class="badge badge-approved mb-1">Status: Order Acknowledged</span>
                                <?php if(!empty($ord['delivery_note'])): ?>
                                    <p class="text-muted" style="font-size:0.8rem; margin:0.6rem 0;">Note to buyer: <?= htmlspecialchars($ord['delivery_note']) ?></p>
                                <?php endif; ?>
                                <form method="POST" style="display:inline-flex;" class="w-100">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="deliver_order">
                                    <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm mt-1" onclick="return confirm('Confirm item sold now?');">Confirm Item Sold</button>
                                </form>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">Message Buyer</a>
                            <?php elseif($ord['status'] === 'delivered'): ?>
                                <span class="badge badge-approved">Status: Sold (Awaiting Buyer Confirmation)</span>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">Message Buyer</a>
                            <?php elseif($ord['status'] === 'completed'): ?>
                                <span class="badge" style="background:var(--success); color:#fff;">Status: Completed</span>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">Chat History</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:1rem;">No orders to manage.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- BUYER ORDERS (shown to anyone with purchases — sellers can buy from other sellers too) -->
        <?php if($showBuyerOrdersView): ?>
        <div id="buyer_orders" class="glass fade-in mt-3" style="padding:2rem;">
            <div class="flex-between mb-3">
                <h3 style="margin:0;">My Orders <?php if(!$hasBuyerDashboardAccess): ?><span style="font-size:0.7rem; color:var(--text-muted); font-weight:500;">(Purchases)</span><?php endif; ?></h3>
                <?php if(hasUnreviewedOrders($pdo, $user['id'])): ?>
                    <span class="badge badge-rejected" style="padding:6px 12px; font-weight:800;">ACTION REQUIRED: Leave Reviews</span>
                <?php endif; ?>
            </div>
            <?php if(count($buyer_orders) > 0): ?>
                <div class="flex-column gap-1">
                    <?php foreach($buyer_orders as $ord): ?>
                    <div class="order-card" style="background:rgba(0,0,0,0.2); border:1px solid var(--border); padding:1rem; border-radius:12px;">
                        <div class="order-item-header flex-between mb-1">
                            <strong><?= htmlspecialchars($ord['product_title']) ?></strong>
                            <span class="badge" style="background:#7c3aed;color:#fff;">GHS <?= number_format($ord['product_price'], 2) ?></span>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem;">Seller: <strong><?= htmlspecialchars($ord['seller_name']) ?></strong> &bull; <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?></p>
                        
                        <div class="order-actions" style="margin-top:0.75rem;">
                            <?php if($ord['status'] === 'ordered'): ?>
                                <span class="badge badge-pending">Status: Pending (Awaiting Seller)</span>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-primary btn-sm mt-1">Message Seller</a>
                            <?php elseif($ord['status'] === 'seller_seen'): ?>
                                <span class="badge badge-approved mb-1">Status: Seller Acknowledged Your Order</span><br>
                                <?php if(!empty($ord['delivery_note'])): ?>
                                    <p class="text-muted" style="font-size:0.8rem; margin:0.6rem 0;">Seller note: <?= htmlspecialchars($ord['delivery_note']) ?></p>
                                <?php endif; ?>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-outline btn-sm mt-1">Message Seller</a>
                            <?php elseif($ord['status'] === 'delivered'): ?>
                                <span class="badge badge-approved mb-1">Status: Sold (Seller Confirmed)</span><br>
                                <form method="POST" style="display:inline-flex;" class="w-100">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="receive_order">
                                    <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm mt-1" onclick="return confirm('Confirm Item Received?');">Confirm Item Received</button>
                                </form>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-outline btn-sm mt-1">Message Seller</a>
                            <?php elseif($ord['status'] === 'completed'): ?>
                                <span class="badge" style="background:var(--success); color:#fff;">Status: Completed</span>
                                <div class="flex gap-1" style="flex-wrap:wrap; margin-top:0.5rem;">
                                    <a href="product.php?id=<?= $ord['product_id'] ?>&review_required=1#review" class="btn btn-outline btn-sm">Submit Review</a>
                                    <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-outline btn-sm">Chat History</a>
                                </div>
                                <a href="#" onclick="document.getElementById('dispute_form_<?= $ord['id'] ?>').style.display='block'; return false;" class="text-muted" style="font-size:0.75rem; margin-top:0.5rem; display:block; text-decoration:underline;">Report Issue</a>
                                <div id="dispute_form_<?= $ord['id'] ?>" style="display:none; margin-top:1rem; padding:1rem; background:rgba(255,59,48,0.05); border-radius:12px; border:1px solid rgba(255,59,48,0.1);">
                                    <p style="font-size:0.8rem; font-weight:700; color:var(--danger); margin-bottom:0.5rem;">Report Dispute to Admin</p>
                                    <form method="POST" action="?action=submit_dispute&oid=<?= $ord['id'] ?>" class="flex-column gap-1">
                                        <input type="hidden" name="action" value="submit_dispute">
                                        <input type="hidden" name="oid" value="<?= $ord['id'] ?>">
                                        <?= csrf_field() ?>
                                        <textarea name="reason" placeholder="Explain the issue..." class="form-control" style="font-size:0.8rem; min-height:60px;"></textarea>
                                        <div class="flex gap-1" style="margin-top:0.5rem;">
                                            <button class="btn btn-danger btn-sm">Report</button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('dispute_form_<?= $ord['id'] ?>').style.display='none';">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:1rem;">You have not placed any orders yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ADDED: Buyer Cart / Order Summary Section -->
<?php if($hasBuyerDashboardAccess): ?>
<div class="glass fade-in mt-3" style="padding:2rem;">
    <h3 class="mb-2">My Cart</h3>
    
    <div id="dash-cart-items" style="display:flex; flex-direction:column; gap:1rem;"></div>
    
    <div id="dash-cart-empty" style="text-align:center; padding:1.5rem; display:none;">
        <p class="text-muted" style="font-size:0.9rem;">Your cart is empty. Browse products to add items.</p>
    </div>
    
    <div id="dash-cart-footer" style="display:none; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <p style="font-size:0.9rem; color:var(--text-muted);">Total</p>
                <p id="dash-cart-total" style="font-size:1.8rem; font-weight:800; color:var(--primary);">GHS 0.00</p>
            </div>
            <div style="display:flex; gap:0.75rem;">
                <button class="btn btn-outline btn-sm" onclick="cmCart.clear(); renderDashCart();">Clear Cart</button>
            </div>
        </div>
        <div class="notice-box-inline mt-2">
            <strong><span style="font-size:1.1rem; vertical-align:middle; margin-right:5px;">Note</span> IMPORTANT</strong>
            <span style="color:var(--light);">This order is configured for <b>IN-PERSON PAYMENT</b>. Please pay the seller directly upon collection.</span>
        </div>
        <button class="btn btn-checkout-lg mt-2" style="width:100%;" onclick="window.location.href='checkout.php?ids=' + cmCart.get().map(i => i.id).join(',');">Proceed to Checkout</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.renderDashCart = function() {
        if(typeof cmCart === 'undefined') return;
        const cart = cmCart.get();
        const container = document.getElementById('dash-cart-items');
        const empty = document.getElementById('dash-cart-empty');
        const footer = document.getElementById('dash-cart-footer');
        
        if (cart.length === 0) {
            container.innerHTML = '';
            empty.style.display = 'block';
            footer.style.display = 'none';
            return;
        }
        
        empty.style.display = 'none';
        footer.style.display = 'block';
        
        container.innerHTML = cart.map(item => `
            <div style="display:grid; grid-template-columns:50px 1fr auto; gap:1rem; align-items:center; padding:1rem; background:rgba(0,0,0,0.2); border-radius:12px; border:1px solid var(--border);">
                <img src="${item.image || ''}" alt="${item.name}" onerror="this.style.background='rgba(0,0,0,0.3)'; this.alt='No Image';" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">
                <div>
                    <p style="font-weight:600; margin-bottom:4px; font-size:0.9rem;">${item.name}</p>
                    <p style="color:var(--primary); font-weight:700; font-size:0.95rem;">GHS ${(item.price * item.qty).toFixed(2)}</p>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="cmCart.updateQty(${item.id}, ${item.qty - 1}); renderDashCart();">-</button>
                    <span style="font-weight:600;font-size:0.9rem;">${item.qty}</span>
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="cmCart.updateQty(${item.id}, ${item.qty + 1}); renderDashCart();">+</button>
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px; color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="cmCart.remove(${item.id}); renderDashCart();">x</button>
                </div>
            </div>
        `).join('');
        
        document.getElementById('dash-cart-total').textContent = 'GHS ' + cmCart.total().toFixed(2);
    };
    renderDashCart();
});
</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
