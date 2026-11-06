<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['display_name'] ?? '');
    if (empty($newName)) {
        header("Location: dashboard.php?err=" . urlencode("Nama tampilan tidak boleh kosong."));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
    $stmt->execute([$newName, $user['id']]);

    $_SESSION['display_name'] = $newName;
    header("Location: dashboard.php?msg=" . urlencode("Nama tampilan berhasil diperbarui."));
    exit;
}

header("Location: dashboard.php");
exit;
