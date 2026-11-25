<?php
require_once __DIR__ . '/config.php';

if (currentUser()) {
    safeRedirect('index.php');
}

$msg       = $_GET['msg'] ?? '';
$err       = $_GET['err'] ?? '';
$returnUrl = sanitizeReturnUrl($_GET['r'] ?? ($_POST['r'] ?? 'index.php'), 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $err = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } elseif (!checkRateLimit($pdo, 'login_attempt', 5, 600)) {
        $err = 'Terlalu banyak percobaan masuk yang gagal. Demi keamanan, akses dari jaringan Anda dikunci sementara selama 10 menit.';
        logActivity($pdo, null, trim($_POST['email'] ?? ''), '', 'login-terkunci', 'Rate limit brute-force aktif');
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $err = 'Alamat email dan kata sandi wajib diisi.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $validPass = $user ? verifyUserPassword($pdo, (int)$user['id'], $password, (string)$user['password']) : false;

            if (!$user || !$validPass) {
                recordRateLimit($pdo, 'login_attempt');
                logActivity($pdo, null, $email, '', 'login-gagal', 'Kredensial tidak cocok');
                $err = 'Email atau kata sandi yang dimasukkan tidak sesuai.';
            } elseif ((int)$user['is_banned'] === 1) {
                logActivity($pdo, $user['id'], $email, $user['display_name'], 'login-ditolak', 'Akun berstatus nonaktif');
                $err = 'Akun ini sedang dinonaktifkan oleh pengurus/administrator.';
            } else {
                clearRateLimit($pdo, 'login_attempt');
                session_regenerate_id(true);

                $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

                $_SESSION['user_id']      = (int)$user['id'];
                $_SESSION['email']        = $user['email'];
                $_SESSION['display_name'] = $user['display_name'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['avatar']       = $user['avatar'] ?? null;
                $_SESSION['bio']          = $user['bio'] ?? '';
                $_SESSION['specialty']    = $user['specialty'] ?? '';
                $_SESSION['last_sync']    = time();

                logActivity($pdo, $user['id'], $email, $user['display_name'], 'login', 'Autentikasi berhasil');
                safeRedirect($returnUrl);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Akun — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?: '6.0' ?>">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
    <script>
        (function() {
            try {
                var t = localStorage.getItem('vinstudio_theme');
                document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
            } catch (e) {}
        })();
    </script>
</head>
<body class="auth-screen-body">
    <div class="auth-ambient-orb orb-1" aria-hidden="true"></div>
    <div class="auth-ambient-orb orb-2" aria-hidden="true"></div>

    <div class="auth-shell">
        <!-- Panel Kiri: Identitas & Keamanan Portal -->
        <aside class="auth-showcase">
            <div>
                <a href="index.php" class="auth-brand-link">
                    <span class="logo-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    </span>
                    <div>
                        <strong class="auth-brand-title"><?= htmlspecialchars(APP_NAME) ?></strong>
                        <span class="auth-brand-sub">TKJ DIGITAL HUB</span>
                    </div>
                </a>

                <div class="auth-showcase-copy">
                    <span class="pill-eyebrow"><?= svgIcon('shield', 14) ?> Autentikasi Terenkripsi</span>
                    <h1>Selamat Datang Kembali di Ruang Kolaborasi Digital.</h1>
                    <p>Masuk untuk mengunggah dokumentasi praktikum, menerbitkan artikel teknis, memperbarui portofolio profil, dan berdiskusi secara real-time di Global Live Chat.</p>
                </div>
            </div>

            <div class="auth-feature-list">
                <div class="auth-feature-item">
                    <span class="auth-feat-icon"><?= svgIcon('image', 17) ?></span>
                    <div>
                        <strong>Portofolio &amp; Dokumentasi</strong>
                        <span>Arsipkan proyek jaringan, konfigurasi server, dan momen kelas.</span>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <span class="auth-feat-icon"><?= svgIcon('chat', 17) ?></span>
                    <div>
                        <strong>Global Live Chat</strong>
                        <span>Ruang diskusi instan antar anggota dengan balasan &amp; status online.</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Panel Kanan: Formulir Login -->
        <section class="auth-form-panel">
            <div class="auth-panel-top">
                <a href="index.php" class="btn btn-ghost btn-sm"><?= svgIcon('arrow-left', 16) ?> Beranda</a>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="mono-tag">TLS / CSRF PROTECTED</span>
                    <button type="button" id="authThemeToggle" class="theme-toggle-btn" style="width:34px;height:34px;border-color:var(--border-strong);background:var(--lighter);color:var(--dark);" aria-label="Ganti Tema Gelap / Terang" title="Mode Gelap / Terang">
                        <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                </div>
            </div>

            <div class="auth-form-head">
                <h2>Masuk ke Akun</h2>
                <p>Gunakan email dan kata sandi akun anggota <?= htmlspecialchars(APP_NAME) ?> Anda.</p>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form-stack" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="r" value="<?= htmlspecialchars($returnUrl) ?>">

                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" required autofocus autocomplete="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="nama@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="Masukkan kata sandi">
                        <button type="button" class="pw-toggle" id="togglePwBtn" aria-label="Tampilkan kata sandi">
                            <?= svgIcon('eye', 16) ?>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <span>Masuk Sekarang</span>
                    <?= svgIcon('arrow-right', 17) ?>
                </button>
            </form>

            <div class="auth-panel-foot">
                Belum memiliki akun anggota?
                <a href="register.php">Daftar akun baru</a>
            </div>
        </section>
    </div>

    <script>
        (function() {
            var btn = document.getElementById('togglePwBtn');
            var inp = document.getElementById('password');
            if (btn && inp) {
                btn.addEventListener('click', function() {
                    inp.type = inp.type === 'password' ? 'text' : 'password';
                });
            }
            var tBtn = document.getElementById('authThemeToggle');
            if (tBtn) {
                tBtn.addEventListener('click', function() {
                    var root = document.documentElement;
                    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    root.setAttribute('data-theme', next);
                    try { localStorage.setItem('vinstudio_theme', next); } catch (e) {}
                });
            }
        })();
    </script>
</body>
</html>
