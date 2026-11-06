<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Cerita & Artikel';
$currentPage = 'stories';

$stories = $pdo->query("
    SELECT s.*, u.display_name AS username,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = s.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'story' AND target_id = s.id) AS comment_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .story-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .story-card {
        cursor: pointer;
        position: relative;
        transition: all var(--t) var(--ease-out);
        border-left: 4px solid var(--primary);
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .story-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
        border-left-color: var(--secondary);
    }
    .story-footer-bar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
        padding-top: 0.75rem;
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
    <section class="stories-page">
        <div class="container">
            <div class="page-header page-anim">
                <div>
                    <h1>&#128214; Cerita, Blog &amp; Pengalaman</h1>
                    <p class="page-subtitle">Kumpulan tulisan, catatan praktikum, dan cerita dari komunitas.</p>
                </div>
                <?php if ($user): ?>
                    <a href="story-add.php" class="btn btn-primary">&#10133; Tulis Cerita Baru</a>
                <?php else: ?>
                    <a href="login.php?r=story-add.php" class="btn btn-outline">Masuk untuk Menulis Cerita</a>
                <?php endif; ?>
            </div>

            <!-- Toolbar Pencarian -->
            <div class="toolbar page-anim" style="margin-bottom:1.75rem;">
                <div class="search-box">
                    <span class="search-icon">&#128269;</span>
                    <input type="search" id="storySearch" placeholder="Cari judul atau isi cerita..." aria-label="Cari cerita">
                </div>
                <span class="admin-hint" id="storyCountDisplay"><?= count($stories) ?> cerita diterbitkan</span>
            </div>

            <?php if (!empty($stories)): ?>
            <div class="story-grid page-anim" id="storiesContainer">
                <?php foreach ($stories as $story): ?>
                <article class="story-card" data-search="<?= strtolower(htmlspecialchars($story['title'] . ' ' . $story['username'] . ' ' . $story['content'])) ?>">
                    <a href="story-detail.php?id=<?= $story['id'] ?>" class="story-link" style="display:flex;flex-direction:column;height:100%;">
                        <div class="story-header" style="margin-bottom:0.75rem;">
                            <h3 style="font-size:1.15rem;color:var(--dark);margin-bottom:0.35rem;"><?= htmlspecialchars($story['title']) ?></h3>
                            <p class="story-meta" style="font-size:0.8rem;color:var(--text-light);margin-bottom:0;">
                                Oleh <strong><?= htmlspecialchars($story['username']) ?></strong> &middot; <?= timeAgo($story['created_at']) ?>
                            </p>
                        </div>
                        <p style="font-size:0.92rem;color:var(--text-soft);line-height:1.65;flex:1;">
                            <?= htmlspecialchars(mb_strimwidth($story['content'], 0, 180, '...')) ?>
                        </p>
                        <div class="story-footer-bar">
                            <span class="stat-pill">❤️ <?= (int)$story['like_count'] ?> Suka</span>
                            <span class="stat-pill">&#128172; <?= (int)$story['comment_count'] ?> Komentar</span>
                            <span style="margin-left:auto;color:var(--primary);font-size:0.85rem;font-weight:700;">Baca Selengkapnya &rarr;</span>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <div id="noStoryResults" class="empty-state" style="display:none;margin-top:2rem;">
                <span class="empty-icon">&#128269;</span>
                <p>Tidak ada cerita yang sesuai dengan kata kunci pencarian.</p>
            </div>
            <?php else: ?>
            <div class="empty-state page-anim">
                <span class="empty-icon">&#128221;</span>
                <p>Belum ada cerita yang dibagikan.</p>
                <p style="margin-top:0.75rem;">
                    <?php if ($user): ?>
                        <a href="story-add.php" class="btn btn-primary btn-sm">Bagikan Cerita Pertama</a>
                    <?php else: ?>
                        <a href="login.php?r=story-add.php" class="btn btn-primary btn-sm">Masuk untuk Menulis</a>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
    const searchInput = document.getElementById('storySearch');
    const container = document.getElementById('storiesContainer');
    const countDisplay = document.getElementById('storyCountDisplay');
    const noResults = document.getElementById('noStoryResults');

    if (searchInput && container) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const cards = container.querySelectorAll('.story-card');
            let visible = 0;
            cards.forEach(card => {
                const match = !query || card.dataset.search.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (countDisplay) countDisplay.textContent = visible + ' cerita ditemukan';
            if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
