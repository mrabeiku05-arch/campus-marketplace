    </div><!-- /container -->
    <footer style="background:var(--card-bg); padding:1.5rem 1rem 0.5rem; color:var(--text-main); border-top:1px solid var(--border); margin-top:2rem; border-radius:24px 24px 0 0; width:100%; box-sizing:border-box;">
        <div class="container footer-grid" style="margin-bottom:1rem;">
            <!-- Brand -->
            <div>
                <h3 style="font-size:1rem; font-weight:800; margin-bottom:0.4rem; display:flex; align-items:center; gap:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    Campus Marketplace
                </h3>
                <p style="color:var(--text-muted); font-size:0.7rem; line-height:1.4;">Everything you need on campus.<br/>Connect. Buy. Sell easily.</p>
                <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
                    <a href="#" style="color:var(--text-muted);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
                    <a href="#" style="color:var(--text-muted);"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                </div>
            </div>

            <!-- Navigation -->
            <div>
                <h4 style="font-weight:700; margin-bottom:0.5rem; font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Navigation</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.25rem;">
                    <li><a href="<?= $baseUrl ?>index.php" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Home</a></li>
                    <li><a href="<?= $baseUrl ?>about.php" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">About</a></li>
                    <li><a href="<?= $baseUrl ?>dashboard.php" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Dashboard</a></li>
                    <li><a href="<?= $baseUrl ?>add_product.php" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Sell</a></li>
                    <li><a href="javascript:void(0)" onclick="if(typeof openSideCart === 'function') openSideCart(); return false;" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Cart</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 style="font-weight:700; margin-bottom:0.5rem; font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Contact</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.25rem;">
                    <li style="display:flex; align-items:center; gap:0.4rem; font-size:0.75rem;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg> TTU Campus</li>
                    <li style="display:flex; align-items:center; gap:0.4rem; font-size:0.75rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 14a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 3h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.01a16 16 0 0 0 6.08 6.08l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg> 
                        <a href="https://wa.me/233506589823" target="_blank" style="color:var(--text-main); text-decoration:none;">0506589823</a>
                    </li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 style="font-weight:700; margin-bottom:0.5rem; font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Support</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.25rem;">
                    <li><a href="<?= $baseUrl ?>about.php#how-it-works" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">How it works</a></li>
                    <li><a href="<?= $baseUrl ?>about.php#safe-trading" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Safety</a></li>
                    <li><a href="javascript:void(0)" onclick="openTermsModal()" style="color:var(--text-main); text-decoration:none; font-size:0.75rem;">Terms & Conditions</a></li>
                </ul>
            </div>
        </div>
        <div style="text-align:center; padding-top:1rem; border-top:1px solid var(--border); color:var(--text-muted); font-size:0.7rem;">
            &copy; <?= date('Y') ?> Campus Marketplace
        </div>
    </footer>

    <!-- TERMS & CONDITIONS MODAL -->
    <div id="termsModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#111111; z-index:1000000; align-items:center; justify-content:center; padding:20px;">
        <div class="glass" style="width:100%; max-width:800px; height:85vh; border-radius:32px; display:flex; flex-direction:column; overflow:hidden; position:relative; box-shadow:0 30px 100px rgb(0, 0, 0); animation:modalSlideUp 0.4s cubic-bezier(0.19, 1, 0.22, 1);">
            <div style="padding:1.5rem 2rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.2rem; font-weight:800;">Terms & Conditions</h3>
                <button onclick="closeTermsModal()" style="background:#f3f4f6; color:#111111; border:none; width:44px; height:44px; border-radius:50%; cursor:pointer; font-size:1.5rem; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">&times;</button>
            </div>
            <!-- Progress Bar -->
            <div style="width:100%; height:4px; background:rgb(124, 58, 237); position:relative; overflow:hidden;">
                <div id="termsProgressBar" style="position:absolute; top:0; left:0; height:100%; width:0%; background:#7c3aed; transition:width 0.1s;"></div>
            </div>
            <div id="modalTermsContent" style="flex:1; overflow-y:auto; padding:2rem 2.5rem; font-size:0.95rem; line-height:1.7; color:var(--text-main);">
                <h4 style="font-size:1.4rem; margin-bottom:1.5rem;">Campus Marketplace Platform</h4>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:2rem;">Last Updated: March 29, 2026</p>
                <div id="tc-body-anchor">
                    <h5 style="margin-top:1.5rem; font-weight:700;">1. INTRODUCTION</h5>
                    <p>Welcome to Campus Marketplace. By accessing or using this platform, you agree to comply with and be bound by these Terms and Conditions. If you do not agree, you must not use this platform.</p>
                    
                    <h5 style="margin-top:1.5rem; font-weight:700;">2. USER ELIGIBILITY</h5>
                    <ul>
                        <li>Users must provide accurate information during registration.</li>
                        <li>Users must belong to the campus community or have valid access.</li>
                        <li>Each user is responsible for maintaining the confidentiality of their account.</li>
                    </ul>

                    <h5 style="margin-top:1.5rem; font-weight:700;">3. ACCOUNT REGISTRATION</h5>
                    <p>By creating an account, you agree to provide truthful personal details (including faculty, department, and residence if required) and keep login credentials secure.</p>
                    
                    <h5 style="margin-top:1.5rem; font-weight:700;">4. BUYER RESPONSIBILITIES</h5>
                    <ul>
                        <li>Only place orders for items you genuinely intend to purchase.</li>
                        <li>Communicate respectfully with sellers.</li>
                        <li>Confirm when an item has been received.</li>
                        <li>Submit a review after successful purchase (mandatory before further browsing).</li>
                    </ul>

                    <h5 style="margin-top:1.5rem; font-weight:700;">5. SELLER RESPONSIBILITIES</h5>
                    <ul>
                        <li>Upload accurate product information and images.</li>
                        <li>Maintain honest communication with buyers.</li>
                        <li>Confirm when an item is sold.</li>
                        <li>Deliver items as agreed.</li>
                    </ul>

                    <h5 style="margin-top:1.5rem; font-weight:700;">6. ORDER PROCESS & CONFIRMATION</h5>
                    <p>An order is only considered “Sold” after seller confirmation and “Completed” after buyer confirmation. Both are required.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">7. PAYMENT TERMS</h5>
                    <p>Default method: <strong>Pay on Delivery (POD)</strong>. The platform does not directly process payments.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">8. MESSAGING & COMMUNICATION</h5>
                    <p>Users must communicate respectfully. All messages may be logged and monitored for safety.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">9. REVIEWS & RATINGS</h5>
                    <p>Reviews are REQUIRED after delivery and must be honest. Fake reviews are prohibited.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">10. ADMIN RIGHTS & CONTROL</h5>
                    <p>Admin has full authority to monitor all activity, resolve disputes, and suspend accounts for violations.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">11. PREMIUM & PAID FEATURES</h5>
                    <p>Sellers may request premium accounts for enhanced features, subject to admin approval and fees.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">12. VACATION MODE</h5>
                    <p>Sellers may request vacation mode with admin approval, which may hide or pause listings.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">13. PROHIBITED ACTIVITIES</h5>
                    <p>Users must NOT post false listings, attempt fraud, or abuse the messaging system.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">14. DISPUTES</h5>
                    <p>Admin reviews disputes using message logs and transaction data to reach final decisions.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">15. LIMITATION OF LIABILITY</h5>
                    <p>The platform is only an intermediary. Not responsible for product quality or payment conflicts.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">16. DATA & PRIVACY</h5>
                    <p>User data is collected for platform improvement and legal purposes only.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">17. MODIFICATIONS TO TERMS</h5>
                    <p>Terms may be updated at any time. Continued use constitutes acceptance.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">18. TERMINATION</h5>
                    <p>We reserve the right to suspend accounts or remove listings that violate policies.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">19. CONTACT INFORMATION</h5>
                    <p style="display:flex; align-items:center; gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> 0506589823</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">20. SHOP SHARING & EXTERNAL LINKS</h5>
                    <ul>
                        <li>Sellers are provided with a <strong>unique Global Shop Link</strong> in their dashboard.</li>
                        <li>We encourage sellers to share this link on external platforms (WhatsApp, Facebook, etc.) to showcase their catalog.</li>
                        <li>This link is a direct gateway for external customers to view a seller's full catalog on the platform.</li>
                        <li>Any misuse of links for spamming is strictly prohibited.</li>
                    </ul>

                    <h5 style="margin-top:1.5rem; font-weight:700;">21. ACCOUNT TIERS & SELLER RULES</h5>
                    <?php
                        $stmt = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC");
                        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <p>Users may choose from <?= count($tiers) ?> account levels, each with specific system-enforced limits and benefits:</p>
                    <ul style="list-style:none; padding-left:0;">
                    <?php foreach ($tiers as $tier): ?>
                        <li style="margin-bottom:1rem; padding:10px; border:1px solid <?= htmlspecialchars($tier['badge']) ?>40; border-left: 4px solid <?= htmlspecialchars($tier['badge']) ?>; border-radius:12px; background:#ffffff;">
                            <h6 style="font-size:0.95rem; font-weight:800; margin-bottom:0.4rem; text-transform:capitalize;"><?= htmlspecialchars($tier['tier_name']) ?> Account</h6>
                            <p style="margin:0; font-size:0.85rem; line-height:1.5;">
                                The <strong><?= ucfirst($tier['tier_name']) ?></strong> account is designed for <?= $tier['tier_name'] === 'basic' ? 'casual sellers' : 'serious businesses' ?>.
                                <?= $tier['price'] <= 0 ? 'It is completely <strong>free</strong> and valid for ' . htmlspecialchars($tier['duration']) . ' month' . ($tier['duration'] == 1 ? '' : 's') . '.' : 'It requires a fee of <strong>₵' . number_format($tier['price'], 2) . '</strong> and is valid for ' . htmlspecialchars($tier['duration']) . ' month' . ($tier['duration'] == 1 ? '' : 's') . '.' ?>
                            </p>
                            <?php 
                                $bens = json_decode($tier['benefits'] ?? '[]', true) ?: []; 
                                if(count($bens) > 0): 
                            ?>
                            <ul style="margin-top:0.75rem; margin-bottom:0.25rem; font-size:0.85rem; color:var(--text-main); padding-left:1.5rem;">
                                <?php foreach($bens as $b): ?>
                                    <li style="margin-bottom:0.25rem;"><strong><?= htmlspecialchars($b) ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <p style="font-size:0.85rem; color:var(--text-muted);">Admin reserves the right to adjust product limits, fees, badges, and ads boost benefits at any time. All changes continuously pull from the system architecture.</p>

                    <h5 style="margin-top:1.5rem; font-weight:700;">22. RICH MEDIA & VOICE MESSAGING</h5>
                    <ul>
                        <li>The platform supports sending **images, videos, and voice notes**.</li>
                        <li>Users must not send explicit, offensive, or harassing media.</li>
                        <li>Rich media is subject to monitoring for platform safety and dispute resolution.</li>
                        <li>Any abuse of voice/video messaging for harassment will result in a permanent ban.</li>
                    </ul>

                    <h5 style="margin-top:1.5rem; font-weight:700;">23. ACCEPTANCE</h5>

                    <p>By using this platform, you confirms that you have read, understood, and agreed to these Terms and Conditions.</p>
                    <div style="height:100px;"></div>
                </div>
            </div>
            <div style="padding:1.5rem 2rem; border-top:1px solid var(--border); text-align:center;">
                <button onclick="closeTermsModal()" id="modalDoneBtn" class="btn btn-primary" style="width:100%; display:none;">I Understand & Have Read Everything</button>
                <div id="modalScrollHint" style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">Please scroll to the bottom to acknowledge the terms</div>
            </div>
        </div>
    </div>

    <script>
        function openTermsModal() {
            const modal = document.getElementById('termsModal');
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            
            const content = document.getElementById('modalTermsContent');
            content.addEventListener('scroll', function() {
                const scrollPercent = (content.scrollTop / (content.scrollHeight - content.clientHeight)) * 100;
                document.getElementById('termsProgressBar').style.width = scrollPercent + '%';

                if (content.scrollTop + content.clientHeight >= content.scrollHeight - 20) {
                    document.getElementById('modalDoneBtn').style.display = 'block';
                    document.getElementById('modalScrollHint').style.display = 'none';
                    
                    // Specific to signup form if present
                    const signupCheckbox = document.getElementById('termsCheckbox');
                    if (signupCheckbox) {
                        signupCheckbox.disabled = false;
                        signupCheckbox.classList.add('ready-to-check');
                    }
                }
            });
        }
        function closeTermsModal() {
            document.getElementById('termsModal').classList.remove('open');
            document.body.style.overflow = 'auto';
        }
    </script>

    <!-- CAMPUS MARKETPLACE AI ASSISTANT -->
    <div id="ai-assistant-widget" style="position:fixed; bottom:20px; right:20px; z-index:9999; font-family:'Inter', sans-serif;">
        <!-- Chat Head Button — Modern SVG icon -->
        <button id="ai-chat-btn" onclick="toggleAIChat()" style="width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg, #7c3aed, #a78bfa); border:none; box-shadow:0 6px 20px rgb(124, 58, 237); color:white; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </button>

        <!-- Chat Window -->
        <div id="ai-chat-window" style="display:none; position:absolute; bottom:72px; right:0; width:360px; max-width:90vw; height:460px; border-radius:20px; flex-direction:column; overflow:hidden; box-shadow:0 8px 24px #d1d5db; border:1px solid var(--border); background:var(--card-bg);">
            <!-- Header -->
            <div style="background:#7c3aed; padding:0.85rem 1rem; color:white; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:44px; height:44px; background:rgb(255, 255, 255); border-radius:10px; display:flex; align-items:center; justify-content:center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div>
                        <h4 style="margin:0; font-size:0.9rem; font-weight:600;">Campus Assistant</h4>
                        <span style="font-size:0.7rem; opacity:0.8;">AI-Powered Help</span>
                    </div>
                </div>
                <button onclick="toggleAIChat()" style="background:none; border:none; color:white; font-size:1.3rem; cursor:pointer; line-height:1; opacity:0.8; transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">&times;</button>
            </div>
            <!-- Messages -->
            <div id="ai-messages" style="flex:1; padding:1rem; overflow-y:auto; display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
                <div style="display:flex; flex-direction:column; align-items:flex-start; max-width:85%;">
                    <div style="background:var(--card-bg); padding:0.75rem 1rem; border-radius:14px 14px 14px 2px; border:1px solid var(--border); color:var(--text-main); font-size:0.85rem; line-height:1.5;">
                        Hi there! I'm the Campus Marketplace Assistant. I can help with buying, selling, safety tips, and navigating the site. Ask me anything!
                    </div>
                </div>
            </div>
            <!-- Input -->
            <div style="padding:0.75rem; border-top:1px solid var(--border); display:flex; gap:8px; background:var(--card-bg);">
                <input type="text" id="ai-input" placeholder="Ask a question..." style="flex:1; padding:0.7rem 1rem; border-radius:999px; border:1px solid var(--border); background:var(--bg); color:var(--text-main); font-size:0.85rem; outline:none;" onkeypress="if(event.key==='Enter') sendAIMessage()">
                <button onclick="sendAIMessage()" style="background:#7c3aed; color:white; border:none; padding:0 1rem; border-radius:999px; cursor:pointer; font-weight:600; font-size:0.84rem; transition:background 0.2s;" onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">Send</button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleAIChat() {
            const chatWindow = document.getElementById('ai-chat-window');
            const chatBtn = document.getElementById('ai-chat-btn');
            if(chatWindow.style.display === 'none') {
                chatWindow.style.display = 'flex';
                chatBtn.style.transform = 'scale(0.85) rotate(90deg)';
                chatBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            } else {
                chatWindow.style.display = 'none';
                chatBtn.style.transform = 'scale(1) rotate(0deg)';
                chatBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
            }
        }

        async function sendAIMessage() {
            const input = document.getElementById('ai-input');
            const msg = input.value.trim();
            if(!msg) return;
            addChatMessage(msg, 'user');
            input.value = '';
            
            // Wait for typing...
            const typingId = 'typing-' + Date.now();
            const container = document.getElementById('ai-messages');
            const typingDiv = document.createElement('div');
            typingDiv.id = typingId;
            typingDiv.style.cssText = 'display:flex;flex-direction:column;max-width:85%;animation:fadeIn 0.3s ease; align-items:flex-start; align-self:flex-start;';
            typingDiv.innerHTML = `<div style="background:var(--card-bg); border:1px solid var(--border); color:var(--text-muted); padding:0.7rem 1rem; border-radius:14px 14px 14px 2px; font-size:0.85rem;">typing...</div>`;
            container.appendChild(typingDiv);
            container.scrollTop = container.scrollHeight;

            try {
                // Smat-Detect the local path to avoid 404s
                const pathParts = window.location.pathname.split('/');
                const isMarketplace = pathParts.includes('marketplace');
                const base = isMarketplace ? '<?= $baseUrl ?>' : '/';
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const res = await fetch(base + 'api/chat_ai.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg, csrf_token: csrfToken })
                });
                const data = await res.json();
                document.getElementById(typingId)?.remove();
                addChatMessage(data.response, 'ai');
            } catch(e) {
                document.getElementById(typingId)?.remove();
                addChatMessage("I'm having trouble connecting right now, let's chat soon!", 'ai');
            }
        }

        function addChatMessage(text, sender) {
            const container = document.getElementById('ai-messages');
            const div = document.createElement('div');
            div.style.cssText = 'display:flex;flex-direction:column;max-width:85%;animation:fadeIn 0.3s ease;';
            if(sender === 'user') {
                div.style.alignItems = 'flex-end';
                div.style.alignSelf = 'flex-end';
                div.innerHTML = `<div style="background:#7c3aed; color:white; padding:0.7rem 1rem; border-radius:14px 14px 2px 14px; font-size:0.85rem; line-height:1.5;">${escapeHtml(text)}</div>`;
            } else {
                div.style.alignItems = 'flex-start';
                div.style.alignSelf = 'flex-start';
                div.innerHTML = `<div style="background:var(--card-bg); border:1px solid var(--border); color:var(--text-main); padding:0.7rem 1rem; border-radius:14px 14px 14px 2px; font-size:0.85rem; line-height:1.5;">${escapeHtml(text)}</div>`;
            }
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        function escapeHtml(unsafe) {
            return unsafe.replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }
    </script>

    <script src="<?= getAssetUrl('assets/js/main.js') ?>"></script>

    <!-- MODULE ERROR HANDLING & FALLBACK -->
    <script>
        // Monitor for module script errors
        document.addEventListener('error', function(event) {
            if (event.filename && (event.filename.includes('app.js') || event.filename.includes('app.css'))) {
                console.warn('Failed to load app module, but site will function with fallback styles');
                document.body.classList.add('module-failed');
            }
        });

        // Handle broken product images on mobile (WCAG compliance)
        document.addEventListener('error', function(event) {
            if (event.target.tagName === 'IMG' && event.target.classList.contains('product-img')) {
                // Replace broken image with placeholder
                const wrapper = event.target.closest('.product-img-wrap');
                if (wrapper) {
                    wrapper.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;color:#86868b;background:#f5f5f7;width:100%;height:100%;border-radius:8px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><path d="m21 15-5-5L5 21"/></svg></div>';
                    console.warn('Product image failed to load, showing placeholder');
                }
            }
        }, true);

        // Ensure mobile viewport is always respected
        function ensureMobileOptimization() {
            const html = document.documentElement;
            const body = document.body;

            // Ensure no horizontal scrollbars
            html.style.width = '100%';
            html.style.maxWidth = '100vw';
            html.style.overflowX = 'hidden';

            body.style.width = '100%';
            body.style.maxWidth = '100vw';
            body.style.overflowX = 'hidden';
        }

        // Run immediately and on load
        ensureMobileOptimization();
        window.addEventListener('load', ensureMobileOptimization);
        window.addEventListener('resize', ensureMobileOptimization);

        // Detect if viewport meta tag exists, if not add it
        if (!document.querySelector('meta[name="viewport"]')) {
            const meta = document.createElement('meta');
            meta.name = 'viewport';
            meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover';
            document.head.appendChild(meta);
        }
    </script>
    <!-- SIDE CART DRAWER -->
    <div class="cart-drawer-backdrop" id="sideCartBackdrop" onclick="closeSideCart()"></div>
    <div class="cart-drawer" id="sideCartDrawer">
        <div class="cart-drawer-header">
            <h2>Your Cart</h2>
            <button class="cart-drawer-close" onclick="closeSideCart()">&times;</button>
        </div>
        <div class="cart-drawer-body" id="sideCartItems"></div>
        <div class="cart-drawer-footer" id="sideCartFooter" style="display:none;">
            <div class="cart-drawer-total">
                <span>Total</span>
                <span id="sideCartTotal">₵0.00</span>
            </div>
            <a href="<?= $baseUrl ?>checkout.php" class="btn-nike-checkout" style="display:block; text-align:center;">Checkout to Contact Seller</a>
        </div>
    </div>
    
    <script>
        function openSideCart() {
            document.getElementById('sideCartBackdrop').classList.add('open');
            document.getElementById('sideCartDrawer').classList.add('open');
            document.body.style.overflow = 'hidden';
            renderSideCart();
        }
        function closeSideCart() {
            document.getElementById('sideCartBackdrop').classList.remove('open');
            document.getElementById('sideCartDrawer').classList.remove('open');
            document.body.style.overflow = 'auto';
        }
        window.openSideCart = openSideCart;

        window.renderSideCart = function() {
            if(typeof cmCart === 'undefined') return;
            const cart = cmCart.get();
            const itemsContainer = document.getElementById('sideCartItems');
            const footer = document.getElementById('sideCartFooter');
            
            if (cart.length === 0) {
                itemsContainer.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding:2rem 0;">Your cart is empty.</p>';
                footer.style.display = 'none';
                return;
            }
            
            footer.style.display = 'block';
            itemsContainer.innerHTML = cart.map(item => `
                <div class="cart-drawer-item">
                    <img src="${item.image || ''}" onerror="this.src=''; this.style.background='var(--border)';">
                    <div class="cart-drawer-details">
                        <div class="cart-drawer-title">${item.name}</div>
                        <div class="cart-drawer-price">₵${item.price.toFixed(2)}</div>
                        <div class="cart-drawer-actions">
                            <div class="cart-drawer-qty">
                                <button onclick="cmCart.updateQty(${item.id}, ${item.qty - 1}); renderSideCart();">&minus;</button>
                                <span style="font-weight:600; width:20px; text-align:center;">${item.qty}</span>
                                <button onclick="cmCart.updateQty(${item.id}, ${item.qty + 1}); renderSideCart();">&plus;</button>
                            </div>
                            <button class="cart-drawer-remove" onclick="cmCart.remove(${item.id}); renderSideCart();">Remove</button>
                        </div>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('sideCartTotal').textContent = '₵' + cmCart.total().toFixed(2);
        };
    </script>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- GLOBAL PROFILE PICTURE PREVIEW MODAL (WhatsApp-style)             -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="profilePreviewOverlay" style="
        display:none; position:fixed; inset:0; z-index:1000001;
        background:#111111; align-items:center; justify-content:center;
        opacity:0; transition:opacity 0.3s ease;
        cursor:zoom-out;
    ">
        <div id="profilePreviewInner" style="
            position:relative; display:flex; flex-direction:column;
            align-items:center; gap:1rem; padding:20px;
            transform:scale(0.92); transition:transform 0.35s cubic-bezier(0.19,1,0.22,1);
        ">
            <!-- Close button -->
            <button onclick="closeProfilePreview()" aria-label="Close preview" style="
                position:absolute; top:-12px; right:-12px;
                background:#f3f4f6; border:none;
                width:42px; height:42px; border-radius:50%;
                cursor:pointer; font-size:1.4rem; color:#111111;
                display:flex; align-items:center; justify-content:center;
                transition:background 0.2s;
            " onmouseover="this.style.background='#e5e7eb'"
               onmouseout="this.style.background='#f3f4f6'">&times;</button>

            <!-- Preview image -->
            <img id="profilePreviewImg" src="" alt="Preview" style="
                max-width:min(500px, 88vw); max-height:72vh;
                border-radius:20px; object-fit:contain;
                box-shadow:0 32px 100px rgb(0, 0, 0);
                border:1px solid rgb(255, 255, 255);
                background:#111;
            ">

            <!-- Caption -->
            <div id="profilePreviewCaption" style="
                color:rgb(255, 255, 255); font-weight:600;
                font-size:0.95rem; letter-spacing:-0.01em;
            "></div>
        </div>
    </div>

    <script>
    /* ── Profile Preview Modal Logic ── */
    (function() {
        const overlay = document.getElementById('profilePreviewOverlay');
        const inner   = document.getElementById('profilePreviewInner');
        const img     = document.getElementById('profilePreviewImg');
        const caption = document.getElementById('profilePreviewCaption');

        window.openProfilePreview = function(src, title) {
            img.src = src;
            caption.textContent = title || '';
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Trigger enter animation on next frame
            requestAnimationFrame(function() {
                overlay.style.opacity = '1';
                inner.style.transform = 'scale(1)';
            });
        };

        window.closeProfilePreview = function() {
            overlay.style.opacity = '0';
            inner.style.transform = 'scale(0.92)';
            setTimeout(function() {
                overlay.style.display = 'none';
                document.body.style.overflow = '';
                img.src = '';
            }, 300);
        };

        // Delegated click listener — any <img> with .profile-pic-previewable
        document.addEventListener('click', function(e) {
            var target = e.target.closest('.profile-pic-previewable');
            if (target && target.tagName === 'IMG') {
                e.preventDefault();
                e.stopPropagation();
                openProfilePreview(target.src, target.alt || 'Profile Picture');
            }
        });

        // Close on overlay background click
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeProfilePreview();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') {
                closeProfilePreview();
            }
        });
    })();
    </script>
    <?php if (isLoggedIn()): ?>
    <script>
    (function () {
        let lastNotificationId = 0;
        let primed = false;
        let toastContainer = null;

        function ensureToastContainer() {
            if (toastContainer) return toastContainer;
            toastContainer = document.createElement('div');
            toastContainer.style.position = 'fixed';
            toastContainer.style.right = '18px';
            toastContainer.style.bottom = '18px';
            toastContainer.style.zIndex = '1000002';
            toastContainer.style.display = 'flex';
            toastContainer.style.flexDirection = 'column';
            toastContainer.style.gap = '10px';
            toastContainer.style.maxWidth = '340px';
            document.body.appendChild(toastContainer);
            return toastContainer;
        }

        function updateBadge(selector, count) {
            document.querySelectorAll(selector).forEach(function (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            });
        }

        function showToast(title, message, linkUrl, actionText) {
            const container = ensureToastContainer();
            const toast = document.createElement('div');
            toast.style.background = 'rgb(17, 24, 39)';
            toast.style.color = '#fff';
            toast.style.borderRadius = '18px';
            toast.style.padding = '14px 16px';
            toast.style.boxShadow = '0 18px 45px rgb(0, 0, 0)';
            toast.style.border = '1px solid rgb(255, 255, 255)';
            toast.style.background = 'rgba(0,0,0,0.85)';
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.cursor = linkUrl ? 'pointer' : 'default';

            const titleEl = document.createElement('div');
            titleEl.style.cssText = 'font-weight:800; margin-bottom:4px;';
            titleEl.textContent = title;
            const bodyEl = document.createElement('div');
            bodyEl.style.cssText = 'font-size:0.9rem; line-height:1.45; color:rgb(255, 255, 255);';
            bodyEl.textContent = message;
            toast.appendChild(titleEl);
            toast.appendChild(bodyEl);
            if (actionText) {
                const actionEl = document.createElement('div');
                actionEl.style.cssText = 'margin-top:10px; font-size:0.8rem; color:#93c5fd; font-weight:700;';
                actionEl.textContent = actionText;
                toast.appendChild(actionEl);
            }

            if (linkUrl) {
                toast.addEventListener('click', function () {
                    window.location.href = linkUrl;
                });
            }

            container.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(8px)';
            }, 5000);
            setTimeout(function () {
                toast.remove();
            }, 5400);
        }

        function notifyBrowser(title, body, linkUrl) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            const notification = new Notification(title, { body: body, tag: 'campus-marketplace' });
            notification.onclick = function () {
                window.focus();
                if (linkUrl) window.location.href = linkUrl;
                notification.close();
            };
        }

        function promptForBrowserAlerts() {
            if (!('Notification' in window) || Notification.permission !== 'default') return;
            if (localStorage.getItem('cm_notification_prompted') === '1') return;
            localStorage.setItem('cm_notification_prompted', '1');
            showToast('Enable browser alerts', 'Turn on browser notifications so new messages and order updates can reach you instantly while this tab is open or in the background.', '', 'Click this card to allow alerts');
            const latestToast = ensureToastContainer().lastElementChild;
            if (latestToast) {
                latestToast.style.cursor = 'pointer';
                latestToast.addEventListener('click', function () {
                    Notification.requestPermission();
                }, { once: true });
            }
        }

        async function pollNotifications() {
            try {
                const response = await fetch('notifications_poll.php?last_id=' + encodeURIComponent(lastNotificationId), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;

                const data = await response.json();
                if (!data || data.success !== true) return;

                updateBadge('.msg-unread-badge', Number(data.unread_messages || 0));
                updateBadge('.notif-unread-badge', Number(data.unread_notifications || 0));

                const incoming = Array.isArray(data.notifications) ? data.notifications : [];

                if (!primed) {
                    incoming.forEach(function (item) {
                        lastNotificationId = Math.max(lastNotificationId, Number(item.id || 0));
                    });
                    primed = true;
                    return;
                }

                incoming.forEach(function (item) {
                    const id = Number(item.id || 0);
                    if (id > lastNotificationId) {
                        lastNotificationId = id;
                    }

                    const title = item.title || 'Campus Marketplace';
                    const message = item.message || '';
                    const linkUrl = item.link_url || '';

                    if (document.hidden) {
                        notifyBrowser(title, message, linkUrl);
                    } else {
                        showToast(title, message, linkUrl, linkUrl ? 'Open now' : '');
                    }
                });
            } catch (error) {
                console.warn('Notification polling failed', error);
            }
        }

        promptForBrowserAlerts();
        pollNotifications();
        setInterval(pollNotifications, 20000);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
