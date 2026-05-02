<?php
$file = "dashboard.php";
$content = file_get_contents($file);

// 1 & 2. Add variables and tab routing
$search1 = '$user = getUser($pdo, $_SESSION[\'user_id\']);
if (!$user) { session_destroy(); redirect(\'login.php\'); }';
$replace1 = $search1 . "\n\n" . 
'// --- STORE SELLER VARIABLES ---
$seller_id = $user[\'id\'];
$seller_name = $user[\'username\'];
$seller_avatar = $user[\'profile_pic\'] ?? \'\';
$seller_plan = $user[\'seller_tier\'] ?: \'basic\';
$seller_verified = !empty($user[\'verified\']);
$wallet_balance = (float)($user[\'balance\'] ?? 0);

// --- TAB ROUTING ---
$tabWhitelist = [\'overview\',\'inventory\',\'orders\',\'analytics\',\'wallet\',\'messages\',\'settings\'];
$tab = $_GET[\'tab\'] ?? \'overview\';
if (!in_array($tab, $tabWhitelist, true)) {
    $tab = \'overview\';
}';
$content = str_replace($search1, $replace1, $content);

// 3. Count query variable mappings
$search2 = '$totalApproved = $approvedCount->fetchColumn();';
$replace2 = $search2 . "\n    \$active_listings = (int)\$totalApproved;";
$content = str_replace($search2, $replace2, $content);

$search3 = '$viewsTotal = $totalViews->fetchColumn();';
$replace3 = $search3 . "\n    \$total_views = (int)\$viewsTotal;";
$content = str_replace($search3, $replace3, $content);

$search4 = '$sellerTotalSold = (int)$s->fetchColumn();';
$replace4 = $search4 . " \$items_sold = \$sellerTotalSold;";
$content = str_replace($search4, $replace4, $content);

$search5 = '$sellerRevenue = (float)$s->fetchColumn();';
$replace5 = $search5 . " \$total_revenue = \$sellerRevenue;";
$content = str_replace($search5, $replace5, $content);

$search6 = 'foreach ($products as $p) { if ($p[\'quantity\'] > 0 && $p[\'quantity\'] <= 5 && $p[\'status\'] === \'approved\') $sellerLowStock++; }';
$replace6 = $search6 . "\n    \$low_stock_count = \$sellerLowStock;\n" .
'    
    // Pending orders and unread messages
    try {
        $po = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id=? AND status NOT IN (\'completed\', \'delivered\', \'cancelled\', \'refunded\')");
        $po->execute([$user[\'id\']]);
        $pending_orders = (int)$po->fetchColumn();
    } catch(PDOException $e) { $pending_orders = 0; }
    
    $unread_messages = function_exists(\'getUnreadCount\') ? getUnreadCount($pdo, $user[\'id\']) : 0;';

$content = str_replace($search6, $replace6, $content);

file_put_contents($file, $content);
echo "Dashboard updated.";
?>
