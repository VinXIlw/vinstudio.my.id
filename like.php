<?php
require_once __DIR__ . '/config.php';

$wantsJson = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

if (!currentUser()) {
    if ($wantsJson) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Silakan masuk terlebih dahulu untuk menyukai karya.']);
        exit;
    }
    requireLogin();
}

requireCsrf($wantsJson, $_POST['return_url'] ?? 'index.php');

$user       = currentUser();
$targetType = $_POST['target_type'] ?? '';
$targetId   = (int)($_POST['target_id'] ?? 0);
$returnUrl  = sanitizeReturnUrl($_POST['return_url'] ?? 'index.php');

if (!in_array($targetType, ['album', 'story'], true) || $targetId <= 0) {
    if ($wantsJson) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Target tidak valid.']);
        exit;
    }
    safeRedirect($returnUrl);
}

$stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?");
$stmt->execute([$user['id'], $targetType, $targetId]);
$existing = $stmt->fetch();

$liked = false;
if ($existing) {
    $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$existing['id']]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id) VALUES (?, ?, ?)")->execute([$user['id'], $targetType, $targetId]);
    $liked = true;
}

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE target_type = ? AND target_id = ?");
$cntStmt->execute([$targetType, $targetId]);
$totalLikes = (int)$cntStmt->fetchColumn();

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => true,
        'liked' => $liked,
        'count' => $totalLikes,
    ]);
    exit;
}

safeRedirect($returnUrl);
