<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Profil & Anggota';
$currentPage = 'profile';

$profile = $pdo->query("SELECT * FROM profiles LIMIT 1")->fetch();

$members = $pdo->query("
    SELECT u.id, u.display_name, u.role, u.avatar, u.bio, u.created_at,
           (SELECT COUNT(*) FROM albums WHERE user_id = u.id) AS album_count,
           (SELECT COUNT(*) FROM stories WHERE user_id = u.id) AS story_count
    FROM users u
    WHERE u.is_banned = 0
    ORDER BY CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.created_at ASC
    LIMIT 40
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.25rem;
        margin-top: 1.25rem;
    }
    .member-card {
        background: var(--lighter);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1rem;
        text-align: center;
        transition: all var(--t) var(--ease);
    }
    .member-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-sm);
        border-color: var(--primary-light);
        background: #fff;
    }
</style>

<main>
    <section class="profile-detail page-anim">
        <div class="container" style="padding:0;">
            <?php if ($profile): ?>
            <div class="profile-header">
                <h1><?= htmlspecialchars($profile['class_name'] ?? APP_NAME) ?></h1>
                <?php if (!empty($profile['photo']) && $profile['photo'] !== 'no-image.png' && $profile['photo'] !== 'tkj-logo.png'): ?>
                    <img src="uploads/<?= htmlspecialchars($profile['photo']) ?>" class="profile-photo" alt="Foto Profil" onerror="this.onerror=null;this.src='img/placeholder.svg';">
                <?php else: ?>
                    <div class="profile-avatar">
                        <span class="avatar-icon">&#128187;</span>
                    </div>
                <?php endif; ?>
                <div class="profile-meta">
                    <span><strong>Pembina / Wali:</strong> <?= htmlspecialchars($profile['teacher_name'] ?? '-') ?></span>
                    <span><strong>Tahun Ajaran:</strong> <?= htmlspecialchars($profile['school_year'] ?? '-') ?></span>
                </div>
            </div>

            <div class="profile-content">
                <div class="profile-section">
                    <h2>&#128221; Tentang &amp; Deskripsi</h2>
                    <p style="font-size:1.05rem;line-height:1.7;"><?= nl2br(htmlspecialchars($profile['description'] ?? '')) ?></p>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.5rem;margin-bottom:2rem;">
                    <div class="profile-section" style="margin-bottom:0;">
                        <h2>&#127919; Visi</h2>
                        <p class="vision-text"><?= nl2br(htmlspecialchars($profile['vision'] ?? '')) ?></p>
                    </div>
                    <div class="profile-section" style="margin-bottom:0;">
                        <h2>&#128640; Misi</h2>
                        <p class="mission-text"><?= nl2br(htmlspecialchars($profile['mission'] ?? '')) ?></p>
                    </div>
                </div>

                <!-- Daftar Anggota -->
                <?php if (!empty($members)): ?>
                <div class="profile-section" style="border-top:1px solid var(--border);padding-top:2rem;">
                    <h2>&#128101; Anggota Komunitas (<?= count($members) ?>)</h2>
                    <div class="members-grid">
                        <?php foreach ($members as $m): ?>
                            <div class="member-card">
                                <?= renderAvatar($m['display_name'], $m['avatar'] ?? null, '54px', '', 'margin:0 auto 0.75rem;') ?>
                                <strong style="font-size:0.95rem;color:var(--dark);display:block;"><?= htmlspecialchars($m['display_name']) ?></strong>
                                <span class="role-badge <?= $m['role'] === 'admin' ? 'role-admin' : 'role-user' ?>" style="font-size:0.68rem;margin-top:0.4rem;">
                                    <?= $m['role'] === 'admin' ? 'Administrator' : 'Anggota' ?>
                                </span>
                                <?php if (!empty($m['bio'])): ?>
                                    <div class="member-bio" title="<?= htmlspecialchars($m['bio']) ?>">
                                        &ldquo;<?= htmlspecialchars(mb_strimwidth($m['bio'], 0, 75, '...')) ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                                <div class="admin-hint" style="margin-top:0.5rem;">
                                    <?= (int)$m['album_count'] ?> album &middot; <?= (int)$m['story_count'] ?> cerita
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user && $user['role'] === 'admin'): ?>
                    <div style="text-align:right;margin-top:2rem;">
                        <a href="admin.php?tab=profil" class="btn btn-outline btn-sm">&#9881; Edit Profil di Panel Admin</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Data profil belum tersedia.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
