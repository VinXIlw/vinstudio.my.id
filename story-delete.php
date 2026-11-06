<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$id]);
    $story = $stmt->fetch();

    if ($story && ($story['user_id'] == $user['id'] || $user['role'] === 'admin')) {
        $pdo->prepare("DELETE FROM comments WHERE target_type = 'story' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'story' AND target_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stories WHERE id = ?")->execute([$id]);
    }
}

header("Location: stories.php?msg=" . urlencode("Cerita berhasil dihapus."));
exit;
