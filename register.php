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
        $err = 'Email dan password wajib diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Format email tidak valid!';
    } elseif ($password !== $confirm) {
        $err = 'Konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 4) {
        $err = 'Password minimal 4 karakter!';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $err = 'Email sudah digunakan oleh akun lain!';
        } else {
            $finalName = !empty($displayName) ? $displayName : explode('@', $email)[0];
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$email, $finalName, $hashed]);
            $newId = $pdo->lastInsertId();

            logActivity($pdo, $newId, $email, $finalName, 'register', 'Pendaftaran akun baru');

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
    <title>Daftar - <?= APP_NAME ?></title>
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
                    <h2>Buat Akun Baru</h2>
                    <p>Bergabung untuk mulai membagikan foto, karya, dan cerita</p>
                </div>

                <?php if ($msg): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required autofocus placeholder="nama@email.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="display_name">Nama Tampilan</label>
                        <input type="text" id="display_name" name="display_name" maxlength="60" placeholder="Contoh: Kelvin" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="Minimal 4 karakter" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm">Konfirmasi Password <span class="required">*</span></label>
                        <input type="password" id="confirm" name="confirm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Daftar Akun Baru</button>
                </form>

                <p class="auth-footer">
                    Sudah punya akun? <a href="login.php">Masuk di sini</a>
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
