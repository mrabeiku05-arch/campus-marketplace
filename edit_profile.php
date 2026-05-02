<?php
require_once 'includes/db.php';
require_once 'includes/storage_helper.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

$error = ''; $success = '';
if (isset($_GET['google_setup'])) {
    $success = "You're in. Please review and complete your profile details, especially faculty and contact info.";
}

// Faculty options
$faculties = [
    'Faculty of Applied Arts and Technology',
    'Faculty of Applied Sciences',
    'Faculty of Engineering',
    'Faculty of Business Studies',
    'Faculty of Built and Natural Environment',
    'Faculty of Health and Allied Sciences',
    'Faculty of Maritime and Nautical Studies',
    'Faculty of Media Technology and Liberal Studies',
];

// Fields that require admin approval
$approval_fields = ['bio', 'department', 'level', 'hall', 'phone', 'faculty', 'whatsapp', 'instagram', 'linkedin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $bio   = trim($_POST['bio'] ?? '');
    $hall   = trim($_POST['hall'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $wa     = trim($_POST['whatsapp'] ?? '');
    $ig     = trim($_POST['instagram'] ?? '');
    $li     = trim($_POST['linkedin'] ?? '');
    $dept   = trim($_POST['department'] ?? '');
    $level  = $_POST['level'] ?? '';
    $faculty = trim($_POST['faculty'] ?? '');

    // Phone format
    if ($phone && substr($phone, 0, 1) === '0') $phone = '+233' . substr($phone, 1);

    // Initialize counter and cancel any existing pending requests BEFORE creating new ones
    $changes_submitted = 0;
    $pdo->prepare("UPDATE profile_edit_requests SET status='rejected', resolved_at=NOW() WHERE user_id=? AND status='pending'")->execute([$user['id']]);
    $insert_stmt = $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?,?,?,?)");

    // ── Image uploads now queue for admin approval (no instant update) ──

    // Profile pic
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $file = $_FILES['profile_pic'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($file['size'] <= 2 * 1024 * 1024 && in_array($mime, $allowedMimes, true)) {
            $url = uploadToCloudinary($file, 'marketplace/avatars');
            if ($url) {
                $insert_stmt->execute([$user['id'], 'profile_pic', $user['profile_pic'] ?? '', $url]);
                $changes_submitted++;
                // Note: Actual DB update is pending admin approval, but requested to refresh $_SESSION
                $_SESSION['profile_pic'] = $url;
            }
        } else {
            $error = 'Profile pic must be JPG, PNG, or WEBP under 2MB.';
        }
    }

    // Shop banner
    if (isset($_FILES['shop_banner']) && $_FILES['shop_banner']['error'] == 0) {
        $url = uploadToCloudinary($_FILES['shop_banner'], 'marketplace/banners');
        if ($url) {
            $insert_stmt->execute([$user['id'], 'shop_banner', $user['shop_banner'] ?? '', $url]);
            $changes_submitted++;
        }
    }

    // ── Text fields ──
    $new_values = [
        'bio' => $bio,
        'department' => $dept,
        'level' => $level,
        'hall' => $hall,
        'phone' => $phone,
        'faculty' => $faculty,
        'whatsapp' => $wa,
        'instagram' => $ig,
        'linkedin' => $li,
    ];

    foreach ($new_values as $field => $new_val) {
        $old_val = $user[$field] ?? '';
        if ((string)$new_val !== (string)$old_val) {
            $insert_stmt->execute([$user['id'], $field, $old_val, $new_val]);
            $changes_submitted++;
        }
    }

    if ($changes_submitted > 0) {
        setFlashMessage('auth_success', "✅ Your profile changes ({$changes_submitted} field" . ($changes_submitted > 1 ? 's' : '') . ") have been submitted for Administrator approval.");
        redirect('dashboard.php');
    } else {
        $success = "No changes detected. Your profile is up to date.";
    }

    $user = getUser($pdo, $user['id']); // refresh for images
}

// Fetch any pending edits for this user
$pending_edits = [];
try {
    $pe_stmt = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $pe_stmt->execute([$user['id']]);
    $pending_edits = $pe_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) { /* table may not exist yet */ }

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in" style="max-width:650px;">
    <h2 class="mb-3">Edit Profile</h2>

    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Pending Edits Notice -->
    <?php if(count($pending_edits) > 0): ?>
    <div class="alert alert-warning" style="border-radius:14px; margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
            <span style="font-size:1.2rem;">⏳</span>
            <strong>Pending Approval</strong>
        </div>
        <p style="font-size:0.85rem; margin-bottom:0.75rem; color:var(--text-main);">The following changes are awaiting admin review:</p>
        <?php foreach($pending_edits as $pe): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0.6rem; background:rgba(0,0,0,0.03); border-radius:8px; margin-bottom:0.3rem; font-size:0.82rem;">
                <span style="font-weight:600; text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $pe['field_name'])) ?></span>
                <?php if (in_array($pe['field_name'], ['profile_pic', 'shop_banner'])): ?>
                    <span style="color:var(--text-muted); display:flex; align-items:center; gap:6px;">
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($pe['new_value'])) ?>" class="profile-pic-previewable" style="width:32px; height:32px; border-radius:6px; object-fit:cover; cursor:pointer; border:2px solid var(--primary);" alt="Pending <?= htmlspecialchars(str_replace('_', ' ', $pe['field_name'])) ?>">
                        <span style="font-size:0.75rem; color:var(--primary); font-weight:600;">Pending</span>
                    </span>
                <?php else: ?>
                    <span style="color:var(--text-muted);">"<?= htmlspecialchars(mb_strimwidth($pe['old_value'] ?: '(empty)', 0, 20, '…')) ?>" → "<span style="color:var(--primary); font-weight:600;"><?= htmlspecialchars(mb_strimwidth($pe['new_value'] ?: '(empty)', 0, 20, '…')) ?></span>"</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group text-center">
            <?php $tierClass = 'profile-pic-' . ($user['role'] === 'seller' ? ($user['seller_tier'] ?: 'basic') : 'basic'); ?>
            <?php if($user['profile_pic']): ?>
                <img id="profilePicPreview" src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>" class="profile-pic profile-pic-lg profile-pic-previewable <?= $tierClass ?> mb-2" style="cursor:pointer;" alt="Profile">
            <?php else: ?>
                <img id="profilePicPreview" src="<?= getAssetUrl('assets/img/default-avatar.svg') ?>" class="profile-pic profile-pic-lg profile-pic-previewable <?= $tierClass ?> mb-2" style="cursor:pointer; display:none;" alt="Profile">
            <?php endif; ?>
            <label>Profile Photo <small style="color:var(--text-muted);">(requires admin approval)</small></label>
            <input type="file" name="profile_pic" class="form-control" accept="image/*" onchange="previewSelectedImage(this, 'profilePicPreview')">
        </div>

        <div class="form-group">
            <label for="faculty">Faculty *</label>
            <select name="faculty" id="faculty" class="form-control" required>
                <option value="">— Choose Faculty —</option>
                <?php foreach($faculties as $f): ?>
                    <option value="<?= $f ?>" <?= ($user['faculty'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="bio">Bio / Slogan</label>
            <textarea name="bio" id="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" name="department" id="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="level">Level</label>
                <select name="level" id="level" class="form-control">
                    <option value="">Select</option>
                    <?php foreach(['100','200','300','400','BTech'] as $l): ?>
                        <option value="<?= $l ?>" <?= ($user['level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="hall">Hall / Residence</label>
                <input type="text" name="hall" id="hall" class="form-control" value="<?= htmlspecialchars($user['hall'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
        </div>

        <h4 class="mb-2 mt-2">Social Links</h4>
        <div class="form-row">
            <div class="form-group">
                <label for="whatsapp">WhatsApp Number</label>
                <input type="text" name="whatsapp" id="whatsapp" class="form-control" value="<?= htmlspecialchars($user['whatsapp'] ?? '') ?>" placeholder="233241234567">
            </div>
            <div class="form-group">
                <label for="instagram">Instagram Handle</label>
                <input type="text" name="instagram" id="instagram" class="form-control" value="<?= htmlspecialchars($user['instagram'] ?? '') ?>" placeholder="username">
            </div>
        </div>
        <div class="form-group">
            <label for="linkedin">LinkedIn</label>
            <input type="text" name="linkedin" id="linkedin" class="form-control" value="<?= htmlspecialchars($user['linkedin'] ?? '') ?>">
        </div>

        <?php if(isSeller()): ?>
        <div class="form-group">
            <label for="shop_banner">Shop Banner Image <small style="color:var(--text-muted);">(requires admin approval)</small></label>
            <?php if(!empty($user['shop_banner'])): ?>
                <img id="shopBannerPreview" src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['shop_banner'])) ?>" class="profile-pic-previewable" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;cursor:pointer;" alt="Shop Banner">
            <?php else: ?>
                <img id="shopBannerPreview" src="" class="profile-pic-previewable" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;cursor:pointer;display:none;" alt="Shop Banner">
            <?php endif; ?>
            <input type="file" name="shop_banner" id="shop_banner" class="form-control" accept="image/*" onchange="previewSelectedImage(this, 'shopBannerPreview')">
        </div>
        <?php endif; ?>

        <div style="background:rgba(124,58,237,0.05); border:1px solid rgba(124,58,237,0.15); border-radius:12px; padding:0.8rem 1rem; margin-bottom:1.25rem; font-size:0.82rem; color:var(--text-muted); display:flex; align-items:center; gap:0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            All profile changes (text fields and images) require admin approval before going live.
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Submit Changes for Approval</button>
    </form>
</div>

<script>
function previewSelectedImage(input, imgId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            img.src = e.target.result;
            img.style.display = 'inline-block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
