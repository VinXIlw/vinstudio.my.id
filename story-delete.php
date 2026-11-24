<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireCsrf(false, 'stories.php');

$user = currentUser();
$id   = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$id]);
    $story = $stmt->fetch();

    if ($story && ((int)$story['user_id'] === $user['id'] || $user['role'] === 'admin')) {
        $pdo->prepare("DELETE FROM comments WHERE target_type = 'story' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'story' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stories WHERE id = ?")->execute([$id]);

        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'artikel:hapus', 'Hapus artikel #' . $id . ': ' . ($story['title'] ?? ''));
        safeRedirect('stories.php?msg=' . urlencode('Artikel berhasil dihapus secara permanen.'));
    }
}

safeRedirect('stories.php?err=' . urlencode('Artikel tidak ditemukan atau akses ditolak.'));
