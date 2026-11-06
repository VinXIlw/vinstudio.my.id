<?php
$user = currentUser();
$title = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME . ' - ' . APP_TAGLINE;
$current = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo" style="text-decoration:none;">
                <span class="logo-icon">&#128187;</span>
                <span class="logo-text"><?= APP_NAME ?></span>
            </a>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="<?= $current === 'home' ? 'active' : '' ?>">Beranda</a></li>
                    <li><a href="profile.php" class="<?= $current === 'profile' ? 'active' : '' ?>">Profil</a></li>
                    <li><a href="albums.php" class="<?= $current === 'albums' ? 'active' : '' ?>">Album</a></li>
                    <li><a href="stories.php" class="<?= $current === 'stories' ? 'active' : '' ?>">Cerita</a></li>
                    <?php if ($user): ?>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a href="admin.php" class="<?= $current === 'admin' ? 'active' : '' ?>">&#9881; Panel Admin</a></li>
                        <?php endif; ?>
                        <li><a href="dashboard.php" class="<?= $current === 'dashboard' ? 'active' : '' ?>" title="<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['displayName']) ?></a></li>
                        <li><a href="logout.php" class="btn-logout">Keluar</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="<?= $current === 'login' ? 'active' : '' ?>">Masuk</a></li>
                        <li><a href="register.php" class="<?= $current === 'register' ? 'active' : '' ?>">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <button class="mobile-menu-btn" aria-label="Menu Navigasi"><span></span><span></span><span></span></button>
        </div>
    </header>
