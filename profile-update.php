<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$action = $_POST['action'] ?? 'update_profile';

// ============================================================
// 1. UPDATE INFO PROFIL (Nama, Bio, Foto Profil / Avatar)
// ============================================================
if ($action === 'update_profile') {
    $displayName = trim($_POST['display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $resetAvatar = !empty($_POST['reset_avatar']);

    if (empty($displayName) || mb_strlen($displayName) < 2) {
        header("Location: dashboard.php?err=" . urlencode("Nama tampilan minimal harus 2 karakter."));
        exit;
    }
    if (mb_strlen($displayName) > 60) {
        $displayName = mb_substr($displayName, 0, 60);
    }
    if (mb_strlen($bio) > 300) {
        $bio = mb_substr($bio, 0, 300);
    }

    // Ambil data user terkini dari database
    $stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmtUser->execute([$user['id']]);
    $currentAvatar = $stmtUser->fetchColumn();
    $finalAvatar = $currentAvatar;

    // Cek jika pengguna memilih untuk reset/hapus foto profil
    if ($resetAvatar) {
        if (!empty($currentAvatar) && file_exists(__DIR__ . '/uploads/' . $currentAvatar)) {
            @unlink(__DIR__ . '/uploads/' . $currentAvatar);
        }
        $finalAvatar = null;
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadRes = saveUploadedImage($_FILES['avatar'], 'avatar-' . $user['id']);
        if (!$uploadRes['ok']) {
            header("Location: dashboard.php?err=" . urlencode($uploadRes['error']));
            exit;
        }

        // Hapus avatar lama jika berbeda
        if (!empty($currentAvatar) && $currentAvatar !== $uploadRes['filename'] && file_exists(__DIR__ . '/uploads/' . $currentAvatar)) {
            @unlink(__DIR__ . '/uploads/' . $currentAvatar);
        }
        $finalAvatar = $uploadRes['filename'];
    }

    // Simpan ke database
    $stmtUpdate = $pdo->prepare("UPDATE users SET display_name = ?, bio = ?, avatar = ? WHERE id = ?");
    $stmtUpdate->execute([$displayName, $bio, $finalAvatar, $user['id']]);

    // Perbarui sesi pengguna
    $_SESSION['display_name'] = $displayName;
    $_SESSION['bio'] = $bio;
    $_SESSION['avatar'] = $finalAvatar;

    logActivity($pdo, $user['id'], $user['email'], $displayName, 'profile:update', 'Pengguna memperbarui data profil');

    header("Location: dashboard.php?msg=" . urlencode("Profil kamu berhasil diperbarui!"));
    exit;
}

// ============================================================
// 2. GANTI KATA SANDI (PASSWORD)
// ============================================================
if ($action === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        header("Location: dashboard.php?tab=keamanan&err=" . urlencode("Semua kolom kata sandi wajib diisi."));
        exit;
    }

    if ($newPass !== $confirmPass) {
        header("Location: dashboard.php?tab=keamanan&err=" . urlencode("Konfirmasi kata sandi baru tidak cocok."));
        exit;
    }

    if (strlen($newPass) < 4) {
        header("Location: dashboard.php?tab=keamanan&err=" . urlencode("Kata sandi baru minimal 4 karakter."));
        exit;
    }

    // Verifikasi kata sandi saat ini
    $stmtCheck = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtCheck->execute([$user['id']]);
    $storedHash = $stmtCheck->fetchColumn();

    $valid = false;
    if ($storedHash) {
        if (password_verify($currentPass, $storedHash) || $currentPass === $storedHash) {
            $valid = true;
        }
    }

    if (!$valid) {
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'profile:password-fail', 'Percobaan ganti password gagal (password lama salah)');
        header("Location: dashboard.php?tab=keamanan&err=" . urlencode("Kata sandi saat ini salah."));
        exit;
    }

    // Update password baru
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmtUpdate->execute([$newHash, $user['id']]);

    logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'profile:password-success', 'Pengguna berhasil mengganti kata sandi');

    header("Location: dashboard.php?tab=keamanan&msg=" . urlencode("Kata sandi berhasil diubah! Gunakan sandi baru pada login berikutnya."));
    exit;
}

header("Location: dashboard.php");
exit;
