<?php
$file = 'dashboard.php';
$content = file_get_contents($file);

$anchor = "            break;\r\n        case 'receive_order':";
$insert = "            break;\r\n        case 'ship_order':\r\n            \$oid = (int)(\$_POST['oid'] ?? 0);\r\n            if (\$oid > 0) {\r\n                \$o = \$pdo->prepare(\"SELECT * FROM orders WHERE id=? AND seller_id=?\");\r\n                \$o->execute([\$oid, \$user['id']]);\r\n                \$ordData = \$o->fetch(PDO::FETCH_ASSOC);\r\n                if (!\$ordData) { \$msg = \"Order not found.\"; break; }\r\n                if ((\$ordData['status'] ?? '') !== 'seller_seen') { \$msg = \"Order must be acknowledged before shipping.\"; break; }\r\n                \$pdo->prepare(\"UPDATE orders SET status='delivered' WHERE id=? AND seller_id=?\")->execute([\$oid, \$user['id']]);\r\n                createNotification(\$pdo, (int)\$ordData['buyer_id'], 'order_update', \"Your order #\$oid has been shipped!\", \$oid, [\r\n                    'title' => 'Order shipped',\r\n                    'link_url' => 'dashboard.php#buyer_orders',\r\n                ]);\r\n                \$msg = \"Order marked as shipped.\";\r\n            }\r\n            break;\r\n        case 'complete_order':\r\n            \$oid = (int)(\$_POST['oid'] ?? 0);\r\n            if (\$oid > 0) {\r\n                \$o = \$pdo->prepare(\"SELECT * FROM orders WHERE id=? AND seller_id=?\");\r\n                \$o->execute([\$oid, \$user['id']]);\r\n                \$ordData = \$o->fetch(PDO::FETCH_ASSOC);\r\n                if (!\$ordData) { \$msg = \"Order not found.\"; break; }\r\n                if (!in_array(\$ordData['status'] ?? '', ['delivered', 'seller_seen'], true)) { \$msg = \"Order cannot be completed from current state.\"; break; }\r\n                \$pdo->prepare(\"UPDATE orders SET status='completed' WHERE id=? AND seller_id=?\")->execute([\$oid, \$user['id']]);\r\n                dashboardCreateSaleTransaction(\$pdo, \$ordData, \$oid);\r\n                createNotification(\$pdo, (int)\$ordData['buyer_id'], 'order_update', \"Your order #\$oid is completed!\", \$oid, [\r\n                    'title' => 'Order completed',\r\n                    'link_url' => 'dashboard.php#buyer_orders',\r\n                ]);\r\n                \$msg = \"Order marked as completed.\";\r\n            }\r\n            break;\r\n        case 'receive_order':";

if (strpos($content, $anchor) !== false) {
    $content = str_replace($anchor, $insert, $content);
    file_put_contents($file, $content);
    echo "Handlers added successfully.\n";
} else {
    echo "WARN: Anchor not found.\n";
}
?>
