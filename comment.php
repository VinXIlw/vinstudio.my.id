<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$action = $_POST['action'] ?? '';
$returnUrl = $_POST['return_url'] ?? 'index.php';

if ($action === 'add') {
    $targetType = $_POST['target_type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (in_array($targetType, ['album', 'story']) && $targetId > 0 && !empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, target_type, target_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $targetType, $targetId, $content]);
    }
} elseif ($action === 'delete') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    if ($commentId > 0) {
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $c = $stmt->fetch();

        if ($c && ($c['user_id'] == $user['id'] || $user['role'] === 'admin')) {
            $del = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $del->execute([$commentId]);
        }
    }
}

header("Location: " . $returnUrl);
exit;
