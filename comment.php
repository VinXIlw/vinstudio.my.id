<?php
require_once __DIR__ . '/config.php';
requireLogin();

$returnUrl = sanitizeReturnUrl($_POST['return_url'] ?? 'index.php');
requireCsrf(false, $returnUrl);

$user   = currentUser();
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    if (!checkRateLimit($pdo, 'comment_post', 6, 60)) {
        $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
        safeRedirect($returnUrl . $sep . 'err=' . urlencode('Terlalu cepat mengirim komentar. Tunggu beberapa detik.'));
    }

    $targetType = $_POST['target_type'] ?? '';
    $targetId   = (int)($_POST['target_id'] ?? 0);
    $content    = trim($_POST['content'] ?? '');

    if (in_array($targetType, ['album', 'story'], true) && $targetId > 0 && $content !== '') {
        if (mb_strlen($content) > 1000) {
            $content = mb_substr($content, 0, 1000);
        }
        recordRateLimit($pdo, 'comment_post');
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, target_type, target_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $targetType, $targetId, $content]);
        $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
        safeRedirect($returnUrl . $sep . 'msg=' . urlencode('Komentar berhasil diterbitkan.'));
    }
} elseif ($action === 'delete') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    if ($commentId > 0) {
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $c = $stmt->fetch();

        if ($c && ((int)$c['user_id'] === $user['id'] || $user['role'] === 'admin')) {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            safeRedirect($returnUrl . $sep . 'msg=' . urlencode('Komentar telah dihapus.'));
        }
    }
}

safeRedirect($returnUrl);
