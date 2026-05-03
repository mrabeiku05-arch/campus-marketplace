<?php
/**
 * User Routes
 * GET  /users/:id          — Public seller profile
 * PUT  /users/profile      — Request profile edit
 * POST /users/profile-pic  — Upload profile picture
 * GET  /users/wallet       — Wallet balance + transactions
 * POST /users/vacation     — Request vacation mode
 */

require_once __DIR__ . '/../config/cloudinary.php';

$userId = is_numeric($action) ? (int) $action : null;

// ── PUBLIC PROFILE ──
if ($method === 'GET' && $userId) {
    $user = getUserPublic($pdo, $userId);
    if (!$user) jsonError('User not found', 404);

    // Get their products
    $stmt = $pdo->prepare("
        SELECT p.*, 
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
        FROM products p 
        WHERE p.user_id = ? AND p.status = 'approved' 
        ORDER BY p.created_at DESC LIMIT 20
    ");
    $stmt->execute([$userId]);
    $user['products'] = $stmt->fetchAll();

    jsonResponse(['user' => $user]);
}

// ── WALLET ──
elseif ($method === 'GET' && $action === 'wallet') {
    $auth = authenticate();
    $user = getUser($pdo, $auth['user_id']);

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$auth['user_id']]);

    jsonResponse([
        'balance' => (float) $user['balance'],
        'transactions' => $stmt->fetchAll(),
    ]);
}

// ── EDIT PROFILE (admin-approved) ──
elseif ($method === 'PUT' && $action === 'profile') {
    $auth = authenticate();
    $body = getJsonBody();
    $user = getUser($pdo, $auth['user_id']);

    global $ALLOWED_PROFILE_FIELDS;
    $changes = [];

    foreach ($body as $field => $newValue) {
        if (!in_array($field, $ALLOWED_PROFILE_FIELDS)) continue;

        $oldValue = $user[$field] ?? '';
        $newValue = trim($newValue);

        if ($oldValue !== $newValue) {
            $changes[] = [$field, $oldValue, $newValue];
        }
    }

    if (empty($changes)) jsonError('No changes detected');

    foreach ($changes as [$field, $oldVal, $newVal]) {
        $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)")
            ->execute([$auth['user_id'], $field, $oldVal, $newVal]);
    }

    jsonSuccess('Profile changes submitted for admin approval');
}

// ── UPLOAD PROFILE PIC ──
elseif ($method === 'POST' && $action === 'profile-pic') {
    $auth = authenticate();

    if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        jsonError('No file uploaded');
    }

    $valErr = validateImageFile($_FILES['profile_pic']);
    if ($valErr) jsonError($valErr);

    $url = uploadToCloudinary($_FILES['profile_pic'], 'marketplace/avatars');
    if (!$url) jsonError('Upload failed', 500);

    // Get current profile_pic to save as old_value
    $user = getUser($pdo, $auth['user_id']);
    $oldVal = $user['profile_pic'] ?? '';

    $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?, 'profile_pic', ?, ?)")
        ->execute([$auth['user_id'], $oldVal, $url]);

    jsonResponse(['success' => true, 'message' => 'Profile picture submitted for admin approval']);
}

// ── REQUEST VACATION ──
elseif ($method === 'POST' && $action === 'vacation') {
    $auth = authenticate();
    requireSeller($pdo, $auth);

    // Check no pending request
    $stmt = $pdo->prepare("SELECT id FROM vacation_requests WHERE seller_id = ? AND status = 'pending'");
    $stmt->execute([$auth['user_id']]);
    if ($stmt->fetch()) jsonError('You already have a pending vacation request');

    $pdo->prepare("INSERT INTO vacation_requests (seller_id) VALUES (?)")->execute([$auth['user_id']]);
    jsonSuccess('Vacation mode request submitted for admin approval');
}

// ── END VACATION ──
elseif ($method === 'DELETE' && $action === 'vacation') {
    $auth = authenticate();
    requireSeller($pdo, $auth);

    // Cancel any pending requests
    $pdo->prepare("UPDATE vacation_requests SET status = 'cancelled' WHERE seller_id = ? AND status = 'pending'")
        ->execute([$auth['user_id']]);

    // Set vacation_mode to false
    $stmt = $pdo->prepare("UPDATE users SET vacation_mode = ? WHERE id = ?");
    $stmt->bindValue(1, false, PDO::PARAM_BOOL);
    $stmt->bindValue(2, $auth['user_id'], PDO::PARAM_INT);
    $stmt->execute();

    jsonSuccess('Vacation mode ended. Your products are now visible.');
}

// ── SLOT COUNT ──
elseif ($method === 'GET' && $action === 'slot-count') {
    $auth = authenticate();
    $user = getUser($pdo, $auth['user_id']);
    
    // Calculate slots
    $tierLimits = getAccountTiers();
    $currentTier = strtolower($user['plan'] ?? 'basic');
    $inventoryLimit = $tierLimits[$currentTier] ?? $tierLimits['basic'];
    
    // Get used slots
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status != 'deleted'");
    $countStmt->execute([$auth['user_id']]);
    $usedSlots = (int) $countStmt->fetchColumn();
    
    // Add pending products to the count
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$auth['user_id']]);
    $pendingSlots = (int) $pendingStmt->fetchColumn();
    
    $totalUsed = $usedSlots + $pendingSlots;
    $availableSlots = max(0, $inventoryLimit - $totalUsed);

    jsonResponse([
        'available_slots' => $availableSlots,
        'used_slots' => $totalUsed,
        'total_slots' => $inventoryLimit
    ]);
}

else {
    jsonError('User endpoint not found', 404);
}
