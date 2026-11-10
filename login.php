<?php
require_once __DIR__ . '/config.php';

if (currentUser()) {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Masuk Akun';
$currentPage = 'login';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$returnUrl = $_GET['r'] ?? 'index.php';

// Pastikan returnUrl aman (hanya path internal)
if (strpos($returnUrl, 'http://') === 0 || strpos($returnUrl, 'https://') === 0 || strpos($returnUrl, '//') === 0) {
    $returnUrl = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $err = 'Email dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $validPass = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $validPass = true;
            } elseif ($password === $user['password']) {
                $validPass = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }
        }

        if (!$user || !$validPass) {
            logActivity($pdo, null, $email, '', 'login-gagal', 'Password salah atau akun tidak ditemukan');
            $err = 'Email atau password yang kamu masukkan salah.';
        } elseif ($user['is_banned']) {
            logActivity($pdo, $user['id'], $email, $user['display_name'], 'login-ditolak', 'Akun diblokir');
            $err = 'Akun ini sedang dinonaktifkan oleh administrator.';
        } else {
            $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];

            logActivity($pdo, $user['id'], $email, $user['display_name'], 'login', 'Login berhasil');

            header("Location: " . $returnUrl);
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
    <title>Masuk Akun — <?= APP_NAME ?></title>
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
                radial-gradient(650px circle at 15% 15%, rgba(99, 102, 241, 0.25), transparent 60%),
                radial-gradient(600px circle at 85% 82%, rgba(14, 165, 233, 0.2), transparent 60%),
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
            max-width: 440px;
            padding: 2rem 1.25rem;
        }
        .auth-glass-card {
            background: rgba(17, 24, 39, 0.78);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 2.5rem 2.25rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            animation: fadeUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .auth-brand-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 1.85rem;
        }
        .auth-brand-link {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
            margin-bottom: 1.15rem;
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
            font-size: 1.55rem;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }
        .auth-brand-top p {
            font-size: 0.9rem;
            color: #94a3b8;
            line-height: 1.5;
        }
        .auth-field {
            margin-bottom: 1.2rem;
        }
        .auth-field label {
            display: block;
            font-size: 0.84rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 0.45rem;
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
            padding: 0.78rem 2.75rem 0.78rem 2.75rem;
            font-size: 0.94rem;
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
            transition: background 0.2s ease, color 0.2s ease;
        }
        .auth-pw-btn:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
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
            margin-top: 1.6rem;
            padding-top: 1.35rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 0.88rem;
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
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
                <h1>Selamat Datang Kembali</h1>
                <p>Masuk ke akunmu untuk berbagi karya, menulis cerita, dan mengobrol di Global Live Chat.</p>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-info" style="margin-bottom:1.25rem;"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-error" style="margin-bottom:1.25rem;"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form action="login.php?r=<?= urlencode($returnUrl) ?>" method="POST">
                <div class="auth-field">
                    <label for="email">Alamat Email</label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="email" name="email" required autofocus placeholder="nama@email.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Kata Sandi</label>
                    <div class="auth-input-box">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="Masukkan kata sandi" autocomplete="current-password">
                        <button type="button" class="auth-pw-btn pw-toggle" data-target="password" aria-label="Tampilkan password">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn auth-submit-btn">Masuk ke Akun</button>
            </form>

            <div class="auth-bottom-links">
                <div>Belum punya akun? <a href="register.php">Daftar akun baru</a></div>
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
