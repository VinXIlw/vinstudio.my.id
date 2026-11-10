<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: stories.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.display_name AS username
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pageTitle = $story['title'];
$currentPage = 'stories';

$cStmt = $pdo->prepare("
    SELECT c.*, u.display_name, u.role
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.target_type = 'story' AND c.target_id = ?
    ORDER BY c.created_at ASC
");
$cStmt->execute([$id]);
$comments = $cStmt->fetchAll();

$lStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = ?");
$lStmt->execute([$id]);
$likeCount = (int)$lStmt->fetchColumn();

$liked = false;
$user = currentUser();
if ($user) {
    $uStmt = $pdo->prepare("SELECT id FROM likes WHERE target_type = 'story' AND target_id = ? AND user_id = ?");
    $uStmt->execute([$id, $user['id']]);
    $liked = (bool)$uStmt->fetch();
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<style>
    .story-article {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 2.75rem;
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
    }
    .story-content-body {
        font-size: 1.12rem;
        line-height: 1.88;
        color: var(--text-soft);
        margin-top: 1.85rem;
        white-space: pre-line;
        letter-spacing: 0.01em;
    }
    .like-bar {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 2.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--border);
        flex-wrap: wrap;
    }
    .like-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1.2rem;
        border-radius: var(--radius-pill);
        border: 1.5px solid var(--border-strong);
        background: var(--white);
        color: var(--text-soft);
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all var(--t) var(--ease);
    }
    .like-btn:hover {
        border-color: #f43f5e;
        color: #f43f5e;
        background: rgba(244, 63, 94, 0.08);
        transform: scale(1.04);
    }
    .like-btn.liked {
        background: rgba(244, 63, 94, 0.14);
        border-color: #f43f5e;
        color: #e11d48;
    }
    .comments-section {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.75rem 2rem;
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
    }
    .comment-item {
        display: flex;
        gap: 1rem;
        padding: 1.1rem 0;
        border-bottom: 1px solid var(--border);
    }
    .comment-item:last-child {
        border-bottom: none;
    }
    .comment-content-box {
        flex: 1;
    }
    .comment-author-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.35rem;
    }
    .comment-text {
        color: var(--text-soft);
        line-height: 1.6;
        word-break: break-word;
    }
</style>

<main>
    <div class="container" style="max-width:880px;">
        <div style="margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
            <a href="stories.php" class="btn btn-outline btn-sm">&larr; Kembali ke Semua Cerita</a>
            <button type="button" class="btn btn-outline btn-sm" data-share-url="">🔗 Bagikan Artikel</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- Artikel Cerita -->
        <article class="story-article page-anim">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div style="flex:1;">
                    <div style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--primary);font-weight:700;background:var(--primary-soft);padding:0.25rem 0.7rem;border-radius:999px;margin-bottom:0.85rem;">
                        <span>⏱ Estimasi baca: <?= readingTime($story['content']) ?></span>
                    </div>
                    <h1 style="font-size:2.2rem;line-height:1.25;color:var(--dark);letter-spacing:-0.02em;margin-bottom:0.85rem;">
                        <?= htmlspecialchars($story['title']) ?>
                    </h1>
                    <div class="user-cell" style="margin-top:0.75rem;">
                        <span class="user-avatar <?= avatarClass($story['username']) ?>" style="width:40px;height:40px;">
                            <?= initials($story['username']) ?>
                        </span>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($story['username']) ?></div>
                            <span class="user-email"><?= fmtDateTime($story['created_at']) ?> &middot; <?= timeAgo($story['created_at']) ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($user && ($story['user_id'] == $user['id'] || $user['role'] === 'admin')): ?>
                    <form action="story-delete.php" method="POST" onsubmit="return confirm('Apakah kamu yakin ingin menghapus cerita ini?');">
                        <input type="hidden" name="id" value="<?= $story['id'] ?>">
                        <button type="submit" class="btn btn-delete btn-sm">🗑 Hapus Cerita</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="story-content-body">
                <?= nl2br(htmlspecialchars($story['content'])) ?>
            </div>

            <!-- Tombol Like -->
            <div class="like-bar">
                <?php if ($user): ?>
                    <form action="like.php" method="POST" class="inline-form">
                        <input type="hidden" name="target_type" value="story">
                        <input type="hidden" name="target_id" value="<?= $story['id'] ?>">
                        <input type="hidden" name="return_url" value="story-detail.php?id=<?= $story['id'] ?>">
                        <button type="submit" class="like-btn <?= $liked ? 'liked' : '' ?>" title="<?= $liked ? 'Batal Suka' : 'Sukai Cerita' ?>">
                            <span><?= $liked ? '❤️' : '🤍' ?></span>
                            <span><?= $likeCount ?> Suka</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php?r=story-detail.php?id=<?= $story['id'] ?>" class="like-btn" title="Masuk untuk menyukai">
                        <span>🤍</span>
                        <span><?= $likeCount ?> Suka</span>
                    </a>
                <?php endif; ?>
                <span class="admin-hint">💬 <?= count($comments) ?> Komentar</span>
            </div>
        </article>

        <!-- Bagian Komentar -->
        <div class="comments-section page-anim">
            <h2 style="font-size:1.3rem;margin-bottom:1.25rem;color:var(--dark);display:flex;align-items:center;gap:0.5rem;">
                💬 Diskusi &amp; Komentar (<?= count($comments) ?>)
            </h2>

            <?php if ($user): ?>
                <form action="comment.php" method="POST" style="margin-bottom:2rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="target_type" value="story">
                    <input type="hidden" name="target_id" value="<?= $story['id'] ?>">
                    <input type="hidden" name="return_url" value="story-detail.php?id=<?= $story['id'] ?>">
                    <div class="form-group">
                        <label for="content">Tulis tanggapan atau apresiasi:</label>
                        <textarea id="content" name="content" rows="3" required placeholder="Tulis komentar kamu di sini..." style="resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Kirim Komentar</button>
                </form>
            <?php else: ?>
                <div class="empty-state" style="padding:1.5rem;margin-bottom:1.5rem;">
                    <p>Ingin berpartisipasi dalam diskusi? <a href="login.php?r=story-detail.php?id=<?= $story['id'] ?>">Masuk untuk menulis komentar</a></p>
                </div>
            <?php endif; ?>

            <?php if (empty($comments)): ?>
                <div class="empty-state" style="padding:2rem;">
                    <p class="admin-hint">Belum ada komentar untuk cerita ini. Jadilah yang pertama memberikan apresiasi!</p>
                </div>
            <?php else: ?>
                <div class="comments-list">
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <span class="user-avatar <?= avatarClass($c['display_name']) ?>" style="width:38px;height:38px;font-size:0.85rem;flex-shrink:0;">
                                <?= initials($c['display_name']) ?>
                            </span>
                            <div class="comment-content-box">
                                <div class="comment-author-row">
                                    <div>
                                        <strong><?= htmlspecialchars($c['display_name']) ?></strong>
                                        <?php if ($c['role'] === 'admin'): ?>
                                            <span class="role-badge role-admin" style="font-size:0.65rem;margin-left:0.3rem;">Admin</span>
                                        <?php endif; ?>
                                        <small class="admin-hint" style="margin-left:0.5rem;"><?= timeAgo($c['created_at']) ?></small>
                                    </div>
                                    <?php if ($user && ($c['user_id'] == $user['id'] || $user['role'] === 'admin')): ?>
                                        <form action="comment.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus komentar ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="return_url" value="story-detail.php?id=<?= $story['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);padding:0.2rem 0.5rem;font-size:0.75rem;">
                                                ✕ Hapus
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
