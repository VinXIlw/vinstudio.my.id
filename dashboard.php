<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Pengaturan Profil & Dasbor';
$currentPage = 'dashboard';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$activeTab = $_GET['tab'] ?? 'profil';

// Ambil data terbaru pengguna langsung dari database
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user['id']]);
$userData = $stmtUser->fetch();

if (!$userData) {
    header("Location: logout.php");
    exit;
}

// Sinkronisasi data ke sesi
$_SESSION['display_name'] = $userData['display_name'];
$_SESSION['role'] = $userData['role'];
$_SESSION['avatar'] = $userData['avatar'] ?? null;
$_SESSION['bio'] = $userData['bio'] ?? '';
$user = currentUser();

// Hitung total konten pengguna
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

            <!-- Notifikasi Pesan / Error -->
            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- Hero Banner Profil Pengguna -->
            <div class="dashboard-profile-hero page-anim">
                <div class="profile-hero-left">
                    <div class="profile-avatar-large-wrap">
                        <?= renderAvatar($userData['display_name'], $userData['avatar'], '76px', '', 'border: 3px solid rgba(255,255,255,0.2);') ?>
                        <span class="role-badge profile-role-badge-overlay <?= $userData['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                            <?= $userData['role'] === 'admin' ? 'Admin' : 'Anggota' ?>
                        </span>
                    </div>
                    <div class="profile-hero-meta">
                        <h2>
                            <?= htmlspecialchars($userData['display_name']) ?>
                            <?php if ($userData['role'] === 'admin'): ?>
                                <span class="badge" style="font-size:0.68rem;vertical-align:middle;">ADMIN</span>
                            <?php endif; ?>
                        </h2>
                        <span class="profile-hero-email">
                            ✉ <?= htmlspecialchars($userData['email']) ?> &middot; Bergabung <?= fmtDate($userData['created_at']) ?>
                        </span>
                        <?php if (!empty($userData['bio'])): ?>
                            <div class="profile-hero-bio">
                                &ldquo;<?= htmlspecialchars($userData['bio']) ?>&rdquo;
                            </div>
                        <?php else: ?>
                            <span class="admin-hint" style="display:block;margin-top:0.25rem;">
                                Belum ada bio profil. Tambahkan deskripsi diri Anda di tab Edit Profil.
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                    <a href="album-add.php" class="btn btn-primary btn-sm">&#10133; Tambah Album</a>
                    <a href="story-add.php" class="btn btn-outline btn-sm">&#10133; Tulis Cerita</a>
                </div>
            </div>

            <!-- Tab Navigasi Dasbor -->
            <div class="tab-wrap" data-tabs data-default-tab="<?= htmlspecialchars($activeTab) ?>">
                <nav class="tabs" role="tablist">
                    <button type="button" class="tab-btn" data-tab="profil" role="tab">
                        <span>👤</span>
                        <span>Edit Profil &amp; Foto</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="keamanan" role="tab">
                        <span>🔒</span>
                        <span>Keamanan Sandi</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="karya" role="tab">
                        <span>📁</span>
                        <span>Karya Saya (<?= $albumCount + $storyCount ?>)</span>
                    </button>
                </nav>

                <!-- ==========================================
                     PANEL 1: EDIT PROFIL (Foto, Nama, Bio)
                     ========================================== -->
                <div class="tab-panel" data-panel="profil">
                    <div class="admin-card">
                        <div class="section-header">
                            <div>
                                <h2>👤 Perbarui Profil Akun</h2>
                                <p class="admin-hint">Ubah foto profil, nama tampilan publik, serta catatan ringkas tentang dirimu.</p>
                            </div>
                        </div>

                        <form action="profile-update.php" method="POST" enctype="multipart/form-data" style="max-width:680px;">
                            <input type="hidden" name="action" value="update_profile">

                            <!-- Bagian Foto Profil / Avatar -->
                            <div class="form-group">
                                <label style="font-weight:700;margin-bottom:0.6rem;display:block;">Foto Profil (Avatar)</label>
                                <div class="avatar-edit-container">
                                    <div class="avatar-preview-box">
                                        <div id="avatarLiveContainer">
                                            <?= renderAvatar($userData['display_name'], $userData['avatar'], '80px', '', 'id="currentAvatarImg"') ?>
                                        </div>
                                        <span class="avatar-preview-label">Foto Saat Ini</span>
                                    </div>

                                    <div class="avatar-upload-inputs">
                                        <label for="avatarInput" style="font-size:0.85rem;color:var(--text-soft);margin-bottom:0.4rem;display:block;">
                                            Pilih Foto Baru (JPG, PNG, WEBP, GIF &middot; Maks 5MB):
                                        </label>
                                        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
                                        
                                        <!-- Pratinjau Gambar Baru -->
                                        <div id="newAvatarPreviewWrap" style="display:none;align-items:center;gap:0.75rem;margin-top:0.75rem;padding:0.6rem 0.85rem;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-sm);">
                                            <img id="newAvatarPreviewImg" src="" alt="Pratinjau Foto Baru" style="width:46px;height:46px;border-radius:50%;object-fit:cover;box-shadow:var(--shadow-xs);">
                                            <div style="font-size:0.8rem;color:var(--text-soft);">
                                                <strong style="color:var(--success);display:block;">✓ Foto baru dipilih</strong>
                                                <span>Klik &quot;Simpan Perubahan Profil&quot; di bawah untuk menerapkan.</span>
                                            </div>
                                        </div>

                                        <?php if (!empty($userData['avatar'])): ?>
                                            <label class="checkbox-inline" style="margin-top:0.85rem;color:#f87171;">
                                                <input type="checkbox" name="reset_avatar" value="1">
                                                <span>Hapus foto profil kustom dan kembali ke inisial warna</span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Bagian Nama Tampilan -->
                            <div class="form-group">
                                <label for="display_name">Nama Tampilan Publik <span class="required">*</span></label>
                                <input type="text" id="display_name" name="display_name" maxlength="60"
                                       value="<?= htmlspecialchars($userData['display_name']) ?>" required
                                       data-counter="nameCounter" placeholder="Contoh: Kelvin">
                                <div class="field-extra">
                                    <span class="admin-hint">Nama ini terlihat di Live Chat, Album, Cerita, dan Profil Anggota.</span>
                                    <span id="nameCounter" class="char-count"></span>
                                </div>
                            </div>

                            <!-- Bagian Bio / Deskripsi Diri -->
                            <div class="form-group">
                                <label for="bio">Bio &amp; Deskripsi Diri (Opsional)</label>
                                <textarea id="bio" name="bio" rows="3" maxlength="300" data-counter="bioCounter"
                                          placeholder="Contoh: Siswa XI TKJ 1. Tertarik pada Network Engineering, Server Linux, dan Web Development..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                <div class="field-extra">
                                    <span class="admin-hint">Tuliskan minat, peran kelas, atau moto singkat kamu (maks 300 karakter).</span>
                                    <span id="bioCounter" class="char-count"></span>
                                </div>
                            </div>

                            <!-- Email Pengguna (Readonly) -->
                            <div class="form-group">
                                <label for="userEmail">Alamat Email Terdaftar</label>
                                <input type="text" id="userEmail" value="<?= htmlspecialchars($userData['email']) ?>" readonly style="opacity:0.8;cursor:not-allowed;">
                                <span class="admin-hint" style="margin-top:0.35rem;display:block;">Alamat email terikat pada akun dan digunakan untuk masuk.</span>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
                                &#128190; Simpan Perubahan Profil
                            </button>
                        </form>
                    </div>
                </div>

                <!-- ==========================================
                     PANEL 2: KEAMANAN SANDI (Ganti Password)
                     ========================================== -->
                <div class="tab-panel" data-panel="keamanan">
                    <div class="admin-card">
                        <div class="section-header">
                            <div>
                                <h2>🔒 Ganti Kata Sandi</h2>
                                <p class="admin-hint">Pastikan menggunakan kombinasi kata sandi yang aman untuk melindungi akun Anda.</p>
                            </div>
                        </div>

                        <form action="profile-update.php" method="POST" style="max-width:520px;">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group">
                                <label for="current_password">Kata Sandi Saat Ini <span class="required">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                                    <button type="button" class="pw-toggle" data-target="current_password" aria-label="Lihat password">&#128065;</button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="new_password">Kata Sandi Baru <span class="required">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" id="new_password" name="new_password" required minlength="4"
                                           data-pw-meter="userPwMeter" data-pw-hint="userPwHint" autocomplete="new-password">
                                    <button type="button" class="pw-toggle" data-target="new_password" aria-label="Lihat password">&#128065;</button>
                                </div>
                                <div class="pw-meter" id="userPwMeter"><span></span></div>
                                <div class="pw-hint" id="userPwHint"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Kata Sandi Baru <span class="required">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="4" autocomplete="new-password">
                                    <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Lihat password">&#128065;</button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
                                &#128273; Perbarui Kata Sandi
                            </button>
                        </form>
                    </div>
                </div>

                <!-- ==========================================
                     PANEL 3: KARYA SAYA (Album & Cerita)
                     ========================================== -->
                <div class="tab-panel" data-panel="karya">
                    <div class="dashboard-stats page-anim" style="margin-bottom:2rem;">
                        <div class="stat-card">
                            <span class="stat-number"><?= $albumCount ?></span>
                            <span class="stat-label">Album Foto yang kamu buat</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number"><?= $storyCount ?></span>
                            <span class="stat-label">Cerita / Artikel yang kamu terbitkan</span>
                        </div>
                    </div>

                    <div class="dashboard-grids page-anim">
                        <!-- Album Saya -->
                        <div class="dashboard-column">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                                <h2 style="font-size:1.2rem;color:var(--dark);">&#128248; Album Foto Saya</h2>
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
                                <p class="empty-state">Belum ada album. <a href="album-add.php">Mulai buat album foto pertamamu</a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Cerita Saya -->
                        <div class="dashboard-column">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                                <h2 style="font-size:1.2rem;color:var(--dark);">&#128214; Cerita &amp; Artikel Saya</h2>
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
                                <p class="empty-state">Belum ada cerita. <a href="story-add.php">Mulai bagikan cerita atau catatan pertamamu</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<script>
(function() {
    // Live Image Preview saat pengguna memilih berkas avatar baru
    const avatarInput = document.getElementById('avatarInput');
    const previewWrap = document.getElementById('newAvatarPreviewWrap');
    const previewImg = document.getElementById('newAvatarPreviewImg');

    if (avatarInput && previewWrap && previewImg) {
        avatarInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) {
                previewWrap.style.display = 'none';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran file foto maksimal adalah 5MB.');
                this.value = '';
                previewWrap.style.display = 'none';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewWrap.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
