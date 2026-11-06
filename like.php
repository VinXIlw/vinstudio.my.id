<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$targetType = $_POST['target_type'] ?? '';
$targetId = (int)($_POST['target_id'] ?? 0);
$returnUrl = $_POST['return_url'] ?? 'index.php';

if (in_array($targetType, ['album', 'story']) && $targetId > 0) {
    // Cek apakah sudah like
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?");
    $stmt->execute([$user['id'], $targetType, $targetId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $del = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $del->execute([$existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id) VALUES (?, ?, ?)");
        $ins->execute([$user['id'], $targetType, $targetId]);
    }
}

header("Location: " . $returnUrl);
exit;
