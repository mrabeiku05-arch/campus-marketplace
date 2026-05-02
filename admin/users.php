<?php
$page_title = 'Users';
require_once 'header.php';
?>
<style>
/* NOTE: This <style> block comes AFTER header.php so it overrides app.css */
/* ADMIN USERS PAGE ALIGNMENT FIXES */
.glass {
background: rgb(255, 255, 255);
    border: 1px solid rgb(255, 255, 255);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

#users-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    background: transparent;
}

#users-table th {
    background: rgb(0, 0, 0);
    color: var(--text-muted);
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.75rem 0.75rem;
    text-align: left;
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}

#users-table td {
    padding: 0.75rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid rgb(128, 128, 128);
    line-height: 1.4;
    white-space: nowrap;
}

#users-table tr:hover {
    background: rgb(124, 58, 237);
}

/* Column widths */
#users-table th:nth-child(1),  #users-table td:nth-child(1)  { width: 52px;  text-align: center; font-weight: 600; }
#users-table th:nth-child(2),  #users-table td:nth-child(2)  { width: 160px; }
#users-table th:nth-child(3),  #users-table td:nth-child(3)  { width: 200px; }
#users-table th:nth-child(4),  #users-table td:nth-child(4)  { width: 75px;  text-align: center; }
#users-table th:nth-child(5),  #users-table td:nth-child(5)  { width: 90px;  text-align: center; }
#users-table th:nth-child(6),  #users-table td:nth-child(6)  { width: 140px; }
#users-table th:nth-child(7),  #users-table td:nth-child(7)  { width: 90px;  text-align: right; font-weight: 600; font-family: 'Courier New', monospace; }
#users-table th:nth-child(8),  #users-table td:nth-child(8)  { width: 100px; text-align: center; }
#users-table th:nth-child(9),  #users-table td:nth-child(9)  { width: 95px;  text-align: center; font-size: 0.78rem; }
#users-table th:nth-child(10), #users-table td:nth-child(10) { width: 240px; }

/* User cell */
#users-table td:nth-child(2) .user-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

#users-table td:nth-child(2) .user-cell img {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

/* Email wraps only if needed */
#users-table td:nth-child(3) {
    white-space: normal;
    word-break: break-all;
}

/* Actions cell always wraps buttons nicely */
#users-table td:nth-child(10) {
    white-space: normal;
}

/* Badge styling */
.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    line-height: 1;
}

.badge-gold {
    background: linear-gradient(135deg, rgb(250, 204, 21), rgb(250, 204, 21));
    color: #ca8a04;
    border: 1px solid rgb(250, 204, 21);
}

.badge-blue {
    background: linear-gradient(135deg, rgb(59, 130, 246), rgb(59, 130, 246));
    color: #1e40af;
    border: 1px solid rgb(59, 130, 246);
}

.badge-rejected {
    background: linear-gradient(135deg, rgb(239, 68, 68), rgb(239, 68, 68));
    color: #dc2626;
    border: 1px solid rgb(239, 68, 68);
}

/* Action buttons */
.flex.gap-1 {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    line-height: 1.2;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.65rem;
}

.btn-outline {
    background: transparent;
    color: var(--text-main);
    border-color: var(--border);
}

.btn-success {
    background: var(--success);
    color: white;
    border-color: var(--success);
}

.btn-warn {
    background: var(--warning);
    color: white;
    border-color: var(--warning);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border-color: var(--danger);
}

.btn-gold {
    background: linear-gradient(135deg, #facc15, #eab308);
    color: #713f12;
    border: 1px solid #facc15;
}

/* Email and balance styling */
table td:nth-child(3) {
    color: var(--text-muted);
    font-size: 0.8rem;
    word-break: break-all;
}

table td:nth-child(7) {
    color: var(--success);
    font-size: 0.9rem;
}

/* Responsive fixes */
@media (max-width: 1200px) {
    table { font-size: 0.8rem; }
    table th, table td { padding: 0.75rem 0.5rem; }
    table th:nth-child(6), table td:nth-child(6) { display: none; }
}

@media (max-width: 768px) {
    /* TABLE SPECIFIC TWEAKS */
    #users-table tr {
        position: relative;
    }
    #users-table td:nth-child(1) { /* ID */
        position: absolute;
        top: 0.5rem;
        right: 1rem;
        width: auto !important;
        background: var(--border);
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
    }
    #users-table td:nth-child(1)::before { content: ""; margin: 0; }
    
    #users-table td:nth-child(2) { /* User */
        padding-top: 0;
        margin-bottom: 0.5rem;
        border-bottom: 1px solid rgb(0, 0, 0);
        padding-bottom: 0.5rem;
    }
    #users-table td:nth-child(2)::before { display: none; }
    
    #users-table td:nth-child(10) { /* Actions */
        margin-top: 0.8rem;
        padding-top: 0.8rem;
        border-top: 1px solid rgb(0, 0, 0);
        display: block;
    }
    #users-table td:nth-child(10)::before { display: block; margin-bottom: 0.5rem; }
    
    .glass { padding: 1rem; overflow-x: visible; }
}
</style>
<?php

// ─── CSRF helpers ────────────────────────────────────────────────────────────
// Using check_csrf() from db.php

$msg = '';

// ─── All state-mutating actions now require POST + CSRF ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    check_csrf();

    $id = (int) $_POST['id'];
    $act = $_POST['action'];
    $sess_id = (int) $_SESSION['user_id']; // OPT 2: cast once, use everywhere — prevents type-juggling

    // BUG FIX #7 / #3: 'make_admin' removed from reachable actions.
    // BUG FIX #1 / #4: All mutations now only reachable via POST.

    if ($act === 'delete' && $id !== $sess_id) {
        // ADJ 3: Both $id and $sess_id are (int)-cast at assignment — strict identity
        // comparison (!==) guarantees an admin can never delete their own session record,
        // even if PHP's loose == would coerce types unexpectedly.
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $sess_id, "Permanently deleted user #" . $id, 'user', $id);
        $msg = "User #$id has been permanently deleted.";

    } elseif ($act === 'verify') {
        $boolT = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE users SET verified = $boolT WHERE id = ?")->execute([$id]);
        auditLog($pdo, $sess_id, "Verified user #$id", 'user', $id);
        $msg = "User #$id verified.";

    } elseif ($act === 'upgrade_pro' || $act === 'upgrade_premium') {
        // BUG FIX #8: Backend tier-guard — prevent downgrading via crafted POST.
        $currentUser = $pdo->prepare("SELECT seller_tier, role FROM users WHERE id = ?");
        $currentUser->execute([$id]);
        $currentUser = $currentUser->fetch();

        $allowedUpgrade = false;
        if ($act === 'upgrade_pro' && $currentUser['seller_tier'] === 'basic')
            $allowedUpgrade = true;
        if ($act === 'upgrade_premium' && in_array($currentUser['seller_tier'], ['basic', 'pro'], true))
            $allowedUpgrade = true;

        if (!$allowedUpgrade || $currentUser['role'] !== 'seller') {
            $msg = "Invalid upgrade path for this user.";
        } else {
            $tier = ($act === 'upgrade_pro') ? 'pro' : 'premium';
            $allTiers = getAccountTiers($pdo);
            $price = (float) ($allTiers[$tier]['price'] ?? 0);

            // FIX 3: Guard against missing tier key entirely before reading 'duration'.
            // If getAccountTiers() returns an incomplete array, fall back to 'forever'
            // rather than hitting an undefined-index notice and passing null to the whitelist.
            $tierData = $allTiers[$tier] ?? [];
            $durStr = isset($tierData['duration']) && $tierData['duration'] !== ''
                ? $tierData['duration']
                : 'forever';

            // Use global duration logic from db.php (shared with Paystack verification)
            $expire_val = next_tier_expiry($currentUser['tier_expires_at'] ?? null, (string)$durStr);

            $pdo->beginTransaction();
            try {
                // ADJ 2: fully parameterised — no $expire_sql string in query.
                // Also turn OFF vacation mode when upgrading
                $boolF = sqlBool(false, $pdo);
                $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = ?, vacation_mode = $boolF WHERE id = ?")
                    ->execute([$tier, $expire_val, $id]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?,?,?,?,?,?)")
                    ->execute([
                        $id,
                        'premium',
                        $price,
                        'completed',
                        generateRef(strtoupper(substr($tier, 0, 2))),
                        ucfirst($tier) . " Seller Account Upgrade"
                    ]);

                // Log to tier_subscriptions for the admin ledger
                $admin_ref = "ADMIN_UPGRADE_" . strtoupper(uniqid());
                $pdo->prepare("INSERT INTO tier_subscriptions (user_id, tier_name, amount, transaction_id, expires_at) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id, $tier, $price, $admin_ref, $expire_val]);

                // OPT 1: auditLog is inside the transaction — if logging throws,
                // rollBack() undoes the UPDATE + INSERT, keeping the paper trail consistent.
                //
                // FIX 2: Audit string uses ASCII "GHS" — NOT the ₵ glyph.
                // Writing ₵ into the DB requires both the connection AND table collation
                // to be utf8mb4; on latin1/utf8 setups it silently stores as mojibake.
                // $msg below keeps ₵ because it only goes into PHP's output buffer, never the DB.
                auditLog(
                    $pdo,
                    $sess_id,
                    "Upgraded user #$id to $tier seller (Revenue: +GHS " . number_format($price, 2) . ")",
                    'user',
                    $id
                );

                $pdo->commit();
                // ₵ is safe here — this string is only echoed to the browser, not stored.
                $msg = "User #$id upgraded to " . ucfirst($tier) . " Seller. Revenue of ₵" . number_format($price, 2) . " recorded.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Error: " . htmlspecialchars($e->getMessage());
            }
        }

    } elseif ($act === 'downgrade_basic') {
        $pdo->prepare("UPDATE users SET seller_tier = 'basic', tier_expires_at = NULL WHERE id = ?")
            ->execute([$id]);
        auditLog($pdo, $sess_id, "Downgraded user #$id to basic seller", 'user', $id);
        $msg = "User #$id downgraded to Basic Seller.";

    } elseif ($act === 'suspend') {
        $boolT = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE users SET suspended = $boolT WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $sess_id, "Suspended user #$id", 'user', $id);
        $msg = "User #$id suspended.";

    } elseif ($act === 'reactivate') {
        $boolF = sqlBool(false, $pdo);
        $pdo->prepare("UPDATE users SET suspended = $boolF WHERE id = ?")->execute([$id]);
        auditLog($pdo, $sess_id, "Reactivated user #$id", 'user', $id);
        $msg = "User #$id reactivated.";
    }
}

// ─── Filtering ────────────────────────────────────────────────────────────────
// BUG FIX #2 / #11: Strict allowlist; params array actually used.
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all' => null, 'sellers' => 'seller', 'buyers' => 'buyer', 'admins' => 'admin'];
if (!array_key_exists($filter, $allowed_filters)) {
    $filter = 'all';
}

// BUG FIX #6: Fetch total count separately so "All" button always shows full count.
$total_count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$role_filter = $allowed_filters[$filter];
$userCols = "id, username, email, role, seller_tier, faculty, balance, suspended, verified, profile_pic, google_avatar, created_at";
if ($role_filter !== null) {
    $stmt = $pdo->prepare("SELECT $userCols FROM users WHERE role = ? ORDER BY created_at DESC");
    $stmt->execute([$role_filter]);
} else {
    $stmt = $pdo->query("SELECT $userCols FROM users ORDER BY created_at DESC");
}
$users = $stmt->fetchAll();

// Helper: emit a POST action form-button (replaces plain <a href> for mutations)
function actionBtn(
    string $action,
    int $id,
    string $filter,
    string $label,
    string $cls = 'btn-outline',
    string $confirm = ''
): string {
    $csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
    $action_e = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
    $filter_e = htmlspecialchars($filter, ENT_QUOTES, 'UTF-8');
    // OPT 3: escape label & cls to prevent XSS if values ever come from dynamic sources
    $label_e = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $cls_e = htmlspecialchars($cls, ENT_QUOTES, 'UTF-8');
    $confirm_js = $confirm ? ' onclick="return confirm(' . json_encode($confirm, JSON_HEX_APOS) . ')"' : '';
    return <<<HTML
    <form method="post" style="display:inline;"{$confirm_js}>
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="action"     value="{$action_e}">
        <input type="hidden" name="id"         value="{$id}">
        <input type="hidden" name="filter"     value="{$filter_e}">
        <button type="submit" class="btn {$cls_e} btn-sm">{$label_e}</button>
    </form>
    HTML;
}

function adminAvatarFallbackUri(string $username): string {
    $initial = strtoupper(substr(trim($username), 0, 1) ?: 'U');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
        . '<rect width="96" height="96" rx="48" fill="#1f2937"/>'
        . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
        . 'font-family="Arial, sans-serif" font-size="40" font-weight="700" fill="#ffffff">'
        . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function adminResolvedAvatarUrl(array $user): string {
    $avatarUrl = trim((string) ($user['profile_pic'] ?? ''));
    if ($avatarUrl === '' && !empty($user['google_avatar'])) {
        $avatarUrl = trim((string) $user['google_avatar']);
    }

    if ($avatarUrl !== '') {
        return getAssetUrl('uploads/' . $avatarUrl);
    }

    return adminAvatarFallbackUri((string) ($user['username'] ?? 'U'));
}
?>

<h2 class="mb-2">User Management</h2>

<?php if ($msg): ?>
    <div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex gap-1 mb-3">
    <!-- BUG FIX #6: "All" always shows total_count, not filtered count -->
    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All
        (<?= $total_count ?>)</a>
    <a href="?filter=sellers" class="btn <?= $filter === 'sellers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Sellers</a>
    <a href="?filter=buyers" class="btn <?= $filter === 'buyers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Buyers</a>
    <a href="?filter=admins" class="btn <?= $filter === 'admins' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Admins</a>
</div>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table id="users-table" class="responsive-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Tier</th>
                <th>Faculty</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $uid = (int) $u['id'];
                $is_suspended = !empty($u['suspended']);
                $is_self = $uid === (int) $_SESSION['user_id']; // OPT 2: strict int comparison
                $avatarSrc = adminResolvedAvatarUrl($u);
                $avatarFallback = adminAvatarFallbackUri((string) $u['username']);
                ?>
                <tr>
                    <td data-label="ID"><?= $uid ?></td>
                    <td data-label="User">
                        <a href="user_profile.php?id=<?= $uid ?>" class="user-cell" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:0.5rem;" title="View full profile">
                        <?php $tierClass = 'profile-pic-' . ($u['role'] === 'seller' ? ($u['seller_tier'] ?: 'basic') : 'basic'); ?>
                        <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                            class="<?= $tierClass ?>"
                            style="border:2px solid transparent;width:32px;height:32px;border-radius:50%;object-fit:cover;"
                            onerror="this.onerror=null;this.src='<?= htmlspecialchars($avatarFallback, ENT_QUOTES, 'UTF-8') ?>';"
                            alt="<?= htmlspecialchars($u['username']) ?>">
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;"><?= htmlspecialchars($u['username']) ?></span>
                        </a>
                    </td>
                    <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                    <td data-label="Role">
                        <span
                            class="badge <?= $u['role'] === 'admin' ? 'badge-gold' : ($u['role'] === 'seller' ? 'badge-blue' : '') ?>">
                            <?= ucfirst(htmlspecialchars($u['role'])) ?>
                        </span>
                    </td>
                    <td data-label="Tier">
                        <?php if ($u['role'] === 'seller'): ?>
                            <?= getBadgeHtml($pdo, $u['seller_tier'] ?: 'basic') ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td data-label="Faculty" style="font-size:0.78rem;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                        title="<?= htmlspecialchars($u['faculty'] ?? '') ?>">
                        <?= htmlspecialchars($u['faculty'] ?? '—') ?>
                    </td>
                    <td data-label="Balance">₵<?= number_format($u['balance'], 2) ?></td>
                    <td data-label="Status">
                        <?php if ($is_suspended): ?>
                            <span class="badge badge-rejected">Suspended</span>
                        <?php elseif ($u['verified']): ?>
                            <span style="color:var(--success);">Verified</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Joined" style="font-size:0.8rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td data-label="Actions">
                        <?php if ($u['role'] !== 'admin'): ?>
                            <div class="flex gap-1" style="flex-wrap:wrap;">

                                <?php if (!$u['verified']): ?>
                                    <?= actionBtn('verify', $uid, $filter, 'Verify', 'btn-success') ?>
                                <?php endif; ?>

                                <?php if ($u['role'] === 'seller'):
                                    // ADJ 1: Normalise missing/null tier to 'basic' so the rank
                                    // map always has a valid starting point — no tier falls through.
                                    $tier_rank = ['basic' => 0, 'pro' => 1, 'premium' => 2];
                                    $cur_tier = $u['seller_tier'] ?: 'basic';
                                    $cur_rank = $tier_rank[$cur_tier] ?? 0;
                                    ?>
                                    <?php // Upgrade buttons only render when the user is strictly below that tier ?>
                                    <?php if ($cur_rank < $tier_rank['pro']): ?>
                                        <?= actionBtn('upgrade_pro', $uid, $filter, 'Pro', 'btn-outline') ?>
                                    <?php endif; ?>
                                    <?php if ($cur_rank < $tier_rank['premium']): ?>
                                        <?= actionBtn('upgrade_premium', $uid, $filter, 'Premium', 'btn-gold') ?>
                                    <?php endif; ?>
                                    <?php if ($cur_rank > $tier_rank['basic']): ?>
                                        <?= actionBtn(
                                            'downgrade_basic',
                                            $uid,
                                            $filter,
                                            'D-grade',
                                            'btn-outline',
                                            'Downgrade this seller to Basic?'
                                        ) ?>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($is_suspended): ?>
                                    <?= actionBtn(
                                        'reactivate',
                                        $uid,
                                        $filter,
                                        'Reactivate',
                                        'btn-success',
                                        'Reactivate this user?'
                                    ) ?>
                                <?php else: ?>
                                    <?= actionBtn(
                                        'suspend',
                                        $uid,
                                        $filter,
                                        'Suspend',
                                        'btn-warn',
                                        'Suspend this user? They will not be able to log in.'
                                    ) ?>
                                <?php endif; ?>

                                <a href="messages.php?view=chat&u1=<?= (int) $_SESSION['user_id'] ?>&u2=<?= $uid ?>" class="btn btn-outline btn-sm">Message</a>

                                <?php if (!$is_self): ?>
                                    <?= actionBtn(
                                        'delete',
                                        $uid,
                                        $filter,
                                        'Delete',
                                        'btn-danger',
                                        'Delete this user permanently? This cannot be undone.'
                                    ) ?>
                                <?php endif; ?>

                            </div>
                        <?php else: ?>
                            <span class="text-muted">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
