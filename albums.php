<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Galeri Proyek & Dokumentasi';
$currentPage = 'albums';

$msg      = $_GET['msg'] ?? '';
$err      = $_GET['err'] ?? '';
$sort     = $_GET['sort'] ?? 'latest';
$orderSql = ($sort === 'popular') ? 'like_count DESC, a.created_at DESC' : 'a.created_at DESC';

$albums = $pdo->query("
    SELECT a.*, u.display_name AS username, u.avatar AS user_avatar,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = a.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'album' AND target_id = a.id) AS comment_count
    FROM albums a
    JOIN users u ON a.user_id = u.id
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
            <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('image', 13) ?> Arsip Visual &amp; Praktikum</span>
            <h1>Galeri Proyek &amp; Dokumentasi</h1>
            <p class="page-subtitle">Eksplorasi hasil praktikum jaringan, proyek kreatif, dan dokumentasi kegiatan kelas.</p>
        </div>
        <?php if ($user): ?>
            <a href="album-add.php" class="btn btn-primary"><?= svgIcon('plus', 16) ?> Unggah Karya Baru</a>
        <?php else: ?>
            <a href="login.php?r=album-add.php" class="btn btn-outline" data-no-transition>Masuk untuk Mengunggah</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($albums)): ?>
    <!-- Toolbar Pencarian & Pengurutan -->
    <div class="toolbar page-anim">
        <div class="search-box">
            <span class="search-icon"><?= svgIcon('search', 16) ?></span>
            <input type="search" id="albumSearch" placeholder="Cari judul karya, deskripsi, atau pengunggah..." aria-label="Cari karya">
        </div>
        <div class="sort-pill-group" data-sort-pills
             data-target-grid="#albumsContainer"
             data-search-input="#albumSearch"
             data-count-label="#albumCountDisplay"
             data-empty-state="#noAlbumResults"
             data-cat-bar="#albumCatBar"
             data-unit="karya ditampilkan">
            <a href="albums.php?sort=latest" data-sort="latest" class="sort-pill-btn <?= $sort !== 'popular' ? 'active' : '' ?>">Terbaru</a>
            <a href="albums.php?sort=popular" data-sort="popular" class="sort-pill-btn <?= $sort === 'popular' ? 'active' : '' ?>">Terpopuler</a>
        </div>
        <span class="admin-hint" id="albumCountDisplay"><?= count($albums) ?> karya ditampilkan</span>
    </div>

    <!-- Filter Kategori Chips -->
    <div class="cat-filter-bar page-anim" id="albumCatBar">
        <button type="button" class="cat-chip active" data-cat="all">Semua Kategori</button>
        <?php foreach (ALBUM_CATEGORIES as $cat): ?>
            <button type="button" class="cat-chip" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="album-grid page-anim" id="albumsContainer">
        <?php foreach ($albums as $album): ?>
        <article class="album-card"
                 data-search="<?= strtolower(htmlspecialchars($album['title'] . ' ' . $album['username'] . ' ' . ($album['description'] ?? '') . ' ' . ($album['category'] ?? ''))) ?>"
                 data-category="<?= htmlspecialchars($album['category'] ?: 'Dokumentasi Kelas') ?>"
                 data-likes="<?= (int)$album['like_count'] ?>"
                 data-time="<?= strtotime($album['created_at'] ?? '') ?: 0 ?>">
            <a href="album-detail.php?id=<?= (int)$album['id'] ?>" class="album-link">
                <div class="album-img-wrap">
                    <img src="<?= albumPhotoUrl($album['photo'] ?? null) ?>"
                         alt="<?= htmlspecialchars($album['title']) ?>" loading="lazy"
                         onerror="this.onerror=null;this.src='img/placeholder.svg';">
                    <span class="album-cat-pill"><?= htmlspecialchars($album['category'] ?: 'Dokumentasi Kelas') ?></span>
                </div>
                <div class="album-content">
                    <h3><?= htmlspecialchars($album['title']) ?></h3>
                    <div style="display:flex;align-items:center;gap:0.45rem;margin:0.25rem 0 0.65rem;">
                        <?= renderAvatar($album['username'], $album['user_avatar'] ?? null, '22px') ?>
                        <span style="font-size:0.78rem;color:var(--text-light);">
                            <strong style="color:var(--text-soft);"><?= htmlspecialchars($album['username']) ?></strong> &middot; <?= timeAgo($album['created_at']) ?>
                        </span>
                    </div>
                    <?php if (!empty($album['description'])): ?>
                        <p style="font-size:0.86rem;color:var(--text-soft);margin-bottom:auto;">
                            <?= htmlspecialchars(mb_strimwidth($album['description'], 0, 95, '...')) ?>
                        </p>
                    <?php endif; ?>
                    <div class="album-stats-bar">
                        <span class="stat-pill"><?= svgIcon('heart', 13) ?> <?= (int)$album['like_count'] ?></span>
                        <span class="stat-pill"><?= svgIcon('chat', 13) ?> <?= (int)$album['comment_count'] ?></span>
                        <span class="stat-pill"><?= svgIcon('eye', 13) ?> <?= (int)($album['views'] ?? 0) ?></span>
                        <span style="margin-left:auto;color:var(--primary-light);font-weight:700;font-size:0.8rem;">Detail &rarr;</span>
                    </div>
                </div>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
    <div id="noAlbumResults" class="empty-state" style="display:none;margin-top:1.5rem;">
        <div style="margin-bottom:0.5rem;color:var(--muted);"><?= svgIcon('search', 32) ?></div>
        <p>Tidak ada karya yang sesuai dengan filter atau kata kunci pencarian.</p>
    </div>
    <?php else: ?>
    <div class="empty-state page-anim">
        <div style="margin-bottom:0.65rem;color:var(--primary-light);"><?= svgIcon('image', 38) ?></div>
        <p style="font-weight:700;color:var(--dark);margin-bottom:0.3rem;">Belum ada album atau proyek yang diunggah.</p>
        <p class="admin-hint" style="margin-bottom:1.1rem;">Bagikan foto kegiatan kelas atau dokumentasi hasil praktikum pertamamu.</p>
        <a href="<?= $user ? 'album-add.php' : 'login.php?r=album-add.php' ?>" class="btn btn-primary btn-sm" <?= !$user ? 'data-no-transition' : '' ?>>
            <?= svgIcon('plus', 15) ?> Mulai Unggah Karya
        </a>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
