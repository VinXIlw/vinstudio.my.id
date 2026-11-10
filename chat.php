<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Global Live Chat';
$currentPage = 'chat';
$user = currentUser();

include __DIR__ . '/includes/header.php';
?>

<main class="chat-page-main">
    <div class="chat-layout page-anim">
        <!-- Area Utama Ruang Obrolan -->
        <section class="chat-room-card">
            <!-- Header Chat -->
            <div class="chat-room-header">
                <div class="chat-room-title-wrap">
                    <div class="chat-live-badge">
                        <span class="live-dot"></span>
                        <span>LIVE GLOBAL</span>
                    </div>
                    <div>
                        <h1>Ruang Obrolan Komunitas</h1>
                        <p class="chat-subtitle">Ngobrol real-time bersama seluruh anggota <?= APP_NAME ?> &middot; <span id="chatTotalCounter">Memuat...</span></p>
                    </div>
                </div>
                <div class="chat-room-controls">
                    <button type="button" id="soundToggleBtn" class="btn btn-outline btn-sm" title="Aktifkan/Matikan Suara Notifikasi">
                        <span id="soundIcon">🔔</span> <span class="hide-sm">Suara</span>
                    </button>
                    <button type="button" id="scrollBottomBtn" class="btn btn-ghost btn-sm" title="Gulir ke pesan terbaru">
                        &darr; Terbaru
                    </button>
                </div>
            </div>

            <!-- Daftar Pesan -->
            <div class="chat-messages-viewport" id="chatViewport">
                <div class="chat-welcome-banner">
                    <div class="chat-welcome-icon">💬</div>
                    <div>
                        <strong>Selamat datang di Global Live Chat <?= APP_NAME ?>!</strong>
                        <p>Jaga kesopanan, saling menghargai, dan bagikan ide atau obrolan seru bersama teman-teman komunitas.</p>
                    </div>
                </div>
                <div id="chatMessagesList" class="chat-messages-list">
                    <div class="chat-loading-state">Memuat obrolan langsung...</div>
                </div>
            </div>

            <!-- Preview Balasan (Reply Banner) -->
            <div id="chatReplyBanner" class="chat-reply-banner" style="display:none;">
                <div class="chat-reply-info">
                    <span class="reply-badge">Membalas <strong id="replyTargetUser"></strong>:</span>
                    <span id="replyTargetText" class="reply-snippet"></span>
                </div>
                <button type="button" id="cancelReplyBtn" class="chat-reply-close" title="Batal membalas">&times;</button>
            </div>

            <!-- Bar Emoji Cepat & Input Pesan -->
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
                    <textarea id="chatInput" name="message" rows="1" maxlength="600" placeholder="Ketik pesan ke Global Chat... (Tekan Enter untuk kirim, Shift+Enter baris baru)" required></textarea>
                    <button type="submit" id="chatSendBtn" class="btn btn-primary chat-send-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        <span>Kirim</span>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="chat-guest-bar">
                <div>
                    <strong>Ingin ikut mengobrol secara langsung?</strong>
                    <p>Masuk atau daftar akun gratis untuk mengirim pesan di Global Live Chat.</p>
                </div>
                <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                    <a href="login.php?r=chat.php" class="btn btn-primary btn-sm">Masuk Akun</a>
                    <a href="register.php" class="btn btn-outline btn-sm">Daftar Baru</a>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Sidebar Anggota Aktif -->
        <aside class="chat-sidebar-card">
            <div class="chat-sidebar-head">
                <h2>
                    <span class="status-dot online"></span>
                    Anggota Komunitas
                </h2>
                <span class="online-pill" id="onlineCountBadge">1 Online</span>
            </div>
            <p class="admin-hint" style="margin-bottom:1rem;">Daftar anggota terdaftar dan status keaktifan langsung.</p>
            <div id="chatMembersList" class="chat-members-list">
                <!-- Diisi via AJAX -->
            </div>
        </aside>
    </div>
</main>

<script>
(function() {
    const messagesList = document.getElementById('chatMessagesList');
    const viewport = document.getElementById('chatViewport');
    const membersList = document.getElementById('chatMembersList');
    const onlineBadge = document.getElementById('onlineCountBadge');
    const totalCounter = document.getElementById('chatTotalCounter');
    const sendForm = document.getElementById('chatSendForm');
    const chatInput = document.getElementById('chatInput');
    const replyBanner = document.getElementById('chatReplyBanner');
    const replyToInput = document.getElementById('chatReplyToId');
    const replyUserEl = document.getElementById('replyTargetUser');
    const replyTextEl = document.getElementById('replyTargetText');
    const cancelReplyBtn = document.getElementById('cancelReplyBtn');
    const soundToggleBtn = document.getElementById('soundToggleBtn');
    const soundIcon = document.getElementById('soundIcon');
    const scrollBottomBtn = document.getElementById('scrollBottomBtn');

    let lastMessageId = 0;
    let isFirstLoad = true;
    let soundEnabled = localStorage.getItem('vinstudio_chat_sound') !== 'off';
    let isFetching = false;

    function updateSoundUI() {
        if (!soundIcon) return;
        soundIcon.textContent = soundEnabled ? '🔔' : '🔕';
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
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.08); // A5
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.14);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {}
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function isNearBottom() {
        if (!viewport) return true;
        return (viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight) < 140;
    }

    function scrollToBottom(smooth) {
        if (!viewport) return;
        viewport.scrollTo({
            top: viewport.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }

    if (scrollBottomBtn) {
        scrollBottomBtn.addEventListener('click', function() {
            scrollToBottom(true);
        });
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

    if (cancelReplyBtn) {
        cancelReplyBtn.addEventListener('click', clearReply);
    }

    function buildMessageElement(m) {
        const item = document.createElement('div');
        item.className = 'chat-msg-item' + (m.is_mine ? ' is-mine' : '');
        item.id = 'chat-msg-' + m.id;
        item.dataset.id = m.id;

        const roleBadge = m.role === 'admin'
            ? '<span class="role-badge role-admin" style="font-size:0.63rem;padding:0.12rem 0.45rem;">Admin</span>'
            : '';

        const replyHtml = m.reply_to_id && m.reply_user
            ? `<div class="chat-quoted-reply">
                   <span class="quote-author">↩ ${escapeHtml(m.reply_user)}</span>
                   <span class="quote-text">${escapeHtml(m.reply_text || '')}</span>
               </div>`
            : '';

        const deleteBtn = m.can_delete
            ? `<button type="button" class="chat-action-btn delete" data-del-id="${m.id}" title="Hapus pesan">Hapus</button>`
            : '';

        const replyBtn = <?= $user ? 'true' : 'false' ?>
            ? `<button type="button" class="chat-action-btn reply" data-reply-id="${m.id}" data-reply-user="${escapeHtml(m.display_name)}" data-reply-text="${escapeHtml(m.message)}" title="Balas pesan">Balas</button>`
            : '';

        item.innerHTML = `
            <div class="user-avatar ${escapeHtml(m.avatar_class)} chat-msg-avatar">${escapeHtml(m.initials)}</div>
            <div class="chat-msg-bubble-wrap">
                <div class="chat-msg-meta">
                    <strong class="chat-msg-author">${escapeHtml(m.display_name)}</strong>
                    ${roleBadge}
                    <span class="chat-msg-time" title="${escapeHtml(m.created_at)}">${escapeHtml(m.time_clock)} &middot; ${escapeHtml(m.time_ago)}</span>
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
        if (!membersList || ! Array.isArray(members)) return;
        membersList.innerHTML = members.map(function(u) {
            return `
                <div class="chat-member-row">
                    <div class="user-avatar ${escapeHtml(u.avatar_class)}" style="width:36px;height:36px;font-size:0.8rem;">
                        ${escapeHtml(u.initials)}
                    </div>
                    <div class="chat-member-info">
                        <div class="chat-member-name">
                            <span>${escapeHtml(u.display_name)}</span>
                            ${u.role === 'admin' ? '<span class="role-badge role-admin" style="font-size:0.6rem;padding:0.1rem 0.4rem;">Admin</span>' : ''}
                        </div>
                        <small>${u.online ? 'Sedang aktif' : escapeHtml(u.last_seen)}</small>
                    </div>
                    <span class="status-dot ${u.online ? 'online' : 'offline'}" title="${u.online ? 'Online' : 'Offline'}"></span>
                </div>
            `;
        }).join('');
    }

    async function fetchMessages() {
        if (isFetching) return;
        isFetching = true;
        try {
            const url = 'chat-api.php?action=fetch&after_id=' + lastMessageId;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data || !data.ok) return;

            if (totalCounter) {
                totalCounter.textContent = data.total_messages + ' pesan terkirim';
            }
            renderMembers(data.members, data.online_count);

            const stickToBottom = isFirstLoad || isNearBottom();

            if (isFirstLoad) {
                messagesList.innerHTML = '';
            }

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                let hasForeignNew = false;
                data.messages.forEach(function(m) {
                    if (m.id > lastMessageId) {
                        lastMessageId = m.id;
                    }
                    if (!document.getElementById('chat-msg-' + m.id)) {
                        messagesList.appendChild(buildMessageElement(m));
                        if (!isFirstLoad && !m.is_mine) {
                            hasForeignNew = true;
                        }
                    }
                });

                if (stickToBottom) {
                    scrollToBottom(!isFirstLoad);
                }
                if (hasForeignNew) {
                    playNotificationPop();
                }
            } else if (isFirstLoad && lastMessageId === 0) {
                messagesList.innerHTML = '<div class="empty-state" style="padding:2rem;">Belum ada obrolan. Mulailah menyapa teman-teman!</div>';
            }

            isFirstLoad = false;
        } catch (err) {
            // Abaikan gangguan jaringan sesaat
        } finally {
            isFetching = false;
        }
    }

    // Delegasi tombol Reply dan Delete pada pesan
    if (messagesList) {
        messagesList.addEventListener('click', async function(e) {
            const replyBtn = e.target.closest('[data-reply-id]');
            if (replyBtn) {
                setReply(
                    replyBtn.getAttribute('data-reply-id'),
                    replyBtn.getAttribute('data-reply-user'),
                    replyBtn.getAttribute('data-reply-text')
                );
                return;
            }

            const delBtn = e.target.closest('[data-del-id]');
            if (delBtn) {
                const id = delBtn.getAttribute('data-del-id');
                if (!confirm('Hapus pesan ini dari Global Chat?')) return;
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);
                try {
                    const res = await fetch('chat-api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        const row = document.getElementById('chat-msg-' + id);
                        if (row) row.remove();
                    }
                } catch (e) {}
            }
        });
    }

    // Emoji cepat
    document.querySelectorAll('.emoji-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            if (!chatInput) return;
            chatInput.value += chip.getAttribute('data-emoji');
            chatInput.focus();
        });
    });

    // Kirim pesan dengan Enter (Shift+Enter untuk baris baru)
    if (chatInput && sendForm) {
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

            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('message', text);
            if (replyToInput && replyToInput.value) {
                fd.append('reply_to_id', replyToInput.value);
            }

            chatInput.value = '';
            clearReply();

            try {
                const res = await fetch('chat-api.php', { method: 'POST', body: fd });
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
                } else if (data && data.error) {
                    alert(data.error);
                }
            } catch (err) {
                alert('Gagal mengirim pesan. Periksa koneksi internet kamu.');
            } finally {
                if (btn) btn.disabled = false;
                chatInput.focus();
            }
        });
    }

    fetchMessages();
    setInterval(fetchMessages, 2500);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
