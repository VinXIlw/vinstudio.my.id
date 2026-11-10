<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Beranda';
$currentPage = 'home';

// Ambil data profil
$profile = $pdo->query("SELECT * FROM profiles LIMIT 1")->fetch();

// Ambil statistik utama
$albumCount  = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
$storyCount  = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$memberCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 0")->fetchColumn();
$chatCount   = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();

// Ambil 3 obrolan terbaru dari Global Live Chat
$recentChats = $pdo->query("
    SELECT m.*, u.display_name, u.role
    FROM chat_messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.id DESC
    LIMIT 3
")->fetchAll();
$recentChats = array_reverse($recentChats);

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
        background: linear-gradient(135deg, #090d16 0%, #1e1b4b 52%, #312e81 100%);
        border-radius: var(--radius-xl);
        padding: 3.75rem 3rem;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2.25rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .hero-banner::before {
        content: '';
        position: absolute;
        width: 520px;
        height: 520px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.32) 0%, transparent 70%);
        top: -180px;
        right: -120px;
        border-radius: 50%;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        width: 420px;
        height: 420px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.24) 0%, transparent 70%);
        bottom: -140px;
        left: -60px;
        border-radius: 50%;
    }
    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 820px;
    }
    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.95rem;
        background: rgba(99, 102, 241, 0.22);
        border: 1px solid rgba(129, 140, 248, 0.4);
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #e0e7ff;
        margin-bottom: 1.15rem;
        letter-spacing: 0.03em;
    }
    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1.12;
        letter-spacing: -0.03em;
        margin-bottom: 1.1rem;
    }
    .hero-title .brand-gradient {
        background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .hero-desc {
        font-size: 1.12rem;
        color: #cbd5e1;
        line-height: 1.68;
        margin-bottom: 1.85rem;
        max-width: 680px;
    }
    .hero-stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        gap: 1rem;
        margin-top: 2.2rem;
    }
    .hero-stat-badge {
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(10px);
        padding: 0.95rem 1.25rem;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        gap: 0.85rem;
        transition: transform 0.25s ease, background 0.25s ease;
    }
    .hero-stat-badge:hover {
        transform: translateY(-3px);
        background: rgba(255, 255, 255, 0.12);
    }
    .hero-stat-badge .num {
        font-size: 1.75rem;
        font-weight: 800;
        color: #38bdf8;
        letter-spacing: -0.02em;
    }
    .hero-stat-badge .lbl {
        font-size: 0.8rem;
        color: #cbd5e1;
        line-height: 1.25;
        font-weight: 500;
    }
    .bento-two-col {
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    .bento-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 1.75rem 2rem;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .chat-preview-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        margin: 1rem 0 1.25rem;
    }
    .chat-preview-item {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.6rem 0.85rem;
        border-radius: 12px;
        background: var(--lighter);
        border: 1px solid var(--border);
        font-size: 0.85rem;
    }
    .card-preview-link {
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    @media (max-width: 920px) {
        .bento-two-col {
            grid-template-columns: 1fr;
        }
        .hero-banner {
            padding: 2.5rem 1.5rem;
        }
        .hero-title {
            font-size: 2.15rem;
        }
    }
</style>

<main>
    <!-- Hero Banner -->
    <section class="hero-banner page-anim">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="nav-live-dot"></span>
                <span><?= htmlspecialchars($profile['class_name'] ?? 'VinStudio Creative Hub') ?> &middot; Tahun <?= htmlspecialchars($profile['school_year'] ?? '2026/2027') ?></span>
            </div>
            <h1 class="hero-title">
                Ruang Karya Digital &amp; Komunitas <span class="brand-gradient"><?= APP_NAME ?></span>
            </h1>
            <p class="hero-desc">
                Eksplorasi galeri dokumentasi visual, portofolio kreatif, catatan &amp; artikel inspiratif, serta terhubung secara langsung melalui Global Live Chat bersama seluruh anggota.
            </p>
            <div style="display:flex;gap:0.8rem;flex-wrap:wrap;">
                <a href="chat.php" class="btn btn-primary">
                    <span class="nav-live-dot" style="background:#fff;box-shadow:none;"></span>
                    <span>Buka Global Live Chat</span>
                </a>
                <a href="albums.php" class="btn btn-outline" style="background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.24);">
                    Jelajahi Galeri Album
                </a>
                <a href="stories.php" class="btn btn-outline" style="background:rgba(255,255,255,0.06);color:#cbd5e1;border-color:rgba(255,255,255,0.18);">
                    Baca Artikel &amp; Cerita
                </a>
            </div>

            <div class="hero-stats-row">
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $albumCount ?>"><?= $albumCount ?></span>
                    <div class="lbl">Karya &amp;<br>Album Foto</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $storyCount ?>"><?= $storyCount ?></span>
                    <div class="lbl">Artikel &amp;<br>Cerita</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $memberCount ?>"><?= $memberCount ?></span>
                    <div class="lbl">Anggota<br>Terdaftar</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $chatCount ?>"><?= $chatCount ?></span>
                    <div class="lbl">Pesan di<br>Live Chat</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bento Grid: Profil Singkat & Spotlight Global Live Chat -->
    <div class="bento-two-col page-anim">
        <?php if ($profile): ?>
        <section class="bento-card" style="border-top:4px solid var(--primary);">
            <div>
                <div style="display:flex;align-items:center;gap:1.15rem;margin-bottom:1rem;">
                    <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png'): ?>
                        <img src="uploads/<?= htmlspecialchars($profile['photo']) ?>" alt="Foto Profil" style="width:68px;height:68px;border-radius:18px;object-fit:cover;box-shadow:var(--shadow-brand);" onerror="this.onerror=null;this.src='img/placeholder.svg';">
                    <?php else: ?>
                        <div style="width:68px;height:68px;border-radius:18px;background:linear-gradient(135deg,var(--primary-light),var(--secondary));display:grid;place-items:center;color:#fff;font-size:1.75rem;box-shadow:var(--shadow-brand);flex-shrink:0;">
                            💻
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="badge" style="margin-left:0;margin-bottom:0.3rem;">PROFIL KOMUNITAS</span>
                        <h2 style="font-size:1.35rem;color:var(--dark);margin:0;"><?= htmlspecialchars($profile['class_name']) ?></h2>
                    </div>
                </div>
                <p style="color:var(--text-soft);font-size:0.95rem;line-height:1.65;margin-bottom:1rem;">
                    <?= htmlspecialchars($profile['description']) ?>
                </p>
                <div class="info-grid" style="margin-top:0.75rem;margin-bottom:1.25rem;">
                    <div>
                        <div class="label">Pembina / Wali</div>
                        <div class="value"><?= htmlspecialchars($profile['teacher_name']) ?></div>
                    </div>
                    <div>
                        <div class="label">Tahun Ajaran</div>
                        <div class="value"><?= htmlspecialchars($profile['school_year']) ?></div>
                    </div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.75rem;border-top:1px solid var(--border);">
                <span class="admin-hint"><?= $memberCount ?> anggota aktif tergabung</span>
                <a href="profile.php" class="btn btn-outline btn-sm">Profil &amp; Direktori Lengkap &rarr;</a>
            </div>
        </section>
        <?php endif; ?>

        <!-- Global Live Chat Spotlight -->
        <section class="bento-card" style="border-top:4px solid #10b981;">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                    <h2 style="font-size:1.25rem;color:var(--dark);display:flex;align-items:center;gap:0.5rem;margin:0;">
                        <span class="nav-live-dot"></span>
                        Global Live Chat
                    </h2>
                    <span class="online-pill">Real-Time</span>
                </div>
                <p class="admin-hint" style="margin-top:0.25rem;">Obrolan terbaru dari ruang diskusi publik komunitas:</p>

                <div class="chat-preview-list">
                    <?php if (!empty($recentChats)): ?>
                        <?php foreach ($recentChats as $rc): ?>
                            <div class="chat-preview-item">
                                <span class="user-avatar <?= avatarClass($rc['display_name']) ?>" style="width:30px;height:30px;font-size:0.72rem;">
                                    <?= initials($rc['display_name']) ?>
                                </span>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
                                        <strong style="color:var(--dark);"><?= htmlspecialchars($rc['display_name']) ?></strong>
                                        <span style="color:var(--muted);"><?= timeAgo($rc['created_at']) ?></span>
                                    </div>
                                    <div style="color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($rc['message']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="chat-preview-item">Belum ada pesan. Jadilah yang pertama menyapa!</div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.75rem;border-top:1px solid var(--border);">
                <span class="admin-hint">Terbuka untuk seluruh anggota</span>
                <a href="chat.php" class="btn btn-primary btn-sm">Masuk ke Ruang Chat &rarr;</a>
            </div>
        </section>
    </div>

    <!-- Album Terbaru -->
    <section style="margin-bottom:3.25rem;">
        <div class="section-header page-anim">
            <div>
                <h2>Galeri Album &amp; Portofolio Terbaru</h2>
                <p class="admin-hint">Dokumentasi visual dan karya pilihan yang baru saja dipublikasikan.</p>
            </div>
            <div style="display:flex;gap:0.6rem;">
                <?php if ($user): ?>
                    <a href="album-add.php" class="btn btn-primary btn-sm">+ Unggah Foto</a>
                <?php endif; ?>
                <a href="albums.php" class="btn btn-outline btn-sm">Semua Album &rarr;</a>
            </div>
        </div>

        <?php if (!empty($recentAlbums)): ?>
        <div class="album-grid page-anim">
            <?php foreach ($recentAlbums as $album): ?>
            <article class="album-card">
                <a href="album-detail.php?id=<?= $album['id'] ?>" class="card-preview-link">
                    <div style="height:200px;overflow:hidden;background:#0f172a;position:relative;">
                        <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars($album['photo']) : 'img/placeholder.svg' ?>"
                             alt="<?= htmlspecialchars($album['title']) ?>"
                             style="width:100%;height:100%;object-fit:cover;transition:transform 0.45s ease;"
                             onerror="this.onerror=null;this.src='img/placeholder.svg';">
                    </div>
                    <div class="album-content" style="flex:1;display:flex;flex-direction:column;">
                        <h3 style="font-size:1.06rem;margin-bottom:0.3rem;color:var(--dark);"><?= htmlspecialchars($album['title']) ?></h3>
                        <p class="album-meta" style="font-size:0.78rem;color:var(--text-light);margin-bottom:0.55rem;">
                            Oleh <strong><?= htmlspecialchars($album['username']) ?></strong> &middot; <?= timeAgo($album['created_at']) ?>
                        </p>
                        <?php if (!empty($album['description'])): ?>
                            <p style="font-size:0.88rem;color:var(--text-soft);margin-bottom:0.85rem;flex:1;">
                                <?= htmlspecialchars(mb_strimwidth($album['description'], 0, 85, '...')) ?>
                            </p>
                        <?php endif; ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;color:var(--text-light);padding-top:0.65rem;border-top:1px solid var(--border);margin-top:auto;">
                            <span>❤️ <?= (int)$album['like_count'] ?> &middot; 💬 <?= (int)$album['comment_count'] ?></span>
                            <span style="color:var(--primary);font-weight:700;">Lihat Karya &rarr;</span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state page-anim">
            <span class="empty-icon">📸</span>
            <p>Belum ada album yang diunggah.</p>
            <p style="margin-top:0.5rem;"><a href="album-add.php">Tambah album pertama</a></p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Cerita Terbaru -->
    <section style="margin-bottom:2rem;">
        <div class="section-header page-anim">
            <div>
                <h2>Cerita, Catatan &amp; Artikel Terbaru</h2>
                <p class="admin-hint">Ruang literasi, tutorial, dan berbagi pengalaman dari anggota komunitas.</p>
            </div>
            <div style="display:flex;gap:0.6rem;">
                <?php if ($user): ?>
                    <a href="story-add.php" class="btn btn-primary btn-sm">+ Tulis Artikel</a>
                <?php endif; ?>
                <a href="stories.php" class="btn btn-outline btn-sm">Semua Cerita &rarr;</a>
            </div>
        </div>

        <?php if (!empty($recentStories)): ?>
        <div class="story-grid page-anim">
            <?php foreach ($recentStories as $story): ?>
            <article class="story-card">
                <a href="story-detail.php?id=<?= $story['id'] ?>" class="card-preview-link">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <div style="display:flex;align-items:center;gap:0.45rem;">
                            <span class="user-avatar <?= avatarClass($story['username']) ?>" style="width:26px;height:26px;font-size:0.65rem;">
                                <?= initials($story['username']) ?>
                            </span>
                            <span style="font-size:0.8rem;font-weight:600;color:var(--text-soft);"><?= htmlspecialchars($story['username']) ?></span>
                        </div>
                        <span style="font-size:0.74rem;color:var(--muted);background:var(--lighter);padding:0.15rem 0.55rem;border-radius:999px;border:1px solid var(--border);">
                            ⏱ <?= readingTime($story['content']) ?>
                        </span>
                    </div>
                    <h3 style="font-size:1.12rem;color:var(--dark);margin-bottom:0.4rem;line-height:1.35;"><?= htmlspecialchars($story['title']) ?></h3>
                    <p style="font-size:0.9rem;color:var(--text-soft);line-height:1.65;margin-bottom:0.9rem;flex:1;">
                        <?= htmlspecialchars(mb_strimwidth($story['content'], 0, 145, '...')) ?>
                    </p>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;color:var(--text-light);padding-top:0.65rem;border-top:1px solid var(--border);margin-top:auto;">
                        <span>❤️ <?= (int)$story['like_count'] ?> &middot; 💬 <?= (int)$story['comment_count'] ?> &middot; <?= timeAgo($story['created_at']) ?></span>
                        <span style="color:var(--primary);font-weight:700;">Baca &rarr;</span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state page-anim">
            <span class="empty-icon">📖</span>
            <p>Belum ada cerita yang dibagikan.</p>
            <p style="margin-top:0.5rem;"><a href="story-add.php">Tulis cerita pertama</a></p>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
