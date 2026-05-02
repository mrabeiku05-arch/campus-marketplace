<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

// If they already accepted, go to dashboard
if (isset($user['terms_accepted']) && $user['terms_accepted']) redirect('dashboard.php');


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_terms'])) {
        $boolT = sqlBool(true, $pdo);
        $stmt = $pdo->prepare("UPDATE users SET terms_accepted = $boolT, accepted_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            redirect('dashboard.php');
        } else {
            $error = "Failed to update agreement. Try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding: 20px;">
    <div class="glass form-container fade-in" style="width:100%; max-width:850px; padding:3.5rem; box-shadow:0 32px 80px rgba(0,0,0,0.12);">
        
        <style>
             @media (max-width: 768px) {
                 .auth-wrapper { padding: 10px; min-height: auto !important; }
                 .form-container { padding: 1.5rem !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
                 #termsContainer { height: 400px !important; padding: 1.25rem !important; margin-bottom: 1.5rem !important; }
                 h1 { font-size: 1.6rem !important; }
                 p { font-size: 0.95rem !important; }
             }
         </style>
        
        <div class="text-center" style="margin-bottom:2rem;">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:22px; background:linear-gradient(135deg, rgba(124,58,237,0.12), rgba(124,58,237,0.06)); margin-bottom:1.25rem; border:1px solid rgba(124,58,237,0.1);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <h1 style="font-size:2.2rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Terms & Conditions</h1>
            <p style="color:var(--text-muted); font-size:1.1rem; margin-top:0.5rem; font-weight:500;">Please read and accept our platform standards to continue</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-error fade-in" style="text-align:center; margin-bottom:1.5rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div id="termsContainer" style="height: 450px; overflow-y: scroll; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.08); border-radius: 20px; padding: 2.5rem; margin-bottom: 2.5rem; font-size: 0.95rem; line-height: 1.8; color: var(--text-main); scroll-behavior: smooth;">
            <div id="termsContent">
                <h2 style="font-size: 1.4rem; margin-bottom: 1.5rem;">CampusMarketplace Platform</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 2rem;">Last Updated: March 29, 2026</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">1. INTRODUCTION</h3>
                <p>Welcome to CampusMarketplace. By accessing or using this platform, you agree to comply with and be bound by these Terms and Conditions. If you do not agree, you must not use this platform.</p>
                <p>This platform connects buyers and sellers within the campus community for the exchange of goods and services.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">2. USER ELIGIBILITY</h3>
                <ul>
                    <li>Users must provide accurate information during registration.</li>
                    <li>Users must belong to the campus community or have valid access.</li>
                    <li>Each user is responsible for maintaining the confidentiality of their account.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">3. ACCOUNT REGISTRATION</h3>
                <p>By creating an account, you agree to:</p>
                <ul>
                    <li>Provide truthful personal details (including faculty, department, and residence if required)</li>
                    <li>Keep login credentials secure</li>
                    <li>Accept responsibility for all activities under your account</li>
                </ul>
                <p>We reserve the right to suspend or terminate accounts that provide false or misleading information.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">4. BUYER RESPONSIBILITIES</h3>
                <p>As a buyer, you agree to:</p>
                <ul>
                    <li>Only place orders for items you genuinely intend to purchase</li>
                    <li>Communicate respectfully with sellers</li>
                    <li>Confirm when an item has been received</li>
                    <li>Submit a review after successful purchase (mandatory before further browsing)</li>
                </ul>
                <p>Failure to confirm delivery or provide accurate feedback may result in account restrictions.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">5. SELLER RESPONSIBILITIES</h3>
                <p>As a seller, you agree to:</p>
                <ul>
                    <li>Upload accurate product information and images</li>
                    <li>Maintain honest communication with buyers</li>
                    <li>Confirm when an item has been sold</li>
                    <li>Deliver items as agreed with the buyer</li>
                    <li>Respect platform limits (e.g., product upload limits for basic/premium accounts)</li>
                </ul>
                <p>Misleading listings or failure to deliver may result in penalties or account suspension.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">6. ORDER PROCESS & CONFIRMATION</h3>
                <p>Orders follow a strict process:</p>
                <ol>
                    <li>Buyer marks item as “Ordered”</li>
                    <li>Seller confirms item as “Sold”</li>
                    <li>Buyer confirms item as “Received”</li>
                </ol>
                <p>An order is only considered:</p>
                <ul>
                    <li><strong>“Sold”</strong> after seller confirmation</li>
                    <li><strong>“Completed”</strong> after buyer confirmation</li>
                </ul>
                <p>Both confirmations are required to finalize a transaction.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">7. PAYMENT TERMS</h3>
                <ul>
                    <li>All transactions are conducted as <strong>Pay on Delivery (POD)</strong> unless both parties agree otherwise.</li>
                    <li>The platform does not directly process payments.</li>
                    <li>Buyers and sellers must mutually agree on payment terms.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">8. MESSAGING & COMMUNICATION</h3>
                <ul>
                    <li>The platform provides a messaging system between buyers and sellers.</li>
                    <li>Users must communicate respectfully and professionally.</li>
                    <li>All messages may be logged and monitored for safety and dispute resolution.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">9. REVIEWS & RATINGS</h3>
                <ul>
                    <li>Buyers are required to leave a review after confirming delivery.</li>
                    <li>Reviews must be honest and not abusive.</li>
                    <li>Fake or misleading reviews are strictly prohibited.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">10. ADMIN RIGHTS & CONTROL</h3>
                <p>The platform administrator has full authority to:</p>
                <ul>
                    <li>Monitor all transactions and communications</li>
                    <li>Approve or reject profile updates</li>
                    <li>Approve premium account requests</li>
                    <li>Adjust pricing for premium features and ads</li>
                    <li>Resolve disputes between users</li>
                    <li>Suspend or terminate accounts for violations</li>
                </ul>
                <p>All administrative decisions are final.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">11. PREMIUM & PAID FEATURES</h3>
                <ul>
                    <li>Sellers may request premium accounts for enhanced features.</li>
                    <li>Admin approval is required before activation.</li>
                    <li>Fees may apply and are subject to change by the admin.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">12. VACATION MODE</h3>
                <ul>
                    <li>Sellers may request to activate vacation mode.</li>
                    <li>Admin approval is required.</li>
                    <li>While active, seller listings may be hidden or paused.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">13. PROHIBITED ACTIVITIES</h3>
                <p>Users must NOT:</p>
                <ul>
                    <li>Post false or misleading product information</li>
                    <li>Attempt fraud or deceive other users</li>
                    <li>Abuse the messaging system</li>
                    <li>Bypass platform rules or restrictions</li>
                    <li>Use the platform for illegal activities</li>
                </ul>
                <p>Violations may result in immediate suspension or permanent ban.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">14. DISPUTES</h3>
                <ul>
                    <li>Any disputes between buyers and sellers may be reviewed by the admin.</li>
                    <li>The platform may use message logs and transaction data to resolve disputes.</li>
                    <li>Users must cooperate during investigations.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">15. LIMITATION OF LIABILITY</h3>
                <p>The platform acts only as an intermediary between buyers and sellers. We are not responsible for:</p>
                <ul>
                    <li>Product quality</li>
                    <li>Delivery issues</li>
                    <li>Payment disputes</li>
                </ul>
                <p>Users engage in transactions at their own risk.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">16. DATA & PRIVACY</h3>
                <ul>
                    <li>User data is collected to improve platform functionality.</li>
                    <li>Data will not be shared without consent, except when required for legal or administrative purposes.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">17. MODIFICATIONS TO TERMS</h3>
                <ul>
                    <li>These Terms may be updated at any time.</li>
                    <li>Continued use of the platform constitutes acceptance of updated Terms.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">18. TERMINATION</h3>
                <p>We reserve the right to suspend or terminate accounts at any time and remove listings that violate policies.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">19. CONTACT INFORMATION</h3>
                <p>For support or inquiries, contact: 📞 0506589823</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">20. SHOP SHARING & EXTERNAL LINKS</h3>
                <ul>
                    <li>Sellers are provided with a <strong>unique Global Shop Link</strong> in their dashboard.</li>
                    <li>We encourage sellers to share this link on external platforms such as WhatsApp (Status and Chats), Facebook, Instagram, and other social media to showcase their products to potential buyers outside the platform.</li>
                    <li>This link serves as a direct gateway for customers to view a seller's full catalog on the CampusMarketplace.</li>
                    <li>Any misuse of links for spamming or unauthorized data collection is strictly prohibited.</li>
                </ul>

                    <?php
                        $stmt = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC");
                        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">21. ACCOUNT TIERS & SELLER RULES</h3>
                    <p>Users may choose from <?= count($tiers) ?> account levels, each with specific system-enforced limits and benefits:</p>
                    <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem;">
                    <?php foreach ($tiers as $tier): ?>
                      <div class="tier-block" style="padding: 15px; border: 1px solid <?= htmlspecialchars($tier['badge']) ?>40; border-radius: 12px; border-left: 4px solid <?= htmlspecialchars($tier['badge']) ?>; background: rgba(0,0,0,0.02);">
                        <h4 style="font-size:1.05rem; font-weight:800; margin-bottom:0.5rem; text-transform:capitalize;"><?= htmlspecialchars($tier['tier_name']) ?> Account</h4>
                        <p style="margin:0; font-size:0.9rem; line-height:1.6;">
                            The <strong><?= ucfirst($tier['tier_name']) ?></strong> account is designed for <?= $tier['tier_name'] === 'basic' ? 'casual sellers' : 'serious businesses' ?>.
                            <?php 
                                $price = (float)$tier['price'];
                                $originalPrice = isset($tier['original_price']) ? (float)$tier['original_price'] : 0.0;
                                $isDiscounted = ($originalPrice > $price);
                                $duration = (int)$tier['duration'];
                                $durationText = $duration . ' month' . ($duration == 1 ? '' : 's');
                            ?>
                            <?php if ($price <= 0): ?>
                                It is completely <strong>free</strong> and valid for <?= htmlspecialchars($durationText) ?>.
                            <?php else: ?>
                                It requires a fee of 
                                <?php if ($isDiscounted): ?>
                                    <span style="text-decoration: line-through; opacity: 0.6;">₵<?= number_format($originalPrice, 2) ?></span>
                                <?php endif; ?>
                                <strong>₵<?= number_format($price, 2) ?></strong>
                                <?php if ($isDiscounted): ?>
                                    <span style="color: var(--primary); font-weight: 800; font-size: 0.85em; margin-left: 4px;">(PROMOTIONAL RATE)</span>
                                <?php endif; ?>
                                and is valid for <?= htmlspecialchars($durationText) ?>.
                            <?php endif; ?>
                        </p>
                        <?php 
                            $bens = json_decode($tier['benefits'] ?? '[]', true) ?: []; 
                            if(count($bens) > 0): 
                        ?>
                        <ul style="margin-top:0.75rem; margin-bottom:0.25rem; font-size:0.9rem; color:var(--text-main); padding-left:1.5rem;">
                            <?php foreach($bens as $b): ?>
                                <li style="margin-bottom:0.25rem;"><strong><?= htmlspecialchars($b) ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    </div>
                    <p><strong>Admin Rights:</strong> Admin may adjust product limits, fees, badges, and ads boost benefits at any time. All changes continuously pull from the system architecture.</p>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">22. RICH MEDIA & VOICE MESSAGING</h3>
                <ul>
                    <li>The platform allows users to send <strong>images, videos, and voice notes</strong>.</li>
                    <li>Users are strictly prohibited from sending offensive, explicit, or harassing media.</li>
                    <li>Voice notes and videos are subject to the same monitoring and recording standards as text messages.</li>
                    <li>The platform is not responsible for data usage incurred during the playback or recording of media.</li>
                    <li>Any abuse of the voice/video messaging system for harassment will result in an immediate and permanent ban.</li>
                </ul>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem;">23. ACCEPTANCE</h3>

                <p>By using this platform, you confirm that you have read, understood, and agreed to these Terms and Conditions.</p>
                <div style="height: 50px;"></div>
            </div>
        </div>

        <form method="POST" id="termsForm">
            <input type="hidden" name="accept_terms" value="1">
            <button type="submit" id="acceptBtn" class="btn btn-primary" disabled style="width:100%; justify-content:center; padding:1.2rem; font-size:1.15rem; font-weight:700; opacity: 0.5; cursor: not-allowed;">
                Please scroll to the bottom to accept
            </button>
        </form>

        <div style="margin-top:2rem; text-align:center;">
             <p style="font-size:0.9rem; color:var(--text-muted);">
                Need help? <a href="#" style="color:var(--primary); font-weight:700;">Contact Support</a>
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const termsContainer = document.getElementById('termsContainer');
    const acceptBtn = document.getElementById('acceptBtn');
    
    termsContainer.addEventListener('scroll', function() {
        const scrollPos = termsContainer.scrollTop + termsContainer.clientHeight;
        const totalHeight = termsContainer.scrollHeight;
        
        // Use a small buffer (5px) for browser inconsistencies
        if (scrollPos >= totalHeight - 5) {
            acceptBtn.disabled = false;
            acceptBtn.style.opacity = '1';
            acceptBtn.style.cursor = 'pointer';
            acceptBtn.innerText = 'I Agree & Continue';
            acceptBtn.style.boxShadow = '0 12px 30px rgba(124,58,237,0.3)';
        }
    });
});
</script>

<style>
    #termsContainer::-webkit-scrollbar {
        width: 8px;
    }
    #termsContainer::-webkit-scrollbar-track {
        background: transparent;
    }
    #termsContainer::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.1);
        border-radius: 99px;
    }
    :root.dark-mode #termsContainer::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
    }
    
    @media (max-width: 600px) {
        .form-container {
            width: 95% !important;
            padding: 2.5rem 1.5rem !important;
        }
        h1 { font-size: 1.7rem !important; }
        #termsContainer { height: 350px !important; padding: 1.5rem !important; }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
