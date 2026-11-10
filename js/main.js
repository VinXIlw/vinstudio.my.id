/*
  XI TKJ 1 Digital Hub — Main Interactive JavaScript (v5.0 Instant PJAX & Smooth Glider)
  Fitur:
    - Continuous Sliding Button Glider (Navbar, Sort Pills Terbaru/Terpopuler, & Tab)
    - Instant PJAX / Turbo Navigation + Hover & Warm Prefetch (pindah halaman instan 0ms delay)
    - Directional Slide Page Transition (geser kanan/kiri sesuai urutan tombol)
    - Dark / Light Mode Toggle dengan persistensi localStorage
    - Floating Mini Global Live Chat Drawer (dengan fitur Hapus & Live Sync)
    - Lightbox Zoom Gambar Resolusi Tinggi
    - Copy Link Share & Toast Notifikasi
*/
(function () {
    'use strict';

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    window.__pjaxCleanupFns = window.__pjaxCleanupFns || [];

    let syncNavToUrl = null;
    const pjaxCache = new Map();
    const PJAX_TTL = 25000; // 25 detik cache prefetch

    window.clearPjaxCache = function () {
        pjaxCache.clear();
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Inisialisasi global sekali jalan (Header, Footer, Router, Delegasi Event)
        initThemeToggle();
        initProgressBar();
        initMobileMenu();
        initNavGlider();
        initPjaxRouter();
        initModals();
        initRipple();
        initLightbox();
        initShareButtons();
        initFloatingChat();

        // Inisialisasi elemen konten di dalam halaman aktif (#pjaxStage)
        initPageContent(document);
    });

    function initPageContent(ctx) {
        initReveal(ctx);
        initTabs(ctx);
        initSortGlider(ctx);
        initFormUX(ctx);
        initTableTools(ctx);
        initToasts(ctx);
        initCounterAnimation(ctx);
        initSmoothScroll(ctx);
    }

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
        if ($('.page-progress')) return;
        const bar = document.createElement('div');
        bar.className = 'page-progress';
        document.body.appendChild(bar);
    }

    function startProgress() {
        const bar = $('.page-progress');
        if (!bar) return;
        bar.classList.remove('done');
        bar.classList.add('active');
    }

    function finishProgress() {
        const bar = $('.page-progress');
        if (!bar) return;
        bar.classList.add('active', 'done');
        setTimeout(function () {
            bar.classList.remove('active', 'done');
        }, 280);
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
       Continuous Sliding Button Glider di Navbar
       (Tidak pernah hilang/reset, langsung geser mulus ke sebelah)
    --------------------------------------------------------- */
    function initNavGlider() {
        const navUl = $('.main-nav ul');
        if (!navUl) return;

        const links = $$('li > a:not(.btn-logout)', navUl);
        if (!links.length) return;

        // Buat Hover Glider (bayangan kapsul halus saat kursor melintas)
        const hoverGlider = document.createElement('span');
        hoverGlider.className = 'nav-hover-glider';
        navUl.appendChild(hoverGlider);

        // Buat Active Glider (kapsul gradien utama yang bergeser antar tombol)
        const glider = document.createElement('span');
        glider.className = 'nav-glider';
        navUl.appendChild(glider);

        let movingTimer = null;

        function getMetrics(el) {
            return {
                x: el.offsetLeft,
                y: el.offsetTop,
                w: el.offsetWidth,
                h: el.offsetHeight
            };
        }

        function applyGlider(el, animate) {
            if (!el || window.innerWidth <= 768) {
                glider.style.opacity = '0';
                return;
            }
            const m = getMetrics(el);
            if (m.w === 0) return;

            if (!animate) {
                glider.style.transition = 'none';
            } else {
                glider.style.transition = '';
                glider.classList.add('is-moving');
                if (movingTimer) clearTimeout(movingTimer);
                movingTimer = setTimeout(function () {
                    glider.classList.remove('is-moving');
                }, 440);
            }

            glider.classList.toggle('is-chat', el.classList.contains('nav-chat-link'));
            glider.style.width = m.w + 'px';
            glider.style.height = m.h + 'px';
            glider.style.transform = `translate3d(${m.x}px, ${m.y}px, 0)`;
            glider.style.opacity = '1';

            if (!animate) {
                void glider.offsetWidth;
                glider.style.transition = '';
            }
        }

        // Hover glider saat mouse diarahkan ke tombol navbar
        links.forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                if (window.innerWidth <= 768) return;
                const m = getMetrics(link);
                hoverGlider.style.width = m.w + 'px';
                hoverGlider.style.height = m.h + 'px';
                hoverGlider.style.transform = `translate3d(${m.x}px, ${m.y}px, 0)`;
                hoverGlider.style.opacity = '1';
            });
        });
        navUl.addEventListener('mouseleave', function () {
            hoverGlider.style.opacity = '0';
        });

        // Posisikan glider di tombol aktif saat pertama kali buka, baru aktifkan .has-glider
        let activeLink = links.find(l => l.classList.contains('active'));
        if (!activeLink) {
            activeLink = findMatchingNavLink(window.location.href, links);
            if (activeLink) activeLink.classList.add('active');
        }
        if (activeLink) {
            applyGlider(activeLink, false);
        }
        navUl.classList.add('has-glider');

        function findMatchingNavLink(urlStr, linkList) {
            try {
                const u = new URL(urlStr, window.location.origin);
                let file = u.pathname.split('/').pop() || 'index.php';
                if (file === '' || file === '/') file = 'index.php';

                // Pemetaan halaman detail/tambah ke menu induknya di navbar
                if (file === 'album-detail.php' || file === 'album-add.php') file = 'albums.php';
                if (file === 'story-detail.php' || file === 'story-add.php') file = 'stories.php';

                return linkList.find(function (a) {
                    const href = (a.getAttribute('href') || '').split('?')[0];
                    return href === file;
                }) || null;
            } catch (e) {
                return null;
            }
        }

        syncNavToUrl = function (urlStr, animate) {
            const prevActive = links.find(l => l.classList.contains('active'));
            const nextActive = findMatchingNavLink(urlStr, links);
            const prevIdx = prevActive ? links.indexOf(prevActive) : -1;
            const nextIdx = nextActive ? links.indexOf(nextActive) : -1;

            let dir = 'fade';
            if (prevIdx !== -1 && nextIdx !== -1 && prevIdx !== nextIdx) {
                dir = nextIdx > prevIdx ? 'next' : 'prev';
            }

            if (nextActive) {
                links.forEach(l => l.classList.toggle('active', l === nextActive));
                applyGlider(nextActive, animate !== false);
            }

            // Tutup menu mobile jika sedang terbuka
            const menuBtn = $('.mobile-menu-btn');
            if (navUl.classList.contains('active')) {
                navUl.classList.remove('active');
                if (menuBtn) menuBtn.classList.remove('active');
            }

            return { prevIdx, nextIdx, dir };
        };

        window.addEventListener('resize', function () {
            const cur = links.find(l => l.classList.contains('active'));
            if (cur) applyGlider(cur, false);
        });
    }

    /* ---------------------------------------------------------
       Sliding Glider untuk Sort Filter (Terbaru / Terpopuler)
       di halaman Album & Cerita — Instan 0ms tanpa reload!
    --------------------------------------------------------- */
    function initSortGlider(ctx) {
        const groups = $$('[data-sort-pills]', ctx);
        groups.forEach(function (group) {
            if (group.dataset.gliderReady === '1') return;
            group.dataset.gliderReady = '1';

            const btns = $$('.sort-pill-btn', group);
            if (!btns.length) return;

            const glider = document.createElement('span');
            glider.className = 'sort-glider';
            group.appendChild(glider);

            function moveSortGlider(btn, animate) {
                if (!btn) return;
                if (!animate) glider.style.transition = 'none';
                else glider.style.transition = '';

                glider.style.width = btn.offsetWidth + 'px';
                glider.style.height = btn.offsetHeight + 'px';
                glider.style.transform = `translate3d(${btn.offsetLeft}px, ${btn.offsetTop}px, 0)`;
                glider.style.opacity = '1';

                if (!animate) {
                    void glider.offsetWidth;
                    glider.style.transition = '';
                }
            }

            const initialActive = btns.find(b => b.classList.contains('active')) || btns[0];
            if (initialActive) {
                moveSortGlider(initialActive, false);
            }
            group.classList.add('has-glider');

            btns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const sortMode = btn.dataset.sort || 'latest';
                    btns.forEach(b => b.classList.toggle('active', b === btn));
                    moveSortGlider(btn, true);

                    // Urutkan kartu secara instan di sisi klien (0ms!)
                    const gridSel = group.dataset.targetGrid;
                    const grid = gridSel ? $(gridSel) : null;
                    if (grid) {
                        const cards = Array.from(grid.children);
                        cards.sort(function (a, b) {
                            const likesA = parseInt(a.dataset.likes || '0', 10);
                            const likesB = parseInt(b.dataset.likes || '0', 10);
                            const timeA = parseInt(a.dataset.time || '0', 10);
                            const timeB = parseInt(b.dataset.time || '0', 10);
                            if (sortMode === 'popular') {
                                if (likesB !== likesA) return likesB - likesA;
                            }
                            return timeB - timeA;
                        });
                        cards.forEach(function (c, idx) {
                            c.style.animation = 'none';
                            void c.offsetWidth;
                            c.style.animation = `cardEntrance 0.36s cubic-bezier(0.22, 1, 0.36, 1) ${idx * 0.035}s both`;
                            grid.appendChild(c);
                        });
                    }

                    // Perbarui URL tanpa reload
                    if (window.history && window.history.replaceState) {
                        const u = new URL(btn.href, window.location.origin);
                        window.history.replaceState({ url: u.href }, '', u.href);
                    }
                });
            });

            window.addEventListener('resize', function () {
                const cur = btns.find(b => b.classList.contains('active'));
                if (cur) moveSortGlider(cur, false);
            });
        });
    }

    /* ---------------------------------------------------------
       Instant PJAX / Turbo Router + Hover & Warm Prefetch
       (Pindah halaman instan tanpa reload Header/Footer)
    --------------------------------------------------------- */
    function isPjaxEligibleUrl(urlObj) {
        if (!urlObj || urlObj.origin !== window.location.origin) return false;
        let file = urlObj.pathname.split('/').pop() || 'index.php';
        if (file === '' || file === '/') file = 'index.php';

        // Halaman autentikasi layar penuh atau aksi khusus menggunakan navigasi langsung
        const bypassFiles = ['login.php', 'register.php', 'logout.php', 'chat-api.php', 'like.php', 'comment.php', 'album-delete.php', 'story-delete.php'];
        if (bypassFiles.includes(file)) return false;
        if (urlObj.searchParams.has('del')) return false;
        return file.endsWith('.php') || file === 'index.php';
    }

    async function fetchPageHtml(urlStr, forceNetwork) {
        const now = Date.now();
        if (!forceNetwork && pjaxCache.has(urlStr)) {
            const cached = pjaxCache.get(urlStr);
            if (now - cached.time < PJAX_TTL) {
                return cached.html;
            }
        }

        const res = await fetch(urlStr, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-PJAX': 'true', 'Accept': 'text/html' }
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        // Jika diredirect ke login.php, batalkan PJAX dan arahkan penuh
        if (res.redirected && res.url.includes('login.php')) {
            window.location.href = res.url;
            return null;
        }
        const html = await res.text();
        pjaxCache.set(urlStr, { html: html, time: Date.now() });
        return html;
    }

    function prefetchUrl(urlStr) {
        try {
            const u = new URL(urlStr, window.location.origin);
            if (!isPjaxEligibleUrl(u)) return;
            // Jangan cache chat.php terlalu lama, tapi boleh diprefetch saat hover
            const now = Date.now();
            if (pjaxCache.has(u.href) && (now - pjaxCache.get(u.href).time < PJAX_TTL)) {
                return;
            }
            fetchPageHtml(u.href, false).catch(function () {});
        } catch (e) {}
    }

    function runCleanupFns() {
        if (Array.isArray(window.__pjaxCleanupFns)) {
            while (window.__pjaxCleanupFns.length > 0) {
                const fn = window.__pjaxCleanupFns.pop();
                try {
                    if (typeof fn === 'function') fn();
                } catch (e) {}
            }
        }
    }

    function initPjaxRouter() {
        const stage = $('#pjaxStage');
        if (!stage) return;

        let activeNavRequest = 0;

        // 1. Warm Prefetch otomatis halaman utama di latar belakang agar klik pertama 0ms!
        setTimeout(function () {
            ['index.php', 'profile.php', 'albums.php', 'stories.php', 'chat.php'].forEach(function (page) {
                const u = new URL(page, window.location.href);
                if (u.href !== window.location.href) {
                    prefetchUrl(u.href);
                }
            });
        }, 300);

        // 2. Prefetch instan saat kursor menyentuh (hover / touchstart) tautan apa pun
        document.addEventListener('mouseover', function (e) {
            const link = e.target.closest('a');
            if (!link || !link.href) return;
            if (link.hasAttribute('data-no-transition') || link.hasAttribute('data-sort')) return;
            prefetchUrl(link.href);
        }, { passive: true });

        document.addEventListener('touchstart', function (e) {
            const link = e.target.closest('a');
            if (!link || !link.href) return;
            if (link.hasAttribute('data-no-transition') || link.hasAttribute('data-sort')) return;
            prefetchUrl(link.href);
        }, { passive: true });

        // 3. Tangani klik navigasi secara instan
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('javascript:')) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.hasAttribute('data-no-transition') || link.hasAttribute('data-sort')) return;

            const url = new URL(link.href, window.location.href);
            if (!isPjaxEligibleUrl(url)) return;

            // Jika klik halaman yang persis sama, cukup geser glider & scroll ke atas
            if (url.href === window.location.href) {
                e.preventDefault();
                if (syncNavToUrl) syncNavToUrl(url.href, true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            e.preventDefault();
            navigateTo(url.href, true);
        });

        // 4. Tangani tombol Back / Forward browser
        window.addEventListener('popstate', function () {
            if (isPjaxEligibleUrl(new URL(window.location.href))) {
                navigateTo(window.location.href, false);
            }
        });

        async function navigateTo(targetUrl, pushState) {
            const reqId = ++activeNavRequest;

            // Langsung geser tombol navbar ke sebelah tanpa menunggu jaringan!
            let slideDir = 'fade';
            if (syncNavToUrl) {
                const navInfo = syncNavToUrl(targetUrl, true);
                if (navInfo && navInfo.dir) slideDir = navInfo.dir;
            }

            // Tutup panel mini chat jika sedang terbuka saat pindah ke chat.php
            const floatingPanel = $('#floatingChatPanel');
            if (floatingPanel && floatingPanel.classList.contains('open')) {
                floatingPanel.classList.remove('open');
                floatingPanel.setAttribute('aria-hidden', 'true');
            }

            const isCached = pjaxCache.has(targetUrl) && (Date.now() - pjaxCache.get(targetUrl).time < PJAX_TTL);
            if (!isCached) {
                startProgress();
            }

            try {
                const isChatTarget = targetUrl.includes('chat.php');
                const html = await fetchPageHtml(targetUrl, isChatTarget);
                if (!html || reqId !== activeNavRequest) return;

                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newStage = doc.getElementById('pjaxStage');

                if (!newStage) {
                    window.location.href = targetUrl;
                    return;
                }

                // Bersihkan listener/interval dari halaman sebelumnya
                runCleanupFns();

                // Perbarui judul dokumen & URL
                if (doc.title) document.title = doc.title;
                if (pushState && window.history && window.history.pushState) {
                    window.history.pushState({ url: targetUrl }, '', targetUrl);
                }

                // Ganti isi #pjaxStage dan jalankan animasi slide directional
                const curStage = $('#pjaxStage');
                curStage.removeAttribute('data-slide-dir');
                const pageAttr = newStage.getAttribute('data-page') || '';
                curStage.setAttribute('data-page', pageAttr);
                curStage.innerHTML = newStage.innerHTML;

                // Paksa reflow ringan agar animasi masuk selalu terpicu mulus
                void curStage.offsetWidth;
                curStage.setAttribute('data-slide-dir', slideDir);

                // Tampilkan/sembunyikan tombol floating mini chat
                const floatingRoot = $('#floatingChatRoot');
                if (floatingRoot) {
                    floatingRoot.style.display = (pageAttr === 'chat' || isChatTarget) ? 'none' : '';
                }

                // Gulir ke atas dengan instan agar transisi halaman langsung terlihat
                window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });

                // Eksekusi inline <script> di dalam #pjaxStage baru
                const scripts = $$('script', curStage);
                scripts.forEach(function (oldScript) {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(function (attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // Inisialisasi ulang komponen interaktif pada halaman baru
                initPageContent(curStage);
                finishProgress();

                // Jika halaman berasal dari cache, segarkan cache di latar belakang (Stale-While-Revalidate)
                if (isCached && !isChatTarget) {
                    fetchPageHtml(targetUrl, true).catch(function () {});
                }
            } catch (err) {
                window.location.href = targetUrl;
            }
        }
    }

    /* ---------------------------------------------------------
       Muncul bertahap saat scroll
    --------------------------------------------------------- */
    function initReveal(ctx) {
        const items = $$('.reveal', ctx);
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
                    }, i * 45);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05, rootMargin: '0px 0px -10px 0px' });

        items.forEach(el => io.observe(el));
    }

    /* ---------------------------------------------------------
       Tab panel (tanpa reload + Sliding Tab Glider)
    --------------------------------------------------------- */
    function initTabs(ctx) {
        const tabGroups = $$('[data-tabs]', ctx);
        tabGroups.forEach(function (group) {
            if (group.dataset.tabsReady === '1') return;
            group.dataset.tabsReady = '1';

            const tabsNav = $('.tabs', group) || group;
            const buttons = $$('.tab-btn', group);
            const panels = $$('.tab-panel', group);
            if (!buttons.length) return;

            let tabGlider = null;
            if (tabsNav && tabsNav.classList.contains('tabs')) {
                tabGlider = document.createElement('span');
                tabGlider.className = 'tab-glider';
                tabsNav.appendChild(tabGlider);
            }

            function updateTabGlider(activeBtn, animate) {
                if (!tabGlider || !activeBtn) return;
                if (!animate) tabGlider.style.transition = 'none';
                else tabGlider.style.transition = '';
                tabGlider.style.width = activeBtn.offsetWidth + 'px';
                tabGlider.style.height = activeBtn.offsetHeight + 'px';
                tabGlider.style.transform = `translate3d(${activeBtn.offsetLeft}px, ${activeBtn.offsetTop}px, 0)`;
                tabGlider.style.opacity = '1';
                if (!animate) {
                    void tabGlider.offsetWidth;
                    tabGlider.style.transition = '';
                }
            }

            function activate(name, push, animate) {
                let activeBtn = null;
                buttons.forEach(function (b) {
                    const on = b.dataset.tab === name;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                    if (on) activeBtn = b;
                });
                panels.forEach(function (p) {
                    p.classList.toggle('active', p.dataset.panel === name);
                });
                if (activeBtn) {
                    updateTabGlider(activeBtn, animate);
                }
                if (push && window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    window.history.replaceState({ url: url.href }, '', url);
                }
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activate(btn.dataset.tab, true, true);
                });
            });

            const params = new URLSearchParams(window.location.search);
            const wanted = params.get('tab');
            const initial = buttons.some(b => b.dataset.tab === wanted)
                ? wanted
                : (group.dataset.defaultTab || (buttons[0] && buttons[0].dataset.tab));
            if (initial) activate(initial, false, false);
            if (tabsNav) tabsNav.classList.add('has-glider');

            window.addEventListener('resize', function () {
                const cur = buttons.find(b => b.classList.contains('active'));
                if (cur) updateTabGlider(cur, false);
            });
        });
    }

    /* ---------------------------------------------------------
       Modal (Event Delegation)
    --------------------------------------------------------- */
    function initModals() {
        function openModal(id) {
            const m = $('#' + id);
            if (!m) return;
            m.classList.add('open');
            document.body.style.overflow = 'hidden';
            const first = m.querySelector('input, select, textarea');
            if (first) setTimeout(function () { first.focus(); }, 100);
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
            setTimeout(function () { ripple.remove(); }, 600);
        });
    }

    /* ---------------------------------------------------------
       UX form: password, meter, penghitung karakter, loading
    --------------------------------------------------------- */
    function initFormUX(ctx) {
        $$('.pw-toggle', ctx).forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                btn.textContent = isPass ? '\u{1F648}' : '\u{1F441}';
                btn.setAttribute('aria-label', isPass ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

        $$('[data-pw-meter]', ctx).forEach(function (input) {
            if (input.dataset.bound === '1') return;
            input.dataset.bound = '1';
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

        $$('[data-counter]', ctx).forEach(function (input) {
            if (input.dataset.bound === '1') return;
            input.dataset.bound = '1';
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

        $$('form', ctx).forEach(function (form) {
            if (form.hasAttribute('data-no-loading') || form.dataset.bound === '1') return;
            form.dataset.bound = '1';
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

                // Bersihkan cache PJAX saat ada perubahan data form
                pjaxCache.clear();

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
    function initTableTools(ctx) {
        const search = $('[data-table-search]', ctx);
        const rows = $$('[data-table-row]', ctx);
        const empty = $('[data-table-empty]', ctx);
        const counter = $('[data-table-count]', ctx);
        if (!search && !rows.length) return;

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

        if (search && search.dataset.bound !== '1') {
            search.dataset.bound = '1';
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

    function initToasts(ctx) {
        if (!toastWrap) {
            toastWrap = $('.toast-wrap');
            if (!toastWrap) {
                toastWrap = document.createElement('div');
                toastWrap.className = 'toast-wrap';
                document.body.appendChild(toastWrap);
            }
        }

        $$('.alert', ctx).forEach(function (alert) {
            if (alert.dataset.toasted === '1') return;
            alert.dataset.toasted = '1';
            showToast(alert.textContent.trim(), alert.classList.contains('alert-error') ? 'error' : 'success');
        });
    }

    /* ---------------------------------------------------------
       Animasi angka statistik
    --------------------------------------------------------- */
    function initCounterAnimation(ctx) {
        const nums = $$('[data-count]', ctx);
        if (!nums.length) return;
        nums.forEach(function (el) {
            el.classList.add('count-up');
            const target = parseFloat(el.dataset.count) || 0;
            const dur = 650;
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
    function initSmoothScroll(ctx) {
        $$('a[href^="#"]', ctx).forEach(function (anchor) {
            if (anchor.dataset.bound === '1') return;
            anchor.dataset.bound = '1';
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') return;
                const target = document.querySelector(href);
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    /* ---------------------------------------------------------
       Lightbox Zoom Gambar Resolusi Tinggi (Event Delegation)
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

        document.addEventListener('click', function (e) {
            const img = e.target.closest('.detail-hero img, [data-lightbox]');
            if (img) {
                openLightbox(img.src, img.alt);
            }
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
                pjaxCache.delete(new URL('index.php', window.location.origin).href);
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
                    pjaxCache.delete(new URL('index.php', window.location.origin).href);
                    await loadMiniChat(true);
                } catch (e) {}
            });
        }
    }
})();
