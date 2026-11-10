/*
  XI TKJ 1 Digital Hub — Main Interactive JavaScript
  Fitur:
    - Dark / Light Mode Toggle dengan persistensi localStorage
    - Progress bar navigasi & transisi halaman halus
    - Floating Mini Global Live Chat Drawer (dengan fitur Hapus & Live Sync)
    - Lightbox Zoom Gambar Resolusi Tinggi
    - Copy Link Share & Toast Notifikasi
    - Tab panel, Modal, Ripple, dan Form UX
*/
(function () {
    'use strict';

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    document.addEventListener('DOMContentLoaded', function () {
        initThemeToggle();
        initProgressBar();
        initMobileMenu();
        initPageTransition();
        initReveal();
        initTabs();
        initModals();
        initRipple();
        initFormUX();
        initTableTools();
        initToasts();
        initActiveNav();
        initCounterAnimation();
        initSmoothScroll();
        initLightbox();
        initShareButtons();
        initFloatingChat();
    });

    /* ---------------------------------------------------------
       Dark / Light Mode Toggle
    --------------------------------------------------------- */
    function initThemeToggle() {
        const btn = $('#themeToggle');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try {
                localStorage.setItem('vinstudio_theme', next);
            } catch (e) {}
        });
    }

    /* ---------------------------------------------------------
       Progress bar navigasi
    --------------------------------------------------------- */
    function initProgressBar() {
        const bar = document.createElement('div');
        bar.className = 'page-progress';
        document.body.appendChild(bar);

        window.addEventListener('beforeunload', function () {
            bar.classList.add('active');
            bar.classList.add('done');
        });
    }

    /* ---------------------------------------------------------
       Mobile menu
    --------------------------------------------------------- */
    function initMobileMenu() {
        const menuBtn = $('.mobile-menu-btn');
        const navUl = $('.main-nav ul');
        if (!menuBtn || !navUl) return;

        menuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            navUl.classList.toggle('active');
            menuBtn.classList.toggle('active');
        });

        document.addEventListener('click', function (e) {
            if (!navUl.contains(e.target) && !menuBtn.contains(e.target)) {
                navUl.classList.remove('active');
                menuBtn.classList.remove('active');
            }
        });
    }

    /* ---------------------------------------------------------
       Transisi perpindahan halaman
    --------------------------------------------------------- */
    function initPageTransition() {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('javascript:')) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.hasAttribute('data-no-transition')) return;

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;

            e.preventDefault();
            const bar = $('.page-progress');
            if (bar) {
                bar.classList.add('active');
                setTimeout(function () { bar.classList.add('done'); }, 240);
            }
            document.body.classList.add('is-leaving');
            setTimeout(function () { window.location.href = url.href; }, 170);
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                document.body.classList.remove('is-leaving');
                const bar = $('.page-progress');
                if (bar) bar.classList.remove('active', 'done');
            }
        });
    }

    /* ---------------------------------------------------------
       Muncul bertahap saat scroll
    --------------------------------------------------------- */
    function initReveal() {
        const items = $$('.reveal');
        if (!items.length) return;

        if (!('IntersectionObserver' in window)) {
            items.forEach(el => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, i) {
                if (entry.isIntersecting) {
                    setTimeout(function () {
                        entry.target.classList.add('is-visible');
                    }, i * 65);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });

        items.forEach(el => io.observe(el));
    }

    /* ---------------------------------------------------------
       Tab panel (tanpa reload)
    --------------------------------------------------------- */
    function initTabs() {
        const tabGroups = $$('[data-tabs]');
        tabGroups.forEach(function (group) {
            const buttons = $$('.tab-btn', group);
            const panels = $$('.tab-panel', group);
            if (!buttons.length) return;

            function activate(name, push) {
                buttons.forEach(function (b) {
                    const on = b.dataset.tab === name;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                panels.forEach(function (p) {
                    p.classList.toggle('active', p.dataset.panel === name);
                });
                if (push && window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    window.history.replaceState({}, '', url);
                }
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activate(btn.dataset.tab, true);
                    const panel = group.parentElement;
                    if (panel) {
                        const top = panel.getBoundingClientRect().top + window.scrollY - 90;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    }
                });
            });

            const params = new URLSearchParams(window.location.search);
            const wanted = params.get('tab');
            const initial = buttons.some(b => b.dataset.tab === wanted)
                ? wanted
                : (group.dataset.defaultTab || (buttons[0] && buttons[0].dataset.tab));
            if (initial) activate(initial, false);
        });
    }

    /* ---------------------------------------------------------
       Modal
    --------------------------------------------------------- */
    function initModals() {
        function openModal(id) {
            const m = $('#' + id);
            if (!m) return;
            m.classList.add('open');
            document.body.style.overflow = 'hidden';
            const first = m.querySelector('input, select, textarea');
            if (first) setTimeout(function () { first.focus(); }, 120);
        }
        function closeModal(m) {
            if (!m) return;
            m.classList.remove('open');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            const opener = e.target.closest('[data-modal-open]');
            if (opener) {
                e.preventDefault();
                openModal(opener.dataset.modalOpen);
                return;
            }
            const closer = e.target.closest('[data-modal-close]');
            if (closer) {
                e.preventDefault();
                closeModal(closer.closest('.modal'));
                return;
            }
            if (e.target.classList && e.target.classList.contains('modal')) {
                closeModal(e.target);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const open = $('.modal.open');
                if (open) closeModal(open);
            }
        });
    }

    /* ---------------------------------------------------------
       Ripple pada tombol
    --------------------------------------------------------- */
    function initRipple() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn');
            if (!btn || btn.disabled) return;
            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 620);
        });
    }

    /* ---------------------------------------------------------
       UX form: password, meter, penghitung karakter, loading
    --------------------------------------------------------- */
    function initFormUX() {
        $$('.pw-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                btn.textContent = isPass ? '\u{1F648}' : '\u{1F441}';
                btn.setAttribute('aria-label', isPass ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

        $$('[data-pw-meter]').forEach(function (input) {
            const meter = $('#' + input.dataset.pwMeter);
            const hint = input.dataset.pwHint ? $('#' + input.dataset.pwHint) : null;
            const labels = ['Sangat lemah', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];

            input.addEventListener('input', function () {
                const v = input.value;
                let score = 0;
                if (v.length >= 4) score++;
                if (v.length >= 8) score++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                score = Math.min(score, 4);
                if (!v) score = 0;

                if (meter) meter.className = 'pw-meter' + (v ? ' is-' + score : '');
                if (hint) hint.textContent = v ? 'Kekuatan: ' + labels[score] : '';
            });
        });

        $$('[data-counter]').forEach(function (input) {
            const out = $('#' + input.dataset.counter);
            if (!out) return;
            const max = input.getAttribute('maxlength');
            function update() {
                const len = input.value.length;
                out.textContent = max ? len + ' / ' + max : len + ' karakter';
            }
            input.addEventListener('input', update);
            update();
        });

        $$('form').forEach(function (form) {
            if (form.hasAttribute('data-no-loading')) return;
            form.addEventListener('submit', function (e) {
                const required = form.querySelectorAll('[required]');
                let valid = true;
                let firstBad = null;

                required.forEach(function (input) {
                    const empty = !input.value.trim();
                    input.style.borderColor = '';
                    if (empty) {
                        valid = false;
                        if (!firstBad) firstBad = input;
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    if (firstBad) {
                        firstBad.classList.add('shake');
                        firstBad.focus();
                        setTimeout(function () { firstBad.classList.remove('shake'); }, 600);
                    }
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !form.hasAttribute('data-no-loading')) {
                    submitBtn.classList.add('is-loading');
                    submitBtn.disabled = true;
                    setTimeout(function () {
                        submitBtn.classList.remove('is-loading');
                        submitBtn.disabled = false;
                    }, 8000);
                }
            });
        });
    }

    /* ---------------------------------------------------------
       Pencarian & filter tabel admin
    --------------------------------------------------------- */
    function initTableTools() {
        const search = $('[data-table-search]');
        const rows = $$('[data-table-row]');
        const empty = $('[data-table-empty]');
        const counter = $('[data-table-count]');

        function applyFilter() {
            const term = (search ? search.value : '').toLowerCase().trim();
            let shown = 0;

            rows.forEach(function (row) {
                const haystack = (row.dataset.search || row.textContent || '').toLowerCase();
                const match = !term || haystack.indexOf(term) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });

            if (counter) counter.textContent = shown + ' pengguna';
            if (empty) empty.style.display = shown === 0 ? '' : 'none';
        }

        if (search) {
            search.addEventListener('input', applyFilter);
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { search.value = ''; applyFilter(); }
            });
        }
        applyFilter();
    }

    /* ---------------------------------------------------------
       Toast dari elemen .alert & helper global showToast
    --------------------------------------------------------- */
    let toastWrap = null;
    function showToast(text, type) {
        if (!toastWrap) {
            toastWrap = $('.toast-wrap');
            if (!toastWrap) {
                toastWrap = document.createElement('div');
                toastWrap.className = 'toast-wrap';
                document.body.appendChild(toastWrap);
            }
        }
        const toast = document.createElement('div');
        toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
        toast.textContent = text;
        toastWrap.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('hide');
            setTimeout(function () { toast.remove(); }, 320);
        }, 4200);
    }
    window.showToast = showToast;

    function initToasts() {
        toastWrap = document.createElement('div');
        toastWrap.className = 'toast-wrap';
        document.body.appendChild(toastWrap);

        $$('.alert').forEach(function (alert) {
            showToast(alert.textContent.trim(), alert.classList.contains('alert-error') ? 'error' : 'success');
        });
    }

    /* ---------------------------------------------------------
       Tandai nav aktif
    --------------------------------------------------------- */
    function initActiveNav() {
        const path = window.location.pathname;
        $$('.main-nav a').forEach(function (link) {
            const href = link.getAttribute('href');
            if (href && (path.endsWith('/' + href) || path === href)) {
                link.classList.add('active');
            }
        });
    }

    /* ---------------------------------------------------------
       Animasi angka statistik
    --------------------------------------------------------- */
    function initCounterAnimation() {
        const nums = $$('[data-count]');
        if (!nums.length) return;
        nums.forEach(function (el) {
            el.classList.add('count-up');
            const target = parseFloat(el.dataset.count) || 0;
            const dur = 750;
            const start = performance.now();
            function step(now) {
                const p = Math.min((now - start) / dur, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased);
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = target;
            }
            requestAnimationFrame(step);
        });
    }

    /* ---------------------------------------------------------
       Smooth scroll untuk anchor
    --------------------------------------------------------- */
    function initSmoothScroll() {
        $$('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    /* ---------------------------------------------------------
       Lightbox Zoom Gambar Resolusi Tinggi
    --------------------------------------------------------- */
    function initLightbox() {
        const backdrop = $('#imageLightbox');
        const imgEl = $('#lightboxImage');
        const capEl = $('#lightboxCaption');
        const closeBtn = $('#lightboxCloseBtn');
        if (!backdrop || !imgEl) return;

        function openLightbox(src, alt) {
            imgEl.src = src;
            if (capEl) capEl.textContent = alt || '';
            backdrop.classList.add('open');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            backdrop.classList.remove('open');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        $$('.detail-hero img, [data-lightbox]').forEach(function (img) {
            img.style.cursor = 'zoom-in';
            img.title = 'Klik untuk memperbesar gambar';
            img.addEventListener('click', function () {
                openLightbox(img.src, img.alt);
            });
        });

        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeLightbox();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) {
                closeLightbox();
            }
        });
    }

    /* ---------------------------------------------------------
       Tombol Bagikan / Copy Link
    --------------------------------------------------------- */
    function initShareButtons() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-share-url]');
            if (!btn) return;
            e.preventDefault();
            const url = btn.getAttribute('data-share-url') || window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    showToast('Tautan berhasil disalin ke papan klip!', 'success');
                });
            } else {
                showToast('Tautan: ' + url, 'success');
            }
        });
    }

    /* ---------------------------------------------------------
       Floating Mini Global Live Chat Drawer
    --------------------------------------------------------- */
    function initFloatingChat() {
        const fab = $('#floatingChatFab');
        const panel = $('#floatingChatPanel');
        const closeBtn = $('#floatingChatClose');
        const body = $('#floatingChatBody');
        const form = $('#floatingChatForm');
        const input = $('#floatingChatInput');
        const onlineText = $('#floatingOnlineText');
        if (!fab || !panel || !body) return;

        let pollTimer = null;

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        async function loadMiniChat(scrollBottom) {
            try {
                const res = await fetch('chat-api.php?action=fetch&limit=25&_t=' + Date.now(), { cache: 'no-store' });
                const data = await res.json();
                if (!data || !data.ok) return;

                if (onlineText && data.online_count) {
                    onlineText.textContent = data.online_count + ' anggota online';
                }

                if (Array.isArray(data.messages) && data.messages.length > 0) {
                    body.innerHTML = data.messages.map(function (m) {
                        const delBtn = m.can_delete
                            ? `<button type="button" data-mini-del="${m.id}" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:0.72rem;" title="Hapus">🗑</button>`
                            : '';
                        return `
                            <div class="mini-msg-item ${m.is_mine ? 'is-mine' : ''}" data-mini-id="${m.id}">
                                <div class="mini-msg-top">
                                    <strong style="color:var(--dark);">${escapeHtml(m.display_name)}</strong>
                                    <span style="color:var(--muted);display:flex;align-items:center;gap:0.35rem;">
                                        ${escapeHtml(m.time_clock)} ${delBtn}
                                    </span>
                                </div>
                                <div style="color:var(--text-soft);word-break:break-word;">${escapeHtml(m.message)}</div>
                            </div>
                        `;
                    }).join('');
                    if (scrollBottom) body.scrollTop = body.scrollHeight;
                } else {
                    body.innerHTML = '<div class="admin-hint" style="text-align:center;padding:2rem 0.5rem;">Belum ada obrolan. Yuk sapa teman-teman!</div>';
                }
            } catch (e) {}
        }

        body.addEventListener('click', async function (e) {
            const delBtn = e.target.closest('[data-mini-del]');
            if (!delBtn) return;
            const id = delBtn.getAttribute('data-mini-del');
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            try {
                await fetch('chat-api.php', { method: 'POST', body: fd });
                await loadMiniChat(false);
            } catch (err) {}
        });

        fab.addEventListener('click', function () {
            const isOpen = panel.classList.toggle('open');
            panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            if (isOpen) {
                loadMiniChat(true);
                if (input) setTimeout(() => input.focus(), 100);
                if (!pollTimer) {
                    pollTimer = setInterval(() => loadMiniChat(false), 3000);
                }
            } else if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            });
        }

        if (form && input) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const msg = input.value.trim();
                if (!msg) return;
                input.value = '';
                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('message', msg);
                try {
                    await fetch('chat-api.php', { method: 'POST', body: fd });
                    await loadMiniChat(true);
                } catch (e) {}
            });
        }
    }
})();
