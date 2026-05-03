<?php
/**
 * Notification Routes
 * GET /notifications — List user's notifications
 * PUT /notifications/read — Mark all as read
 */

$auth = authenticate();

// ── GET /notifications ──
if ($method === 'GET' && empty($action)) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$auth['user_id']]);
    jsonResponse([
        'notifications' => $stmt->fetchAll(),
        'unread_count' => getUnreadNotificationCount($pdo, $auth['user_id']),
    ]);
}

// ── GET /notifications/unread-count ──
elseif ($method === 'GET' && $action === 'unread-count') {
    jsonResponse([
        'unread_count' => getUnreadNotificationCount($pdo, $auth['user_id']),
    ]);
}

// ── PATCH /notifications/read-all ──
elseif ($method === 'PATCH' && $action === 'read-all') {
    $boolTrue = sqlBool(true, $pdo);
    $pdo->prepare("UPDATE notifications SET is_read = $boolTrue WHERE user_id = ?")->execute([$auth['user_id']]);
    jsonSuccess('All notifications marked as read');
}

// ── PATCH /notifications/:id/read ──
elseif ($method === 'PATCH' && is_numeric($action) && $param === 'read') {
    $notifId = (int) $action;
    $boolTrue = sqlBool(true, $pdo);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = $boolTrue WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $auth['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        jsonError('Notification not found or already read', 404);
    }
    jsonSuccess('Notification marked as read');
}

// Support legacy PUT /notifications/read for backward compatibility
elseif ($method === 'PUT' && $action === 'read') {
    $boolTrue = sqlBool(true, $pdo);
    $pdo->prepare("UPDATE notifications SET is_read = $boolTrue WHERE user_id = ?")->execute([$auth['user_id']]);
    jsonSuccess('All notifications marked as read');
}

else {
    jsonError('Notification endpoint not found', 404);
}
