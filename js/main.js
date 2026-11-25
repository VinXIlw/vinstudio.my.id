/*
  XI TKJ 1 Digital Hub — Enterprise Interactive Engine (v6.0)
  Fitur:
    - Continuous Sliding Glider (Navbar, Segmented Sort, & Panel Tabs)
    - Instant PJAX / Turbo Router + Hover & Warm Prefetch (0ms perceived navigation)
    - Instant AJAX Like Toggle dengan proteksi CSRF & animasi Heart Pop
    - Instant Combined Search + Category Chip + Sort Filter di sisi klien
    - Secure CSRF-protected Floating Mini Live Chat Drawer
*/
(function () {
    'use strict';

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    window.__pjaxCleanupFns = window.__pjaxCleanupFns || [];

    function getCsrfToken() {
        const meta = $('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }
    window.getCsrfToken = getCsrfToken;

    let syncNavToUrl = null;
    const pjaxCache = new Map();
    const PJAX_TTL = 25000;

    window.clearPjaxCache = function () {
        pjaxCache.clear();
    };

    document.addEventListener('DOMContentLoaded', function () {
        initThemeToggle();
        initProgressBar();
        initMobileMenu();
        initNavGlider();
        initPjaxRouter();
        initAjaxLikes();
        initModals();
        initRipple();
        initLightbox();
        initShareButtons();
        initFloatingChat();

        initPageContent(document);
    });

    function initPageContent(ctx) {
        initReveal(ctx);
        initTabs(ctx);
        initSortAndFilter(ctx);
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
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('vinstudio_theme', next); } catch (e) {}
        });
    }

    /* ---------------------------------------------------------
       Top Progress Bar
    --------------------------------------------------------- */
    function initProgressBar() {
        if ($('.page-progress')) return;
        const bar = document.createElement('div');
        bar.className = 'page-progress';
        document.body.appendChild(bar);
    }
    function startProgress() {
        const bar = $('.page-progress');
        if (bar) { bar.classList.remove('done'); bar.classList.add('active'); }
    }
    function finishProgress() {
        const bar = $('.page-progress');
        if (!bar) return;
        bar.classList.add('active', 'done');
        setTimeout(() => bar.classList.remove('active', 'done'), 280);
    }

    /* ---------------------------------------------------------
       Mobile Menu
    --------------------------------------------------------- */
    function initMobileMenu() {
        const menuBtn = $('.mobile-menu-btn');
        const navUl   = $('.main-nav ul');
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
       Continuous Sliding Navbar Glider
    --------------------------------------------------------- */
    function initNavGlider() {
        const navUl = $('.main-nav ul');
        if (!navUl) return;

        const links = $$('li > a', navUl);
        if (!links.length) return;

        const hoverGlider = document.createElement('span');
        hoverGlider.className = 'nav-hover-glider';
        navUl.appendChild(hoverGlider);

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
            if (!el || window.innerWidth <= 820) {
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
                movingTimer = setTimeout(() => glider.classList.remove('is-moving'), 430);
            }

            glider.classList.toggle('is-chat', el.classList.contains('nav-chat-link'));
            glider.style.width     = m.w + 'px';
            glider.style.height    = m.h + 'px';
            glider.style.transform = `translate3d(${m.x}px, ${m.y}px, 0)`;
            glider.style.opacity   = '1';

            if (!animate) {
                void glider.offsetWidth;
                glider.style.transition = '';
            }
        }

        links.forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                if (window.innerWidth <= 820) return;
                const m = getMetrics(link);
                hoverGlider.style.width     = m.w + 'px';
                hoverGlider.style.height    = m.h + 'px';
                hoverGlider.style.transform = `translate3d(${m.x}px, ${m.y}px, 0)`;
                hoverGlider.style.opacity   = '1';
            });
        });
        navUl.addEventListener('mouseleave', function () {
            hoverGlider.style.opacity = '0';
        });

        function findMatchingNavLink(urlStr) {
            try {
                const u = new URL(urlStr, window.location.origin);
                let file = u.pathname.split('/').pop() || 'index.php';
                if (file === '' || file === '/') file = 'index.php';

                if (file === 'album-detail.php' || file === 'album-add.php') file = 'albums.php';
                if (file === 'story-detail.php' || file === 'story-add.php') file = 'stories.php';
                if (file === 'admin-logs.php') file = 'admin.php';

                return links.find(a => (a.getAttribute('href') || '').split('?')[0] === file) || null;
            } catch (e) {
                return null;
            }
        }

        let activeLink = links.find(l => l.classList.contains('active')) || findMatchingNavLink(window.location.href);
        if (activeLink) {
            activeLink.classList.add('active');
            applyGlider(activeLink, false);
        }
        navUl.classList.add('has-glider');

        syncNavToUrl = function (urlStr, animate) {
            const prevActive = links.find(l => l.classList.contains('active'));
            const nextActive = findMatchingNavLink(urlStr);
            const prevIdx    = prevActive ? links.indexOf(prevActive) : -1;
            const nextIdx    = nextActive ? links.indexOf(nextActive) : -1;

            let dir = 'fade';
            if (prevIdx !== -1 && nextIdx !== -1 && prevIdx !== nextIdx) {
                dir = nextIdx > prevIdx ? 'next' : 'prev';
            }

            if (nextActive) {
                links.forEach(l => l.classList.toggle('active', l === nextActive));
                applyGlider(nextActive, animate !== false);
            } else {
                links.forEach(l => l.classList.remove('active'));
                glider.style.opacity = '0';
            }

            const menuBtn = $('.mobile-menu-btn');
            if (navUl.classList.contains('active')) {
                navUl.classList.remove('active');
                if (menuBtn) menuBtn.classList.remove('active');
            }

            return { prevIdx, nextIdx, dir };
        };

        function realignNavGlider() {
            const cur = links.find(l => l.classList.contains('active'));
            if (cur) applyGlider(cur, false);
        }
        window.addEventListener('resize', realignNavGlider);
        window.addEventListener('load', realignNavGlider);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(realignNavGlider);
        }
    }

    /* ---------------------------------------------------------
       Combined Search + Category Filter + Sliding Sort Glider
    --------------------------------------------------------- */
    function initSortAndFilter(ctx) {
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
                glider.style.transition = animate ? '' : 'none';
                glider.style.width     = btn.offsetWidth + 'px';
                glider.style.height    = btn.offsetHeight + 'px';
                glider.style.transform = `translate3d(${btn.offsetLeft}px, ${btn.offsetTop}px, 0)`;
                glider.style.opacity   = '1';
                if (!animate) {
                    void glider.offsetWidth;
                    glider.style.transition = '';
                }
            }

            const initialActive = btns.find(b => b.classList.contains('active')) || btns[0];
            if (initialActive) moveSortGlider(initialActive, false);
            group.classList.add('has-glider');

            const gridSel   = group.dataset.targetGrid;
            const searchSel = group.dataset.searchInput;
            const countSel  = group.dataset.countLabel;
            const emptySel  = group.dataset.emptyState;
            const catBarSel = group.dataset.catBar;

            const grid      = gridSel ? $(gridSel) : null;
            const searchInp = searchSel ? $(searchSel) : null;
            const countEl   = countSel ? $(countSel) : null;
            const emptyEl   = emptySel ? $(emptySel) : null;
            const catBar    = catBarSel ? $(catBarSel) : null;
            const unitLabel = group.dataset.unit || 'item';

            let currentCat = 'all';

            function applyFilterAndVisibility() {
                if (!grid) return;
                const q = searchInp ? searchInp.value.toLowerCase().trim() : '';
                const cards = Array.from(grid.children);
                let visible = 0;

                cards.forEach(function (card) {
                    const hay = (card.dataset.search || card.dataset.title || '').toLowerCase();
                    const cat = (card.dataset.category || '').toLowerCase();
                    const matchQ   = !q || hay.includes(q);
                    const matchCat = currentCat === 'all' || cat === currentCat.toLowerCase();
                    const show = matchQ && matchCat;
                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                if (countEl) countEl.textContent = visible + ' ' + unitLabel;
                if (emptyEl) emptyEl.style.display = visible === 0 ? '' : 'none';
                grid.style.display = visible === 0 ? 'none' : '';
            }

            if (searchInp) {
                searchInp.addEventListener('input', applyFilterAndVisibility);
            }

            if (catBar) {
                $$('[data-cat]', catBar).forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        $$('[data-cat]', catBar).forEach(c => c.classList.toggle('active', c === chip));
                        currentCat = chip.dataset.cat || 'all';
                        applyFilterAndVisibility();
                    });
                });
            }

            btns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const sortMode = btn.dataset.sort || 'latest';
                    btns.forEach(b => b.classList.toggle('active', b === btn));
                    moveSortGlider(btn, true);

                    if (grid) {
                        const cards = Array.from(grid.children);
                        cards.sort(function (a, b) {
                            const likesA = parseInt(a.dataset.likes || '0', 10);
                            const likesB = parseInt(b.dataset.likes || '0', 10);
                            const timeA  = parseInt(a.dataset.time || '0', 10);
                            const timeB  = parseInt(b.dataset.time || '0', 10);
                            if (sortMode === 'popular' && likesB !== likesA) {
                                return likesB - likesA;
                            }
                            return timeB - timeA;
                        });
                        cards.forEach(function (c, idx) {
                            c.style.animation = 'none';
                            void c.offsetWidth;
                            c.style.animation = `cardEntrance 0.34s cubic-bezier(0.22, 1, 0.36, 1) ${idx * 0.03}s both`;
                            grid.appendChild(c);
                        });
                        applyFilterAndVisibility();
                    }

                    if (window.history && window.history.replaceState) {
                        const u = new URL(btn.href, window.location.origin);
                        window.history.replaceState({ url: u.href }, '', u.href);
                    }
                });
            });

            function realignSortGlider() {
                const cur = btns.find(b => b.classList.contains('active'));
                if (cur) moveSortGlider(cur, false);
            }
            window.addEventListener('resize', realignSortGlider);
            window.addEventListener('load', realignSortGlider);
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(realignSortGlider);
            }
        });
    }

    /* ---------------------------------------------------------
       Instant AJAX Like Toggle (CSRF Protected, 0ms Reload)
    --------------------------------------------------------- */
    function initAjaxLikes() {
        document.addEventListener('submit', async function (e) {
            const form = e.target.closest('.js-like-form');
            if (!form) return;
            e.preventDefault();

            const btn = $('.like-btn', form);
            const countSpan = $('[data-like-count]', form);
            if (btn) btn.disabled = true;

            const fd = new FormData(form);
            if (!fd.get('csrf_token')) {
                fd.append('csrf_token', getCsrfToken());
            }

            try {
                const res = await fetch('like.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'fetch',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: fd
                });
                const data = await res.json();
                if (data && data.ok) {
                    if (btn) {
                        btn.classList.toggle('liked', !!data.liked);
                        btn.classList.remove('pop');
                        void btn.offsetWidth;
                        btn.classList.add('pop');
                    }
                    if (countSpan) {
                        countSpan.textContent = data.count + ' Suka';
                    }
                    pjaxCache.clear();
                } else if (data && data.error) {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                form.submit();
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    /* ---------------------------------------------------------
       Instant PJAX Router + Warm & Hover Prefetch
    --------------------------------------------------------- */
    function isPjaxEligibleUrl(urlObj) {
        if (!urlObj || urlObj.origin !== window.location.origin) return false;
        let file = urlObj.pathname.split('/').pop() || 'index.php';
        if (file === '' || file === '/') file = 'index.php';

        const bypassFiles = [
            'login.php', 'register.php', 'logout.php',
            'chat-api.php', 'like.php', 'comment.php',
            'album-delete.php', 'story-delete.php', 'profile-update.php'
        ];
        if (bypassFiles.includes(file)) return false;
        if (urlObj.searchParams.has('del')) return false;
        return file.endsWith('.php') || file === 'index.php';
    }

    async function fetchPageHtml(urlStr, forceNetwork) {
        const now = Date.now();
        if (!forceNetwork && pjaxCache.has(urlStr)) {
            const cached = pjaxCache.get(urlStr);
            if (now - cached.time < PJAX_TTL) return cached.html;
        }

        const res = await fetch(urlStr, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-PJAX': 'true', 'Accept': 'text/html' }
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        if (res.redirected && res.url.includes('login.php')) {
            window.location.href = res.url;
            return null;
        }
        const html = await res.text();
        pjaxCache.set(urlStr, { html, time: Date.now() });
        return html;
    }

    function prefetchUrl(urlStr) {
        try {
            const u = new URL(urlStr, window.location.origin);
            if (!isPjaxEligibleUrl(u)) return;
            const file = u.pathname.split('/').pop() || 'index.php';
            if (file === 'chat.php') return;
            const now = Date.now();
            if (pjaxCache.has(u.href) && (now - pjaxCache.get(u.href).time < PJAX_TTL)) return;
            fetchPageHtml(u.href, false).catch(() => {});
        } catch (e) {}
    }

    function normalizeComparableUrl(u) {
        let p = u.pathname || '/';
        if (p === '/' || p.endsWith('/')) p += 'index.php';
        return u.origin + p + u.search;
    }

    function runCleanupFns() {
        while (Array.isArray(window.__pjaxCleanupFns) && window.__pjaxCleanupFns.length > 0) {
            const fn = window.__pjaxCleanupFns.pop();
            try { if (typeof fn === 'function') fn(); } catch (e) {}
        }
    }

    function initPjaxRouter() {
        const stage = $('#pjaxStage');
        if (!stage) return;

        let activeNavRequest = 0;

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

            if (normalizeComparableUrl(url) === normalizeComparableUrl(new URL(window.location.href))) {
                e.preventDefault();
                if (syncNavToUrl) syncNavToUrl(url.href, true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            e.preventDefault();
            navigateTo(url.href, true);
        });

        window.addEventListener('popstate', function () {
            if (isPjaxEligibleUrl(new URL(window.location.href))) {
                navigateTo(window.location.href, false);
            }
        });

        async function navigateTo(targetUrl, pushState) {
            const reqId = ++activeNavRequest;

            let slideDir = 'fade';
            if (syncNavToUrl) {
                const navInfo = syncNavToUrl(targetUrl, true);
                if (navInfo && navInfo.dir) slideDir = navInfo.dir;
            }

            const floatingPanel = $('#floatingChatPanel');
            if (floatingPanel && floatingPanel.classList.contains('open')) {
                floatingPanel.classList.remove('open');
                floatingPanel.setAttribute('aria-hidden', 'true');
            }

            const isCached = pjaxCache.has(targetUrl) && (Date.now() - pjaxCache.get(targetUrl).time < PJAX_TTL);
            if (!isCached) startProgress();

            try {
                const isChatTarget = targetUrl.includes('chat.php');
                const html = await fetchPageHtml(targetUrl, isChatTarget);
                if (!html || reqId !== activeNavRequest) return;

                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newStage = doc.getElementById('pjaxStage');
                if (!newStage) {
                    window.location.href = targetUrl;
                    return;
                }

                // Sinkronkan token CSRF & pil profil header dari dokumen baru jika ada
                const newCsrf = doc.querySelector('meta[name="csrf-token"]');
                const curCsrf = $('meta[name="csrf-token"]');
                if (newCsrf && curCsrf) {
                    curCsrf.setAttribute('content', newCsrf.getAttribute('content') || '');
                }

                const newUserPill = doc.querySelector('.nav-user-pill');
                const curUserPill = $('.nav-user-pill');
                if (newUserPill && curUserPill) {
                    curUserPill.innerHTML = newUserPill.innerHTML;
                    curUserPill.title = newUserPill.title;
                }

                // Reset body scroll lock, modal, & lightbox sebelum swap halaman
                document.body.style.overflow = '';
                const lb = $('#imageLightbox');
                if (lb && lb.classList.contains('open')) {
                    lb.classList.remove('open');
                    lb.setAttribute('aria-hidden', 'true');
                }
                $$('.modal.open').forEach(m => m.classList.remove('open'));

                runCleanupFns();

                if (doc.title) document.title = doc.title;
                if (pushState && window.history && window.history.pushState) {
                    window.history.pushState({ url: targetUrl }, '', targetUrl);
                }

                const curStage = $('#pjaxStage');
                curStage.removeAttribute('data-slide-dir');
                const pageAttr = newStage.getAttribute('data-page') || '';
                curStage.setAttribute('data-page', pageAttr);
                curStage.innerHTML = newStage.innerHTML;

                void curStage.offsetWidth;
                curStage.setAttribute('data-slide-dir', slideDir);
                setTimeout(function () {
                    if (curStage) curStage.removeAttribute('data-slide-dir');
                }, 380);

                const floatingRoot = $('#floatingChatRoot');
                if (floatingRoot) {
                    floatingRoot.style.display = (pageAttr === 'chat' || isChatTarget) ? 'none' : '';
                }

                window.scrollTo({ top: 0, behavior: 'auto' });

                $$('script', curStage).forEach(function (oldScript) {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                initPageContent(curStage);
                finishProgress();

                if (isCached && !isChatTarget) {
                    fetchPageHtml(targetUrl, true).catch(() => {});
                }
            } catch (err) {
                window.location.href = targetUrl;
            }
        }
    }

    /* ---------------------------------------------------------
       Scroll Reveal, Tabs, Modals, Ripple, Form UX, Lightbox
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
                    setTimeout(() => entry.target.classList.add('is-visible'), i * 40);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });
        items.forEach(el => io.observe(el));
    }

    function initTabs(ctx) {
        $$('[data-tabs]', ctx).forEach(function (group) {
            if (group.dataset.tabsReady === '1') return;
            group.dataset.tabsReady = '1';

            const tabsNav = $('.tabs', group) || group;
            const buttons = $$('.tab-btn', group);
            const panels  = $$('.tab-panel', group);
            if (!buttons.length) return;

            let tabGlider = null;
            if (tabsNav && tabsNav.classList.contains('tabs')) {
                tabGlider = document.createElement('span');
                tabGlider.className = 'tab-glider';
                tabsNav.appendChild(tabGlider);
            }

            function updateTabGlider(activeBtn, animate) {
                if (!tabGlider || !activeBtn) return;
                tabGlider.style.transition = animate ? '' : 'none';
                tabGlider.style.width      = activeBtn.offsetWidth + 'px';
                tabGlider.style.height     = activeBtn.offsetHeight + 'px';
                tabGlider.style.transform  = `translate3d(${activeBtn.offsetLeft}px, ${activeBtn.offsetTop}px, 0)`;
                tabGlider.style.opacity    = '1';
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
                panels.forEach(p => p.classList.toggle('active', p.dataset.panel === name));
                if (activeBtn) updateTabGlider(activeBtn, animate);
                if (push && window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    window.history.replaceState({ url: url.href }, '', url);
                }
            }

            buttons.forEach(btn => btn.addEventListener('click', () => activate(btn.dataset.tab, true, true)));

            const wanted  = new URLSearchParams(window.location.search).get('tab');
            const initial = buttons.some(b => b.dataset.tab === wanted)
                ? wanted
                : (group.dataset.defaultTab || (buttons[0] && buttons[0].dataset.tab));
            if (initial) activate(initial, false, false);
            if (tabsNav) tabsNav.classList.add('has-glider');

            function realignTabGlider() {
                const cur = buttons.find(b => b.classList.contains('active'));
                if (cur) updateTabGlider(cur, false);
            }
            window.addEventListener('resize', realignTabGlider);
            window.addEventListener('load', realignTabGlider);
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(realignTabGlider);
            }
        });
    }

    function initModals() {
        function openModal(id) {
            const m = $('#' + id);
            if (!m) return;
            m.classList.add('open');
            document.body.style.overflow = 'hidden';
            const first = m.querySelector('input:not([type="hidden"]), select, textarea');
            if (first) setTimeout(() => first.focus(), 80);
        }
        function closeModal(m) {
            if (!m) return;
            m.classList.remove('open');
            document.body.style.overflow = '';
        }
        document.addEventListener('click', function (e) {
            const opener = e.target.closest('[data-modal-open]');
            if (opener) { e.preventDefault(); openModal(opener.dataset.modalOpen); return; }
            const closer = e.target.closest('[data-modal-close]');
            if (closer) { e.preventDefault(); closeModal(closer.closest('.modal')); return; }
            if (e.target.classList && e.target.classList.contains('modal')) closeModal(e.target);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const open = $('.modal.open');
                if (open) closeModal(open);
            }
        });
    }

    function initRipple() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn');
            if (!btn || btn.disabled) return;
            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left  = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top   = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(() => ripple.remove(), 560);
        });
    }

    function initFormUX(ctx) {
        $$('.pw-toggle', ctx).forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });

        $$('[data-pw-meter]', ctx).forEach(function (input) {
            if (input.dataset.bound === '1') return;
            input.dataset.bound = '1';
            const meter  = $('#' + input.dataset.pwMeter);
            const hint   = input.dataset.pwHint ? $('#' + input.dataset.pwHint) : null;
            const labels = ['Terlalu pendek', 'Lemah', 'Cukup baik', 'Kuat', 'Sangat kuat'];
            input.addEventListener('input', function () {
                const v = input.value;
                let s = 0;
                if (v.length >= 6) s++;
                if (v.length >= 10) s++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
                if (/\d/.test(v) || /[^A-Za-z0-9]/.test(v)) s++;
                if (!v) {
                    if (meter) meter.className = 'pw-meter';
                    if (hint) hint.textContent = '';
                    return;
                }
                if (meter) meter.className = 'pw-meter is-' + Math.max(1, s);
                if (hint) hint.textContent = 'Kekuatan sandi: ' + labels[s];
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
            if (form.hasAttribute('data-no-loading') || form.classList.contains('js-like-form') || form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', function () {
                pjaxCache.clear();
            });
        });
    }

    function initTableTools(ctx) {
        const search  = $('[data-table-search]', ctx);
        const rows    = $$('[data-table-row]', ctx);
        const counter = $('[data-table-count]', ctx);
        if (!search || !rows.length) return;

        function apply() {
            const term = search.value.toLowerCase().trim();
            let shown = 0;
            rows.forEach(function (row) {
                const hay = (row.dataset.search || row.textContent || '').toLowerCase();
                const ok  = !term || hay.includes(term);
                row.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            if (counter) counter.textContent = shown + ' pengguna';
        }
        if (search.dataset.bound !== '1') {
            search.dataset.bound = '1';
            search.addEventListener('input', apply);
        }
        apply();
    }

    let toastWrap = null;
    function showToast(text, type) {
        if (!toastWrap) {
            toastWrap = $('.toast-wrap') || document.createElement('div');
            toastWrap.className = 'toast-wrap';
            if (!toastWrap.parentNode) document.body.appendChild(toastWrap);
        }
        const toast = document.createElement('div');
        toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
        toast.textContent = text;
        toastWrap.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 3800);
    }
    window.showToast = showToast;

    function initToasts(ctx) {
        let hasInlineAlert = false;
        $$('.alert', ctx).forEach(function (alert) {
            if (alert.closest('#pjaxStage')) {
                hasInlineAlert = true;
                return;
            }
            if (alert.dataset.toasted === '1') return;
            alert.dataset.toasted = '1';
            showToast(alert.textContent.trim(), alert.classList.contains('alert-error') ? 'error' : 'success');
        });

        if (hasInlineAlert && window.history && window.history.replaceState) {
            const u = new URL(window.location.href);
            if (u.searchParams.has('msg') || u.searchParams.has('err')) {
                u.searchParams.delete('msg');
                u.searchParams.delete('err');
                window.history.replaceState({ url: u.href }, '', u.href);
            }
        }
    }

    function initCounterAnimation(ctx) {
        $$('[data-count]', ctx).forEach(function (el) {
            const target = parseFloat(el.dataset.count) || 0;
            const dur    = 600;
            const start  = performance.now();
            function step(now) {
                const p = Math.min((now - start) / dur, 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = target;
            }
            requestAnimationFrame(step);
        });
    }

    function initSmoothScroll(ctx) {
        $$('a[href^="#"]', ctx).forEach(function (anchor) {
            if (anchor.dataset.bound === '1') return;
            anchor.dataset.bound = '1';
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') return;
                const target = document.querySelector(href);
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });
    }

    function initLightbox() {
        const backdrop = $('#imageLightbox');
        const imgEl    = $('#lightboxImage');
        const capEl    = $('#lightboxCaption');
        const closeBtn = $('#lightboxCloseBtn');
        if (!backdrop || !imgEl) return;

        function closeLightbox() {
            backdrop.classList.remove('open');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        document.addEventListener('click', function (e) {
            const img = e.target.closest('.detail-hero img, [data-lightbox]');
            if (img) {
                imgEl.src = img.src;
                if (capEl) capEl.textContent = img.alt || '';
                backdrop.classList.add('open');
                backdrop.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
        });
        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
        backdrop.addEventListener('click', e => { if (e.target === backdrop) closeLightbox(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) closeLightbox();
        });
    }

    function initShareButtons() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-share-url]');
            if (!btn) return;
            e.preventDefault();
            let rawUrl = btn.getAttribute('data-share-url');
            if (!rawUrl) {
                const u = new URL(window.location.href);
                u.searchParams.delete('msg');
                u.searchParams.delete('err');
                rawUrl = u.href;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(rawUrl).then(() => showToast('Tautan berhasil disalin ke papan klip!', 'success'));
            } else {
                showToast('Tautan: ' + rawUrl, 'success');
            }
        });
    }

    /* ---------------------------------------------------------
       Secure Floating Mini Global Live Chat Drawer
    --------------------------------------------------------- */
    function initFloatingChat() {
        const fab        = $('#floatingChatFab');
        const panel      = $('#floatingChatPanel');
        const closeBtn   = $('#floatingChatClose');
        const body       = $('#floatingChatBody');
        const form       = $('#floatingChatForm');
        const input      = $('#floatingChatInput');
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
                const res  = await fetch('chat-api.php?action=fetch&limit=25&_t=' + Date.now(), { cache: 'no-store' });
                const data = await res.json();
                if (!data || !data.ok) return;

                if (onlineText && data.online_count) {
                    onlineText.textContent = data.online_count + ' anggota online';
                }

                if (Array.isArray(data.messages) && data.messages.length > 0) {
                    const trashSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
                    body.innerHTML = data.messages.map(function (m) {
                        const delBtn = m.can_delete
                            ? `<button type="button" data-mini-del="${m.id}" style="background:none;border:none;color:#fb7185;cursor:pointer;display:inline-flex;align-items:center;padding:2px;" title="Hapus">${trashSvg}</button>`
                            : '';
                        const miniAv = m.avatar
                            ? `<img src="uploads/${escapeHtml(m.avatar)}" class="user-avatar-img" style="width:20px;height:20px;margin-right:0.35rem;" alt="">`
                            : `<span class="user-avatar ${escapeHtml(m.avatar_class)}" style="width:20px;height:20px;font-size:0.55rem;margin-right:0.35rem;">${escapeHtml(m.initials)}</span>`;

                        return `
                            <div class="mini-msg-item" data-mini-id="${m.id}">
                                <div class="mini-msg-top">
                                    <strong style="color:var(--dark);display:flex;align-items:center;">
                                        ${miniAv}<span>${escapeHtml(m.display_name)}</span>
                                    </strong>
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
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', delBtn.getAttribute('data-mini-del'));
            fd.append('csrf_token', getCsrfToken());
            try {
                const res = await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': getCsrfToken() }, body: fd });
                if (res.ok) {
                    pjaxCache.delete(new URL('index.php', window.location.origin).href);
                    await loadMiniChat(false);
                } else {
                    showToast('Gagal menghapus pesan.', 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server.', 'error');
            }
        });

        fab.addEventListener('click', function () {
            const isOpen = panel.classList.toggle('open');
            panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            if (isOpen) {
                loadMiniChat(true);
                if (input) setTimeout(() => input.focus(), 100);
                if (!pollTimer) pollTimer = setInterval(() => loadMiniChat(false), 3000);
            } else if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
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
                fd.append('csrf_token', getCsrfToken());
                try {
                    const res  = await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': getCsrfToken() }, body: fd });
                    const data = await res.json();
                    if (data && data.error) showToast(data.error, 'error');
                    pjaxCache.delete(new URL('index.php', window.location.origin).href);
                    await loadMiniChat(true);
                } catch (e) {}
            });
        }
    }
})();
