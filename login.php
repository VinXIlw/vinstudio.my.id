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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $newName = trim($_POST['display_name'] ?? '');

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
                // Password lama plain text, verifikasi valid lalu auto-upgrade ke bcrypt
                $validPass = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }
        }

        if (!$user || !$validPass) {
            logActivity($pdo, null, $email, '', 'login-gagal', 'Password salah atau akun tidak ditemukan');
            $err = 'Email atau password yang Anda masukkan salah!';
        } elseif ($user['is_banned']) {
            logActivity($pdo, $user['id'], $email, $user['display_name'], 'login-ditolak', 'Akun diblokir');
            $err = 'Akun ini dinonaktifkan oleh administrator.';
        } else {
            // Update nama tampilan jika diisi berbeda
            $finalName = $user['display_name'];
            if (!empty($newName) && $newName !== $user['display_name']) {
                $finalName = $newName;
                $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?")->execute([$finalName, $user['id']]);
            }

            // Update waktu login terakhir
            $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['display_name'] = $finalName;
            $_SESSION['role'] = $user['role'];

            logActivity($pdo, $user['id'], $email, $finalName, 'login', 'Login berhasil');

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
    <title>Masuk - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
</head>
<body>
    <main>
        <section class="auth-page">
            <div class="auth-card">
                <div class="auth-header">
                    <a href="index.php" class="logo" style="justify-content:center;text-decoration:none;margin-bottom:1rem;">
                        <span class="logo-icon">&#128187;</span>
                        <span class="logo-text"><?= APP_NAME ?></span>
                    </a>
                    <h2>Selamat Datang Kembali</h2>
                    <p>Masuk untuk berbagi album, cerita, dan berdiskusi</p>
                </div>

                <?php if ($msg): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
                <?php endif; ?>

                <form action="login.php?r=<?= urlencode($returnUrl) ?>" method="POST">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required autofocus placeholder="nama@email.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="display_name">Nama Tampilan <span class="label-hint">(opsional, untuk mengubah namamu)</span></label>
                        <input type="text" id="display_name" name="display_name" maxlength="60" placeholder="Contoh: Kelvin">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Masuk ke Akun</button>
                </form>

                <p class="auth-footer">
                    Belum punya akun? <a href="register.php">Daftar sekarang</a>
                </p>
                <p style="text-align:center;margin-top:1rem;">
                    <a href="admin-login.php">Masuk ke Panel Administrator</a>
                </p>
                <p style="text-align:center;margin-top:1rem;">
                    <a href="index.php">&larr; Kembali ke Beranda</a>
                </p>
            </div>
        </section>
    </main>

    <script src="js/main.js"></script>
</body>
</html>
