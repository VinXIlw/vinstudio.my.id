<?php
require_once __DIR__ . '/config.php';

if (currentUser()) {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Daftar Akun Baru';
$currentPage = 'register';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($email) || empty($password)) {
        $err = 'Email dan kata sandi wajib diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Format alamat email tidak valid!';
    } elseif ($password !== $confirm) {
        $err = 'Konfirmasi kata sandi tidak cocok!';
    } elseif (strlen($password) < 4) {
        $err = 'Kata sandi minimal 4 karakter!';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $err = 'Email tersebut sudah terdaftar pada akun lain!';
        } else {
            $finalName = !empty($displayName) ? $displayName : explode('@', $email)[0];
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Pendaftaran publik selalu membuat akun dengan role 'user' (Anggota).
            // Pembuatan akun Administrator hanya bisa dilakukan dari dalam Panel Admin.
            $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$email, $finalName, $hashed]);
            $newId = $pdo->lastInsertId();

            logActivity($pdo, $newId, $email, $finalName, 'register', 'Pendaftaran akun anggota baru');

            header("Location: login.php?msg=" . urlencode("Pendaftaran berhasil! Silakan masuk dengan akun barumu."));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=2.1">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
    <style>
        body.auth-body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #060913;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        .auth-ambient {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(650px circle at 82% 15%, rgba(99, 102, 241, 0.25), transparent 60%),
                radial-gradient(600px circle at 18% 82%, rgba(14, 165, 233, 0.2), transparent 60%),
                linear-gradient(135deg, #060913 0%, #0f172a 50%, #1e1b4b 100%);
            z-index: 0;
        }
        .auth-grid-overlay {
            position: fixed;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: radial-gradient(circle at center, black 45%, transparent 95%);
            -webkit-mask-image: radial-gradient(circle at center, black 45%, transparent 95%);
            z-index: 0;
            pointer-events: none;
        }
        .auth-shell {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 460px;
            padding: 2rem 1.25rem;
        }
        .auth-glass-card {
            background: rgba(17, 24, 39, 0.78);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 2.35rem 2.25rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            animation: fadeUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .auth-brand-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 1.65rem;
        }
        .auth-brand-link {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
            margin-bottom: 1rem;
        }
        .auth-brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #6366f1, #0ea5e9);
            display: grid;
            place-items: center;
            color: #fff;
            box-shadow: 0 8px 22px rgba(99, 102, 241, 0.45);
        }
        .auth-brand-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #ffffff, #c7d2fe);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .auth-brand-top h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: -0.02em;
            margin-bottom: 0.3rem;
        }
        .auth-brand-top p {
            font-size: 0.88rem;
            color: #94a3b8;
            line-height: 1.5;
        }
        .auth-field {
            margin-bottom: 1.05rem;
        }
        .auth-field label {
            display: block;
            font-size: 0.83rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 0.4rem;
        }
        .auth-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .auth-input-icon {
            position: absolute;
            left: 0.95rem;
            color: #64748b;
            pointer-events: none;
            display: flex;
        }
        .auth-input-box input {
            width: 100%;
            background: rgba(15, 23, 42, 0.75);
            border: 1.5px solid rgba(148, 163, 184, 0.22);
            color: #f8fafc;
            border-radius: 12px;
            padding: 0.74rem 2.75rem 0.74rem 2.75rem;
            font-size: 0.93rem;
            transition: all 0.2s ease;
        }
        .auth-input-box input::placeholder {
            color: #64748b;
            font-style: normal;
        }
        .auth-input-box input:hover {
            border-color: rgba(129, 140, 248, 0.45);
        }
        .auth-input-box input:focus {
            background: rgba(15, 23, 42, 0.95);
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.18);
        }
        .auth-pw-btn {
            position: absolute;
            right: 0.65rem;
            background: rgba(255, 255, 255, 0.06);
            border: none;
            color: #94a3b8;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: grid;
            place-items: center;
        }
        .auth-submit-btn {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.85rem 1.4rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #0284c7 100%);
            color: #fff;
            border: none;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.5);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .auth-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -5px rgba(79, 70, 229, 0.65);
        }
        .auth-bottom-links {
            margin-top: 1.45rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 0.88rem;
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }
        .auth-bottom-links a {
            color: #818cf8;
            font-weight: 600;
            text-decoration: none;
        }
        .auth-bottom-links a:hover {
            color: #a5b4fc;
            text-decoration: underline;
        }
        .auth-back-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            color: #64748b !important;
            font-weight: 500 !important;
            font-size: 0.83rem;
        }
        .auth-back-home:hover {
            color: #cbd5e1 !important;
            text-decoration: none !important;
        }
    </style>
</head>
<body class="auth-body">
    <div class="auth-ambient"></div>
    <div class="auth-grid-overlay"></div>

    <div class="auth-shell">
        <div class="auth-glass-card">
            <div class="auth-brand-top">
                <a href="index.php" class="auth-brand-link">
                    <span class="auth-brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    </span>
                    <span class="auth-brand-name"><?= APP_NAME ?></span>
                </a>
                <h1>Buat Akun Anggota</h1>
                <p>Bergabung bersama komunitas untuk mengunggah karya, menulis cerita, dan berdiskusi.</p>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-info" style="margin-bottom:1.15rem;"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-error" style="margin-bottom:1.15rem;"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="auth-field">
                    <label for="display_name">Nama Tampilan</label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="display_name" name="display_name" maxlength="60" placeholder="Contoh: Kelvin" autocomplete="name" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="email">Alamat Email <span class="required">*</span></label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="email" name="email" required placeholder="nama@email.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Kata Sandi <span class="required">*</span></label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="Minimal 4 karakter" autocomplete="new-password" data-pw-meter="pwMeter" data-pw-hint="pwHint">
                        <button type="button" class="auth-pw-btn pw-toggle" data-target="password" aria-label="Tampilkan password">👁</button>
                    </div>
                    <div class="pw-meter" id="pwMeter"><span></span></div>
                    <div class="pw-hint" id="pwHint" style="color:#94a3b8;"></div>
                </div>

                <div class="auth-field">
                    <label for="confirm">Konfirmasi Kata Sandi <span class="required">*</span></label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </span>
                        <input type="password" id="confirm" name="confirm" required placeholder="Ulangi kata sandi" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn auth-submit-btn">Daftar Sekarang</button>
            </form>

            <div class="auth-bottom-links">
                <div>Sudah punya akun? <a href="login.php">Masuk di sini</a></div>
                <a href="index.php" class="auth-back-home">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    <span>Kembali ke Beranda</span>
                </a>
            </div>
        </div>
    </div>

    <script src="js/main.js?v=2.1"></script>
</body>
</html>
