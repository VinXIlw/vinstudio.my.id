<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Beranda';
$currentPage = 'home';

$profile      = $pdo->query("SELECT * FROM profiles LIMIT 1")->fetch();
$albumCount   = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
$storyCount   = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$memberCount  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 0")->fetchColumn();
$chatCount    = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();

// Pengumuman resmi kelas (disematkan di atas lalu terbaru)
$announcements = $pdo->query("
    SELECT a.*, u.display_name AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
    LIMIT 3
")->fetchAll();

// 4 Album Terbaru
$recentAlbums = $pdo->query("
    SELECT a.*, u.display_name AS username, u.avatar AS user_avatar,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = a.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'album' AND target_id = a.id) AS comment_count
    FROM albums a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 4
")->fetchAll();

// 3 Artikel Terbaru
$recentStories = $pdo->query("
    SELECT s.*, u.display_name AS username, u.avatar AS user_avatar,
           (SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = s.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE target_type = 'story' AND target_id = s.id) AS comment_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 3
")->fetchAll();

// 3 Pesan Terakhir Live Chat
$recentChats = $pdo->query("
    SELECT m.*, u.display_name, u.avatar
    FROM chat_messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.id DESC
    LIMIT 3
")->fetchAll();
$recentChats = array_reverse($recentChats);

include __DIR__ . '/includes/header.php';
?>

<!-- Kanvas Interaktif Partikel & Halo Kursor (Khusus Beranda) -->
<canvas id="antigravityBgCanvas" aria-hidden="true"></canvas>

<main class="home-main-content">
    <!-- Hero Banner Utama -->
    <section class="hero-banner page-anim">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="nav-live-dot"></span>
                <span><?= htmlspecialchars($profile['class_name'] ?? 'Kelas XI TKJ 1') ?> &middot; Tahun Ajaran <?= htmlspecialchars($profile['school_year'] ?? '2026/2027') ?></span>
            </div>
            <h1 class="hero-title">
                Ekosistem Karya Digital &amp; Kolaborasi <span class="brand-gradient"><?= htmlspecialchars(APP_NAME) ?></span>
            </h1>
            <p class="hero-desc">
                Pusat dokumentasi proyek praktikum jaringan, repositori catatan &amp; tutorial teknologi, papan pengumuman kelas, serta ruang obrolan real-time bersama seluruh anggota komunitas.
            </p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="chat.php" class="btn btn-primary btn-lg">
                    <span class="nav-live-dot" style="background:#fff;box-shadow:none;"></span>
                    <span>Buka Global Live Chat</span>
                </a>
                <a href="albums.php" class="btn btn-outline btn-lg" style="background:rgba(255,255,255,0.08);color:#fff !important;border-color:rgba(255,255,255,0.2);">
                    <?= svgIcon('image', 17) ?>
                    <span>Eksplorasi Galeri</span>
                </a>
                <a href="stories.php" class="btn btn-outline btn-lg" style="background:rgba(255,255,255,0.04);color:#cbd5e1 !important;border-color:rgba(255,255,255,0.14);">
                    <?= svgIcon('book', 17) ?>
                    <span>Baca Artikel TI</span>
                </a>
            </div>

            <div class="hero-stats-row">
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $albumCount ?>"><?= $albumCount ?></span>
                    <div class="lbl">Proyek &amp;<br>Album Foto</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $storyCount ?>"><?= $storyCount ?></span>
                    <div class="lbl">Artikel &amp;<br>Tutorial</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" data-count="<?= $memberCount ?>"><?= $memberCount ?></span>
                    <div class="lbl">Anggota<br>Aktif</div>
                </div>
                <div class="hero-stat-badge">
                    <span class="num" id="homeChatStatCount" data-count="<?= $chatCount ?>"><?= $chatCount ?></span>
                    <div class="lbl">Pesan di<br>Live Chat</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Papan Pengumuman & Agenda Kelas (Jika Ada) -->
    <?php if (!empty($announcements)): ?>
    <section class="page-anim" style="margin-bottom:1.75rem;">
        <div class="section-header" style="margin-bottom:0.9rem;">
            <div style="display:flex;align-items:center;gap:0.55rem;">
                <span class="pill-eyebrow"><?= svgIcon('megaphone', 14) ?> Pengumuman Resmi</span>
            </div>
            <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="admin.php?tab=pengumuman" class="btn btn-ghost btn-sm"><?= svgIcon('settings', 15) ?> Kelola Pengumuman</a>
            <?php endif; ?>
        </div>
        <div class="announcement-strip">
            <?php foreach ($announcements as $ann): ?>
                <?php
                $prio = $ann['priority'] ?? 'info';
                $prioLabel = $prio === 'important' ? 'PENTING' : ($prio === 'event' ? 'AGENDA' : 'INFO KELAS');
                $prioBadge = $prio === 'important' ? 'role-banned' : ($prio === 'event' ? 'role-admin' : 'role-user');
                ?>
                <article class="announce-card prio-<?= htmlspecialchars($prio) ?>">
                    <div class="announce-top">
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <?php if ((int)$ann['is_pinned'] === 1): ?>
                                <span class="badge" title="Disematkan"><?= svgIcon('pin', 12) ?> Pinned</span>
                            <?php endif; ?>
                            <span class="role-badge <?= $prioBadge ?>"><?= $prioLabel ?></span>
                        </div>
                        <span class="admin-hint"><?= timeAgo($ann['created_at']) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($ann['title']) ?></h3>
                    <p><?= nl2br(htmlspecialchars(mb_strimwidth($ann['content'], 0, 180, '...'))) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Bento Grid: Profil Komunitas & Global Live Chat -->
    <div class="bento-two-col page-anim">
        <?php if ($profile): ?>
        <section class="bento-card reveal" style="border-top:3px solid var(--primary);">
            <div>
                <div style="display:flex;align-items:center;gap:1.1rem;margin-bottom:1.1rem;">
                    <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png' && is_file(__DIR__ . '/uploads/' . basename($profile['photo']))): ?>
                        <img src="uploads/<?= htmlspecialchars(basename($profile['photo'])) ?>" alt="Logo Kelas" style="width:64px;height:64px;border-radius:18px;object-fit:cover;box-shadow:var(--shadow-brand);">
                    <?php else: ?>
                        <div class="logo-icon" style="width:64px;height:64px;border-radius:18px;">
                            <?= svgIcon('terminal', 28) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="pill-eyebrow" style="margin-bottom:0.3rem;">Identitas Komunitas</span>
                        <h2 style="font-size:1.35rem;color:var(--dark);"><?= htmlspecialchars($profile['class_name']) ?></h2>
                    </div>
                </div>
                <p style="color:var(--text-soft);font-size:0.92rem;line-height:1.68;margin-bottom:1.15rem;">
                    <?= htmlspecialchars($profile['description']) ?>
                </p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;padding:0.9rem 1rem;background:var(--lighter);border-radius:var(--radius);border:1px solid var(--border);font-size:0.84rem;margin-bottom:1.2rem;">
                    <div>
                        <span style="color:var(--muted);display:block;font-size:0.72rem;text-transform:uppercase;font-weight:700;">Wali / Pembina</span>
                        <strong style="color:var(--dark);"><?= htmlspecialchars($profile['teacher_name'] ?: '-') ?></strong>
                    </div>
                    <div>
                        <span style="color:var(--muted);display:block;font-size:0.72rem;text-transform:uppercase;font-weight:700;">Tahun Akademik</span>
                        <strong style="color:var(--dark);"><?= htmlspecialchars($profile['school_year'] ?: '-') ?></strong>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                <a href="profile.php" class="btn btn-outline btn-sm">
                    <span>Lihat Direktori Anggota</span>
                    <?= svgIcon('arrow-right', 15) ?>
                </a>
                <?php if (!$user): ?>
                    <a href="register.php" class="btn btn-primary btn-sm" data-no-transition>Gabung Anggota</a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Spotlight Global Live Chat -->
        <section class="bento-card reveal" style="border-top:3px solid var(--success);">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;margin-bottom:0.35rem;">
                    <div style="display:flex;align-items:center;gap:0.55rem;">
                        <span class="nav-live-dot"></span>
                        <h2 style="font-size:1.28rem;color:var(--dark);margin:0;">Global Live Chat</h2>
                    </div>
                    <span class="role-badge btn-success" style="font-size:0.68rem;">REAL-TIME</span>
                </div>
                <p class="admin-hint">Percakapan langsung terbaru dari ruang diskusi komunitas:</p>

                <div class="chat-preview-list" id="homeChatPreviewList">
                    <?php if (!empty($recentChats)): ?>
                        <?php foreach ($recentChats as $cm): ?>
                            <?php $canDel = $user && ((int)$cm['user_id'] === (int)$user['id'] || $user['role'] === 'admin'); ?>
                            <div class="chat-preview-item" data-preview-id="<?= (int)$cm['id'] ?>">
                                <?= renderAvatar($cm['display_name'], $cm['avatar'] ?? null, '32px') ?>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.74rem;">
                                        <strong style="color:var(--dark);"><?= htmlspecialchars($cm['display_name']) ?></strong>
                                        <span style="color:var(--muted);"><?= timeAgo($cm['created_at']) ?></span>
                                    </div>
                                    <div style="color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($cm['message']) ?>
                                    </div>
                                </div>
                                <?php if ($canDel): ?>
                                    <button type="button" class="preview-del-btn" data-home-del="<?= (int)$cm['id'] ?>" title="Hapus pesan">
                                        <?= svgIcon('trash', 14) ?>
                                    </button>
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

            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                <span class="admin-hint">Terhubung otomatis tanpa muat ulang</span>
                <a href="chat.php" class="btn btn-primary btn-sm">
                    <?= svgIcon('chat', 15) ?>
                    <span>Masuk Ruang Obrolan</span>
                </a>
            </div>
        </section>
    </div>

    <!-- Kotak Bento: Galeri Proyek & Dokumentasi Terbaru -->
    <section class="bento-card bento-section-box reveal" style="border-top:3px solid var(--primary);">
        <div class="section-header">
            <div>
                <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('image', 13) ?> Portofolio Visual</span>
                <h2>Galeri Proyek &amp; Dokumentasi Terbaru</h2>
                <p class="admin-hint">Dokumentasi praktikum jaringan, kegiatan kelas, dan karya visual pilihan.</p>
            </div>
            <div style="display:flex;gap:0.55rem;flex-wrap:wrap;">
                <?php if ($user): ?>
                    <a href="album-add.php" class="btn btn-primary btn-sm"><?= svgIcon('plus', 15) ?> Unggah Karya</a>
                <?php endif; ?>
                <a href="albums.php" class="btn btn-outline btn-sm">Semua Galeri <?= svgIcon('arrow-right', 14) ?></a>
            </div>
        </div>

        <?php if (!empty($recentAlbums)): ?>
        <div class="album-grid">
            <?php foreach ($recentAlbums as $album): ?>
                <article class="album-card">
                    <a href="album-detail.php?id=<?= (int)$album['id'] ?>" class="album-link">
                        <div class="album-img-wrap">
                            <img src="<?= (!empty($album['photo']) && $album['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars(basename($album['photo'])) : 'img/placeholder.svg' ?>"
                                 alt="<?= htmlspecialchars($album['title']) ?>" loading="lazy"
                                 onerror="this.onerror=null;this.src='img/placeholder.svg';">
                            <span class="album-cat-pill"><?= htmlspecialchars($album['category'] ?: 'Dokumentasi') ?></span>
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
                                <p style="font-size:0.85rem;color:var(--text-soft);margin-bottom:auto;">
                                    <?= htmlspecialchars(mb_strimwidth($album['description'], 0, 88, '...')) ?>
                                </p>
                            <?php endif; ?>
                            <div class="album-stats-bar">
                                <span class="stat-pill"><?= svgIcon('heart', 13) ?> <?= (int)$album['like_count'] ?></span>
                                <span class="stat-pill"><?= svgIcon('chat', 13) ?> <?= (int)$album['comment_count'] ?></span>
                                <span class="stat-pill"><?= svgIcon('eye', 13) ?> <?= (int)($album['views'] ?? 0) ?></span>
                            </div>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div style="margin-bottom:0.6rem;color:var(--primary-light);"><?= svgIcon('image', 34) ?></div>
            <p style="font-weight:600;color:var(--dark);margin-bottom:0.25rem;">Belum ada dokumentasi atau proyek yang diunggah.</p>
            <p class="admin-hint" style="margin-bottom:1rem;">Jadilah anggota pertama yang membagikan hasil praktikum atau momen kelas.</p>
            <a href="<?= $user ? 'album-add.php' : 'login.php?r=album-add.php' ?>" class="btn btn-primary btn-sm" <?= !$user ? 'data-no-transition' : '' ?>>
                <?= svgIcon('plus', 15) ?> Tambah Karya Pertama
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Kotak Bento: Artikel, Tutorial & Catatan Terbaru -->
    <section class="bento-card bento-section-box reveal" style="border-top:3px solid var(--secondary);margin-bottom:0;">
        <div class="section-header">
            <div>
                <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('book', 13) ?> Literasi &amp; Tutorial TI</span>
                <h2>Artikel, Catatan Praktikum &amp; Cerita Terbaru</h2>
                <p class="admin-hint">Berbagi panduan konfigurasi, rangkuman materi jaringan, dan pengalaman belajar.</p>
            </div>
            <div style="display:flex;gap:0.55rem;flex-wrap:wrap;">
                <?php if ($user): ?>
                    <a href="story-add.php" class="btn btn-primary btn-sm"><?= svgIcon('plus', 15) ?> Tulis Artikel</a>
                <?php endif; ?>
                <a href="stories.php" class="btn btn-outline btn-sm">Semua Artikel <?= svgIcon('arrow-right', 14) ?></a>
            </div>
        </div>

        <?php if (!empty($recentStories)): ?>
        <div class="story-grid">
            <?php foreach ($recentStories as $story): ?>
                <article class="story-card">
                    <a href="story-detail.php?id=<?= (int)$story['id'] ?>" class="story-link">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                            <span class="badge"><?= htmlspecialchars($story['category'] ?: 'Catatan Praktikum') ?></span>
                            <span class="mono-tag"><?= readingTime($story['content']) ?></span>
                        </div>
                        <h3 style="font-size:1.12rem;color:var(--dark);margin-bottom:0.45rem;line-height:1.35;">
                            <?= htmlspecialchars($story['title']) ?>
                        </h3>
                        <p style="font-size:0.88rem;color:var(--text-soft);line-height:1.65;flex:1;">
                            <?= htmlspecialchars(mb_strimwidth($story['content'], 0, 160, '...')) ?>
                        </p>
                        <div class="story-footer-bar">
                            <?= renderAvatar($story['username'], $story['user_avatar'] ?? null, '22px') ?>
                            <span style="font-size:0.77rem;font-weight:600;color:var(--text-soft);"><?= htmlspecialchars($story['username']) ?></span>
                            <span style="margin-left:auto;display:flex;gap:0.4rem;">
                                <span class="stat-pill"><?= svgIcon('heart', 12) ?> <?= (int)$story['like_count'] ?></span>
                                <span class="stat-pill"><?= svgIcon('chat', 12) ?> <?= (int)$story['comment_count'] ?></span>
                            </span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div style="margin-bottom:0.6rem;color:var(--secondary);"><?= svgIcon('book', 34) ?></div>
            <p style="font-weight:600;color:var(--dark);margin-bottom:0.25rem;">Belum ada artikel atau catatan yang diterbitkan.</p>
            <p class="admin-hint" style="margin-bottom:1rem;">Bagikan tutorial jaringan, catatan Linux, atau cerita menarikmu di sini.</p>
            <a href="<?= $user ? 'story-add.php' : 'login.php?r=story-add.php' ?>" class="btn btn-primary btn-sm" <?= !$user ? 'data-no-transition' : '' ?>>
                <?= svgIcon('plus', 15) ?> Tulis Artikel Pertama
            </a>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
/* 1. Efek Partikel Kapsul & Cincin Magnetik Kursor (Khusus Beranda) */
(function() {
    const canvas = document.getElementById('antigravityBgCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    let width = window.innerWidth;
    let height = window.innerHeight;
    let dpr = Math.min(window.devicePixelRatio || 1, 2);
    const palette = ['#4285F4', '#6366F1', '#38BDF8', '#A855F7', '#EC4899', '#10B981', '#F59E0B'];
    const pointer = { x: width * 0.5, y: height * 0.38, targetX: width * 0.5, targetY: height * 0.38, active: false, pulse: 0 };
    let particles = [];

    function initCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const spacing = width < 768 ? 56 : 46;
        const cols = Math.ceil(width / spacing) + 1;
        const rows = Math.ceil(height / spacing) + 1;
        particles = [];
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const hx = c * spacing + (Math.random() - 0.5) * spacing * 0.65;
                const hy = r * spacing + (Math.random() - 0.5) * spacing * 0.65;
                particles.push({
                    x: hx, y: hy, homeX: hx, homeY: hy, vx: 0, vy: 0,
                    angle: Math.random() * Math.PI * 2,
                    color: palette[(r * cols + c) % palette.length],
                    phase: Math.random() * Math.PI * 2,
                    speed: 0.6 + Math.random() * 0.75,
                    baseLen: 4.2 + Math.random() * 4.2,
                    thickness: 2 + Math.random() * 1
                });
            }
        }
    }

    const onMove = e => { pointer.targetX = e.clientX; pointer.targetY = e.clientY; pointer.active = true; };
    const onTouch = e => { if (e.touches && e.touches[0]) { pointer.targetX = e.touches[0].clientX; pointer.targetY = e.touches[0].clientY; pointer.active = true; } };
    const onDown = () => { pointer.pulse = 1.0; };

    window.addEventListener('resize', initCanvas);
    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('touchmove', onTouch, { passive: true });
    window.addEventListener('mousedown', onDown);
    initCanvas();

    let startTime = performance.now();
    let rafId = null;
    let alive = true;

    window.__pjaxCleanupFns = window.__pjaxCleanupFns || [];
    window.__pjaxCleanupFns.push(function() {
        alive = false;
        if (rafId) cancelAnimationFrame(rafId);
        window.removeEventListener('resize', initCanvas);
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('touchmove', onTouch);
        window.removeEventListener('mousedown', onDown);
    });

    function animate(now) {
        if (!alive || !document.body.contains(canvas)) return;
        const t = (now - startTime) * 0.001;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        ctx.clearRect(0, 0, width, height);
        if (!pointer.active) {
            pointer.targetX = width * 0.5 + Math.cos(t * 0.55) * (width * 0.22);
            pointer.targetY = height * 0.36 + Math.sin(t * 0.9) * (height * 0.14);
        }
        pointer.x += (pointer.targetX - pointer.x) * 0.12;
        pointer.y += (pointer.targetY - pointer.y) * 0.12;
        pointer.pulse = pointer.pulse > 0.01 ? pointer.pulse * 0.91 : 0;

        const glowRadius = width < 768 ? 240 : 370;
        const grad = ctx.createRadialGradient(pointer.x, pointer.y, 10, pointer.x, pointer.y, glowRadius);
        grad.addColorStop(0, isDark ? 'rgba(99, 102, 241, 0.16)' : 'rgba(79, 70, 229, 0.10)');
        grad.addColorStop(0.5, isDark ? 'rgba(14, 165, 233, 0.07)' : 'rgba(14, 165, 233, 0.04)');
        grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, width, height);

        const maxInfluence = width < 768 ? 205 : 300;
        const baseRingRadius = (width < 768 ? 92 : 140) + pointer.pulse * 72;
        ctx.lineCap = 'round';

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];
            const floatX = p.homeX + Math.cos(t * p.speed + p.phase) * 8.5;
            const floatY = p.homeY + Math.sin(t * p.speed + p.phase) * 8.5;
            const dx = p.x - pointer.x;
            const dy = p.y - pointer.y;
            const dist = Math.hypot(dx, dy) || 0.001;
            const angleToCursor = Math.atan2(dy, dx);

            let targetX = floatX, targetY = floatY, targetAngle = p.phase + t * 0.4, energy = 0;
            if (dist < maxInfluence) {
                energy = Math.pow(1 - dist / maxInfluence, 1.35);
                const waveOffset = Math.sin(angleToCursor * 4 + t * 2.5) * 16 + Math.cos(angleToCursor * 3 - t * 1.8) * 11;
                const ringR = baseRingRadius + waveOffset;
                const swirlAngle = angleToCursor + 0.18 * energy;
                targetX = floatX * (1 - energy * 0.78) + (pointer.x + Math.cos(swirlAngle) * ringR) * (energy * 0.78);
                targetY = floatY * (1 - energy * 0.78) + (pointer.y + Math.sin(swirlAngle) * ringR) * (energy * 0.78);
                targetAngle = angleToCursor + Math.sin(t * 2 + p.phase) * 0.25;
            }

            p.vx = (p.vx + (targetX - p.x) * 0.085) * 0.80;
            p.vy = (p.vy + (targetY - p.y) * 0.085) * 0.80;
            p.x += p.vx;
            p.y += p.vy;

            let diffAngle = targetAngle - p.angle;
            while (diffAngle > Math.PI) diffAngle -= Math.PI * 2;
            while (diffAngle < -Math.PI) diffAngle += Math.PI * 2;
            p.angle += diffAngle * 0.14;

            const halfLen = (p.baseLen + energy * 8) * 0.5;
            const cosA = Math.cos(p.angle) * halfLen;
            const sinA = Math.sin(p.angle) * halfLen;

            const baseAlpha = isDark ? 0.24 : 0.20;
            const activeAlpha = isDark ? 0.85 : 0.74;
            ctx.globalAlpha = baseAlpha + energy * (activeAlpha - baseAlpha);
            ctx.strokeStyle = p.color;
            ctx.lineWidth = p.thickness + energy;
            ctx.beginPath();
            ctx.moveTo(p.x - cosA, p.y - sinA);
            ctx.lineTo(p.x + cosA, p.y + sinA);
            ctx.stroke();
        }
        ctx.globalAlpha = 1;
        rafId = requestAnimationFrame(animate);
    }
    rafId = requestAnimationFrame(animate);
})();

/* 2. Sinkronisasi Real-Time Global Live Chat di Beranda (CSRF-Protected) */
(function() {
    const previewList = document.getElementById('homeChatPreviewList');
    const statCount   = document.getElementById('homeChatStatCount');
    if (!previewList) return;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    let homeChatTimer = null;
    async function refreshHomeChat() {
        if (!document.body.contains(previewList)) {
            if (homeChatTimer) clearInterval(homeChatTimer);
            return;
        }
        try {
            const res  = await fetch('chat-api.php?action=fetch&limit=3&_t=' + Date.now(), { cache: 'no-store' });
            const data = await res.json();
            if (!data || !data.ok) return;

            if (statCount && typeof data.total_messages === 'number') {
                statCount.textContent = data.total_messages;
            }

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                previewList.innerHTML = data.messages.map(function(m) {
                    const delBtn = m.can_delete
                        ? `<button type="button" class="preview-del-btn" data-home-del="${m.id}" title="Hapus pesan">&times;</button>`
                        : '';
                    const av = m.avatar
                        ? `<img src="uploads/${escapeHtml(m.avatar)}" class="user-avatar-img" style="width:32px;height:32px;" alt="">`
                        : `<span class="user-avatar ${escapeHtml(m.avatar_class)}" style="width:32px;height:32px;font-size:0.74rem;">${escapeHtml(m.initials)}</span>`;
                    return `
                        <div class="chat-preview-item" data-preview-id="${m.id}">
                            ${av}
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;font-size:0.74rem;">
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
                previewList.innerHTML = `<div class="chat-preview-item" style="justify-content:center;color:var(--text-light);">Belum ada pesan di ruang obrolan. Yuk sapa teman-teman!</div>`;
            }
        } catch (e) {}
    }

    previewList.addEventListener('click', async function(e) {
        const btn = e.target.closest('[data-home-del]');
        if (!btn) return;
        const row = btn.closest('.chat-preview-item');
        if (row) row.style.opacity = '0.35';
        const token = window.getCsrfToken ? window.getCsrfToken() : '';
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', btn.getAttribute('data-home-del'));
        fd.append('csrf_token', token);
        try {
            await fetch('chat-api.php', { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
            await refreshHomeChat();
            if (window.showToast) window.showToast('Pesan dihapus.', 'success');
        } catch (err) {}
    });

    refreshHomeChat();
    homeChatTimer = setInterval(refreshHomeChat, 3500);
    window.__pjaxCleanupFns = window.__pjaxCleanupFns || [];
    window.__pjaxCleanupFns.push(function() {
        if (homeChatTimer) clearInterval(homeChatTimer);
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
