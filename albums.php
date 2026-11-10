<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Galeri Album';
$currentPage = 'albums';

$sort = $_GET['sort'] ?? 'latest';
$orderSql = ($sort === 'popular') ? 'like_count DESC, a.created_at DESC' : 'a.created_at DESC';

$albums = $pdo->query("
    SELECT a.*, u.display_name AS username,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = a.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'album' AND target_id = a.id) AS comment_count
    FROM albums a
    JOIN users u ON a.user_id = u.id
    ORDER BY {$orderSql}
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .album-link {
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .album-card {
        cursor: pointer;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .album-img-wrap {
        position: relative;
        overflow: hidden;
        height: 205px;
        background: #0f172a;
    }
    .album-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.45s ease;
    }
    .album-card:hover .album-img-wrap img {
        transform: scale(1.06);
    }
    .album-stats-bar {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-top: 0.85rem;
        padding-top: 0.65rem;
        border-top: 1px solid var(--border);
        font-size: 0.8rem;
        color: var(--text-light);
    }
    .stat-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: var(--lighter);
        border: 1px solid var(--border);
        padding: 0.2rem 0.55rem;
        border-radius: var(--radius-pill);
        font-weight: 600;
    }
</style>

<main>
    <section class="gallery-page">
        <div class="container">
            <div class="page-header page-anim">
                <div>
                    <h1>Galeri Foto &amp; Portofolio</h1>
                    <p class="page-subtitle">Dokumentasi momen, kegiatan, dan karya visual terbaik komunitas.</p>
                </div>
                <?php if ($user): ?>
                    <a href="album-add.php" class="btn btn-primary">+ Unggah Album Baru</a>
                <?php else: ?>
                    <a href="login.php?r=album-add.php" class="btn btn-outline">Masuk untuk Mengunggah</a>
                <?php endif; ?>
            </div>

            <!-- Toolbar Pencarian & Urutan -->
            <div class="toolbar page-anim" style="margin-bottom:1.75rem;">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="search" id="albumSearch" placeholder="Cari judul karya atau nama pengunggah..." aria-label="Cari album">
                </div>
                <div class="sort-pill-group" data-sort-pills data-target-grid="#albumsContainer">
                    <a href="albums.php?sort=latest" data-sort="latest" class="sort-pill-btn <?= $sort !== 'popular' ? 'active' : '' ?>">Terbaru</a>
                    <a href="albums.php?sort=popular" data-sort="popular" class="sort-pill-btn <?= $sort === 'popular' ? 'active' : '' ?>">Terpopuler</a>
                </div>
                <span class="admin-hint" id="albumCountDisplay"><?= count($albums) ?> album tersedia</span>
            </div>

            <?php if (!empty($albums)): ?>
            <div class="album-grid page-anim" id="albumsContainer">
                <?php foreach ($albums as $album): ?>
                <article class="album-card" data-title="<?= strtolower(htmlspecialchars($album['title'] . ' ' . $album['username'])) ?>" data-likes="<?= (int)$album['like_count'] ?>" data-time="<?= strtotime($album['created_at'] ?? '') ?: 0 ?>">
                    <a href="album-detail.php?id=<?= $album['id'] ?>" class="album-link">
                        <div class="album-img-wrap">
                            <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars($album['photo']) : 'img/placeholder.svg' ?>"
                                 alt="<?= htmlspecialchars($album['title']) ?>" onerror="this.onerror=null;this.src='img/placeholder.svg';">
                        </div>
                        <div class="album-content" style="flex:1;display:flex;flex-direction:column;">
                            <h3 style="font-size:1.06rem;margin-bottom:0.35rem;color:var(--dark);"><?= htmlspecialchars($album['title']) ?></h3>
                            <p class="album-meta" style="font-size:0.78rem;color:var(--text-light);margin-bottom:0.55rem;">
                                Oleh <strong><?= htmlspecialchars($album['username']) ?></strong> &middot; <?= timeAgo($album['created_at']) ?>
                            </p>
                            <?php if (!empty($album['description'])): ?>
                                <p style="font-size:0.88rem;color:var(--text-soft);margin-bottom:auto;">
                                    <?= htmlspecialchars(mb_strimwidth($album['description'], 0, 90, '...')) ?>
                                </p>
                            <?php endif; ?>
                            <div class="album-stats-bar">
                                <span class="stat-pill" title="Disukai">❤️ <?= (int)$album['like_count'] ?></span>
                                <span class="stat-pill" title="Komentar">💬 <?= (int)$album['comment_count'] ?></span>
                                <span style="margin-left:auto;color:var(--primary);font-size:0.82rem;font-weight:700;">Detail &rarr;</span>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <div id="noResults" class="empty-state" style="display:none;margin-top:2rem;">
                <span class="empty-icon">🔍</span>
                <p>Tidak ada album yang sesuai dengan kata kunci pencarian.</p>
            </div>
            <?php else: ?>
            <div class="empty-state page-anim">
                <span class="empty-icon">📸</span>
                <p>Belum ada album foto yang diunggah.</p>
                <p style="margin-top:0.75rem;">
                    <?php if ($user): ?>
                        <a href="album-add.php" class="btn btn-primary btn-sm">Mulai Buat Album Pertama</a>
                    <?php else: ?>
                        <a href="login.php?r=album-add.php" class="btn btn-primary btn-sm">Masuk untuk Menambah Foto</a>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function() {
    const searchInput = document.getElementById('albumSearch');
    const container = document.getElementById('albumsContainer');
    const countDisplay = document.getElementById('albumCountDisplay');
    const noResults = document.getElementById('noResults');

    if (searchInput && container) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const cards = container.querySelectorAll('.album-card');
            let visible = 0;
            cards.forEach(card => {
                const match = !query || card.dataset.title.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (countDisplay) countDisplay.textContent = visible + ' album ditemukan';
            if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
