<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireCsrf(false, 'dashboard.php');

$user   = currentUser();
$action = $_POST['action'] ?? 'update_profile';

// ============================================================
// 1. UPDATE INFO PROFIL (Nama, Spesialisasi, Bio, Foto Avatar)
// ============================================================
if ($action === 'update_profile') {
    $displayName = trim($_POST['display_name'] ?? '');
    $specialty   = trim($_POST['specialty'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $resetAvatar = !empty($_POST['reset_avatar']);

    if ($displayName === '' || mb_strlen($displayName) < 2) {
        safeRedirect('dashboard.php?err=' . urlencode('Nama tampilan minimal 2 karakter.'));
    }
    if (mb_strlen($displayName) > 60) {
        $displayName = mb_substr($displayName, 0, 60);
    }
    if (mb_strlen($specialty) > 70) {
        $specialty = mb_substr($specialty, 0, 70);
    }
    if (mb_strlen($bio) > 300) {
        $bio = mb_substr($bio, 0, 300);
    }

    $stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmtUser->execute([$user['id']]);
    $currentAvatar = $stmtUser->fetchColumn();
    $finalAvatar   = $currentAvatar;

    if ($resetAvatar) {
        if (!empty($currentAvatar)) {
            $oldPath = __DIR__ . '/uploads/' . basename($currentAvatar);
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $finalAvatar = null;
    } elseif (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadRes = saveUploadedImage($_FILES['avatar'], 'avatar-' . $user['id']);
        if (!$uploadRes['ok']) {
            safeRedirect('dashboard.php?err=' . urlencode($uploadRes['error']));
        }

        if (!empty($currentAvatar) && $currentAvatar !== $uploadRes['filename']) {
            $oldPath = __DIR__ . '/uploads/' . basename($currentAvatar);
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $finalAvatar = $uploadRes['filename'];
    }

    $stmtUpdate = $pdo->prepare("UPDATE users SET display_name = ?, specialty = ?, bio = ?, avatar = ? WHERE id = ?");
    $stmtUpdate->execute([$displayName, $specialty, $bio, $finalAvatar, $user['id']]);

    $_SESSION['display_name'] = $displayName;
    $_SESSION['specialty']    = $specialty;
    $_SESSION['bio']          = $bio;
    $_SESSION['avatar']       = $finalAvatar;

    logActivity($pdo, $user['id'], $user['email'], $displayName, 'profile:update', 'Memperbarui identitas & foto profil');
    safeRedirect('dashboard.php?msg=' . urlencode('Profil kamu berhasil diperbarui!'));
}

// ============================================================
// 2. GANTI KATA SANDI (PASSWORD) DENGAN REGENERASI SESI
// ============================================================
if ($action === 'change_password') {
    if (!checkRateLimit($pdo, 'pw_change_' . $user['id'], 5, 600)) {
        safeRedirect('dashboard.php?tab=keamanan&err=' . urlencode('Terlalu banyak percobaan gagal. Coba lagi dalam beberapa menit.'));
    }

    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if ($currentPass === '' || $newPass === '') {
        safeRedirect('dashboard.php?tab=keamanan&err=' . urlencode('Semua kolom kata sandi wajib diisi.'));
    }
    if ($newPass !== $confirmPass) {
        safeRedirect('dashboard.php?tab=keamanan&err=' . urlencode('Konfirmasi kata sandi baru tidak cocok.'));
    }
    if (strlen($newPass) < 6) {
        safeRedirect('dashboard.php?tab=keamanan&err=' . urlencode('Kata sandi baru minimal 6 karakter demi keamanan.'));
    }

    $stmtCheck = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtCheck->execute([$user['id']]);
    $storedHash = $stmtCheck->fetchColumn();

    if (!$storedHash || !password_verify($currentPass, $storedHash)) {
        recordRateLimit($pdo, 'pw_change_' . $user['id']);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'profile:password-fail', 'Kata sandi lama salah');
        safeRedirect('dashboard.php?tab=keamanan&err=' . urlencode('Kata sandi saat ini tidak sesuai.'));
    }

    clearRateLimit($pdo, 'pw_change_' . $user['id']);
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
    session_regenerate_id(true);

    logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'profile:password-success', 'Kata sandi berhasil diperbarui');
    safeRedirect('dashboard.php?tab=keamanan&msg=' . urlencode('Kata sandi berhasil diperbarui dan sesi diamankan!'));
}

safeRedirect('dashboard.php');
