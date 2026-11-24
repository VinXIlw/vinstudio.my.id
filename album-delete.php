<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireCsrf(false, 'albums.php');

$user = currentUser();
$id   = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ?");
    $stmt->execute([$id]);
    $album = $stmt->fetch();

    if ($album && ((int)$album['user_id'] === $user['id'] || $user['role'] === 'admin')) {
        if (!empty($album['photo']) && $album['photo'] !== 'no-image.png') {
            $filePath = __DIR__ . '/uploads/' . basename($album['photo']);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $pdo->prepare("DELETE FROM comments WHERE target_type = 'album' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'album' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$id]);

        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'album:hapus', 'Hapus album #' . $id . ': ' . ($album['title'] ?? ''));
        safeRedirect('albums.php?msg=' . urlencode('Album berhasil dihapus secara permanen.'));
    }
}

safeRedirect('albums.php?err=' . urlencode('Album tidak ditemukan atau akses ditolak.'));
