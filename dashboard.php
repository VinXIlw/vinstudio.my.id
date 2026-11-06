<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Dashboard Pengguna';
$currentPage = 'dashboard';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Hitung total konten saya
$albumStmt = $pdo->prepare("SELECT COUNT(*) FROM albums WHERE user_id = ?");
$albumStmt->execute([$user['id']]);
$albumCount = (int)$albumStmt->fetchColumn();

$storyStmt = $pdo->prepare("SELECT COUNT(*) FROM stories WHERE user_id = ?");
$storyStmt->execute([$user['id']]);
$storyCount = (int)$storyStmt->fetchColumn();

// Ambil daftar album saya
$myAlbumsStmt = $pdo->prepare("SELECT * FROM albums WHERE user_id = ? ORDER BY created_at DESC");
$myAlbumsStmt->execute([$user['id']]);
$myAlbums = $myAlbumsStmt->fetchAll();

// Ambil daftar cerita saya
$myStoriesStmt = $pdo->prepare("SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC");
$myStoriesStmt->execute([$user['id']]);
$myStories = $myStoriesStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="dashboard-page">
        <div class="container">
            <div class="page-header page-anim">
                <div>
                    <h1>Halo, <span class="highlight"><?= htmlspecialchars($user['displayName']) ?></span>!</h1>
                    <p class="page-subtitle">Kelola konten dan pengaturan profil akun Anda.</p>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <a href="album-add.php" class="btn btn-primary btn-sm">&#10133; Tambah Album</a>
                    <a href="story-add.php" class="btn btn-outline btn-sm">&#10133; Tulis Cerita</a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <section class="dashboard-card page-anim">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
                    <span class="user-avatar <?= avatarClass($user['displayName']) ?>" style="width:52px;height:52px;font-size:1.2rem;">
                        <?= initials($user['displayName']) ?>
                    </span>
                    <div>
                        <h2 style="font-size:1.25rem;margin-bottom:0.2rem;color:var(--dark);"><?= htmlspecialchars($user['displayName']) ?></h2>
                        <span class="admin-hint">Email: <strong><?= htmlspecialchars($user['email']) ?></strong> &middot; Peran: <strong><?= $user['role'] === 'admin' ? 'Administrator' : 'Anggota' ?></strong></span>
                    </div>
                </div>

                <form action="account-name.php" method="POST" class="name-form" style="max-width:440px;">
                    <div class="form-group">
                        <label for="display_name">Ganti Nama Tampilan</label>
                        <input type="text" id="display_name" name="display_name" maxlength="60"
                               value="<?= htmlspecialchars($user['displayName']) ?>" required
                               placeholder="Contoh: Kelvin">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">&#9999; Simpan Perubahan Nama</button>
                </form>
            </section>

            <div class="dashboard-stats page-anim">
                <div class="stat-card">
                    <span class="stat-number"><?= $albumCount ?></span>
                    <span class="stat-label">Album yang kamu buat</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $storyCount ?></span>
                    <span class="stat-label">Cerita yang kamu terbitkan</span>
                </div>
            </div>

            <div class="dashboard-grids page-anim">
                <!-- Album Saya -->
                <div class="dashboard-column">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h2 style="font-size:1.2rem;color:var(--dark);">&#128248; Album Saya</h2>
                        <a href="album-add.php" class="btn btn-outline btn-sm">+ Baru</a>
                    </div>
                    <?php if (!empty($myAlbums)): ?>
                        <div class="dashboard-list">
                            <?php foreach ($myAlbums as $a): ?>
                            <article class="list-item">
                                <div style="min-width:0;flex:1;">
                                    <a href="album-detail.php?id=<?= $a['id'] ?>" style="font-weight:700;color:var(--dark);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </a>
                                    <small class="admin-hint"><?= timeAgo($a['created_at']) ?></small>
                                </div>
                                <div style="display:flex;gap:0.4rem;align-items:center;flex-shrink:0;">
                                    <a href="album-detail.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem;font-size:0.75rem;">Lihat</a>
                                    <form action="album-delete.php" method="POST" onsubmit="return confirm('Hapus album ini?');" style="margin:0;">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" style="padding:0.3rem 0.6rem;font-size:0.75rem;">&#128465;</button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Belum ada album. <a href="album-add.php">Buat album pertama</a></p>
                    <?php endif; ?>
                </div>

                <!-- Cerita Saya -->
                <div class="dashboard-column">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h2 style="font-size:1.2rem;color:var(--dark);">&#128214; Cerita Saya</h2>
                        <a href="story-add.php" class="btn btn-outline btn-sm">+ Baru</a>
                    </div>
                    <?php if (!empty($myStories)): ?>
                        <div class="dashboard-list">
                            <?php foreach ($myStories as $s): ?>
                            <article class="list-item">
                                <div style="min-width:0;flex:1;">
                                    <a href="story-detail.php?id=<?= $s['id'] ?>" style="font-weight:700;color:var(--dark);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($s['title']) ?>
                                    </a>
                                    <small class="admin-hint"><?= timeAgo($s['created_at']) ?></small>
                                </div>
                                <div style="display:flex;gap:0.4rem;align-items:center;flex-shrink:0;">
                                    <a href="story-detail.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem;font-size:0.75rem;">Baca</a>
                                    <form action="story-delete.php" method="POST" onsubmit="return confirm('Hapus cerita ini?');" style="margin:0;">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" style="padding:0.3rem 0.6rem;font-size:0.75rem;">&#128465;</button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Belum ada cerita. <a href="story-add.php">Tulis cerita pertama</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
