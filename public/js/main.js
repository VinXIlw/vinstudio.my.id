/*
  TKJ Profile Web App — JavaScript
  Interaktivitas untuk portal kelas TKJ:
    - Transisi perpindahan halaman (progress bar + fade out)
    - Tab panel (tanpa reload halaman)
    - Modal, toast, ripple pada tombol
    - Tampilkan/sembunyikan password + meter kekuatan
    - Penghitung karakter pada textarea
    - Pencarian & filter tabel admin (live)
    - Muncul bertahap saat scroll (reveal)
*/
(function () {
    'use strict';

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    document.addEventListener('DOMContentLoaded', function () {
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
    });

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
            if (!href || href.startsWith('#') || href.startsWith('mailto:')) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.hasAttribute('data-no-transition')) return;

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;

            e.preventDefault();
            const bar = $('.page-progress');
            if (bar) {
                bar.classList.add('active');
                setTimeout(function () { bar.classList.add('done'); }, 260);
            }
            document.body.classList.add('is-leaving');
            setTimeout(function () { window.location.href = url.href; }, 190);
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
                    }, i * 70);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

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

            // Tab awal dari query string (?tab=pengguna) atau default
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
        // Tampilkan / sembunyikan password
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

        // Meter kekuatan password
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

        // Penghitung karakter
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

        // Tombol jadi loading saat form dikirim
        $$('form').forEach(function (form) {
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
       Pencarian & filter tabel admin (live, tanpa reload)
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
       Toast dari elemen .alert
    --------------------------------------------------------- */
    function initToasts() {
        const wrap = document.createElement('div');
        wrap.className = 'toast-wrap';
        document.body.appendChild(wrap);

        $$('.alert').forEach(function (alert) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + (alert.classList.contains('alert-error') ? 'error' : 'success');
            toast.textContent = alert.textContent.trim();
            wrap.appendChild(toast);
            setTimeout(function () {
                toast.classList.add('hide');
                setTimeout(function () { toast.remove(); }, 320);
            }, 5200);
        });
    }

    /* ---------------------------------------------------------
       Tandai nav aktif
    --------------------------------------------------------- */
    function initActiveNav() {
        const path = window.location.pathname;
        $$('.main-nav a').forEach(function (link) {
            const href = link.getAttribute('href');
            if (href === path || (href !== '/' && path.indexOf(href) === 0)) {
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
            const dur = 700;
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
})();

