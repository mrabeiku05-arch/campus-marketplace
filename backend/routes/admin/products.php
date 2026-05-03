<?php
/**
 * Admin Product Moderation
 * GET  /admin/products              — List products by status
 * PUT  /admin/products/:id/approve  — Approve product
 * PUT  /admin/products/:id/reject   — Reject product
 * DELETE /admin/products/:id        — Delete product
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

$productId = is_numeric($param) ? (int) $param : null;
$productAction = $segments[3] ?? '';

if ($method === 'GET' && !$productId) {
    $status = getQueryParam('status', 'pending');
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as seller_name, u.seller_tier,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
        FROM products p JOIN users u ON p.user_id = u.id
        WHERE p.status = ?
        ORDER BY p.created_at ASC
    ");
    $stmt->execute([$status]);
    jsonResponse(['products' => $stmt->fetchAll()]);
}

elseif ($method === 'PUT' && $productId && $productAction === 'approve') {
    $pdo->prepare("UPDATE products SET status = 'approved' WHERE id = ?")->execute([$productId]);
    
    $stmt = $pdo->prepare("SELECT user_id, title FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $p = $stmt->fetch();
    if ($p) {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'approval', 'Product Approved', ?, ?)")
            ->execute([$p['user_id'], "Your product \"{$p['title']}\" has been approved!", $productId]);
    }

    auditLog($pdo, $auth['user_id'], "Approved product #$productId", 'product', $productId);
    jsonSuccess('Product approved');
}

elseif ($method === 'PUT' && $productId && $productAction === 'reject') {
    $pdo->prepare("UPDATE products SET status = 'rejected' WHERE id = ?")->execute([$productId]);

    $stmt = $pdo->prepare("SELECT user_id, title FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $p = $stmt->fetch();
    if ($p) {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'rejection', 'Product Rejected', ?, ?)")
            ->execute([$p['user_id'], "Your product \"{$p['title']}\" was rejected.", $productId]);
    }

    auditLog($pdo, $auth['user_id'], "Rejected product #$productId", 'product', $productId);
    jsonSuccess('Product rejected');
}

elseif ($method === 'DELETE' && $productId) {
    // Fetch image URLs before deleting records
    $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $imgStmt->execute([$productId]);
    $images = $imgStmt->fetchAll();

    // Delete each image from Cloudinary (or Supabase)
    require_once __DIR__ . '/../../config/cloudinary.php';
    foreach ($images as $img) {
        if (!empty($img['image_path'])) {
            deleteFromCloudinary($img['image_path']);
        }
    }

    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$productId]);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
    auditLog($pdo, $auth['user_id'], "Deleted product #$productId", 'product', $productId);
    jsonSuccess('Product deleted');
}

else {
    jsonError('Admin products endpoint not found', 404);
}
