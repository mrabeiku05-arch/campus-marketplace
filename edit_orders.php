<?php
$file = 'dashboard.php';
$content = file_get_contents($file);

// Edit the seller_orders query to add filter + pagination
$old = '    if ($hasSellerDashboardAccess) {' . "\r\n" .
       '        $s = $pdo->prepare("SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users b ON o.buyer_id=b.id WHERE o.seller_id=? ORDER BY o.created_at DESC");' . "\r\n" .
       '        $s->execute([$user[\'id\']]); $seller_orders = $s->fetchAll(PDO::FETCH_ASSOC);' . "\r\n" .
       '    }';

$new = '    if ($hasSellerDashboardAccess) {' . "\r\n" .
       '        $order_filter = $_GET[\'order_filter\'] ?? \'all\';' . "\r\n" .
       '        $order_page = max(1, (int)($_GET[\'page\'] ?? 1));' . "\r\n" .
       '        $order_offset = ($order_page - 1) * 10;' . "\r\n" .
       '        $orderSql = "SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users b ON o.buyer_id=b.id WHERE o.seller_id=?";' . "\r\n" .
       '        $orderParams = [$user[\'id\']];' . "\r\n" .
       '        if ($order_filter !== \'all\') {' . "\r\n" .
       '            $orderSql .= " AND o.status=?";' . "\r\n" .
       '            $orderParams[] = $order_filter;' . "\r\n" .
       '        }' . "\r\n" .
       '        // Count total for pagination' . "\r\n" .
       '        $countSql = str_replace("SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name", "SELECT COUNT(*)", $orderSql);' . "\r\n" .
       '        $cs = $pdo->prepare($countSql); $cs->execute($orderParams); $total_orders = (int)$cs->fetchColumn();' . "\r\n" .
       '        $order_total_pages = max(1, (int)ceil($total_orders / 10));' . "\r\n" .
       '        $orderSql .= " ORDER BY o.created_at DESC LIMIT 10 OFFSET $order_offset";' . "\r\n" .
       '        $s = $pdo->prepare($orderSql);' . "\r\n" .
       '        $s->execute($orderParams); $seller_orders = $s->fetchAll(PDO::FETCH_ASSOC);' . "\r\n" .
       '    }';

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    echo "1. Orders query updated with filter + pagination.\n";
} else {
    echo "1. WARN: Orders query not found.\n";
}

file_put_contents($file, $content);
echo "Done.\n";
?>
