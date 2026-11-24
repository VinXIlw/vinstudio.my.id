<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('403 Forbidden');
}
$user    = currentUser();
$title   = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$current = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(APP_NAME . ' — ' . APP_TAGLINE) ?>. Portofolio proyek TI, dokumentasi kegiatan, artikel teknis, pengumuman kelas, dan Global Live Chat.">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: '6.0' ?>">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('vinstudio_theme');
                document.documentElement.setAttribute('data-theme', saved === 'light' ? 'light' : 'dark');
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <span class="logo-icon" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                </span>
                <div class="logo-brand-stack">
                    <span class="logo-text"><?= htmlspecialchars(APP_NAME) ?></span>
                    <span class="logo-sub">TKJ DIGITAL HUB</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Navigasi Utama">
                <ul>
                    <li>
                        <a href="index.php" class="<?= $current === 'home' ? 'active' : '' ?>">
                            <?= svgIcon('home', 16) ?>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="<?= $current === 'profile' ? 'active' : '' ?>">
                            <?= svgIcon('users', 16) ?>
                            <span>Direktori</span>
                        </a>
                    </li>
                    <li>
                        <a href="albums.php" class="<?= $current === 'albums' ? 'active' : '' ?>">
                            <?= svgIcon('image', 16) ?>
                            <span>Galeri</span>
                        </a>
                    </li>
                    <li>
                        <a href="stories.php" class="<?= $current === 'stories' ? 'active' : '' ?>">
                            <?= svgIcon('book', 16) ?>
                            <span>Artikel</span>
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
                                    <?= svgIcon('shield', 16) ?>
                                    <span>Admin</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="dashboard.php" class="nav-user-pill <?= $current === 'dashboard' ? 'active' : '' ?>" title="Dasbor &amp; Pengaturan Profil (<?= htmlspecialchars($user['email']) ?>)">
                                <?php if (!empty($user['avatar']) && is_file(__DIR__ . '/../uploads/' . basename($user['avatar']))): ?>
                                    <img src="uploads/<?= htmlspecialchars(basename($user['avatar'])) ?>" alt="<?= htmlspecialchars($user['displayName']) ?>" class="nav-mini-avatar-img">
                                <?php else: ?>
                                    <span class="nav-mini-avatar <?= avatarClass($user['displayName']) ?>"><?= initials($user['displayName']) ?></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($user['displayName']) ?></span>
                            </a>
                        </li>
                        <li class="nav-logout-item">
                            <form action="logout.php" method="POST" class="nav-logout-form" data-no-loading>
                                <?= csrfField() ?>
                                <button type="submit" class="btn-logout-pill" title="Keluar dari Akun">Keluar</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php" class="<?= $current === 'login' ? 'active' : '' ?>" data-no-transition>Masuk</a></li>
                        <li><a href="register.php" class="nav-cta-register <?= $current === 'register' ? 'active' : '' ?>" data-no-transition>Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions-right">
                <button type="button" id="themeToggle" class="theme-toggle-btn" aria-label="Ganti Tema Gelap / Terang" title="Mode Gelap / Terang">
                    <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <button class="mobile-menu-btn" aria-label="Menu Navigasi"><span></span><span></span><span></span></button>
            </div>
        </div>
    </header>
    <div id="pjaxStage" class="pjax-stage" data-page="<?= htmlspecialchars($current) ?>">
