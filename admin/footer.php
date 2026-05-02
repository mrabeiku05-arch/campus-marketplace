    </div> <!-- Closes .container opened in header.php -->

    <footer style="margin-top: 5rem; padding: 2.5rem 0; border-top: 1px solid var(--border); background: var(--bg-main);">
        <div style="width: 96%; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">
            <div>
                &copy; <?= date('Y') ?> <span style="color: var(--text-main); font-weight: 700;">Campus Marketplace</span> Admin
            </div>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <span style="background: rgba(124, 58, 237, 0.1); color: #7c3aed; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem;">
                    v2.5.0-PRO
                </span>
                <span>System Status: <span style="color: #28a745;">● Online</span></span>
            </div>
        </div>
    </footer>

    <!-- Global Admin Scripts -->
    <script>
        // Auto-refresh logic or global notifications could be initialized here
        console.log("Admin Panel Loaded - Version 2.5.0");
    </script>
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

        function showToast(title, message, linkUrl) {
            const container = ensureToastContainer();
            const toast = document.createElement('div');
            toast.style.background = 'rgba(17,24,39,0.96)';
            toast.style.color = '#fff';
            toast.style.borderRadius = '18px';
            toast.style.padding = '14px 16px';
            toast.style.boxShadow = '0 18px 45px rgba(0,0,0,0.25)';
            toast.style.border = '1px solid rgba(255,255,255,0.08)';
            toast.style.background = 'rgba(0,0,0,0.85)';
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            if (linkUrl) {
                toast.style.cursor = 'pointer';
                toast.addEventListener('click', function () {
                    window.location.href = '../' + linkUrl.replace(/^\//, '');
                });
            }
            toast.innerHTML = '<div style="font-weight:800; margin-bottom:4px;">' + title + '</div>'
                + '<div style="font-size:0.9rem; line-height:1.45; color:rgba(255,255,255,0.82);">' + message + '</div>';
            container.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(8px)';
            }, 5000);
            setTimeout(function () {
                toast.remove();
            }, 5400);
        }

        async function pollNotifications() {
            try {
                const response = await fetch('../notifications_poll.php?last_id=' + encodeURIComponent(lastNotificationId), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || data.success !== true) return;

                const badge = document.getElementById('admin-notif-badge');
                if (badge) {
                    const count = Number(data.unread_notifications || 0);
                    badge.textContent = count;
                    badge.style.display = count > 0 ? '' : 'none';
                }

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
                    showToast(item.title || 'Campus Marketplace', item.message || '', item.link_url || '');
                });
            } catch (error) {
                console.warn('Admin notification polling failed', error);
            }
        }

        pollNotifications();
        setInterval(pollNotifications, 20000);
    })();
    </script>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- GLOBAL PROFILE PICTURE PREVIEW MODAL (WhatsApp-style)             -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="profilePreviewOverlay" style="
        display:none; position:fixed; inset:0; z-index:1000001;
        background:rgba(0,0,0,0.92);
        align-items:center; justify-content:center;
        opacity:0; transition:opacity 0.3s ease;
        cursor:zoom-out;
    ">
        <div id="profilePreviewInner" style="
            position:relative; display:flex; flex-direction:column;
            align-items:center; gap:1rem; padding:20px;
            transform:scale(0.92); transition:transform 0.35s cubic-bezier(0.19,1,0.22,1);
        ">
            <button onclick="closeProfilePreview()" aria-label="Close preview" style="
                position:absolute; top:-12px; right:-12px;
                background:rgba(255,255,255,0.12); border:none;
                width:42px; height:42px; border-radius:50%;
                cursor:pointer; font-size:1.4rem; color:#fff;
                display:flex; align-items:center; justify-content:center;
                transition:background 0.2s;
            " onmouseover="this.style.background='rgba(255,255,255,0.22)'"
               onmouseout="this.style.background='rgba(255,255,255,0.12)'">&times;</button>
            <img id="profilePreviewImg" src="" alt="Preview" style="
                max-width:min(500px, 88vw); max-height:72vh;
                border-radius:20px; object-fit:contain;
                box-shadow:0 32px 100px rgba(0,0,0,0.55);
                border:1px solid rgba(255,255,255,0.08);
                background:#111;
            ">
            <div id="profilePreviewCaption" style="
                color:rgba(255,255,255,0.85); font-weight:600;
                font-size:0.95rem; letter-spacing:-0.01em;
            "></div>
        </div>
    </div>

    <script>
    (function() {
        var overlay = document.getElementById('profilePreviewOverlay');
        var inner   = document.getElementById('profilePreviewInner');
        var img     = document.getElementById('profilePreviewImg');
        var caption = document.getElementById('profilePreviewCaption');

        window.openProfilePreview = function(src, title) {
            img.src = src;
            caption.textContent = title || '';
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
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

        document.addEventListener('click', function(e) {
            var target = e.target.closest('.profile-pic-previewable');
            if (target && target.tagName === 'IMG') {
                e.preventDefault();
                e.stopPropagation();
                openProfilePreview(target.src, target.alt || 'Profile Picture');
            }
        });

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeProfilePreview();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') {
                closeProfilePreview();
            }
        });
    })();
    </script>
</body>
</html>
