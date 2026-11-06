<?php
require_once __DIR__ . '/config.php';

$user = currentUser();
if ($user && $user['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

$pageTitle = 'Login Administrator';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $err = 'Email dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        $valid = false;
        if ($u) {
            if (password_verify($password, $u['password'])) {
                $valid = true;
            } elseif ($password === $u['password']) {
                $valid = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $u['id']]);
            }
        }

        if (!$u || !$valid) {
            logActivity($pdo, null, $email, '', 'admin-login-gagal', 'Email atau password salah');
            $err = 'Email atau password salah.';
        } elseif ($u['role'] !== 'admin') {
            logActivity($pdo, $u['id'], $email, $u['display_name'], 'admin-login-ditolak', 'Bukan akun admin');
            $err = 'Akun ini bukan administrator. Silakan login di halaman masuk pengguna biasa.';
        } else {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['email'] = $u['email'];
            $_SESSION['display_name'] = $u['display_name'];
            $_SESSION['role'] = $u['role'];

            $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$u['id']]);
            logActivity($pdo, $u['id'], $email, $u['display_name'], 'admin-login', 'Berhasil masuk ke panel admin');

            header("Location: admin.php");
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
    <title>Login Administrator - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="img/placeholder.svg" type="image/svg+xml">
</head>
<body>
    <main>
        <section class="auth-page">
            <div class="auth-card">
                <div class="auth-header">
                    <a href="index.php" class="logo" style="justify-content:center;text-decoration:none;margin-bottom:1rem;">
                        <span class="logo-icon">&#9881;</span>
                        <span class="logo-text"><?= APP_NAME ?> Admin</span>
                    </a>
                    <h2>Panel Administrator</h2>
                    <p>Masuk sebagai admin untuk mengelola portal</p>
                </div>

                <?php if ($msg): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
                <?php endif; ?>

                <form action="admin-login.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email Admin</label>
                        <input type="email" id="email" name="email" required autofocus placeholder="admin@email.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Masuk sebagai Admin</button>
                </form>

                <p class="auth-footer">
                    <a href="login.php">Login sebagai pengguna biasa</a>
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
