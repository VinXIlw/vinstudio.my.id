<?php
require_once __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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
    /* Kanvas Latar Belakang Interaktif Antigravity (Khusus Beranda) */
    #antigravityBgCanvas {
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        pointer-events: none;
        z-index: 0;
    }
    main.home-main-content,
    .site-footer {
        position: relative;
        z-index: 2;
    }

    .hero-banner {
        background: linear-gradient(135deg, rgba(9, 13, 22, 0.82) 0%, rgba(30, 27, 75, 0.80) 52%, rgba(49, 46, 129, 0.78) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-radius: var(--radius-xl);
        padding: 3.75rem 3rem;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2.25rem;
        border: 1px solid rgba(255, 255, 255, 0.14);
    }
    .hero-banner::before {
        content: '';
        position: absolute;
        width: 540px;
        height: 540px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.36) 0%, transparent 70%);
        top: -180px;
        right: -120px;
        border-radius: 50%;
        animation: auroraFloat1 10s ease-in-out infinite;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        width: 440px;
        height: 440px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.26) 0%, transparent 70%);
        bottom: -140px;
        left: -60px;
        border-radius: 50%;
        animation: auroraFloat2 13s ease-in-out infinite;
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
        background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc, #38bdf8);
        background-size: 200% auto;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: textGradientFlow 5s linear infinite;
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
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .hero-stat-badge:hover {
        transform: translateY(-5px) scale(1.02);
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(56, 189, 248, 0.45);
        box-shadow: 0 12px 28px rgba(14, 165, 233, 0.22);
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
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease, border-color 0.35s ease;
    }
    html[data-theme="dark"] .bento-card,
    html[data-theme="dark"] .album-card,
    html[data-theme="dark"] .story-card {
        background: rgba(17, 24, 39, 0.82);
    }
    html:not([data-theme="dark"]) .bento-card,
    html:not([data-theme="dark"]) .album-card,
    html:not([data-theme="dark"]) .story-card {
        background: rgba(255, 255, 255, 0.86);
    }
    .bento-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    .chat-preview-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        margin: 1rem 0 1.25rem;
        min-height: 120px;
    }
    .chat-preview-item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.65rem 0.85rem;
        border-radius: 12px;
        background: var(--lighter);
        border: 1px solid var(--border);
        font-size: 0.85rem;
        animation: fadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) both;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .chat-preview-item:hover {
        transform: translateX(4px);
        border-color: var(--primary-light);
    }
    .preview-del-btn {
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
        border-radius: 7px;
        padding: 0.2rem 0.45rem;
        font-size: 0.72rem;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .preview-del-btn:hover {
        background: #ef4444;
        color: #fff;
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

<!-- Kanvas Efek Partikel & Halo Kursor bergaya Google Antigravity (Khusus Beranda) -->
<canvas id="antigravityBgCanvas" aria-hidden="true"></canvas>

<main class="home-main-content">
    <!-- Hero Banner -->
    <section class="hero-banner page-anim">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="nav-live-dot"></span>
                <span><?= htmlspecialchars($profile['class_name'] ?? 'Kelas XI TKJ 1') ?> &middot; Tahun <?= htmlspecialchars($profile['school_year'] ?? '2026/2027') ?></span>
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
                    <span class="num" id="homeChatStatCount" data-count="<?= $chatCount ?>"><?= $chatCount ?></span>
                    <div class="lbl">Pesan di<br>Live Chat</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bento Grid: Profil Singkat & Spotlight Global Live Chat -->
    <div class="bento-two-col page-anim">
        <?php if ($profile): ?>
        <section class="bento-card reveal" style="border-top:4px solid var(--primary);">
            <div>
                <div style="display:flex;align-items:center;gap:1.15rem;margin-bottom:1rem;">
                    <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png'): ?>
                        <img src="uploads/<?= htmlspecialchars($profile['photo']) ?>" alt="Foto Profil" style="width:68px;height:68px;border-radius:18px;object-fit:cover;box-shadow:var(--shadow-brand);" onerror="this.onerror=null;this.src='img/placeholder.svg';">
                    <?php else: ?>
                        <div class="logo-icon" style="width:68px;height:68px;border-radius:18px;font-size:1.75rem;flex-shrink:0;">
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

        <!-- Global Live Chat Spotlight (Live Synced) -->
        <section class="bento-card reveal" style="border-top:4px solid #10b981;">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                    <h2 style="font-size:1.25rem;color:var(--dark);display:flex;align-items:center;gap:0.5rem;margin:0;">
                        <span class="nav-live-dot"></span>
                        Global Live Chat
                    </h2>
                    <span class="online-pill">Real-Time</span>
                </div>
                <p class="admin-hint" style="margin-top:0.25rem;">Obrolan terbaru dari ruang diskusi publik komunitas:</p>

                <div class="chat-preview-list" id="homeChatPreviewList">
                    <?php if (!empty($recentChats)): ?>
                        <?php foreach ($recentChats as $rc): ?>
                            <div class="chat-preview-item" data-preview-id="<?= (int)$rc['id'] ?>">
                                <span class="user-avatar <?= avatarClass($rc['display_name']) ?>" style="width:32px;height:32px;font-size:0.74rem;">
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
                                <?php if ($user && ((int)$rc['user_id'] === (int)$user['id'] || $user['role'] === 'admin')): ?>
                                    <button type="button" class="preview-del-btn" data-home-del="<?= (int)$rc['id'] ?>" title="Hapus pesan ini">🗑</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="chat-preview-item" style="justify-content:center;color:var(--text-light);">
                            Belum ada pesan di ruang obrolan. Yuk sapa teman-teman!
                        </div>
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
    <section style="margin-bottom:3.25rem;" class="reveal">
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
    <section style="margin-bottom:2rem;" class="reveal">
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

<script>
/* ==========================================================================
   1. EFEK LATAR BELAKANG INTERAKTIF ANTIGRAVITY (KHUSUS BERANDA)
   Partikel kapsul melayang & membentuk cincin magnetik (halo) mengikuti kursor
   ========================================================================== */
(function() {
    const canvas = document.getElementById('antigravityBgCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    let width = window.innerWidth;
    let height = window.innerHeight;
    let dpr = Math.min(window.devicePixelRatio || 1, 2);

    // Palet warna ikonik Google Antigravity
    const palette = [
        '#4285F4', // Google Blue
        '#6366F1', // Indigo
        '#38BDF8', // Sky Cyan
        '#A855F7', // Purple
        '#EC4899', // Coral Pink
        '#10B981', // Emerald
        '#F59E0B'  // Amber
    ];

    const pointer = {
        x: width * 0.5,
        y: height * 0.38,
        targetX: width * 0.5,
        targetY: height * 0.38,
        active: false,
        pulse: 0
    };

    let particles = [];

    function initCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        // Buat sebaran partikel kapsul di seluruh layar
        const spacing = width < 768 ? 54 : 44;
        const cols = Math.ceil(width / spacing) + 1;
        const rows = Math.ceil(height / spacing) + 1;
        particles = [];

        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const jitterX = (Math.random() - 0.5) * spacing * 0.65;
                const jitterY = (Math.random() - 0.5) * spacing * 0.65;
                const hx = c * spacing + jitterX;
                const hy = r * spacing + jitterY;
                particles.push({
                    x: hx,
                    y: hy,
                    homeX: hx,
                    homeY: hy,
                    vx: 0,
                    vy: 0,
                    angle: Math.random() * Math.PI * 2,
                    color: palette[(r * cols + c) % palette.length],
                    phase: Math.random() * Math.PI * 2,
                    speed: 0.6 + Math.random() * 0.8,
                    baseLen: 4.5 + Math.random() * 4.5,
                    thickness: 2.1 + Math.random() * 1.1
                });
            }
        }
    }

    window.addEventListener('resize', initCanvas);

    window.addEventListener('mousemove', function(e) {
        pointer.targetX = e.clientX;
        pointer.targetY = e.clientY;
        pointer.active = true;
    }, { passive: true });

    window.addEventListener('touchmove', function(e) {
        if (e.touches && e.touches[0]) {
            pointer.targetX = e.touches[0].clientX;
            pointer.targetY = e.touches[0].clientY;
            pointer.active = true;
        }
    }, { passive: true });

    window.addEventListener('mousedown', function() {
        pointer.pulse = 1.0;
    });

    initCanvas();

    let startTime = performance.now();

    function animate(now) {
        const t = (now - startTime) * 0.001;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        ctx.clearRect(0, 0, width, height);

        // Jika kursor belum digerakkan, gerakkan titik fokus secara lembut membentuk angka 8
        if (!pointer.active) {
            pointer.targetX = width * 0.5 + Math.cos(t * 0.55) * (width * 0.22);
            pointer.targetY = height * 0.36 + Math.sin(t * 0.9) * (height * 0.14);
        }

        // Interpolasi halus posisi kursor (trailing spring)
        pointer.x += (pointer.targetX - pointer.x) * 0.12;
        pointer.y += (pointer.targetY - pointer.y) * 0.12;

        if (pointer.pulse > 0.01) {
            pointer.pulse *= 0.91;
        } else {
            pointer.pulse = 0;
        }

        // 1. Gambar Ambient Spotlight Glow yang mengikuti kursor
        const glowRadius = width < 768 ? 240 : 380;
        const grad = ctx.createRadialGradient(pointer.x, pointer.y, 10, pointer.x, pointer.y, glowRadius);
        if (isDark) {
            grad.addColorStop(0, 'rgba(99, 102, 241, 0.18)');
            grad.addColorStop(0.45, 'rgba(14, 165, 233, 0.08)');
            grad.addColorStop(1, 'rgba(9, 13, 22, 0)');
        } else {
            grad.addColorStop(0, 'rgba(79, 70, 229, 0.12)');
            grad.addColorStop(0.5, 'rgba(14, 165, 233, 0.05)');
            grad.addColorStop(1, 'rgba(255, 255, 255, 0)');
        }
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, width, height);

        // 2. Update & gambar setiap partikel kapsul Antigravity
        const maxInfluence = width < 768 ? 210 : 310;
        const baseRingRadius = (width < 768 ? 95 : 145) + pointer.pulse * 75;

        ctx.lineCap = 'round';

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            // Gerakan mengambang alami (anti-gravity drift)
            const floatX = p.homeX + Math.cos(t * p.speed + p.phase) * 9;
            const floatY = p.homeY + Math.sin(t * p.speed + p.phase) * 9;

            const dx = p.x - pointer.x;
            const dy = p.y - pointer.y;
            const dist = Math.hypot(dx, dy) || 0.001;
            const angleToCursor = Math.atan2(dy, dx);

            let targetX = floatX;
            let targetY = floatY;
            let targetAngle = p.phase + t * 0.4;
            let energy = 0;

            if (dist < maxInfluence) {
                energy = Math.pow(1 - dist / maxInfluence, 1.35);

                // Bentuk cincin bergelombang organik (medusa/halo) di sekeliling kursor
                const waveOffset = Math.sin(angleToCursor * 4 + t * 2.6) * 18
                                 + Math.cos(angleToCursor * 3 - t * 1.9) * 12;
                const ringR = baseRingRadius + waveOffset;

                // Tarik partikel menuju keliling cincin halo + sedikit efek pusaran (swirl)
                const swirlAngle = angleToCursor + 0.18 * energy;
                const ringX = pointer.x + Math.cos(swirlAngle) * ringR;
                const ringY = pointer.y + Math.sin(swirlAngle) * ringR;

                targetX = floatX * (1 - energy * 0.78) + ringX * (energy * 0.78);
                targetY = floatY * (1 - energy * 0.78) + ringY * (energy * 0.78);

                // Arahkan rotasi kapsul mengikuti garis medan magnet kursor
                targetAngle = angleToCursor + Math.sin(t * 2 + p.phase) * 0.25;
            }

            // Fisika pegas halus (spring + damping)
            p.vx = (p.vx + (targetX - p.x) * 0.085) * 0.80;
            p.vy = (p.vy + (targetY - p.y) * 0.085) * 0.80;
            p.x += p.vx;
            p.y += p.vy;

            // Interpolasi sudut rotasi kapsul
            let diffAngle = targetAngle - p.angle;
            while (diffAngle > Math.PI) diffAngle -= Math.PI * 2;
            while (diffAngle < -Math.PI) diffAngle += Math.PI * 2;
            p.angle += diffAngle * 0.14;

            // Panjang kapsul memanjang saat terpengaruh medan kursor
            const halfLen = (p.baseLen + energy * 8.5) * 0.5;
            const cosA = Math.cos(p.angle) * halfLen;
            const sinA = Math.sin(p.angle) * halfLen;

            const baseAlpha = isDark ? 0.26 : 0.24;
            const activeAlpha = isDark ? 0.88 : 0.78;
            ctx.globalAlpha = baseAlpha + energy * (activeAlpha - baseAlpha);
            ctx.strokeStyle = p.color;
            ctx.lineWidth = p.thickness + energy * 1.1;

            ctx.beginPath();
            ctx.moveTo(p.x - cosA, p.y - sinA);
            ctx.lineTo(p.x + cosA, p.y + sinA);
            ctx.stroke();
        }

        ctx.globalAlpha = 1;
        requestAnimationFrame(animate);
    }

    requestAnimationFrame(animate);
})();

/* ==========================================================================
   2. SINKRONISASI REAL-TIME GLOBAL LIVE CHAT DI BERANDA
   ========================================================================== */
(function() {
    const previewList = document.getElementById('homeChatPreviewList');
    const statCount = document.getElementById('homeChatStatCount');
    if (!previewList) return;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    async function refreshHomeChat() {
        try {
            const res = await fetch('chat-api.php?action=fetch&limit=3&_t=' + Date.now(), { cache: 'no-store' });
            const data = await res.json();
            if (!data || !data.ok) return;

            if (statCount && typeof data.total_messages === 'number') {
                statCount.textContent = data.total_messages;
            }

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                previewList.innerHTML = data.messages.map(function(m) {
                    const delBtn = m.can_delete
                        ? `<button type="button" class="preview-del-btn" data-home-del="${m.id}" title="Hapus pesan ini">🗑</button>`
                        : '';
                    return `
                        <div class="chat-preview-item" data-preview-id="${m.id}">
                            <span class="user-avatar ${escapeHtml(m.avatar_class)}" style="width:32px;height:32px;font-size:0.74rem;">
                                ${escapeHtml(m.initials)}
                            </span>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
                                    <strong style="color:var(--dark);">${escapeHtml(m.display_name)}</strong>
                                    <span style="color:var(--muted);">${escapeHtml(m.time_ago)}</span>
                                </div>
                                <div style="color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    ${escapeHtml(m.message)}
                                </div>
                            </div>
                            ${delBtn}
                        </div>
                    `;
                }).join('');
            } else {
                previewList.innerHTML = `
                    <div class="chat-preview-item" style="justify-content:center;color:var(--text-light);">
                        Belum ada pesan di ruang obrolan. Yuk sapa teman-teman!
                    </div>
                `;
            }
        } catch (e) {}
    }

    previewList.addEventListener('click', async function(e) {
        const btn = e.target.closest('[data-home-del]');
        if (!btn) return;
        const id = btn.getAttribute('data-home-del');
        const row = btn.closest('.chat-preview-item');
        if (row) row.style.opacity = '0.35';
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        try {
            await fetch('chat-api.php', { method: 'POST', body: fd });
            await refreshHomeChat();
            if (window.showToast) window.showToast('Pesan dihapus dari Global Live Chat.', 'success');
        } catch (err) {}
    });

    window.addEventListener('pageshow', refreshHomeChat);
    refreshHomeChat();
    setInterval(refreshHomeChat, 3500);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
