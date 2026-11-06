<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Beranda';
$currentPage = 'home';

// Ambil data profil
$profile = $pdo->query("SELECT * FROM profiles LIMIT 1")->fetch();

// Ambil total album & cerita
$albumCount = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
$storyCount = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();

// Ambil album terbaru beserta jumlah like & komentar
$recentAlbums = $pdo->query("
    SELECT a.*, u.display_name AS username,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = a.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'album' AND target_id = a.id) AS comment_count
    FROM albums a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 6
")->fetchAll();

// Ambil cerita terbaru beserta jumlah like & komentar
$recentStories = $pdo->query("
    SELECT s.*, u.display_name AS username,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = s.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'story' AND target_id = s.id) AS comment_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 6
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .hero-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
        border-radius: var(--radius-xl);
        padding: 3.5rem 2.5rem;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2.5rem;
    }
    .hero-banner::before {
        content: '';
        position: absolute;
        width: 450px;
        height: 450px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%);
        top: -150px;
        right: -100px;
        border-radius: 50%;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        width: 350px;
        height: 350px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, transparent 70%);
        bottom: -100px;
        left: -50px;
        border-radius: 50%;
    }
    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 780px;
    }
    .hero-title {
        font-size: 2.75rem;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -0.03em;
        margin-bottom: 1rem;
    }
    .hero-title .brand-gradient {
        background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .hero-desc {
        font-size: 1.15rem;
        color: #cbd5e1;
        line-height: 1.6;
        margin-bottom: 1.75rem;
    }
    .hero-stats-row {
        display: flex;
        gap: 1.25rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }
    .hero-stat-badge {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        padding: 0.85rem 1.4rem;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .hero-stat-badge .num {
        font-size: 1.65rem;
        font-weight: 800;
        color: #38bdf8;
        letter-spacing: -0.02em;
    }
    .hero-stat-badge .lbl {
        font-size: 0.82rem;
        color: #94a3b8;
        line-height: 1.2;
    }
    .card-preview-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .feature-card {
        border-top: 4px solid var(--primary);
        padding: 1.75rem 2rem;
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        margin-bottom: 2.5rem;
        display: flex;
        gap: 2rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .feature-avatar {
        width: 90px;
        height: 90px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--primary-light), var(--secondary));
        display: grid;
        place-items: center;
        font-size: 2.5rem;
        color: #fff;
        box-shadow: var(--shadow-brand);
        flex-shrink: 0;
    }
</style>

<main>
    <!-- Hero Banner -->
    <section class="hero-banner page-anim">
        <div class="hero-content">
            <div style="display:inline-block;padding:0.3rem 0.85rem;background:rgba(99,102,241,0.25);border:1px solid rgba(99,102,241,0.4);border-radius:999px;font-size:0.8rem;font-weight:700;color:#c7d2fe;margin-bottom:1rem;">
                &#9733; <?= htmlspecialchars($profile['class_name'] ?? 'VinStudio Portal') ?>
            </div>
            <h1 class="hero-title">
                Selamat Datang di <span class="brand-gradient"><?= APP_NAME ?></span>
            </h1>
            <p class="hero-desc">
                Ruang kreatif dan galeri karya digital. Jelajahi dokumentasi foto terbaik, karya portofolio, cerita inspiratif, dan profil komunitas kami.
            </p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="albums.php" class="btn btn-primary">&#128248; Jelajahi Album</a>
                <a href="stories.php" class="btn btn-outline" style="background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.25);">&#128214; Baca Cerita</a>
                <?php if (!$user): ?>
                    <a href="register.php" class="btn btn-secondary">&#10133; Bergabung Sekarang</a>
                <?php endif; ?>
            </div>

            <div class="hero-stats-row">
                <div class="hero-stat-badge">
                    <span class="num"><?= $albumCount ?></span>
                    <div class="lbl">Album<br>Foto</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num"><?= $storyCount ?></span>
                    <div class="lbl">Cerita &amp;<br>Artikel</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num"><?= htmlspecialchars($profile['school_year'] ?? '2026/2027') ?></span>
                    <div class="lbl">Tahun<br>Ajaran</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Profil Singkat -->
    <?php if ($profile): ?>
    <section class="feature-card page-anim">
        <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png'): ?>
            <img src="uploads/<?= htmlspecialchars($profile['photo']) ?>" alt="Foto Profil" style="width:90px;height:90px;border-radius:20px;object-fit:cover;box-shadow:var(--shadow-brand);" onerror="this.onerror=null;this.src='img/placeholder.svg';">
        <?php else: ?>
            <div class="feature-avatar">&#128187;</div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px;">
            <h2 style="font-size:1.35rem;margin-bottom:0.35rem;color:var(--dark);"><?= htmlspecialchars($profile['class_name']) ?></h2>
            <p style="color:var(--text-soft);margin-bottom:0.75rem;font-size:0.95rem;"><?= htmlspecialchars($profile['description']) ?></p>
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:0.88rem;color:var(--text-light);">
                <span><strong>Pembina / Wali:</strong> <?= htmlspecialchars($profile['teacher_name']) ?></span>
                <span><strong>Tahun Ajaran:</strong> <?= htmlspecialchars($profile['school_year']) ?></span>
            </div>
        </div>
        <div>
            <a href="profile.php" class="btn btn-outline btn-sm">Lihat Profil Lengkap &rarr;</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Album Terbaru -->
    <section style="margin-bottom:3rem;">
        <div class="section-header page-anim">
            <div>
                <h2>&#128248; Album &amp; Portofolio Terbaru</h2>
                <p class="admin-hint">Dokumentasi foto dan momen yang baru saja diabadikan.</p>
            </div>
            <a href="albums.php" class="btn btn-outline btn-sm">Lihat Semua Album &rarr;</a>
        </div>

        <?php if (!empty($recentAlbums)): ?>
        <div class="album-grid page-anim">
            <?php foreach ($recentAlbums as $album): ?>
            <article class="album-card">
                <a href="album-detail.php?id=<?= $album['id'] ?>" class="card-preview-link">
                    <div style="height:190px;overflow:hidden;background:#0f172a;">
                        <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars($album['photo']) : 'img/placeholder.svg' ?>"
                             alt="<?= htmlspecialchars($album['title']) ?>"
                             style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;"
                             onerror="this.onerror=null;this.src='img/placeholder.svg';">
                    </div>
                    <div class="album-content">
                        <h3 style="font-size:1.05rem;margin-bottom:0.3rem;color:var(--dark);"><?= htmlspecialchars($album['title']) ?></h3>
                        <p class="album-meta" style="font-size:0.8rem;color:var(--text-light);margin-bottom:0.5rem;">
                            Oleh <strong><?= htmlspecialchars($album['username']) ?></strong> &middot; <?= timeAgo($album['created_at']) ?>
                        </p>
                        <?php if (!empty($album['description'])): ?>
                            <p style="font-size:0.88rem;color:var(--text-soft);margin-bottom:0.75rem;">
                                <?= htmlspecialchars(mb_strimwidth($album['description'], 0, 80, '...')) ?>
                            </p>
                        <?php endif; ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;color:var(--text-light);padding-top:0.5rem;border-top:1px solid var(--border);">
                            <span>❤️ <?= (int)$album['like_count'] ?> Suka &middot; &#128172; <?= (int)$album['comment_count'] ?> Komentar</span>
                            <span style="color:var(--primary);font-weight:700;">Detail &rarr;</span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state page-anim">
            <span class="empty-icon">&#128248;</span>
            <p>Belum ada album yang diunggah.</p>
            <p style="margin-top:0.5rem;"><a href="album-add.php">Tambah album pertama</a></p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Cerita Terbaru -->
    <section style="margin-bottom:2rem;">
        <div class="section-header page-anim">
            <div>
                <h2>&#128214; Cerita &amp; Artikel Terbaru</h2>
                <p class="admin-hint">Catatan pengalaman, karya tulis, dan artikel dari anggota.</p>
            </div>
            <a href="stories.php" class="btn btn-outline btn-sm">Lihat Semua Cerita &rarr;</a>
        </div>

        <?php if (!empty($recentStories)): ?>
        <div class="story-grid page-anim">
            <?php foreach ($recentStories as $story): ?>
            <article class="story-card">
                <a href="story-detail.php?id=<?= $story['id'] ?>" class="card-preview-link">
                    <div style="margin-bottom:0.5rem;">
                        <h3 style="font-size:1.15rem;color:var(--dark);margin-bottom:0.25rem;"><?= htmlspecialchars($story['title']) ?></h3>
                        <p class="story-meta" style="font-size:0.8rem;color:var(--text-light);margin-bottom:0.5rem;">
                            Oleh <strong><?= htmlspecialchars($story['username']) ?></strong> &middot; <?= timeAgo($story['created_at']) ?>
                        </p>
                    </div>
                    <p style="font-size:0.92rem;color:var(--text-soft);line-height:1.65;margin-bottom:0.75rem;">
                        <?= htmlspecialchars(mb_strimwidth($story['content'], 0, 140, '...')) ?>
                    </p>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;color:var(--text-light);padding-top:0.5rem;border-top:1px solid var(--border);">
                        <span>❤️ <?= (int)$story['like_count'] ?> Suka &middot; &#128172; <?= (int)$story['comment_count'] ?> Komentar</span>
                        <span style="color:var(--primary);font-weight:700;">Baca &rarr;</span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state page-anim">
            <span class="empty-icon">&#128214;</span>
            <p>Belum ada cerita yang dibagikan.</p>
            <p style="margin-top:0.5rem;"><a href="story-add.php">Tulis cerita pertama</a></p>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
