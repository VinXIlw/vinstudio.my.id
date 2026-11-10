<?php
$user = currentUser();
$title = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$current = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= APP_NAME ?> - <?= APP_TAGLINE ?>. Galeri karya digital, dokumentasi album, cerita komunitas, dan Global Live Chat.">
    <title><?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: '5.0' ?>">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('vinstudio_theme');
                if (saved === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo" style="text-decoration:none;">
                <span class="logo-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                </span>
                <div class="logo-brand-stack">
                    <span class="logo-text"><?= APP_NAME ?></span>
                    <span class="logo-sub">TKJ DIGITAL HUB</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Navigasi Utama">
                <ul>
                    <li>
                        <a href="index.php" class="<?= $current === 'home' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="<?= $current === 'profile' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>Profil</span>
                        </a>
                    </li>
                    <li>
                        <a href="albums.php" class="<?= $current === 'albums' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span>Album</span>
                        </a>
                    </li>
                    <li>
                        <a href="stories.php" class="<?= $current === 'stories' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                            <span>Cerita</span>
                        </a>
                    </li>
                    <li>
                        <a href="chat.php" class="nav-chat-link <?= $current === 'chat' ? 'active' : '' ?>">
                            <span class="nav-live-dot"></span>
                            <span>Live Chat</span>
                        </a>
                    </li>
                    <?php if ($user): ?>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li>
                                <a href="admin.php" class="<?= $current === 'admin' ? 'active' : '' ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                    <span>Admin</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="dashboard.php" class="nav-user-pill <?= $current === 'dashboard' ? 'active' : '' ?>" title="<?= htmlspecialchars($user['email']) ?>">
                                <span class="nav-mini-avatar <?= avatarClass($user['displayName']) ?>"><?= initials($user['displayName']) ?></span>
                                <span><?= htmlspecialchars($user['displayName']) ?></span>
                            </a>
                        </li>
                <li><a href="logout.php" class="btn-logout" data-no-transition>Keluar</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="<?= $current === 'login' ? 'active' : '' ?>" data-no-transition>Masuk</a></li>
                        <li><a href="register.php" class="nav-cta-register <?= $current === 'register' ? 'active' : '' ?>" data-no-transition>Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions-right">
                <button type="button" id="themeToggle" class="theme-toggle-btn" aria-label="Ganti Tema Gelap / Terang" title="Mode Gelap / Terang">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </button>
                <button class="mobile-menu-btn" aria-label="Menu Navigasi"><span></span><span></span><span></span></button>
            </div>
        </div>
    </header>
    <div id="pjaxStage" class="pjax-stage" data-page="<?= htmlspecialchars($current) ?>">
