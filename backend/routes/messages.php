<?php
/**
 * Message Routes
 * GET  /messages/conversations    — List conversation partners
 * GET  /messages/:userId          — Get messages with specific user
 * POST /messages                  — Send message (text + optional media)
 * GET  /messages/unread-count     — Unread count
 */

require_once __DIR__ . '/../config/cloudinary.php';

$auth = authenticate();
$me = $auth['user_id'];
$targetUserId = is_numeric($action) ? (int) $action : null;

// ── LIST CONVERSATIONS ──
if ($method === 'GET' && $action === 'conversations') {
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $caseSql = $driver === 'pgsql' 
        ? "CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END" 
        : "IF(sender_id = ?, receiver_id, sender_id)";

    $boolFalse = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_pic, u.last_seen,
            (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
            (SELECT message_type FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg_type,
            (SELECT attachment_url FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_attachment,
            (SELECT SUM(CASE WHEN is_read=0 AND receiver_id=? THEN 1 ELSE 0 END) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as unread,
            (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as last_msg_time
        FROM users u
        WHERE u.id IN (SELECT DISTINCT $caseSql FROM messages WHERE sender_id = ? OR receiver_id = ?)
        AND u.id != ?
        ORDER BY last_msg_time DESC
    ");
    $stmt->execute(array_fill(0, 15, $me));
    $conversations = $stmt->fetchAll();

    foreach ($conversations as &$c) {
        $c['is_online'] = $c['last_seen'] && (time() - strtotime($c['last_seen'])) < 300;
        // Generate preview text for media messages
        if (!$c['last_msg'] && $c['last_attachment']) {
            $type = $c['last_msg_type'];
            if ($type === 'video') $c['last_msg'] = '🎬 Video';
            elseif ($type === 'audio') $c['last_msg'] = '🎵 Voice Note';
            else $c['last_msg'] = '📷 Image';
        }
    }

    jsonResponse(['conversations' => $conversations]);
}

// ── GET MESSAGES WITH USER ──
elseif ($method === 'GET' && $targetUserId) {
    // SECURITY: verify the authenticated user is actually a participant in this
    // conversation before reading or mutating anything. Without this check any
    // logged-in user could mark another user's messages as read by hitting
    // GET /messages/<victim_id>.
    $check = $pdo->prepare("
        SELECT 1 FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        LIMIT 1
    ");
    $check->execute([$me, $targetUserId, $targetUserId, $me]);
    if (!$check->fetch()) {
        jsonResponse(['messages' => []]);
    }

    // Mark messages as read
    $boolTrue = sqlBool(true, $pdo);
    $pdo->prepare("UPDATE messages SET is_read = $boolTrue, delivery_status = 'seen' WHERE sender_id = ? AND receiver_id = ?")
        ->execute([$targetUserId, $me]);

    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name, u.profile_pic as sender_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
        LIMIT 200
    ");
    $stmt->execute([$me, $targetUserId, $targetUserId, $me]);

    jsonResponse(['messages' => $stmt->fetchAll()]);
}

// ── UNREAD COUNT ──
elseif ($method === 'GET' && $action === 'unread-count') {
    jsonResponse([
        'unread_messages' => getUnreadMessageCount($pdo, $me),
        'unread_notifications' => getUnreadNotificationCount($pdo, $me),
    ]);
}

// ── SEND MESSAGE ──
elseif ($method === 'POST') {
    $body = !empty($_POST) ? $_POST : getJsonBody();
    $receiverId = (int) ($body['receiver_id'] ?? $body['receiver'] ?? 0);
    $message = trim($body['message'] ?? '');

    if (!$receiverId) jsonError('Receiver ID is required');

    // Handle attachment
    $attachmentUrl = null;
    $messageType = 'text';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $valErr = validateMediaFile($_FILES['attachment']);
        if ($valErr) jsonError($valErr);

        $mediaType = getMediaType($_FILES['attachment']['name']);
        $folder = 'marketplace/chat';
        $attachmentUrl = uploadToCloudinary($_FILES['attachment'], $folder);
        $messageType = $mediaType;
    }

    if (!$message && !$attachmentUrl) {
        jsonError('Message or attachment is required');
    }

    $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, message_type, attachment_url, delivery_status)
        VALUES (?, ?, ?, ?, ?, 'sent')
    ")->execute([$me, $receiverId, $message, $messageType, $attachmentUrl]);

    $msgId = $pdo->lastInsertId();

    // Update delivery status for receiver's messages to 'delivered'
    $pdo->prepare("UPDATE messages SET delivery_status = 'delivered' WHERE sender_id = ? AND receiver_id = ? AND delivery_status = 'sent'")
        ->execute([$me, $receiverId]);

    createMessageNotification($pdo, $receiverId, $me, $message ?: ($attachmentUrl ? 'Media attachment' : ''));

    jsonResponse([
        'success' => true,
        'message_id' => $msgId,
    ]);
}

else {
    jsonError('Messages endpoint not found', 404);
}
