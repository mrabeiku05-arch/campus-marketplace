<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$me = $_SESSION['user_id'];
$chat_user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;
$chat_user = null;

// Get conversation partners
$boolFalse = sqlBool(false, $pdo);
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.profile_pic, u.last_seen, u.role, u.seller_tier,
        (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT message_type FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg_type,
        (SELECT attachment_url FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_attachment,
        (SELECT SUM(CASE WHEN is_read={$boolFalse} AND receiver_id=? THEN 1 ELSE 0 END) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as unread
    FROM users u
    WHERE u.id IN (SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END FROM messages WHERE sender_id = ? OR receiver_id = ?)
    AND u.id != ?
    ORDER BY (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) DESC
");
$stmt->execute([
    $me, $me, // last_msg
    $me, $me, // last_msg_type
    $me, $me, // last_attachment
    $me, $me, $me, // unread (SUM(CASE...), receiver_id, sender_id)
    $me, $me, $me, // WHERE IN
    $me,      // !=
    $me, $me  // ORDER BY
]);
$history_users = $stmt->fetchAll() ?: [];

if ($chat_user_id) {
    $stmt = $pdo->prepare("SELECT id, username, profile_pic, last_seen, role, seller_tier FROM users WHERE id = ?");
    $stmt->execute([$chat_user_id]);
    $chat_user = $stmt->fetch();

    // Mark messages as read and seen
    if ($chat_user) {
        $boolTrue = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE messages SET is_read = $boolTrue, delivery_status = 'seen' WHERE sender_id = ? AND receiver_id = ?")->execute([$chat_user_id, $me]);
    }

    // Add to sidebar if new conversation
    if ($chat_user && !in_array($chat_user['id'], array_column($history_users, 'id'))) {
        array_unshift($history_users, array_merge($chat_user, [
            'role' => $chat_user['role'] ?? 'buyer',
            'seller_tier' => $chat_user['seller_tier'] ?? 'basic',
            'last_msg' => null,
            'last_msg_type' => null,
            'last_attachment' => null,
            'unread' => 0
        ]));
    }
}

// Handle fast sending (Contact Admin)
if (isset($_GET['action']) && $_GET['action'] === 'send_fast' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $receiver = (int)$_POST['receiver_id'];
    $msg = trim($_POST['message'] ?? '');
    
    // Max 1000 character validation
    if (strlen($msg) > 1000) {
        $msg = substr($msg, 0, 1000);
    }

    if ($receiver > 0 && $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$me, $receiver, $msg]);
        createMessageNotification($pdo, $receiver, $me, $msg);
        redirect('dashboard.php?tab=messages&with=' . $receiver);
    }
}

require_once 'includes/header.php';
?>

<div class="glass chat-container fade-in">
    <div class="chat-users">
        <div style="padding:1rem; border-bottom:1px solid var(--border); font-weight:700;">Conversations</div>
        <?php if(count($history_users) === 0): ?>
            <p class="text-muted" style="padding:1rem; font-size:0.85rem;">No conversations yet.</p>
        <?php endif; ?>
        <?php foreach($history_users as $u): ?>
            <?php $isOnline = $u['last_seen'] && (time() - strtotime($u['last_seen'])) < 300; ?>
            <a href="chat.php?user=<?= $u['id'] ?>" class="chat-user-item <?= $chat_user_id == $u['id'] ? 'active' : '' ?>">
                <?php
                    $userRole = $u['role'] ?? 'buyer';
                    $userTier = $u['seller_tier'] ?? 'basic';
                    $tierClass = 'profile-pic-' . ($userRole === 'seller' ? ($userTier ?: 'basic') : 'basic');
                ?>
                <?php if($u['profile_pic']): ?>
                    <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($u['profile_pic'])) ?>" class="profile-pic-previewable <?= $tierClass ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;cursor:pointer;border:2px solid transparent;">
                <?php else: ?>
                    <div class="<?= $tierClass ?>" style="width:36px;height:36px;border-radius:50%;background:rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;flex-shrink:0;border:2px solid transparent;"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div style="flex:1; overflow:hidden;">
                    <div class="flex-between">
                        <strong style="font-size:0.9rem; color:var(--text-main);"><?= htmlspecialchars($u['username']) ?></strong>
                        <span class="online-dot" style="width:8px;height:8px;border-radius:50%;background:<?= $isOnline ? 'var(--success)' : '#555' ?>;"></span>
                    </div>
                    <?php 
                        $preview = $u['last_msg'];
                        if(!$preview && $u['last_attachment']) {
                            $ext = strtolower(pathinfo($u['last_attachment'], PATHINFO_EXTENSION));
                            if ($u['last_msg_type'] === 'video' || in_array($ext, ['mp4','webm','mov'])) $preview = 'Video';
                            elseif ($u['last_msg_type'] === 'audio' || in_array($ext, ['mp3','wav','m4a','ogg'])) $preview = 'Audio';
                            else $preview = 'Image';
                        }
                    ?>
                    <?php if($preview): ?><p style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(substr($preview, 0, 40)) ?></p><?php endif; ?>
                </div>
                <?php if($u['unread'] > 0): ?>
                    <span class="notif-badge" style="position:static;"><?= $u['unread'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-window">
        <?php if($chat_user): ?>
            <div class="chat-header">
                <?= htmlspecialchars($chat_user['username']) ?>
                <span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.5rem;">
                    <?php $on = $chat_user['last_seen'] && (time()-strtotime($chat_user['last_seen'])) < 300; ?>
                    <?= $on ? 'Online' : 'Offline' ?>
                </span>
            </div>
            <div class="chat-messages" id="chatMessages" data-user="<?= $chat_user['id'] ?>"></div>
            <div class="chat-input" style="gap:0.75rem; padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); background: rgba(255,255,255,0.02);">
                <input type="file" id="msgFile" style="display:none;" accept="image/*,video/*,audio/*">
                <button class="btn-glass" onclick="document.getElementById('msgFile').click()" style="width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; padding:0; background:rgba(124,58,237,0.08); color:var(--primary); border:none; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='rgba(124,58,237,0.15)'" onmouseout="this.style.background='rgba(124,58,237,0.08)'" title="Attach image or video">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </button>
                <button class="btn-glass" id="recordBtn" style="width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; padding:0; background:rgba(124,58,237,0.08); color:var(--primary); border:none; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='rgba(124,58,237,0.15)'" onmouseout="this.style.background='rgba(124,58,237,0.08)'" title="Record Voice Note">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                </button>
                <input type="text" id="msgInput" class="form-control" placeholder="Type a message..." style="flex:1; border-radius:14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05); padding:10px 16px;">
                <button class="btn btn-primary" onclick="sendMessage()" style="padding: 0 1.5rem; border-radius:14px; font-weight:700; height:40px; box-shadow:0 4px 12px rgba(124,58,237,0.25);">Send</button>
            </div>
            
            <div id="recordingUI" style="display:none; padding:10px 1.5rem; border-top:1px solid var(--border); background:rgba(239,68,68,0.05); align-items:center; gap:15px; animation: fadeIn 0.3s;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="pulse-red" style="width:10px; height:10px; background:#ef4444; border-radius:50%;"></span>
                    <span id="recordTimer" style="font-family:monospace; font-weight:700; color:#ef4444;">0:00</span>
                </div>
                <div style="flex:1; font-size:0.85rem; font-weight:600; color:var(--text-main);">Recording voice note...</div>
                <button onclick="cancelRecording()" style="background:none; border:none; color:var(--text-muted); font-weight:700; cursor:pointer; padding:5px 10px;">Cancel</button>
                <button onclick="stopAndSendRecording()" style="background:#ef4444; color:#fff; border:none; padding:6px 15px; border-radius:10px; font-weight:700; cursor:pointer; box-shadow:0 4px 12px rgba(239,68,68,0.2);">Stop & Send</button>
            </div>

            <div id="filePreview" style="display:none; padding:10px 1.5rem; border-top:1px solid var(--border); background:rgba(0,0,0,0.02); align-items:center; gap:10px;">
                <div style="position:relative; width:60px; height:60px; border-radius:8px; overflow:hidden; border:1px solid var(--border);">
                    <img id="previewImg" style="width:100%; height:100%; object-fit:cover; display:none;">
                    <video id="previewVid" style="width:100%; height:100%; object-fit:cover; display:none;"></video>
                    <audio id="previewAud" style="display:none;"></audio>
                    <button onclick="clearFile()" style="position:absolute; top:2px; right:2px; background:rgba(0,0,0,0.5); color:#fff; border:none; padding:2px; border-radius:50%; cursor:pointer; width:16px; height:16px; font-size:10px; display:flex; align-items:center; justify-content:center;">×</button>
                </div>
                <span id="fileName" style="font-size:0.8rem; color:var(--text-muted); font-weight:500;"></span>
            </div>

            <script>
                const fileInput = document.getElementById('msgFile');
                const filePreview = document.getElementById('filePreview');
                const previewImg = document.getElementById('previewImg');
                const previewVid = document.getElementById('previewVid');
                const previewAud = document.getElementById('previewAud');
                const fileName = document.getElementById('fileName');

                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        filePreview.style.display = 'flex';
                        fileName.textContent = file.name;
                        // Reset both previews
                        previewImg.style.display = 'none';
                        previewVid.style.display = 'none';
                        previewImg.src = '';
                        previewVid.src = '';

                        if (file.type.startsWith('image/')) {
                            const url = URL.createObjectURL(file);
                            previewImg.src = url;
                            previewImg.style.display = 'block';
                        } else if (file.type.startsWith('video/')) {
                            const url = URL.createObjectURL(file);
                            previewVid.src = url;
                            previewVid.style.display = 'block';
                        } else if (file.type.startsWith('audio/')) {
                            const url = URL.createObjectURL(file);
                            previewAud.src = url;
                            previewAud.style.display = 'block';
                        }
                    } else {
                        filePreview.style.display = 'none';
                    }
                });

                // Expose globally so main.js sendMessage can call it after a successful send
                window.clearFile = function() {
                    fileInput.value = '';
                    filePreview.style.display = 'none';
                    previewImg.src = '';
                    previewImg.style.display = 'none';
                    previewVid.src = '';
                    previewVid.style.display = 'none';
                    previewAud.src = '';
                    previewAud.style.display = 'none';
                };
            </script>
        <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);flex-direction:column;gap:1rem;">
                                <p>Select a conversation to start chatting.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
