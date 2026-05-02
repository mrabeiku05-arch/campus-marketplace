<?php
$file = 'dashboard.php';
$content = file_get_contents($file);

// 1. Edit delete handler: soft delete + flash + redirect
$old1 = "        case 'request_delete':\r\n            if (\$pid > 0) {\r\n                \$pdo->prepare(\"UPDATE products SET status='deletion_requested' WHERE id=? AND user_id=? AND status NOT IN ('deletion_requested')\")->execute([\$pid, \$user['id']]);\r\n                \$msg = \"Deletion request submitted.\";\r\n            }\r\n            break;";
$new1 = "        case 'request_delete':\r\n            if (\$pid > 0) {\r\n                \$pdo->prepare(\"UPDATE products SET status='deleted' WHERE id=? AND user_id=? AND status NOT IN ('deleted')\")->execute([\$pid, \$user['id']]);\r\n                \$_SESSION['flash'] = 'Product removed successfully.';\r\n                header('Location: dashboard.php?tab=inventory');\r\n                exit;\r\n            }\r\n            break;";

if (strpos($content, $old1) !== false) {
    $content = str_replace($old1, $new1, $content);
    echo "1. Delete handler updated.\n";
} else {
    echo "1. WARN: Delete handler not found.\n";
}

// 2. Edit products query: add filter + search conditions
$old2 = "    // Show ALL seller products\r\n    \$stmt = \$pdo->prepare(\"SELECT p.*, \r\n        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,\r\n        (SELECT COUNT(*) FROM discount_requests WHERE product_id = p.id AND status = 'pending') as has_pending_discount\r\n        FROM products p WHERE p.user_id = ? ORDER BY p.created_at DESC\");\r\n    \$stmt->execute([\$user['id']]);\r\n    \$products = \$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];";

$new2 = "    // Show seller products (with optional filter + search)\r\n    \$invFilter = \$_GET['filter'] ?? 'all';\r\n    \$invSearch = trim(\$_GET['search'] ?? '');\r\n    \$productSql = \"SELECT p.*, \r\n        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,\r\n        (SELECT COUNT(*) FROM discount_requests WHERE product_id = p.id AND status = 'pending') as has_pending_discount\r\n        FROM products p WHERE p.user_id = ? AND p.status != 'deleted'\";\r\n    \$productParams = [\$user['id']];\r\n\r\n    if (\$invFilter === 'approved') {\r\n        \$productSql .= \" AND p.status = 'approved'\";\r\n    } elseif (\$invFilter === 'pending') {\r\n        \$productSql .= \" AND p.status = 'pending'\";\r\n    } elseif (\$invFilter === 'paused') {\r\n        \$productSql .= \" AND p.status = 'paused'\";\r\n    } elseif (\$invFilter === 'low') {\r\n        \$productSql .= \" AND p.quantity > 0 AND p.quantity <= 5 AND p.status = 'approved'\";\r\n    }\r\n\r\n    if (\$invSearch !== '') {\r\n        \$productSql .= \" AND p.title LIKE ?\";\r\n        \$productParams[] = '%' . \$invSearch . '%';\r\n    }\r\n\r\n    \$productSql .= \" ORDER BY p.created_at DESC\";\r\n    \$stmt = \$pdo->prepare(\$productSql);\r\n    \$stmt->execute(\$productParams);\r\n    \$products = \$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];";

if (strpos($content, $old2) !== false) {
    $content = str_replace($old2, $new2, $content);
    echo "2. Products query updated.\n";
} else {
    echo "2. WARN: Products query not found.\n";
}

file_put_contents($file, $content);
echo "Done.\n";
?>
