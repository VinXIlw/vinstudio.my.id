<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    safeRedirect('albums.php');
}

$stmt = $pdo->prepare("
    SELECT a.*, u.display_name AS username, u.avatar AS user_avatar, u.specialty AS user_specialty
    FROM albums a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$album = $stmt->fetch();

if (!$album) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Tambah view counter 1x per sesi untuk album ini
if (!isset($_SESSION['viewed_albums'][$id])) {
    $_SESSION['viewed_albums'][$id] = true;
    $pdo->prepare("UPDATE albums SET views = COALESCE(views, 0) + 1 WHERE id = ?")->execute([$id]);
    $album['views'] = (int)($album['views'] ?? 0) + 1;
}

$pageTitle   = $album['title'];
$currentPage = 'albums';
$user        = currentUser();
$msg         = $_GET['msg'] ?? '';
$err         = $_GET['err'] ?? '';

$cStmt = $pdo->prepare("
    SELECT c.*, u.display_name, u.role, u.avatar AS user_avatar
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.target_type = 'album' AND c.target_id = ?
    ORDER BY c.created_at ASC
");
$cStmt->execute([$id]);
$comments = $cStmt->fetchAll();

$lStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = ?");
$lStmt->execute([$id]);
$likeCount = (int)$lStmt->fetchColumn();

$liked = false;
if ($user) {
    $ulStmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'album' AND target_id = ?");
    $ulStmt->execute([$user['id'], $id]);
    $liked = (bool)$ulStmt->fetch();
}

$canManage = $user && ((int)$album['user_id'] === $user['id'] || $user['role'] === 'admin');

include __DIR__ . '/includes/header.php';
?>

<main>
    <div style="max-width:960px;margin:0 auto;">
        <div style="margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.6rem;">
            <a href="albums.php" class="btn btn-outline btn-sm"><?= svgIcon('arrow-left', 15) ?> Kembali ke Galeri</a>
            <button type="button" class="btn btn-outline btn-sm" data-share-url=""><?= svgIcon('share', 14) ?> Bagikan Tautan</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- Hero Pratinjau Gambar Resolusi Tinggi -->
        <div class="detail-hero page-anim">
            <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars(basename($album['photo'])) : 'img/placeholder.svg' ?>"
                 alt="<?= htmlspecialchars($album['title']) ?>"
                 data-lightbox
                 onerror="this.onerror=null;this.src='img/placeholder.svg';">
            <span class="zoom-hint-badge"><?= svgIcon('search', 13) ?> Klik gambar untuk perbesar</span>
        </div>

        <!-- Detail & Metadata Karya -->
        <article class="detail-meta-card page-anim">
            <div class="detail-header-flex">
                <div style="flex:1;min-width:240px;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.55rem;flex-wrap:wrap;">
                        <span class="badge"><?= htmlspecialchars($album['category'] ?: 'Dokumentasi Kelas') ?></span>
                        <span class="mono-tag"><?= svgIcon('eye', 12) ?> <?= (int)($album['views'] ?? 0) ?> dilihat</span>
                    </div>
                    <h1 style="font-size:1.85rem;color:var(--dark);margin-bottom:0.75rem;line-height:1.25;">
                        <?= htmlspecialchars($album['title']) ?>
                    </h1>
                    <a href="profile.php?member=<?= (int)$album['user_id'] ?>" class="user-cell" style="text-decoration:none;">
                        <?= renderAvatar($album['username'], $album['user_avatar'] ?? null, '40px') ?>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($album['username']) ?></div>
                            <span class="user-email">
                                <?= !empty($album['user_specialty']) ? htmlspecialchars($album['user_specialty']) . ' &middot; ' : '' ?>
                                <?= fmtDateTime($album['created_at']) ?>
                            </span>
                        </div>
                    </a>
                </div>

                <?php if ($canManage): ?>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <a href="album-add.php?edit=<?= (int)$album['id'] ?>" class="btn btn-outline btn-sm">
                        <?= svgIcon('edit', 14) ?> Edit Karya
                    </a>
                    <form action="album-delete.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus karya ini secara permanen beserta seluruh komentarnya?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
                        <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('trash', 14) ?> Hapus</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($album['description'])): ?>
                <div class="story-content-body" style="margin-top:1.35rem;font-size:0.98rem;">
                    <?= nl2br(htmlspecialchars($album['description'])) ?>
                </div>
            <?php endif; ?>

            <div class="like-bar">
                <?php if ($user): ?>
                    <form action="like.php" method="POST" class="inline-form js-like-form" data-no-loading>
                        <?= csrfField() ?>
                        <input type="hidden" name="target_type" value="album">
                        <input type="hidden" name="target_id" value="<?= (int)$album['id'] ?>">
                        <input type="hidden" name="return_url" value="album-detail.php?id=<?= (int)$album['id'] ?>">
                        <button type="submit" class="like-btn <?= $liked ? 'liked' : '' ?>">
                            <?= svgIcon('heart', 16) ?>
                            <span data-like-count><?= $likeCount ?> Suka</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php?r=<?= urlencode('album-detail.php?id=' . $album['id']) ?>" class="like-btn" data-no-transition>
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
                <?= svgIcon('chat', 19) ?> Diskusi &amp; Apresiasi (<?= count($comments) ?>)
            </h2>

            <?php if ($user): ?>
                <form action="comment.php" method="POST" style="margin-bottom:1.75rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="target_type" value="album">
                    <input type="hidden" name="target_id" value="<?= (int)$album['id'] ?>">
                    <input type="hidden" name="return_url" value="album-detail.php?id=<?= (int)$album['id'] ?>">
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <textarea name="content" rows="3" maxlength="1000" required placeholder="Tulis tanggapan, pertanyaan teknis, atau apresiasi..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Kirim Komentar</button>
                </form>
            <?php else: ?>
                <div class="empty-state" style="padding:1.35rem;margin-bottom:1.5rem;">
                    <p>Ingin ikut memberikan komentar? <a href="login.php?r=<?= urlencode('album-detail.php?id=' . $album['id']) ?>" data-no-transition><strong>Masuk ke Akun</strong></a></p>
                </div>
            <?php endif; ?>

            <?php if (empty($comments)): ?>
                <div class="empty-state" style="padding:1.8rem;">
                    <p class="admin-hint">Belum ada komentar. Jadilah yang pertama memberikan tanggapan!</p>
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
                                            <input type="hidden" name="return_url" value="album-detail.php?id=<?= (int)$album['id'] ?>">
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
