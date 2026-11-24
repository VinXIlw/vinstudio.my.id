<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    safeRedirect('stories.php');
}

$stmt = $pdo->prepare("
    SELECT s.*, u.display_name AS username, u.avatar AS user_avatar, u.specialty AS user_specialty
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

if (!isset($_SESSION['viewed_stories'][$id])) {
    $_SESSION['viewed_stories'][$id] = true;
    $pdo->prepare("UPDATE stories SET views = COALESCE(views, 0) + 1 WHERE id = ?")->execute([$id]);
    $story['views'] = (int)($story['views'] ?? 0) + 1;
}

$pageTitle   = $story['title'];
$currentPage = 'stories';
$user        = currentUser();
$msg         = $_GET['msg'] ?? '';
$err         = $_GET['err'] ?? '';

$cStmt = $pdo->prepare("
    SELECT c.*, u.display_name, u.role, u.avatar AS user_avatar
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
if ($user) {
    $ulStmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'story' AND target_id = ?");
    $ulStmt->execute([$user['id'], $id]);
    $liked = (bool)$ulStmt->fetch();
}

$canManage = $user && ((int)$story['user_id'] === $user['id'] || $user['role'] === 'admin');

include __DIR__ . '/includes/header.php';
?>

<main>
    <div style="max-width:880px;margin:0 auto;">
        <div style="margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.6rem;">
            <a href="stories.php" class="btn btn-outline btn-sm"><?= svgIcon('arrow-left', 15) ?> Semua Artikel</a>
            <button type="button" class="btn btn-outline btn-sm" data-share-url=""><?= svgIcon('share', 14) ?> Bagikan Artikel</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <article class="story-reader-card page-anim">
            <div class="detail-header-flex">
                <div style="flex:1;min-width:240px;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.65rem;flex-wrap:wrap;">
                        <span class="badge"><?= htmlspecialchars($story['category'] ?: 'Catatan Praktikum') ?></span>
                        <span class="mono-tag"><?= readingTime($story['content']) ?></span>
                        <span class="mono-tag"><?= svgIcon('eye', 12) ?> <?= (int)($story['views'] ?? 0) ?> dibaca</span>
                    </div>
                    <h1 style="font-size:clamp(1.65rem, 3.2vw, 2.3rem);line-height:1.25;color:var(--dark);margin-bottom:1rem;">
                        <?= htmlspecialchars($story['title']) ?>
                    </h1>
                    <a href="profile.php?member=<?= (int)$story['user_id'] ?>" class="user-cell" style="text-decoration:none;">
                        <?= renderAvatar($story['username'], $story['user_avatar'] ?? null, '42px') ?>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($story['username']) ?></div>
                            <span class="user-email">
                                <?= !empty($story['user_specialty']) ? htmlspecialchars($story['user_specialty']) . ' &middot; ' : '' ?>
                                <?= fmtDateTime($story['created_at']) ?>
                            </span>
                        </div>
                    </a>
                </div>

                <?php if ($canManage): ?>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <a href="story-add.php?edit=<?= (int)$story['id'] ?>" class="btn btn-outline btn-sm">
                        <?= svgIcon('edit', 14) ?> Edit Artikel
                    </a>
                    <form action="story-delete.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus artikel ini secara permanen beserta seluruh komentarnya?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$story['id'] ?>">
                        <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('trash', 14) ?> Hapus</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="story-content-body">
                <?= nl2br(htmlspecialchars($story['content'])) ?>
            </div>

            <div class="like-bar">
                <?php if ($user): ?>
                    <form action="like.php" method="POST" class="inline-form js-like-form" data-no-loading>
                        <?= csrfField() ?>
                        <input type="hidden" name="target_type" value="story">
                        <input type="hidden" name="target_id" value="<?= (int)$story['id'] ?>">
                        <input type="hidden" name="return_url" value="story-detail.php?id=<?= (int)$story['id'] ?>">
                        <button type="submit" class="like-btn <?= $liked ? 'liked' : '' ?>">
                            <?= svgIcon('heart', 16) ?>
                            <span data-like-count><?= $likeCount ?> Suka</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php?r=<?= urlencode('story-detail.php?id=' . $story['id']) ?>" class="like-btn" data-no-transition>
                        <?= svgIcon('heart', 16) ?>
                        <span><?= $likeCount ?> Suka</span>
                    </a>
                <?php endif; ?>
                <span class="admin-hint"><?= svgIcon('chat', 15) ?> <?= count($comments) ?> Komentar</span>
            </div>
        </article>

        <!-- Diskusi & Komentar -->
        <section class="comments-section page-anim">
            <h2 style="font-size:1.25rem;color:var(--dark);margin-bottom:1.2rem;display:flex;align-items:center;gap:0.5rem;">
                <?= svgIcon('chat', 19) ?> Diskusi &amp; Komentar (<?= count($comments) ?>)
            </h2>

            <?php if ($user): ?>
                <form action="comment.php" method="POST" style="margin-bottom:1.75rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="target_type" value="story">
                    <input type="hidden" name="target_id" value="<?= (int)$story['id'] ?>">
                    <input type="hidden" name="return_url" value="story-detail.php?id=<?= (int)$story['id'] ?>">
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <textarea name="content" rows="3" maxlength="1000" required placeholder="Tulis tanggapan atau diskusi mengenai artikel ini..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Kirim Komentar</button>
                </form>
            <?php else: ?>
                <div class="empty-state" style="padding:1.35rem;margin-bottom:1.5rem;">
                    <p>Ingin berpartisipasi dalam diskusi? <a href="login.php?r=<?= urlencode('story-detail.php?id=' . $story['id']) ?>" data-no-transition><strong>Masuk ke Akun</strong></a></p>
                </div>
            <?php endif; ?>

            <?php if (empty($comments)): ?>
                <div class="empty-state" style="padding:1.8rem;">
                    <p class="admin-hint">Belum ada komentar untuk artikel ini. Jadilah yang pertama berdiskusi!</p>
                </div>
            <?php else: ?>
                <div class="comments-list">
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <?= renderAvatar($c['display_name'], $c['user_avatar'] ?? null, '38px') ?>
                            <div class="comment-content-box">
                                <div class="comment-author-row">
                                    <div style="display:flex;align-items:center;gap:0.45rem;flex-wrap:wrap;">
                                        <a href="profile.php?member=<?= (int)$c['user_id'] ?>" style="font-weight:700;color:var(--dark);font-size:0.9rem;">
                                            <?= htmlspecialchars($c['display_name']) ?>
                                        </a>
                                        <?php if ($c['role'] === 'admin'): ?>
                                            <span class="role-badge role-admin" style="font-size:0.62rem;">Admin</span>
                                        <?php endif; ?>
                                        <span class="admin-hint" style="font-size:0.75rem;"><?= timeAgo($c['created_at']) ?></span>
                                    </div>
                                    <?php if ($user && ((int)$c['user_id'] === $user['id'] || $user['role'] === 'admin')): ?>
                                        <form action="comment.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus komentar ini?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                            <input type="hidden" name="return_url" value="story-detail.php?id=<?= (int)$story['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger) !important;padding:0.2rem 0.45rem;" title="Hapus komentar">
                                                <?= svgIcon('trash', 14) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.9rem;color:var(--text-soft);line-height:1.6;">
                                    <?= nl2br(htmlspecialchars($c['content'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
