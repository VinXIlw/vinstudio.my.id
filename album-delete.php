<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ?");
    $stmt->execute([$id]);
    $album = $stmt->fetch();

    if ($album && ($album['user_id'] == $user['id'] || $user['role'] === 'admin')) {
        // Hapus file fisik jika ada
        if (!empty($album['photo']) && $album['photo'] !== 'no-image.png') {
            $filePath = __DIR__ . '/uploads/' . $album['photo'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Hapus like & komentar terkait
        $pdo->prepare("DELETE FROM comments WHERE target_type = 'album' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'album' AND target_id = ?")->execute([$id]);

        // Hapus album
        $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$id]);
    }
}

header("Location: albums.php?msg=" . urlencode("Album berhasil dihapus."));
exit;
