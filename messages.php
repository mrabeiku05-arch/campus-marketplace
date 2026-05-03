<?php
// messages.php - Dashboard Native Chat
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$me = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_body'])) {
    check_csrf();
    $receiver = (int)$_POST['conversation_id'];
    $msg = trim($_POST['message_body']);
    if (strlen($msg) > 1000) $msg = substr($msg, 0, 1000);
    
    if ($receiver > 0 && $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$me, $receiver, $msg]);
        createMessageNotification($pdo, $receiver, $me, $msg);
    }
    header("Location: messages.php?conversation_id=" . $receiver);
    exit;
}

// Fetch conversations
$boolFalse = sqlBool(false, $pdo);
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.profile_pic, u.last_seen, u.role, u.seller_tier,
        (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT SUM(CASE WHEN is_read={$boolFalse} AND receiver_id=? THEN 1 ELSE 0 END) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as unread
    FROM users u
    WHERE u.id IN (SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END FROM messages WHERE sender_id = ? OR receiver_id = ?)
    AND u.id != ?
    ORDER BY (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) DESC
");
$stmt->execute([
    $me, $me, // last_msg
    $me, $me, $me, // unread
    $me, $me, $me, // WHERE IN
    $me,      // !=
    $me, $me  // ORDER BY
]);
$dash_chat_users = $stmt->fetchAll() ?: [];

$dash_chat_active_user = null;
$dash_chat_messages = [];

if ($conversation_id) {
    $stmt = $pdo->prepare("SELECT id, username, profile_pic, last_seen, role, seller_tier FROM users WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $dash_chat_active_user = $stmt->fetch();

    if ($dash_chat_active_user) {
        $boolTrue = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE messages SET is_read = $boolTrue, delivery_status = 'seen' WHERE sender_id = ? AND receiver_id = ?")->execute([$conversation_id, $me]);
        
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->execute([$me, $conversation_id, $conversation_id, $me]);
        $dash_chat_messages = $stmt->fetchAll();
        
        if (!in_array($dash_chat_active_user['id'], array_column($dash_chat_users, 'id'))) {
            array_unshift($dash_chat_users, array_merge($dash_chat_active_user, [
                'role' => $dash_chat_active_user['role'] ?? 'buyer',
                'seller_tier' => $dash_chat_active_user['seller_tier'] ?? 'basic',
                'last_msg' => null,
                'unread' => 0
            ]));
        }
    }
}

// Set page title for shell
$headerTitle = "Messages";
$page_title = "Messages";

$_GET['tab'] = 'messages';
require 'dashboard.php';
