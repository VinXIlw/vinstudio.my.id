<?php
require_once __DIR__ . '/config.php';

if (currentUser()) {
    safeRedirect('index.php');
}

$err = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $err = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } elseif (!checkRateLimit($pdo, 'register_attempt', 4, 1800)) {
        $err = 'Batas pendaftaran dari jaringan Anda telah tercapai. Silakan coba kembali nanti.';
    } else {
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $specialty   = trim($_POST['specialty'] ?? 'Anggota XI TKJ 1');
        $password    = $_POST['password'] ?? '';
        $confirm     = $_POST['confirm'] ?? '';

        if ($email === '' || $displayName === '' || $password === '') {
            $err = 'Nama tampilan, alamat email, dan kata sandi wajib diisi.';
        } elseif (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 60) {
            $err = 'Nama tampilan harus terdiri dari 2 hingga 60 karakter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Format alamat email tidak valid.';
        } elseif (strlen($password) < 6) {
            $err = 'Kata sandi minimal harus 6 karakter demi keamanan akun.';
        } elseif ($password !== $confirm) {
            $err = 'Konfirmasi kata sandi tidak cocok.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $err = 'Alamat email tersebut sudah terdaftar pada akun lain.';
            } else {
                recordRateLimit($pdo, 'register_attempt');
                if (!in_array($specialty, MEMBER_SPECIALTIES, true)) {
                    $specialty = 'Anggota XI TKJ 1';
                }
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Pendaftaran publik dikunci ketat pada role 'user' (Anggota).
                $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password, role, specialty) VALUES (?, ?, ?, 'user', ?)");
                $stmt->execute([$email, $displayName, $hashed, $specialty]);
                $newId = (int)$pdo->lastInsertId();

                logActivity($pdo, $newId, $email, $displayName, 'register', 'Pendaftaran anggota baru (' . $specialty . ')');
                safeRedirect('login.php?msg=' . urlencode('Akun berhasil dibuat! Silakan masuk menggunakan email dan kata sandi Anda.'));
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
    <title>Pendaftaran Anggota — <?= htmlspecialchars(APP_NAME) ?></title>
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
                    <span class="pill-eyebrow"><?= svgIcon('users', 14) ?> Registrasi Anggota</span>
                    <h1>Bergabung ke Ekosistem Digital <?= htmlspecialchars(APP_NAME) ?>.</h1>
                    <p>Bangun identitas portofolio digitalmu, pilih bidang spesialisasi teknologi, publikasikan artikel &amp; dokumentasi karya, serta terhubung dengan seluruh teman sekelas.</p>
                </div>
            </div>

            <div class="auth-feature-list">
                <div class="auth-feature-item">
                    <span class="auth-feat-icon"><?= svgIcon('terminal', 17) ?></span>
                    <div>
                        <strong>Identitas &amp; Peminatan TI</strong>
                        <span>Tampilkan fokus keahlian mulai dari Network Engineering hingga Cyber Security.</span>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <span class="auth-feat-icon"><?= svgIcon('shield', 17) ?></span>
                    <div>
                        <strong>Privasi &amp; Keamanan Ketat</strong>
                        <span>Kata sandi dienkripsi penuh dengan algoritma Bcrypt modern.</span>
                    </div>
                </div>
            </div>
        </aside>

        <section class="auth-form-panel">
            <div class="auth-panel-top">
                <a href="index.php" class="btn btn-ghost btn-sm"><?= svgIcon('arrow-left', 16) ?> Beranda</a>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="mono-tag">MEMBER ONBOARDING</span>
                    <button type="button" id="authThemeToggle" class="theme-toggle-btn" style="width:34px;height:34px;border-color:var(--border-strong);background:var(--lighter);color:var(--dark);" aria-label="Ganti Tema Gelap / Terang" title="Mode Gelap / Terang">
                        <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                </div>
            </div>

            <div class="auth-form-head">
                <h2>Buat Akun Anggota</h2>
                <p>Lengkapi data di bawah ini untuk membuat akun resmi komunitas.</p>
            </div>

            <?php if ($err): ?>
                <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="auth-form-stack" novalidate>
                <?= csrfField() ?>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="display_name">Nama Lengkap / Panggilan <span class="required">*</span></label>
                        <input type="text" id="display_name" name="display_name" required maxlength="60"
                               value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>"
                               placeholder="Contoh: Rizky Pratama">
                    </div>

                    <div class="form-group">
                        <label for="specialty">Fokus Bidang TI</label>
                        <select id="specialty" name="specialty">
                            <?php
                            $curSpec = $_POST['specialty'] ?? 'Network Engineering';
                            foreach (MEMBER_SPECIALTIES as $spec):
                            ?>
                                <option value="<?= htmlspecialchars($spec) ?>" <?= $curSpec === $spec ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Alamat Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="nama@email.com">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="password">Kata Sandi <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"
                                   placeholder="Min. 6 karakter">
                            <button type="button" class="pw-toggle" data-pw-target="password" aria-label="Tampilkan kata sandi">
                                <?= svgIcon('eye', 16) ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm">Ulangi Kata Sandi <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="confirm" name="confirm" required minlength="6" autocomplete="new-password"
                                   placeholder="Ketik ulang sandi">
                            <button type="button" class="pw-toggle" data-pw-target="confirm" aria-label="Tampilkan kata sandi">
                                <?= svgIcon('eye', 16) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pw-meter" id="regPwMeter"><span></span></div>
                <div class="pw-hint" id="regPwHint" style="margin-top:-0.4rem;margin-bottom:0.5rem;"></div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <span>Daftarkan Akun</span>
                    <?= svgIcon('arrow-right', 17) ?>
                </button>
            </form>

            <div class="auth-panel-foot">
                Sudah memiliki akun?
                <a href="login.php">Masuk di sini</a>
            </div>
        </section>
    </div>

    <script>
        (function() {
            var pw = document.getElementById('password');
            var meter = document.getElementById('regPwMeter');
            var hint = document.getElementById('regPwHint');
            var labels = ['Terlalu pendek', 'Lemah', 'Cukup baik', 'Kuat', 'Sangat kuat'];
            if (pw && meter && hint) {
                pw.addEventListener('input', function() {
                    var v = pw.value;
                    var s = 0;
                    if (v.length >= 6) s++;
                    if (v.length >= 10) s++;
                    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
                    if (/\d/.test(v) || /[^A-Za-z0-9]/.test(v)) s++;
                    if (!v) { meter.className = 'pw-meter'; hint.textContent = ''; return; }
                    meter.className = 'pw-meter is-' + Math.max(1, s);
                    hint.textContent = 'Kekuatan sandi: ' + labels[s];
                });
            }
            document.querySelectorAll('[data-pw-target]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var target = document.getElementById(btn.getAttribute('data-pw-target'));
                    if (target) target.type = target.type === 'password' ? 'text' : 'password';
                });
            });
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
