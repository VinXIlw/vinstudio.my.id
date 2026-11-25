<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user        = currentUser();
$pageTitle   = 'Dasbor & Pengaturan Profil';
$currentPage = 'dashboard';

$msg       = $_GET['msg'] ?? '';
$err       = $_GET['err'] ?? '';
$activeTab = $_GET['tab'] ?? 'profil';

$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user['id']]);
$userData = $stmtUser->fetch();

if (!$userData || (int)$userData['is_banned'] === 1) {
    safeRedirect('index.php');
}

$_SESSION['display_name'] = $userData['display_name'];
$_SESSION['role']         = $userData['role'];
$_SESSION['avatar']       = $userData['avatar'] ?? null;
$_SESSION['bio']          = $userData['bio'] ?? '';
$_SESSION['specialty']    = $userData['specialty'] ?? '';
$user = currentUser();

$albumStmt = $pdo->prepare("SELECT * FROM albums WHERE user_id = ? ORDER BY created_at DESC");
$albumStmt->execute([$user['id']]);
$myAlbums   = $albumStmt->fetchAll();
$albumCount = count($myAlbums);

$storyStmt = $pdo->prepare("SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC");
$storyStmt->execute([$user['id']]);
$myStories  = $storyStmt->fetchAll();
$storyCount = count($myStories);

include __DIR__ . '/includes/header.php';
?>

<main>
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Hero Kartu Profil Pengguna -->
    <div class="dashboard-profile-hero page-anim">
        <div class="profile-hero-left">
            <div class="profile-avatar-large-wrap">
                <?= renderAvatar($userData['display_name'], $userData['avatar'], '78px') ?>
                <span class="role-badge profile-role-badge-overlay <?= $userData['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                    <?= $userData['role'] === 'admin' ? 'Admin' : 'Anggota' ?>
                </span>
            </div>
            <div class="profile-hero-meta">
                <h2>
                    <?= htmlspecialchars($userData['display_name']) ?>
                    <?php if (!empty($userData['specialty'])): ?>
                        <span class="specialty-pill" style="margin-top:0;"><?= htmlspecialchars($userData['specialty']) ?></span>
                    <?php endif; ?>
                </h2>
                <span class="profile-hero-email">
                    <?= htmlspecialchars($userData['email']) ?> &middot; Bergabung <?= fmtDate($userData['created_at']) ?>
                </span>
                <?php if (!empty($userData['bio'])): ?>
                    <div class="profile-hero-bio">&ldquo;<?= htmlspecialchars($userData['bio']) ?>&rdquo;</div>
                <?php else: ?>
                    <span class="admin-hint" style="display:block;margin-top:0.25rem;">Lengkapi bio singkat Anda di bawah agar tampil di direktori anggota.</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:0.55rem;flex-wrap:wrap;">
            <a href="profile.php?member=<?= (int)$user['id'] ?>" class="btn btn-outline btn-sm">
                <?= svgIcon('user', 14) ?> Portofolio Publik
            </a>
            <a href="album-add.php" class="btn btn-primary btn-sm"><?= svgIcon('plus', 14) ?> Karya Baru</a>
            <a href="story-add.php" class="btn btn-outline btn-sm"><?= svgIcon('plus', 14) ?> Tulis Artikel</a>
        </div>
    </div>

    <!-- Tab Navigasi Dasbor -->
    <div class="tab-wrap" data-tabs data-default-tab="<?= htmlspecialchars($activeTab) ?>">
        <nav class="tabs" role="tablist">
            <button type="button" class="tab-btn" data-tab="profil" role="tab">
                <?= svgIcon('user', 16) ?>
                <span>Edit Profil &amp; Foto</span>
            </button>
            <button type="button" class="tab-btn" data-tab="keamanan" role="tab">
                <?= svgIcon('lock', 16) ?>
                <span>Keamanan Sandi</span>
            </button>
            <button type="button" class="tab-btn" data-tab="karya" role="tab">
                <?= svgIcon('image', 16) ?>
                <span>Karya Saya (<?= $albumCount + $storyCount ?>)</span>
            </button>
        </nav>

        <!-- PANEL 1: EDIT PROFIL -->
        <div class="tab-panel" data-panel="profil">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('user', 20) ?> Pengaturan Identitas &amp; Foto Profil</h2>
                        <p class="admin-hint">Perbarui foto avatar, nama publik, bidang peminatan TI, serta deskripsi singkat profil Anda.</p>
                    </div>
                </div>

                <form action="profile-update.php" method="POST" enctype="multipart/form-data" style="max-width:700px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label>Foto Profil / Avatar</label>
                        <div class="avatar-edit-container">
                            <div class="avatar-preview-box">
                                <div id="avatarLiveContainer">
                                    <?= renderAvatar($userData['display_name'], $userData['avatar'], '80px', '', '', 'currentAvatarImg') ?>
                                </div>
                                <span class="avatar-preview-label">Saat Ini</span>
                            </div>

                            <div class="avatar-upload-inputs">
                                <label for="avatarInput" style="font-size:0.82rem;color:var(--text-soft);margin-bottom:0.4rem;">
                                    Unggah Foto Baru (JPG, PNG, WEBP, GIF &middot; Maks. 5 MB):
                                </label>
                                <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">

                                <div id="newAvatarPreviewWrap" style="display:none;align-items:center;gap:0.75rem;margin-top:0.75rem;padding:0.6rem 0.85rem;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-sm);">
                                    <img id="newAvatarPreviewImg" src="" alt="Pratinjau" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                                    <div style="font-size:0.8rem;color:var(--text-soft);">
                                        <strong style="color:var(--success);display:block;">Foto baru siap diterapkan</strong>
                                        <span>Klik tombol Simpan Perubahan Profil di bawah.</span>
                                    </div>
                                </div>

                                <?php if (!empty($userData['avatar'])): ?>
                                    <label class="checkbox-inline" style="margin-top:0.75rem;color:#fb7185;">
                                        <input type="checkbox" name="reset_avatar" value="1">
                                        <span>Hapus foto kustom dan gunakan avatar inisial</span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="display_name">Nama Tampilan Publik <span class="required">*</span></label>
                            <input type="text" id="display_name" name="display_name" maxlength="60" required
                                   value="<?= htmlspecialchars($userData['display_name']) ?>"
                                   data-counter="nameCounter" placeholder="Contoh: Kelvin">
                            <div class="field-extra">
                                <span>Tampil di seluruh portal</span>
                                <span id="nameCounter" class="char-count"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="specialty">Fokus / Peminatan TI</label>
                            <select id="specialty" name="specialty">
                                <?php
                                $curSpec = $userData['specialty'] ?: 'Anggota XI TKJ 1';
                                foreach (MEMBER_SPECIALTIES as $spec):
                                ?>
                                    <option value="<?= htmlspecialchars($spec) ?>" <?= $curSpec === $spec ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($spec) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio &amp; Deskripsi Singkat (Opsional)</label>
                        <textarea id="bio" name="bio" rows="3" maxlength="300" data-counter="bioCounter"
                                  placeholder="Contoh: Siswa XI TKJ 1, antusias mempelajari Routing MikroTik, Linux Server, dan Keamanan Jaringan..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                        <div class="field-extra">
                            <span>Tampil di Direktori &amp; Portofolio Publik</span>
                            <span id="bioCounter" class="char-count"></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= svgIcon('check', 16) ?>
                        <span>Simpan Perubahan Profil</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- PANEL 2: KEAMANAN SANDI -->
        <div class="tab-panel" data-panel="keamanan">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('lock', 20) ?> Perbarui Kata Sandi Akun</h2>
                        <p class="admin-hint">Gunakan kata sandi unik minimal 6 karakter. Sesi akan diamankan ulang secara otomatis setelah sandi diganti.</p>
                    </div>
                </div>

                <form action="profile-update.php" method="POST" style="max-width:500px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Kata Sandi Saat Ini <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" data-target="current_password" aria-label="Tampilkan sandi"><?= svgIcon('eye', 16) ?></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Kata Sandi Baru <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                   data-pw-meter="userPwMeter" data-pw-hint="userPwHint" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="new_password" aria-label="Tampilkan sandi"><?= svgIcon('eye', 16) ?></button>
                        </div>
                        <div class="pw-meter" id="userPwMeter"><span></span></div>
                        <div class="pw-hint" id="userPwHint"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Kata Sandi Baru <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Tampilkan sandi"><?= svgIcon('eye', 16) ?></button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= svgIcon('shield', 16) ?>
                        <span>Perbarui Kata Sandi</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- PANEL 3: KARYA SAYA -->
        <div class="tab-panel" data-panel="karya">
            <div class="stat-grid" style="max-width:560px;">
                <div class="stat-box">
                    <div class="stat-icon"><?= svgIcon('image', 20) ?></div>
                    <div>
                        <span class="stat-value"><?= $albumCount ?></span>
                        <span class="stat-caption">Karya Galeri</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon"><?= svgIcon('book', 20) ?></div>
                    <div>
                        <span class="stat-value"><?= $storyCount ?></span>
                        <span class="stat-caption">Artikel Diterbitkan</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grids">
                <!-- Album Saya -->
                <div class="admin-card" style="margin-bottom:0;">
                    <div class="section-header">
                        <h2><?= svgIcon('image', 18) ?> Galeri Karya Saya</h2>
                        <a href="album-add.php" class="btn btn-outline btn-sm"><?= svgIcon('plus', 14) ?> Baru</a>
                    </div>
                    <?php if (!empty($myAlbums)): ?>
                        <div class="dashboard-list">
                            <?php foreach ($myAlbums as $a): ?>
                            <article class="list-item">
                                <div style="min-width:0;flex:1;">
                                    <a href="album-detail.php?id=<?= (int)$a['id'] ?>" style="font-weight:700;color:var(--dark);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </a>
                                    <small class="admin-hint"><?= htmlspecialchars($a['category'] ?: 'Dokumentasi') ?> &middot; <?= timeAgo($a['created_at']) ?></small>
                                </div>
                                <div style="display:flex;gap:0.35rem;align-items:center;flex-shrink:0;">
                                    <a href="album-add.php?edit=<?= (int)$a['id'] ?>" class="btn btn-outline btn-sm" title="Edit"><?= svgIcon('edit', 14) ?></a>
                                    <form action="album-delete.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus karya ini?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" title="Hapus"><?= svgIcon('trash', 14) ?></button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:1.75rem;">
                            <p class="admin-hint">Belum ada karya yang diunggah.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Artikel Saya -->
                <div class="admin-card" style="margin-bottom:0;">
                    <div class="section-header">
                        <h2><?= svgIcon('book', 18) ?> Artikel &amp; Catatan Saya</h2>
                        <a href="story-add.php" class="btn btn-outline btn-sm"><?= svgIcon('plus', 14) ?> Baru</a>
                    </div>
                    <?php if (!empty($myStories)): ?>
                        <div class="dashboard-list">
                            <?php foreach ($myStories as $s): ?>
                            <article class="list-item">
                                <div style="min-width:0;flex:1;">
                                    <a href="story-detail.php?id=<?= (int)$s['id'] ?>" style="font-weight:700;color:var(--dark);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($s['title']) ?>
                                    </a>
                                    <small class="admin-hint"><?= htmlspecialchars($s['category'] ?: 'Catatan') ?> &middot; <?= timeAgo($s['created_at']) ?></small>
                                </div>
                                <div style="display:flex;gap:0.35rem;align-items:center;flex-shrink:0;">
                                    <a href="story-add.php?edit=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm" title="Edit"><?= svgIcon('edit', 14) ?></a>
                                    <form action="story-delete.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus artikel ini?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" title="Hapus"><?= svgIcon('trash', 14) ?></button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:1.75rem;">
                            <p class="admin-hint">Belum ada artikel yang diterbitkan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const avatarInput = document.getElementById('avatarInput');
    const previewWrap = document.getElementById('newAvatarPreviewWrap');
    const previewImg  = document.getElementById('newAvatarPreviewImg');
    const resetCb     = document.querySelector('input[name="reset_avatar"]');

    if (resetCb && avatarInput && previewWrap) {
        resetCb.addEventListener('change', function() {
            if (this.checked) {
                avatarInput.value = '';
                previewWrap.style.display = 'none';
            }
        });
    }

    if (avatarInput && previewWrap && previewImg) {
        avatarInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) { previewWrap.style.display = 'none'; return; }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran berkas foto maksimal adalah 5 MB.');
                this.value = '';
                previewWrap.style.display = 'none';
                return;
            }
            if (resetCb) resetCb.checked = false;
            const reader = new FileReader();
            reader.onload = e => { previewImg.src = e.target.result; previewWrap.style.display = 'flex'; };
            reader.readAsDataURL(file);
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
