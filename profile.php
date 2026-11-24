<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Profil Kelas & Direktori Anggota';
$currentPage = 'profile';

$profile = $pdo->query("SELECT * FROM profiles LIMIT 1")->fetch();

// Cek apakah sedang melihat portofolio publik salah satu anggota (?member=ID)
$viewMemberId = isset($_GET['member']) ? (int)$_GET['member'] : 0;
$selectedMember = null;
$memberAlbums   = [];
$memberStories  = [];

if ($viewMemberId > 0) {
    $mStmt = $pdo->prepare("SELECT id, display_name, role, avatar, bio, specialty, created_at FROM users WHERE id = ? AND is_banned = 0");
    $mStmt->execute([$viewMemberId]);
    $selectedMember = $mStmt->fetch();

    if ($selectedMember) {
        $pageTitle = $selectedMember['display_name'] . ' — Portofolio Anggota';
        $aStmt = $pdo->prepare("
            SELECT a.*,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'album' AND target_id = a.id) AS like_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'album' AND target_id = a.id) AS comment_count
            FROM albums a WHERE a.user_id = ? ORDER BY a.created_at DESC
        ");
        $aStmt->execute([$viewMemberId]);
        $memberAlbums = $aStmt->fetchAll();

        $sStmt = $pdo->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'story' AND target_id = s.id) AS like_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'story' AND target_id = s.id) AS comment_count
            FROM stories s WHERE s.user_id = ? ORDER BY s.created_at DESC
        ");
        $sStmt->execute([$viewMemberId]);
        $memberStories = $sStmt->fetchAll();
    }
}

$members = $pdo->query("
    SELECT u.id, u.display_name, u.role, u.avatar, u.bio, u.specialty, u.created_at,
           (SELECT COUNT(*) FROM albums WHERE user_id = u.id) AS album_count,
           (SELECT COUNT(*) FROM stories WHERE user_id = u.id) AS story_count
    FROM users u
    WHERE u.is_banned = 0
    ORDER BY CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.created_at ASC
    LIMIT 60
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main>
    <?php if ($selectedMember): ?>
        <!-- Tampilan Portofolio Publik Anggota -->
        <div style="margin-bottom:1.25rem;">
            <a href="profile.php" class="btn btn-outline btn-sm"><?= svgIcon('arrow-left', 15) ?> Kembali ke Direktori Kelas</a>
        </div>

        <section class="dashboard-profile-hero page-anim">
            <div class="profile-hero-left">
                <div class="profile-avatar-large-wrap">
                    <?= renderAvatar($selectedMember['display_name'], $selectedMember['avatar'], '82px') ?>
                </div>
                <div class="profile-hero-meta">
                    <h2>
                        <?= htmlspecialchars($selectedMember['display_name']) ?>
                        <span class="role-badge <?= $selectedMember['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                            <?= $selectedMember['role'] === 'admin' ? 'Administrator' : 'Anggota' ?>
                        </span>
                    </h2>
                    <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-top:0.2rem;">
                        <?php if (!empty($selectedMember['specialty'])): ?>
                            <span class="specialty-pill" style="margin-top:0;"><?= htmlspecialchars($selectedMember['specialty']) ?></span>
                        <?php endif; ?>
                        <span class="admin-hint">Bergabung <?= fmtDate($selectedMember['created_at']) ?></span>
                    </div>
                    <?php if (!empty($selectedMember['bio'])): ?>
                        <div class="profile-hero-bio"><?= htmlspecialchars($selectedMember['bio']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;">
                <div class="stat-pill" style="padding:0.45rem 0.9rem;font-size:0.82rem;">
                    <?= svgIcon('image', 15) ?> <strong><?= count($memberAlbums) ?></strong> Karya
                </div>
                <div class="stat-pill" style="padding:0.45rem 0.9rem;font-size:0.82rem;">
                    <?= svgIcon('book', 15) ?> <strong><?= count($memberStories) ?></strong> Artikel
                </div>
            </div>
        </section>

        <?php if (!empty($memberAlbums)): ?>
        <section class="admin-card page-anim">
            <div class="section-header">
                <h2><?= svgIcon('image', 20) ?> Portofolio Galeri oleh <?= htmlspecialchars($selectedMember['display_name']) ?></h2>
            </div>
            <div class="album-grid">
                <?php foreach ($memberAlbums as $a): ?>
                <article class="album-card">
                    <a href="album-detail.php?id=<?= (int)$a['id'] ?>" class="album-link">
                        <div class="album-img-wrap">
                            <img src="<?= (!empty($a['photo']) && $a['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars(basename($a['photo'])) : 'img/placeholder.svg' ?>"
                                 alt="<?= htmlspecialchars($a['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='img/placeholder.svg';">
                            <span class="album-cat-pill"><?= htmlspecialchars($a['category'] ?: 'Dokumentasi') ?></span>
                        </div>
                        <div class="album-content">
                            <h3><?= htmlspecialchars($a['title']) ?></h3>
                            <span class="admin-hint"><?= fmtDate($a['created_at']) ?></span>
                            <div class="album-stats-bar">
                                <span class="stat-pill"><?= svgIcon('heart', 12) ?> <?= (int)$a['like_count'] ?></span>
                                <span class="stat-pill"><?= svgIcon('chat', 12) ?> <?= (int)$a['comment_count'] ?></span>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($memberStories)): ?>
        <section class="admin-card page-anim">
            <div class="section-header">
                <h2><?= svgIcon('book', 20) ?> Artikel oleh <?= htmlspecialchars($selectedMember['display_name']) ?></h2>
            </div>
            <div class="story-grid">
                <?php foreach ($memberStories as $s): ?>
                <article class="story-card">
                    <a href="story-detail.php?id=<?= (int)$s['id'] ?>" class="story-link">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.65rem;">
                            <span class="badge"><?= htmlspecialchars($s['category'] ?: 'Catatan') ?></span>
                            <span class="mono-tag"><?= readingTime($s['content']) ?></span>
                        </div>
                        <h3 style="font-size:1.08rem;color:var(--dark);margin-bottom:0.4rem;"><?= htmlspecialchars($s['title']) ?></h3>
                        <p style="font-size:0.86rem;color:var(--text-soft);flex:1;"><?= htmlspecialchars(mb_strimwidth($s['content'], 0, 140, '...')) ?></p>
                        <div class="story-footer-bar">
                            <span class="stat-pill"><?= svgIcon('heart', 12) ?> <?= (int)$s['like_count'] ?></span>
                            <span class="stat-pill"><?= svgIcon('chat', 12) ?> <?= (int)$s['comment_count'] ?></span>
                            <span style="margin-left:auto;font-size:0.76rem;color:var(--muted);"><?= fmtDate($s['created_at']) ?></span>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (empty($memberAlbums) && empty($memberStories)): ?>
            <div class="empty-state page-anim" style="margin-bottom:2rem;">
                <p>Anggota ini belum mempublikasikan album atau artikel.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Profil Utama Kelas & Direktori Anggota -->
    <?php if ($profile): ?>
    <section class="profile-hero-banner page-anim">
        <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png' && is_file(__DIR__ . '/uploads/' . basename($profile['photo']))): ?>
            <img src="uploads/<?= htmlspecialchars(basename($profile['photo'])) ?>" class="profile-class-emblem" alt="Logo Kelas">
        <?php else: ?>
            <div class="profile-class-emblem">
                <?= svgIcon('terminal', 38) ?>
            </div>
        <?php endif; ?>

        <div style="flex:1;min-width:240px;">
            <span class="hero-eyebrow" style="margin-bottom:0.65rem;"><?= svgIcon('users', 14) ?> Profil Resmi Komunitas</span>
            <h1 style="font-size:2.1rem;color:#fff;margin-bottom:0.45rem;"><?= htmlspecialchars($profile['class_name']) ?></h1>
            <p style="color:rgba(226,232,240,0.86);max-width:680px;font-size:0.96rem;line-height:1.68;margin-bottom:1rem;">
                <?= nl2br(htmlspecialchars($profile['description'] ?? '')) ?>
            </p>
            <div style="display:flex;gap:1.25rem;flex-wrap:wrap;font-size:0.85rem;color:#cbd5e1;">
                <span><strong>Wali / Pembina:</strong> <?= htmlspecialchars($profile['teacher_name'] ?: '-') ?></span>
                <span>&bull;</span>
                <span><strong>Tahun Akademik:</strong> <?= htmlspecialchars($profile['school_year'] ?: '-') ?></span>
                <?php if (!empty($profile['schedule_note'])): ?>
                    <span>&bull;</span>
                    <span><?= htmlspecialchars($profile['schedule_note']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user && $user['role'] === 'admin'): ?>
            <a href="admin.php?tab=profil" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:#fff !important;border-color:rgba(255,255,255,0.22);">
                <?= svgIcon('edit', 14) ?> Edit Profil Kelas
            </a>
        <?php endif; ?>
    </section>

    <div class="bento-two-col page-anim" style="grid-template-columns:1fr 1fr;">
        <section class="bento-card" style="border-top:3px solid var(--primary);">
            <div>
                <span class="pill-eyebrow" style="margin-bottom:0.55rem;">Visi Komunitas</span>
                <p style="font-size:1rem;color:var(--dark);line-height:1.75;font-weight:500;">
                    <?= nl2br(htmlspecialchars($profile['vision'] ?? '-')) ?>
                </p>
            </div>
        </section>
        <section class="bento-card" style="border-top:3px solid var(--secondary);">
            <div>
                <span class="pill-eyebrow" style="margin-bottom:0.55rem;">Misi &amp; Fokus Pengembangan</span>
                <p style="font-size:0.93rem;color:var(--text-soft);line-height:1.75;">
                    <?= nl2br(htmlspecialchars($profile['mission'] ?? '-')) ?>
                </p>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <!-- Direktori Anggota Interaktif -->
    <section class="admin-card page-anim">
        <div class="section-header">
            <div>
                <h2><?= svgIcon('users', 20) ?> Direktori Anggota (<?= count($members) ?>)</h2>
                <p class="admin-hint">Klik kartu anggota untuk melihat portofolio album dan artikel yang telah dipublikasikan.</p>
            </div>
        </div>

        <div class="toolbar" style="margin-bottom:1.4rem;">
            <div class="search-box">
                <span class="search-icon"><?= svgIcon('search', 16) ?></span>
                <input type="search" id="memberSearchInput" placeholder="Cari nama anggota atau bidang keahlian TI..." aria-label="Cari anggota">
            </div>
            <span class="admin-hint" id="memberCountLabel"><?= count($members) ?> anggota aktif</span>
        </div>

        <?php if (!empty($members)): ?>
        <div class="members-grid" id="membersDirectoryGrid">
            <?php foreach ($members as $m): ?>
                <a href="profile.php?member=<?= (int)$m['id'] ?>" class="member-card"
                   data-member-search="<?= strtolower(htmlspecialchars($m['display_name'] . ' ' . ($m['specialty'] ?? '') . ' ' . ($m['bio'] ?? ''))) ?>"
                   style="color:inherit;text-decoration:none;">
                    <?= renderAvatar($m['display_name'], $m['avatar'] ?? null, '58px', '', 'margin-bottom:0.75rem;') ?>
                    <strong style="font-size:0.96rem;color:var(--dark);display:block;"><?= htmlspecialchars($m['display_name']) ?></strong>
                    <div style="display:flex;gap:0.35rem;flex-wrap:wrap;justify-content:center;margin-top:0.35rem;">
                        <span class="role-badge <?= $m['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                            <?= $m['role'] === 'admin' ? 'Admin' : 'Anggota' ?>
                        </span>
                    </div>
                    <?php if (!empty($m['specialty'])): ?>
                        <span class="specialty-pill"><?= htmlspecialchars($m['specialty']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($m['bio'])): ?>
                        <p class="member-bio">&ldquo;<?= htmlspecialchars($m['bio']) ?>&rdquo;</p>
                    <?php endif; ?>
                    <div class="admin-hint" style="margin-top:auto;padding-top:0.75rem;font-size:0.76rem;">
                        <strong><?= (int)$m['album_count'] ?></strong> karya &middot; <strong><?= (int)$m['story_count'] ?></strong> artikel
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>Belum ada anggota terdaftar.</p>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
(function() {
    const inp  = document.getElementById('memberSearchInput');
    const grid = document.getElementById('membersDirectoryGrid');
    const lbl  = document.getElementById('memberCountLabel');
    if (inp && grid) {
        inp.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let shown = 0;
            grid.querySelectorAll('.member-card').forEach(function(card) {
                const ok = !q || (card.dataset.memberSearch || '').includes(q);
                card.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            if (lbl) lbl.textContent = shown + ' anggota ditemukan';
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
