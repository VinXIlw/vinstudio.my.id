<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: albums.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, u.display_name AS username, u.avatar AS user_avatar
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

$pageTitle = $album['title'];
$currentPage = 'albums';

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
$user = currentUser();
if ($user) {
    $uStmt = $pdo->prepare("SELECT id FROM likes WHERE target_type = 'album' AND target_id = ? AND user_id = ?");
    $uStmt->execute([$id, $user['id']]);
    $liked = (bool)$uStmt->fetch();
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<style>
    .detail-hero {
        position: relative;
        border-radius: var(--radius-xl);
        overflow: hidden;
        background: #020617;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        max-height: 560px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
    }
    .detail-hero img {
        width: 100%;
        height: 100%;
        max-height: 560px;
        object-fit: contain;
        background: #020617;
        transition: transform 0.3s ease;
    }
    .zoom-hint-badge {
        position: absolute;
        bottom: 1rem;
        right: 1rem;
        background: rgba(15, 23, 42, 0.78);
        backdrop-filter: blur(8px);
        color: #f8fafc;
        padding: 0.4rem 0.85rem;
        border-radius: var(--radius-pill);
        font-size: 0.76rem;
        font-weight: 600;
        pointer-events: none;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .detail-meta-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.75rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
    }
    .detail-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .like-bar {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-top: 1.35rem;
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
        font-size: 0.92rem;
        cursor: pointer;
        transition: all var(--t) var(--ease);
    }
    .like-btn:hover {
        border-color: #f43f5e;
        color: #f43f5e;
        background: rgba(244, 63, 94, 0.08);
        transform: scale(1.03);
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
    <div class="container" style="max-width:960px;">
        <div style="margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
            <a href="albums.php" class="btn btn-outline btn-sm">&larr; Kembali ke Galeri Album</a>
            <button type="button" class="btn btn-outline btn-sm" data-share-url="">🔗 Bagikan Tautan</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- Preview Foto dengan Lightbox -->
        <div class="detail-hero page-anim">
            <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars($album['photo']) : 'img/placeholder.svg' ?>"
                 alt="<?= htmlspecialchars($album['title']) ?>"
                 data-lightbox
                 onerror="this.onerror=null;this.src='img/placeholder.svg';">
            <span class="zoom-hint-badge">🔍 Klik gambar untuk perbesar</span>
        </div>

        <!-- Metadata Album -->
        <div class="detail-meta-card page-anim">
            <div class="detail-header-flex">
                <div>
                    <h1 style="font-size:1.85rem;margin-bottom:0.4rem;color:var(--dark);"><?= htmlspecialchars($album['title']) ?></h1>
                    <div class="user-cell" style="margin-top:0.5rem;">
                        <?= renderAvatar($album['username'], $album['user_avatar'] ?? null, '38px') ?>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($album['username']) ?></div>
                            <span class="user-email"><?= fmtDateTime($album['created_at']) ?> (<?= timeAgo($album['created_at']) ?>)</span>
                        </div>
                    </div>
                </div>
                <?php if ($user && ($album['user_id'] == $user['id'] || $user['role'] === 'admin')): ?>
                    <form action="album-delete.php" method="POST" onsubmit="return confirm('Apakah kamu yakin ingin menghapus album ini?');">
                        <input type="hidden" name="id" value="<?= $album['id'] ?>">
                        <button type="submit" class="btn btn-delete btn-sm">🗑 Hapus Album</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!empty($album['description'])): ?>
                <div style="margin-top:1.25rem;font-size:1.02rem;color:var(--text-soft);line-height:1.72;">
                    <?= nl2br(htmlspecialchars($album['description'])) ?>
                </div>
            <?php endif; ?>

            <!-- Bar Like & Share -->
            <div class="like-bar">
                <?php if ($user): ?>
                    <form action="like.php" method="POST" class="inline-form">
                        <input type="hidden" name="target_type" value="album">
                        <input type="hidden" name="target_id" value="<?= $album['id'] ?>">
                        <input type="hidden" name="return_url" value="album-detail.php?id=<?= $album['id'] ?>">
                        <button type="submit" class="like-btn <?= $liked ? 'liked' : '' ?>" title="<?= $liked ? 'Batal Suka' : 'Sukai Album' ?>">
                            <span><?= $liked ? '❤️' : '🤍' ?></span>
                            <span><?= $likeCount ?> Suka</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php?r=album-detail.php?id=<?= $album['id'] ?>" class="like-btn" title="Masuk untuk menyukai">
                        <span>🤍</span>
                        <span><?= $likeCount ?> Suka</span>
                    </a>
                <?php endif; ?>
                <span class="admin-hint">💬 <?= count($comments) ?> Komentar</span>
            </div>
        </div>

        <!-- Bagian Komentar -->
        <div class="comments-section page-anim">
            <h2 style="font-size:1.3rem;margin-bottom:1.25rem;color:var(--dark);display:flex;align-items:center;gap:0.5rem;">
                💬 Diskusi &amp; Komentar (<?= count($comments) ?>)
            </h2>

            <?php if ($user): ?>
                <form action="comment.php" method="POST" style="margin-bottom:2rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="target_type" value="album">
                    <input type="hidden" name="target_id" value="<?= $album['id'] ?>">
                    <input type="hidden" name="return_url" value="album-detail.php?id=<?= $album['id'] ?>">
                    <div class="form-group">
                        <label for="content">Tulis tanggapan atau apresiasi:</label>
                        <textarea id="content" name="content" rows="3" required placeholder="Tulis komentar kamu di sini..." style="resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Kirim Komentar</button>
                </form>
            <?php else: ?>
                <div class="empty-state" style="padding:1.5rem;margin-bottom:1.5rem;">
                    <p>Ingin bergabung dalam diskusi? <a href="login.php?r=album-detail.php?id=<?= $album['id'] ?>">Masuk untuk menulis komentar</a></p>
                </div>
            <?php endif; ?>

            <?php if (empty($comments)): ?>
                <div class="empty-state" style="padding:2rem;">
                    <p class="admin-hint">Belum ada komentar untuk karya ini. Jadilah yang pertama memberikan tanggapan!</p>
                </div>
            <?php else: ?>
                <div class="comments-list">
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <?= renderAvatar($c['display_name'], $c['user_avatar'] ?? null, '38px', '', 'margin-right:0.6rem;') ?>
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
                                            <input type="hidden" name="return_url" value="album-detail.php?id=<?= $album['id'] ?>">
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
