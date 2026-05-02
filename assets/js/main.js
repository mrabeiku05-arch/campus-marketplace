document.addEventListener('DOMContentLoaded', () => {
    // Dynamic base path detection — works in any folder
    const _base = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/marketplace');
        if (idx !== -1) return p.substring(0, idx) + '/marketplace/';
        // fallback: use the directory of the current page
        return p.substring(0, p.lastIndexOf('/') + 1);
    })();

    const resolveAttachmentUrl = (attachmentUrl) => {
        if (!attachmentUrl) return '';
        if (/^https?:\/\//i.test(attachmentUrl) || attachmentUrl.startsWith('//')) {
            return attachmentUrl;
        }
        const clean = attachmentUrl.replace(/^\.?\//, '');
        if (clean.startsWith('uploads/')) {
            return _base + clean;
        }
        return _base + 'uploads/' + clean;
    };
    // ── CART SYSTEM (localStorage) ──
    window.cmCart = {
        get() {
            try { return JSON.parse(localStorage.getItem('cm_cart') || '[]'); } catch { return []; }
        },
        save(cart) {
            localStorage.setItem('cm_cart', JSON.stringify(cart));
            this.updateBadge();
        },
        add(id, name, price, image) {
            const cart = this.get();
            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.qty += 1;
            } else {
                cart.push({ id, name, price: parseFloat(price), image, qty: 1 });
            }
            this.save(cart);
            if(window.openSideCart) window.openSideCart();
            if(window.renderSideCart) window.renderSideCart();
        },
        remove(id) {
            const cart = this.get().filter(item => item.id !== id);
            this.save(cart);
        },
        updateQty(id, qty) {
            const cart = this.get();
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty = Math.max(1, qty);
                this.save(cart);
            }
        },
        count() {
            return this.get().reduce((sum, item) => sum + item.qty, 0);
        },
        total() {
            return this.get().reduce((sum, item) => sum + item.price * item.qty, 0);
        },
        clear() {
            this.save([]);
        },
        updateBadge() {
            const badges = document.querySelectorAll('.cart-count-badge');
            const count = this.count();
            badges.forEach(badge => {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            });
        },
        showToast(msg) {
            let toast = document.getElementById('cm-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'cm-toast';
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:rgba(16,185,129,0.95);color:#fff;padding:14px 24px;border-radius:12px;font-size:0.9rem;font-weight:600;z-index:10000;box-shadow:0 10px 40px rgba(0,0,0,0.4);transition:all 0.3s;opacity:0;transform:translateY(20px);';
                document.body.appendChild(toast);
            }
            toast.textContent = msg;
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
            }, 2500);
        }
    };

    // ── WISHLIST SYSTEM (localStorage) ──
    window.cmWishlist = {
        get() {
            try { return JSON.parse(localStorage.getItem('cm_wishlist') || '[]'); } catch { return []; }
        },
        save(list) {
            localStorage.setItem('cm_wishlist', JSON.stringify(list));
        },
        has(id) {
            return this.get().some(item => item.id === id);
        },
        toggle(id, name, price, image) {
            let list = this.get();
            const idx = list.findIndex(item => item.id === id);
            if (idx > -1) {
                list.splice(idx, 1);
                this.save(list);
                this.updateHearts(id, false);
                cmCart.showToast(`"${name}" removed from wishlist`);
            } else {
                list.push({ id, name, price: parseFloat(price), image });
                this.save(list);
                this.updateHearts(id, true);
                cmCart.showToast(`"${name}" added to wishlist`);
            }
        },
        updateHearts(id, active) {
            document.querySelectorAll(`[data-wishlist-id="${id}"]`).forEach(btn => {
                btn.classList.toggle('wishlist-active', active);
                btn.innerHTML = active ? 'Saved to Wishlist' : 'Add to Wishlist';
            });
        },
        count() {
            return this.get().length;
        }
    };

    // Initialize cart badge on page load
    cmCart.updateBadge();

    // ── CHAT SYSTEM ──
    const chatContainer = document.getElementById('chatMessages');
    if (chatContainer) {
        const otherUserId = chatContainer.dataset.user;
        const input = document.getElementById('msgInput');

        let lastProcessedId = 0;
        const fetchMessages = async () => {
            try {
                const res = await fetch(`${_base}api/chat.php?action=get&user=${otherUserId}`);
                const msgs = await res.json();
                
                if (!Array.isArray(msgs)) return;

                let addedNew = false;
                msgs.forEach(m => {
                    if (parseInt(m.id) <= lastProcessedId) {
                        // Update status if it's our message and status changed
                        if (m.sender_id != otherUserId) {
                             const existingMsg = document.querySelector(`[data-msg-id="${m.id}"] .msg-status`);
                             if (existingMsg) {
                                 const status = m.delivery_status || 'sent';
                                 let icon = '✓';
                                 if (status === 'delivered') icon = '✓✓';
                                 if (status === 'seen') icon = '<span style="color:#34a853;">✓✓</span>';
                                 if (existingMsg.innerHTML !== icon) existingMsg.innerHTML = icon;
                             }
                        }
                        return;
                    }
                    lastProcessedId = parseInt(m.id);
                    addedNew = true;

                    const isSent = m.sender_id != otherUserId;
                    let statusHtml = '';
                    if (isSent) {
                        const status = m.delivery_status || 'sent';
                        let icon = '✓';
                        if (status === 'delivered') icon = '✓✓';
                        if (status === 'seen') icon = '<span style="color:#34a853;">✓✓</span>';
                        statusHtml = `<span class="msg-status" title="${status}">${icon}</span>`;
                    }
                    
                    let attachmentHtml = '';
                    if (m.attachment_url) {
                        const fileUrl = resolveAttachmentUrl(m.attachment_url);
                        const ext = m.attachment_url.split('.').pop().toLowerCase();
                        let type = m.message_type;
                        if (type === 'text' || !type) {
                            if (['jpg','jpeg','png','gif','webp'].includes(ext)) type = 'image';
                            else if (['mp4','webm','mov'].includes(ext)) type = 'video';
                            else if (['mp3','wav','m4a','ogg'].includes(ext)) type = 'audio';
                        }

                        if (type === 'image') {
                            attachmentHtml = `<img src="${fileUrl}" class="msg-attachment" onclick="window.open(this.src)" onerror="this.src='https://placehold.co/200x200?text=Error';">`;
                        } else if (type === 'video') {
                            attachmentHtml = `<video src="${fileUrl}" controls playsinline class="msg-attachment" style="background:#000;"></video>`;
                        } else if (type === 'audio') {
                            attachmentHtml = `<audio src="${fileUrl}" controls class="msg-attachment"></audio>`;
                        }
                    }
                    
                    const msgDiv = document.createElement('div');
                    msgDiv.className = `msg ${isSent ? 'sent' : 'recv'}`;
                    msgDiv.setAttribute('data-msg-id', m.id);
                    msgDiv.innerHTML = `
                        ${attachmentHtml}
                        <div class="msg-text">${escHtml(m.message || '')}</div>
                        <div class="msg-footer">
                            <span class="msg-time">${formatTime(m.created_at)}</span>
                            ${statusHtml}
                        </div>`;
                    chatContainer.appendChild(msgDiv);
                });
                
                if (addedNew) {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            } catch (e) { console.error(e); }
        };
        fetchMessages();
        const chatInterval = setInterval(fetchMessages, 2000);

        window.sendMessage = async () => {
            const chatInput = document.getElementById('msgInput');
            const fileInput = document.getElementById('msgFile');
            
            if (!chatInput) return;
            const txt = chatInput.value.trim();
            const file = fileInput ? fileInput.files[0] : null;
            
            // Check if there is anything to send
            if (!txt && !file) return;

            const formData = new FormData();
            formData.append('receiver', otherUserId);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) formData.append('csrf_token', csrfToken);
            
            // Explicitly allow empty text if there is a file
            if (txt) formData.append('message', txt);
            if (file) formData.append('attachment', file);

            try {
                // Show loading state on button if possible
                const sendBtn = chatInput.parentElement.querySelector('button.btn-primary');
                const originalBtnText = sendBtn ? sendBtn.textContent : 'Send';
                if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = '...'; }

                const res = await fetch(_base + 'api/chat.php?action=send', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = originalBtnText; }

                if (data.success) {
                    chatInput.value = '';
                    if (window.clearFile) window.clearFile(); // Reset file input and preview
                    fetchMessages();
                } else {
                    alert('Could not send message: ' + (data.error || 'Server error'));
                }
            } catch (e) { 
                console.error(e);
                alert('Connection error. Please check your internet or try again.');
            }
        };
        input.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

        // ── VOICE RECORDING ──
        let mediaRecorder;
        let audioChunks = [];
        let recordInterval;
        let recordStartTime;

        const recordBtn = document.getElementById('recordBtn');
        const recordingUI = document.getElementById('recordingUI');
        const recordTimer = document.getElementById('recordTimer');

        if (recordBtn) {
            recordBtn.onclick = async () => {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    
                    // MIME TYPE DETECTION
                    let mimeType = 'audio/webm';
                    let ext = 'webm';
                    if (!MediaRecorder.isTypeSupported(mimeType)) {
                        mimeType = 'audio/ogg';
                        ext = 'ogg';
                        if (!MediaRecorder.isTypeSupported(mimeType)) {
                            mimeType = 'audio/mp4';
                            ext = 'm4a';
                        }
                    }

                    mediaRecorder = new MediaRecorder(stream, { mimeType });
                    audioChunks = [];
                    
                    mediaRecorder.ondataavailable = event => {
                        audioChunks.push(event.data);
                    };

                    mediaRecorder.onstop = async () => {
                        const audioBlob = new Blob(audioChunks, { type: mimeType });
                        const file = new File([audioBlob], `voice_note_${Date.now()}.${ext}`, { type: mimeType });
                        
                        const formData = new FormData();
                        formData.append('receiver', otherUserId);
                        formData.append('attachment', file);
                        
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        if (csrfToken) formData.append('csrf_token', csrfToken);
                        
                        const res = await fetch(_base + 'api/chat.php?action=send', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data?.success) {
                            fetchMessages();
                        } else {
                            alert('Voice note failed to send: ' + (data?.error || 'Server error'));
                        }
                        stream.getTracks().forEach(track => track.stop());
                    };

                    mediaRecorder.start();
                    recordingUI.style.display = 'flex';
                    recordStartTime = Date.now();
                    recordInterval = setInterval(() => {
                        const seconds = Math.floor((Date.now() - recordStartTime) / 1000);
                        const mins = Math.floor(seconds / 60);
                        const secs = seconds % 60;
                        recordTimer.innerText = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
                    }, 1000);
                } catch (err) {
                    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                        alert('Microphone access is BLOCKED by your browser because this site is not using HTTPS. Secure connection is required for recordings on mobile.');
                    } else {
                        alert('Could not access microphone. Please check your browser permissions.');
                    }
                }
            };
        }

        window.stopAndSendRecording = () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                clearInterval(recordInterval);
                mediaRecorder.stop();
                recordingUI.style.display = 'none';
                recordTimer.innerText = '0:00';
            }
        };

        window.cancelRecording = () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.onstop = null; 
                clearInterval(recordInterval);
                mediaRecorder.stop();
                recordingUI.style.display = 'none';
                recordTimer.innerText = '0:00';
            }
        };
    }

    // ── GLOBAL NOTIFICATION POLLING ──
    const updateGlobalStats = async () => {
        try {
            const res = await fetch(_base + 'api/poll_notifications.php');
            const data = await res.json();
            
            // Update message badges
            const msgBadges = document.querySelectorAll('.msg-unread-badge');
            msgBadges.forEach(b => {
                b.textContent = data.unread_messages;
                b.style.display = data.unread_messages > 0 ? 'flex' : 'none';
            });
            
            // Update general notification badges
            const notifBadges = document.querySelectorAll('.notif-count-badge');
            notifBadges.forEach(b => {
                b.textContent = data.unread_notifications;
                b.style.display = data.unread_notifications > 0 ? 'flex' : 'none';
            });
            
        } catch(e) {}
    };
    if (document.querySelector('.is-logged-in')) {
        setInterval(updateGlobalStats, 5000);
        updateGlobalStats();
    }

    // ── PASSWORD STRENGTH ──
    const pwdInput = document.getElementById('regPassword');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    if (pwdInput && strengthFill) {
        pwdInput.addEventListener('input', () => {
            const val = pwdInput.value;
            let score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const pct = (score / 5) * 100;
            const colors = ['#ef4444', '#f59e0b', '#eab308', '#10b981', '#10b981'];
            const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
            strengthFill.style.width = pct + '%';
            strengthFill.style.background = colors[Math.min(score, 4)];
            if (strengthText) strengthText.textContent = val ? labels[Math.min(score, 4)] : '';
        });
    }

    // ── USERNAME / EMAIL AVAILABILITY ──
    const usernameInput = document.getElementById('regUsername');
    const emailInput = document.getElementById('regEmail');
    const usernameCheck = document.getElementById('usernameCheck');
    const emailCheck = document.getElementById('emailCheck');

    let debounceTimer;
    const checkAvail = (field, value, el) => {
        clearTimeout(debounceTimer);
        if (!value || value.length < 2) { el.textContent = ''; return; }
        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`${_base}api/chat.php?action=check_user&field=${field}&value=${encodeURIComponent(value)}`);
                const data = await res.json();
                el.textContent = data.taken ? '❌ Taken' : '✅ Available';
                el.style.color = data.taken ? '#ef4444' : '#10b981';
            } catch(e) {}
        }, 500);
    };
    if (usernameInput) usernameInput.addEventListener('input', () => checkAvail('username', usernameInput.value, usernameCheck));
    if (emailInput) emailInput.addEventListener('input', () => checkAvail('email', emailInput.value, emailCheck));
    // ── GLOBAL AUTO-CAPITALIZATION ──
    document.addEventListener('input', (e) => {
        const el = e.target;
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            const forbidden = ['password', 'email', 'username', 'login', 'id', 'identifier'];
            const name = (el.name || '').toLowerCase();
            const id = (el.id || '').toLowerCase();
            const type = (el.type || '').toLowerCase();
            const autocomplete = (el.getAttribute('autocomplete') || '').toLowerCase();

            // Skip sensitive or technical fields
            if (forbidden.some(f => name.includes(f) || id.includes(f) || type === f || autocomplete.includes(f))) return;

            if (el.value.length > 0) {
                const start = el.selectionStart;
                const end = el.selectionEnd;
                // Capitalize only the first character of the entire input
                const capitalized = el.value.charAt(0).toUpperCase() + el.value.slice(1);
                if (el.value !== capitalized) {
                    el.value = capitalized;
                    // Restore cursor position
                    el.setSelectionRange(start, end);
                }
            }
        }
    });
});

function escHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
