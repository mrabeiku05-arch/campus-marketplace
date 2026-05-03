<?php
require_once 'includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$lastId = max(0, (int) ($_GET['last_id'] ?? 0));
$boolFalse = sqlBool(false, $pdo);

// Handle mark_read=1 → mark all notifications as read for this user
if (!empty($_GET['mark_read'])) {
    $boolTrue = sqlBool(true, $pdo);
    $markStmt = $pdo->prepare("UPDATE notifications SET is_read = {$boolTrue} WHERE user_id = ? AND is_read = {$boolFalse}");
    $markStmt->execute([$userId]);
}

$notifStmt = $pdo->prepare("SELECT id, type, title, message, link_url, reference_id, is_read, created_at
    FROM notifications
    WHERE user_id = ? AND id > ?
    ORDER BY id ASC
    LIMIT 25");
$notifStmt->execute([$userId, $lastId]);
$notifications = $notifStmt->fetchAll() ?: [];

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = {$boolFalse}");
$msgStmt->execute([$userId]);
$unreadMessages = (int) $msgStmt->fetchColumn();

$notifCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = {$boolFalse}");
$notifCountStmt->execute([$userId]);
$unreadNotifications = (int) $notifCountStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_messages' => $unreadMessages,
    'unread_notifications' => $unreadNotifications,
], JSON_UNESCAPED_UNICODE);
