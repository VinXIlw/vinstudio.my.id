<?php
/**
 * XI TKJ 1 - Global Live Chat AJAX API
 * Mendukung operasi: fetch, send, delete, clear_all (admin)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$user = currentUser();
$action = $_REQUEST['action'] ?? 'fetch';

function formatChatMessage($row, $currentUser) {
    $isMine = $currentUser && ((int)$row['user_id'] === (int)$currentUser['id']);
    $isAdmin = $currentUser && ($currentUser['role'] === 'admin');
    $time = strtotime($row['created_at']);
    $timeClock = $time ? date('H:i', $time) : '';

    return [
        'id'           => (int)$row['id'],
        'user_id'      => (int)$row['user_id'],
        'display_name' => $row['display_name'] ?? 'Pengguna',
        'role'         => $row['role'] ?? 'user',
        'initials'     => initials($row['display_name'] ?? 'Pengguna'),
        'avatar_class' => avatarClass($row['display_name'] ?? 'Pengguna'),
        'message'      => $row['message'],
        'reply_to_id'  => $row['reply_to_id'] ? (int)$row['reply_to_id'] : null,
        'reply_user'   => $row['reply_user'] ?? null,
        'reply_text'   => isset($row['reply_text']) ? mb_strimwidth($row['reply_text'], 0, 80, '...') : null,
        'created_at'   => $row['created_at'],
        'time_clock'   => $timeClock,
        'time_ago'     => timeAgo($row['created_at']),
        'is_mine'      => $isMine,
        'can_delete'   => ($isMine || $isAdmin),
    ];
}

try {
    if ($action === 'fetch') {
        $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
        $limit = isset($_GET['limit']) ? min(120, max(1, (int)$_GET['limit'])) : 80;

        if ($afterId > 0) {
            $stmt = $pdo->prepare("
                SELECT m.*, u.display_name, u.role,
                       rm.message AS reply_text, ru.display_name AS reply_user
                FROM chat_messages m
                JOIN users u ON m.user_id = u.id
                LEFT JOIN chat_messages rm ON m.reply_to_id = rm.id
                LEFT JOIN users ru ON rm.user_id = ru.id
                WHERE m.id > ?
                ORDER BY m.id ASC
                LIMIT 100
            ");
            $stmt->execute([$afterId]);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT m.*, u.display_name, u.role,
                           rm.message AS reply_text, ru.display_name AS reply_user
                    FROM chat_messages m
                    JOIN users u ON m.user_id = u.id
                    LEFT JOIN chat_messages rm ON m.reply_to_id = rm.id
                    LEFT JOIN users ru ON rm.user_id = ru.id
                    ORDER BY m.id DESC
                    LIMIT {$limit}
                ) sub
                ORDER BY sub.id ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }

        $messages = [];
        foreach ($rows as $r) {
            $messages[] = formatChatMessage($r, $user);
        }

        // Ambil daftar anggota komunitas & status aktif (online jika aktif dalam 10 menit terakhir)
        $usersRows = $pdo->query("
            SELECT id, display_name, role, last_login_at
            FROM users
            WHERE is_banned = 0
            ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, last_login_at DESC, id ASC
            LIMIT 30
        ")->fetchAll();

        $members = [];
        $onlineCount = 0;
        $now = time();
        foreach ($usersRows as $u) {
            $lastTs = $u['last_login_at'] ? strtotime($u['last_login_at']) : 0;
            $isOnline = ($lastTs && ($now - $lastTs) <= 600) || ($user && (int)$u['id'] === (int)$user['id']);
            if ($isOnline) $onlineCount++;
            $members[] = [
                'id'           => (int)$u['id'],
                'display_name' => $u['display_name'],
                'role'         => $u['role'],
                'initials'     => initials($u['display_name']),
                'avatar_class' => avatarClass($u['display_name']),
                'online'       => $isOnline,
                'last_seen'    => $u['last_login_at'] ? timeAgo($u['last_login_at']) : 'Baru bergabung'
            ];
        }

        $totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();
        $allIds = $pdo->query("SELECT id FROM chat_messages ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'ok'             => true,
            'messages'       => $messages,
            'members'        => $members,
            'online_count'   => max(1, $onlineCount),
            'total_messages' => $totalMessages,
            'active_ids'     => array_map('intval', $allIds)
        ]);
        exit;
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Silakan masuk terlebih dahulu untuk mengirim pesan.']);
            exit;
        }

        $uStmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
        $uStmt->execute([$user['id']]);
        if ((int)$uStmt->fetchColumn() === 1) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Akun kamu sedang dibatasi.']);
            exit;
        }

        $message = trim($_POST['message'] ?? '');
        $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

        if ($message === '') {
            echo json_encode(['ok' => false, 'error' => 'Pesan tidak boleh kosong.']);
            exit;
        }
        if (mb_strlen($message) > 600) {
            $message = mb_substr($message, 0, 600);
        }

        $ins = $pdo->prepare("INSERT INTO chat_messages (user_id, message, reply_to_id) VALUES (?, ?, ?)");
        $ins->execute([$user['id'], $message, $replyToId]);
        $newId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

        $sel = $pdo->prepare("
            SELECT m.*, u.display_name, u.role,
                   rm.message AS reply_text, ru.display_name AS reply_user
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN chat_messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.user_id = ru.id
            WHERE m.id = ?
        ");
        $sel->execute([$newId]);
        $row = $sel->fetch();

        echo json_encode([
            'ok'      => true,
            'message' => formatChatMessage($row, $user)
        ]);
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Tidak diizinkan.']);
            exit;
        }

        $msgId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();

        if (!$msg) {
            echo json_encode(['ok' => true, 'deleted_id' => $msgId]);
            exit;
        }

        if ((int)$msg['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Kamu tidak berhak menghapus pesan ini.']);
            exit;
        }

        $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$msgId]);
        echo json_encode(['ok' => true, 'deleted_id' => $msgId]);
        exit;
    }

    if ($action === 'clear_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Hanya Administrator yang dapat membersihkan seluruh chat.']);
            exit;
        }
        $pdo->exec("DELETE FROM chat_messages");
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Aksi tidak dikenali.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
