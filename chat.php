<?php
require_once __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$pageTitle   = 'Global Live Chat';
$currentPage = 'chat';
$user        = currentUser();

include __DIR__ . '/includes/header.php';
?>

<main class="chat-page-main">
    <div class="chat-layout page-anim">
        <section class="chat-room-card">
            <div class="chat-room-header">
                <div class="chat-room-title-wrap">
                    <div class="chat-live-badge">
                        <span class="live-dot"></span>
                        <span>LIVE GLOBAL</span>
                    </div>
                    <div>
                        <h1>Ruang Obrolan <?= htmlspecialchars(APP_NAME) ?></h1>
                        <p class="chat-subtitle">Diskusi real-time seluruh anggota &middot; <span id="chatTotalCounter">Memuat...</span></p>
                    </div>
                </div>
                <div class="chat-room-controls">
                    <button type="button" id="soundToggleBtn" class="btn btn-outline btn-sm" title="Suara Notifikasi">
                        <span id="soundIcon">Suara: ON</span>
                    </button>
                    <?php if ($user && $user['role'] === 'admin'): ?>
                    <button type="button" id="clearAllChatBtn" class="btn btn-outline btn-sm" style="border-color:rgba(244,63,94,0.45);color:#fda4af !important;" title="Bersihkan Semua Pesan">
                        <?= svgIcon('trash', 14) ?> <span class="hide-sm">Bersihkan</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" id="scrollBottomBtn" class="btn btn-ghost btn-sm" title="Gulir ke terbaru">
                        &darr; Terbaru
                    </button>
                </div>
            </div>

            <div class="chat-messages-viewport" id="chatViewport">
                <div class="chat-welcome-banner">
                    <span style="color:var(--primary-light);"><?= svgIcon('shield', 20) ?></span>
                    <div>
                        <strong style="color:var(--dark);display:block;">Selamat datang di Global Live Chat <?= htmlspecialchars(APP_NAME) ?></strong>
                        <span>Gunakan bahasa yang sopan, saling mendukung, dan diskusikan proyek atau kegiatan kelas secara positif.</span>
                    </div>
                </div>
                <div id="chatMessagesList" class="chat-messages-list">
                    <div class="empty-state" style="padding:2rem;">Memuat percakapan langsung...</div>
                </div>
            </div>

            <div id="chatReplyBanner" class="chat-reply-banner" style="display:none;">
                <div>
                    <span>Membalas <strong id="replyTargetUser" style="color:var(--dark);"></strong>:</span>
                    <span id="replyTargetText" style="color:var(--text-soft);margin-left:0.3rem;"></span>
                </div>
                <button type="button" id="cancelReplyBtn" class="chat-reply-close" title="Batal membalas">&times;</button>
            </div>

            <?php if ($user): ?>
            <div class="chat-composer-wrap">
                <div class="chat-emoji-bar">
                    <span class="emoji-label">Reaksi Cepat:</span>
                    <?php foreach (['👋', '🔥', '✨', '🚀', '👏', '❤️', '😂', '👍', '🎉', '💻'] as $emo): ?>
                        <button type="button" class="emoji-chip" data-emoji="<?= $emo ?>"><?= $emo ?></button>
                    <?php endforeach; ?>
                </div>
                <form id="chatSendForm" class="chat-composer" data-no-loading>
                    <input type="hidden" id="chatReplyToId" name="reply_to_id" value="">
                    <textarea id="chatInput" name="message" rows="1" maxlength="600" placeholder="Ketik pesan ke Global Chat... (Enter untuk kirim, Shift+Enter baris baru)" required></textarea>
                    <button type="submit" id="chatSendBtn" class="btn btn-primary">
                        <span>Kirim</span>
                        <?= svgIcon('arrow-right', 16) ?>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="chat-guest-bar">
                <div>
                    <strong style="color:var(--dark);display:block;">Ingin ikut berdiskusi secara langsung?</strong>
                    <span class="admin-hint">Masuk ke akun anggota untuk mengirim dan membalas pesan di ruang obrolan.</span>
                </div>
                <div style="display:flex;gap:0.55rem;">
                    <a href="login.php?r=chat.php" class="btn btn-primary btn-sm" data-no-transition>Masuk Akun</a>
                    <a href="register.php" class="btn btn-outline btn-sm" data-no-transition>Daftar</a>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <aside class="chat-sidebar-card">
            <div class="chat-sidebar-head">
                <h2><?= svgIcon('users', 16) ?> Anggota Komunitas</h2>
                <span class="role-badge btn-success" id="onlineCountBadge">1 Online</span>
            </div>
            <div class="chat-members-list" id="chatMembersList">
                <div class="admin-hint" style="text-align:center;padding:1.5rem 0;">Memuat daftar anggota...</div>
            </div>
        </aside>
    </div>
</main>

<script>
(function() {
    const messagesList    = document.getElementById('chatMessagesList');
    const viewport        = document.getElementById('chatViewport');
    const membersList     = document.getElementById('chatMembersList');
    const onlineBadge     = document.getElementById('onlineCountBadge');
    const totalCounter    = document.getElementById('chatTotalCounter');
    const sendForm        = document.getElementById('chatSendForm');
    const chatInput       = document.getElementById('chatInput');
    const replyBanner     = document.getElementById('chatReplyBanner');
    const replyToInput    = document.getElementById('chatReplyToId');
    const replyUserEl     = document.getElementById('replyTargetUser');
    const replyTextEl     = document.getElementById('replyTargetText');
    const cancelReplyBtn  = document.getElementById('cancelReplyBtn');
    const soundToggleBtn  = document.getElementById('soundToggleBtn');
    const soundIcon       = document.getElementById('soundIcon');
    const scrollBottomBtn = document.getElementById('scrollBottomBtn');
    const clearAllBtn     = document.getElementById('clearAllChatBtn');

    if (!messagesList) return;

    let lastMessageId = 0;
    let isFirstLoad   = true;
    let isFetching    = false;
    let soundEnabled  = localStorage.getItem('vinstudio_chat_sound') !== 'off';
    let sharedAudioCtx = null;

    function getToken() {
        return window.getCsrfToken ? window.getCsrfToken() : '';
    }

    function updateSoundUI() {
        if (soundIcon) soundIcon.textContent = soundEnabled ? 'Suara: ON' : 'Suara: OFF';
    }
    updateSoundUI();

    if (soundToggleBtn) {
        soundToggleBtn.addEventListener('click', function() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('vinstudio_chat_sound', soundEnabled ? 'on' : 'off');
            updateSoundUI();
        });
    }

    function playNotificationPop() {
        if (!soundEnabled) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!sharedAudioCtx) {
                sharedAudioCtx = new AudioCtx();
            }
            if (sharedAudioCtx.state === 'suspended') {
                sharedAudioCtx.resume().catch(() => {});
            }
            const osc = sharedAudioCtx.createOscillator();
            const gain = sharedAudioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(580, sharedAudioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(860, sharedAudioCtx.currentTime + 0.08);
            gain.gain.setValueAtTime(0.06, sharedAudioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, sharedAudioCtx.currentTime + 0.14);
            osc.connect(gain);
            gain.connect(sharedAudioCtx.destination);
            osc.start();
            osc.stop(sharedAudioCtx.currentTime + 0.15);
        } catch (e) {}
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function isNearBottom() {
        if (!viewport) return true;
        return (viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight) < 140;
    }

    function scrollToBottom(smooth) {
        if (!viewport) return;
        viewport.scrollTo({ top: viewport.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    if (scrollBottomBtn) {
        scrollBottomBtn.addEventListener('click', () => scrollToBottom(true));
    }

    function setReply(id, user, text) {
        if (!replyToInput || !replyBanner) return;
        replyToInput.value = id;
        replyUserEl.textContent = user;
        replyTextEl.textContent = text;
        replyBanner.style.display = 'flex';
        if (chatInput) chatInput.focus();
    }

    function clearReply() {
        if (!replyToInput || !replyBanner) return;
        replyToInput.value = '';
        replyBanner.style.display = 'none';
    }
    if (cancelReplyBtn) cancelReplyBtn.addEventListener('click', clearReply);

    function showEmptyIfNeeded() {
        const items = messagesList.querySelectorAll('.chat-msg-item');
        const empty = messagesList.querySelector('.empty-state');
        if (items.length === 0 && !empty) {
            messagesList.innerHTML = '<div class="empty-state" style="padding:2rem;">Belum ada obrolan. Mulailah menyapa teman-teman!</div>';
        } else if (items.length > 0 && empty) {
            empty.remove();
        }
    }

    function buildMessageElement(m) {
        const item = document.createElement('div');
        item.className = 'chat-msg-item' + (m.is_mine ? ' is-mine' : '');
        item.id = 'chat-msg-' + m.id;
        item.dataset.id = m.id;

        const roleBadge = m.role === 'admin'
            ? '<span class="role-badge role-admin" style="font-size:0.62rem;padding:0.1rem 0.42rem;">Admin</span>'
            : '';

        const replyHtml = m.reply_to_id && m.reply_user
            ? `<div class="chat-quoted-reply">
                   <strong style="font-size:0.72rem;">↩ ${escapeHtml(m.reply_user)}</strong>
                   <span>${escapeHtml(m.reply_text || '')}</span>
               </div>`
            : '';

        const deleteBtn = m.can_delete
            ? `<button type="button" class="chat-action-btn delete" data-del-id="${m.id}">Hapus</button>`
            : '';

        const replyBtn = <?= $user ? 'true' : 'false' ?>
            ? `<button type="button" class="chat-action-btn reply" data-reply-id="${m.id}" data-reply-user="${escapeHtml(m.display_name)}" data-reply-text="${escapeHtml(m.message)}">↩ Balas</button>`
            : '';

        const avatarHtml = m.avatar
            ? `<img src="uploads/${escapeHtml(m.avatar)}" class="user-avatar-img chat-msg-avatar" alt="">`
            : `<div class="user-avatar ${escapeHtml(m.avatar_class)} chat-msg-avatar">${escapeHtml(m.initials)}</div>`;

        item.innerHTML = `
            ${avatarHtml}
            <div class="chat-msg-bubble-wrap">
                <div class="chat-msg-meta">
                    <a href="profile.php?member=${m.user_id}" class="chat-msg-author">${escapeHtml(m.display_name)}</a>
                    ${roleBadge}
                    <span class="chat-msg-time">${escapeHtml(m.time_clock)} &middot; ${escapeHtml(m.time_ago)}</span>
                </div>
                <div class="chat-msg-bubble">
                    ${replyHtml}
                    <div class="chat-msg-text">${escapeHtml(m.message).replace(/\n/g, '<br>')}</div>
                </div>
                <div class="chat-msg-actions">
                    ${replyBtn}
                    ${deleteBtn}
                </div>
            </div>
        `;
        return item;
    }

    function renderMembers(members, onlineCount) {
        if (onlineBadge) onlineBadge.textContent = onlineCount + ' Online';
        if (!membersList || !Array.isArray(members)) return;
        membersList.innerHTML = members.map(function(u) {
            const av = u.avatar
                ? `<img src="uploads/${escapeHtml(u.avatar)}" class="user-avatar-img" style="width:34px;height:34px;" alt="">`
                : `<div class="user-avatar ${escapeHtml(u.avatar_class)}" style="width:34px;height:34px;font-size:0.76rem;">${escapeHtml(u.initials)}</div>`;
            return `
                <a href="profile.php?member=${u.id}" class="chat-member-row" style="text-decoration:none;color:inherit;">
                    ${av}
                    <div class="chat-member-info">
                        <div class="chat-member-name">
                            <span>${escapeHtml(u.display_name)}</span>
                            ${u.role === 'admin' ? '<span class="role-badge role-admin" style="font-size:0.58rem;padding:0.08rem 0.38rem;">Admin</span>' : ''}
                        </div>
                        <small>${u.online ? 'Sedang online' : escapeHtml(u.last_seen)}</small>
                    </div>
                    <span class="status-dot ${u.online ? 'online' : 'offline'}"></span>
                </a>
            `;
        }).join('');
    }

    let chatPollTimer = null;
    async function fetchMessages() {
        if (!document.body.contains(messagesList)) {
            if (chatPollTimer) clearInterval(chatPollTimer);
            return;
        }
        if (isFetching) return;
        isFetching = true;
        try {
            const res  = await fetch('chat-api.php?action=fetch&after_id=' + lastMessageId + '&_t=' + Date.now(), { cache: 'no-store' });
            const data = await res.json();
            if (!data || !data.ok) return;

            if (totalCounter) totalCounter.textContent = data.total_messages + ' pesan terkirim';
            renderMembers(data.members, data.online_count);

            const stick = isFirstLoad || isNearBottom();
            if (isFirstLoad) messagesList.innerHTML = '';

            if (Array.isArray(data.active_ids)) {
                const activeSet = new Set(data.active_ids.map(Number));
                messagesList.querySelectorAll('.chat-msg-item').forEach(el => {
                    if (!activeSet.has(Number(el.dataset.id))) el.remove();
                });
            }

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                let hasForeignNew = false;
                data.messages.forEach(function(m) {
                    if (m.id > lastMessageId) lastMessageId = m.id;
                    if (!document.getElementById('chat-msg-' + m.id)) {
                        messagesList.appendChild(buildMessageElement(m));
                        if (!isFirstLoad && !m.is_mine) hasForeignNew = true;
                    }
                });
                if (stick) scrollToBottom(!isFirstLoad);
                if (hasForeignNew) playNotificationPop();
            }

            showEmptyIfNeeded();
            isFirstLoad = false;
        } catch (err) {
        } finally {
            isFetching = false;
        }
    }

    messagesList.addEventListener('click', async function(e) {
        const replyBtn = e.target.closest('[data-reply-id]');
        if (replyBtn) {
            setReply(replyBtn.dataset.replyId, replyBtn.dataset.replyUser, replyBtn.dataset.replyText);
            return;
        }

        const delBtn = e.target.closest('[data-del-id]');
        if (delBtn) {
            const id  = delBtn.dataset.delId;
            const row = document.getElementById('chat-msg-' + id);
            if (row) row.style.opacity = '0.35';
            const token = getToken();
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', token);
            try {
                const res  = await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
                const data = await res.json();
                if (data && data.ok) {
                    if (row) row.remove();
                    showEmptyIfNeeded();
                    if (window.showToast) window.showToast('Pesan berhasil dihapus.', 'success');
                }
            } catch (e) {}
        }
    });

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async function() {
            if (!confirm('Kosongkan seluruh riwayat pesan di Global Live Chat?')) return;
            const token = getToken();
            const fd = new FormData();
            fd.append('action', 'clear_all');
            fd.append('csrf_token', token);
            try {
                const res  = await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
                const data = await res.json();
                if (data && data.ok) {
                    messagesList.innerHTML = '';
                    lastMessageId = 0;
                    showEmptyIfNeeded();
                    if (totalCounter) totalCounter.textContent = '0 pesan terkirim';
                    if (window.showToast) window.showToast('Ruang obrolan telah dibersihkan.', 'success');
                }
            } catch (e) {}
        });
    }

    document.querySelectorAll('.emoji-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            if (!chatInput) return;
            chatInput.value += chip.dataset.emoji;
            chatInput.focus();
        });
    });

    if (chatInput && sendForm) {
        chatInput.addEventListener('input', function() {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 110) + 'px';
        });

        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendForm.requestSubmit();
            }
        });

        sendForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const text = chatInput.value.trim();
            if (!text) return;

            const btn = document.getElementById('chatSendBtn');
            if (btn) btn.disabled = true;

            const savedReplyId   = replyToInput ? replyToInput.value : '';
            const savedReplyUser = replyUserEl ? replyUserEl.textContent : '';
            const savedReplyText = replyTextEl ? replyTextEl.textContent : '';

            const token = getToken();
            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('message', text);
            fd.append('csrf_token', token);
            if (savedReplyId) {
                fd.append('reply_to_id', savedReplyId);
            }

            chatInput.value = '';
            chatInput.style.height = 'auto';
            clearReply();

            try {
                const res  = await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
                const data = await res.json();
                if (data && data.ok && data.message) {
                    const m = data.message;
                    if (m.id > lastMessageId) lastMessageId = m.id;
                    const emptyEl = messagesList.querySelector('.empty-state');
                    if (emptyEl) emptyEl.remove();
                    if (!document.getElementById('chat-msg-' + m.id)) {
                        messagesList.appendChild(buildMessageElement(m));
                    }
                    scrollToBottom(true);
                } else {
                    chatInput.value = text;
                    if (savedReplyId) setReply(savedReplyId, savedReplyUser, savedReplyText);
                    if (window.showToast) window.showToast((data && data.error) ? data.error : 'Gagal mengirim pesan.', 'error');
                }
            } catch (err) {
                chatInput.value = text;
                if (savedReplyId) setReply(savedReplyId, savedReplyUser, savedReplyText);
                if (window.showToast) window.showToast('Gagal mengirim pesan.', 'error');
            } finally {
                if (btn) btn.disabled = false;
                chatInput.focus();
            }
        });
    }

    fetchMessages();
    chatPollTimer = setInterval(fetchMessages, 2500);
    window.__pjaxCleanupFns = window.__pjaxCleanupFns || [];
    window.__pjaxCleanupFns.push(function() {
        if (chatPollTimer) clearInterval(chatPollTimer);
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
