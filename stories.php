<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Artikel, Tutorial & Catatan';
$currentPage = 'stories';

$msg      = $_GET['msg'] ?? '';
$err      = $_GET['err'] ?? '';
$sort     = $_GET['sort'] ?? 'latest';
$orderSql = ($sort === 'popular') ? 'like_count DESC, s.created_at DESC' : 's.created_at DESC';

$stories = $pdo->query("
    SELECT s.*, u.display_name AS username, u.avatar AS user_avatar,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = s.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'story' AND target_id = s.id) AS comment_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    ORDER BY {$orderSql}
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main>
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="page-header page-anim">
        <div>
            <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('book', 13) ?> Publikasi &amp; Literasi TI</span>
            <h1>Artikel, Tutorial &amp; Catatan</h1>
            <p class="page-subtitle">Kumpulan panduan teknis jaringan, rangkuman praktikum, dan cerita inspiratif anggota.</p>
        </div>
        <?php if ($user): ?>
            <a href="story-add.php" class="btn btn-primary"><?= svgIcon('plus', 16) ?> Tulis Artikel Baru</a>
        <?php else: ?>
            <a href="login.php?r=story-add.php" class="btn btn-outline" data-no-transition>Masuk untuk Menulis</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($stories)): ?>
    <!-- Toolbar Pencarian & Pengurutan -->
    <div class="toolbar page-anim">
        <div class="search-box">
            <span class="search-icon"><?= svgIcon('search', 16) ?></span>
            <input type="search" id="storySearch" placeholder="Cari judul artikel, topik jaringan, atau nama penulis..." aria-label="Cari artikel">
        </div>
        <div class="sort-pill-group" data-sort-pills
             data-target-grid="#storiesContainer"
             data-search-input="#storySearch"
             data-count-label="#storyCountDisplay"
             data-empty-state="#noStoryResults"
             data-cat-bar="#storyCatBar"
             data-unit="artikel diterbitkan">
            <a href="stories.php?sort=latest" data-sort="latest" class="sort-pill-btn <?= $sort !== 'popular' ? 'active' : '' ?>">Terbaru</a>
            <a href="stories.php?sort=popular" data-sort="popular" class="sort-pill-btn <?= $sort === 'popular' ? 'active' : '' ?>">Terpopuler</a>
        </div>
        <span class="admin-hint" id="storyCountDisplay"><?= count($stories) ?> artikel diterbitkan</span>
    </div>

    <!-- Filter Kategori Chips -->
    <div class="cat-filter-bar page-anim" id="storyCatBar">
        <button type="button" class="cat-chip active" data-cat="all">Semua Topik</button>
        <?php foreach (STORY_CATEGORIES as $cat): ?>
            <button type="button" class="cat-chip" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="story-grid page-anim" id="storiesContainer">
        <?php foreach ($stories as $story): ?>
        <article class="story-card"
                 data-search="<?= strtolower(htmlspecialchars($story['title'] . ' ' . $story['username'] . ' ' . $story['content'] . ' ' . ($story['category'] ?? ''))) ?>"
                 data-category="<?= htmlspecialchars($story['category'] ?: 'Catatan Praktikum') ?>"
                 data-likes="<?= (int)$story['like_count'] ?>"
                 data-time="<?= strtotime($story['created_at'] ?? '') ?: 0 ?>">
            <a href="story-detail.php?id=<?= (int)$story['id'] ?>" class="story-link">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                    <span class="badge"><?= htmlspecialchars($story['category'] ?: 'Catatan Praktikum') ?></span>
                    <span class="mono-tag"><?= readingTime($story['content']) ?></span>
                </div>
                <h3 style="font-size:1.15rem;color:var(--dark);margin-bottom:0.45rem;line-height:1.35;">
                    <?= htmlspecialchars($story['title']) ?>
                </h3>
                <p style="font-size:0.9rem;color:var(--text-soft);line-height:1.68;flex:1;">
                    <?= htmlspecialchars(mb_strimwidth($story['content'], 0, 175, '...')) ?>
                </p>
                <div class="story-footer-bar">
                    <?= renderAvatar($story['username'], $story['user_avatar'] ?? null, '24px') ?>
                    <span style="font-size:0.79rem;font-weight:600;color:var(--text-soft);">
                        <?= htmlspecialchars($story['username']) ?>
                    </span>
                    <span style="font-size:0.74rem;color:var(--muted);">&middot; <?= timeAgo($story['created_at']) ?></span>
                    <span style="margin-left:auto;display:flex;gap:0.4rem;">
                        <span class="stat-pill"><?= svgIcon('heart', 12) ?> <?= (int)$story['like_count'] ?></span>
                        <span class="stat-pill"><?= svgIcon('chat', 12) ?> <?= (int)$story['comment_count'] ?></span>
                    </span>
                </div>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
    <div id="noStoryResults" class="empty-state" style="display:none;margin-top:1.5rem;">
        <div style="margin-bottom:0.5rem;color:var(--muted);"><?= svgIcon('search', 32) ?></div>
        <p>Tidak ada artikel yang sesuai dengan filter atau kata kunci pencarian.</p>
    </div>
    <?php else: ?>
    <div class="empty-state page-anim">
        <div style="margin-bottom:0.65rem;color:var(--secondary);"><?= svgIcon('book', 38) ?></div>
        <p style="font-weight:700;color:var(--dark);margin-bottom:0.3rem;">Belum ada artikel yang diterbitkan.</p>
        <p class="admin-hint" style="margin-bottom:1.1rem;">Mulai bagikan tutorial konfigurasi jaringan, catatan materi, atau cerita pengalamanmu.</p>
        <a href="<?= $user ? 'story-add.php' : 'login.php?r=story-add.php' ?>" class="btn btn-primary btn-sm" <?= !$user ? 'data-no-transition' : '' ?>>
            <?= svgIcon('plus', 15) ?> Tulis Artikel Pertama
        </a>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
