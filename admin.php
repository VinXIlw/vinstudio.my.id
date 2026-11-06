<?php
require_once __DIR__ . '/config.php';
requireAdmin($pdo);

$user = currentUser();
$pageTitle = 'Panel Administrator';
$currentPage = 'admin';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$activeTab = $_GET['tab'] ?? 'ringkasan';

// ============================================================
// PROSES FORM POST ADMIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';

    // --- 1. Simpan Profil Kelas ---
    if ($action === 'save_profile') {
        $className = trim($_POST['class_name'] ?? '');
        $teacherName = trim($_POST['teacher_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $schoolYear = trim($_POST['school_year'] ?? '');
        $vision = trim($_POST['vision'] ?? '');
        $mission = trim($_POST['mission'] ?? '');
        $resetPhoto = !empty($_POST['reset_photo']);

        if (empty($className) || empty($teacherName)) {
            header("Location: admin.php?tab=profil&err=" . urlencode("Nama kelas dan wali/pembina wajib diisi!"));
            exit;
        }

        $curPhoto = $pdo->query("SELECT photo FROM profiles WHERE id = 1")->fetchColumn() ?: 'tkj-logo.png';
        $finalPhoto = $curPhoto;

        if ($resetPhoto) {
            $finalPhoto = 'tkj-logo.png';
        } elseif (isset($_FILES['classphoto']) && $_FILES['classphoto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['classphoto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $finalPhoto = 'profile-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['classphoto']['tmp_name'], __DIR__ . '/uploads/' . $finalPhoto);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE profiles SET
                class_name = ?, description = ?, teacher_name = ?,
                school_year = ?, vision = ?, mission = ?, photo = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = 1
        ");
        $stmt->execute([$className, $description, $teacherName, $schoolYear, $vision, $mission, $finalPhoto]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:simpan-profil', 'Profil diperbarui');

        header("Location: admin.php?tab=profil&msg=" . urlencode("Profil berhasil disimpan!"));
        exit;
    }

    // --- 2. Buat Akun Pengguna Baru ---
    if ($action === 'create_user') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (empty($email) || empty($password)) {
            header("Location: admin.php?tab=pengguna&err=" . urlencode("Email dan password wajib diisi!"));
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            header("Location: admin.php?tab=pengguna&err=" . urlencode("Email sudah digunakan akun lain!"));
            exit;
        }

        $finalName = !empty($displayName) ? $displayName : explode('@', $email)[0];
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, ?)");
        $ins->execute([$email, $finalName, $hashed, $role]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:buat-akun', "Buat user: $finalName ($email) as $role");

        header("Location: admin.php?tab=pengguna&msg=" . urlencode("Akun $finalName berhasil dibuat."));
        exit;
    }

    // --- 3. Ubah Peran Pengguna ---
    if ($action === 'change_role') {
        $id = (int)($_POST['user_id'] ?? 0);
        $newRole = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($id === $user['id']) {
            header("Location: admin.php?tab=pengguna&err=" . urlencode("Anda tidak bisa mengubah peran akun sendiri!"));
            exit;
        }

        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $id]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:ubah-peran', "User #$id jadi $newRole");

        header("Location: admin.php?tab=pengguna&msg=" . urlencode("Peran pengguna berhasil diubah."));
        exit;
    }

    // --- 4. Ubah Nama Tampilan Pengguna ---
    if ($action === 'rename_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $newName = trim($_POST['display_name'] ?? '');

        if ($id && !empty($newName)) {
            $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?")->execute([$newName, $id]);
            logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:ubah-nama', "User #$id rename ke $newName");
            header("Location: admin.php?tab=pengguna&msg=" . urlencode("Nama pengguna berhasil diubah."));
            exit;
        }
    }

    // --- 5. Reset Password Pengguna ---
    if ($action === 'reset_password') {
        $id = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if ($id && strlen($newPass) >= 4) {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
            logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:reset-password', "Reset password user #$id");
            header("Location: admin.php?tab=pengguna&msg=" . urlencode("Password pengguna berhasil di-reset."));
            exit;
        }
        header("Location: admin.php?tab=pengguna&err=" . urlencode("Password minimal 4 karakter!"));
        exit;
    }

    // --- 6. Nonaktifkan / Aktifkan Akun (Ban/Unban) ---
    if ($action === 'toggle_ban') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $user['id']) {
            header("Location: admin.php?tab=pengguna&err=" . urlencode("Anda tidak bisa menonaktifkan akun sendiri!"));
            exit;
        }

        $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $currentBan = $stmt->fetchColumn();

        $nextState = $currentBan ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ?")->execute([$nextState, $id]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], $nextState ? 'admin:nonaktifkan' : 'admin:aktifkan', "User #$id");

        header("Location: admin.php?tab=pengguna&msg=" . urlencode($nextState ? "Akun berhasil dinonaktifkan." : "Akun berhasil diaktifkan kembali."));
        exit;
    }

    // --- 7. Hapus Pengguna Beserta Kontennya ---
    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $user['id']) {
            header("Location: admin.php?tab=pengguna&err=" . urlencode("Anda tidak bisa menghapus akun sendiri!"));
            exit;
        }

        $pdo->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM albums WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stories WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:hapus-akun', "User #$id dihapus beserta kontennya");
        header("Location: admin.php?tab=pengguna&msg=" . urlencode("Pengguna berhasil dihapus."));
        exit;
    }

    // --- 8. Ganti Password Admin Sendiri ---
    if ($action === 'change_my_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $stored = $stmt->fetchColumn();

        if (!password_verify($oldPass, $stored) && $oldPass !== $stored) {
            header("Location: admin.php?tab=keamanan&err=" . urlencode("Password lama salah!"));
            exit;
        }
        if (strlen($newPass) < 4) {
            header("Location: admin.php?tab=keamanan&err=" . urlencode("Password baru minimal 4 karakter!"));
            exit;
        }
        if ($newPass !== $confirm) {
            header("Location: admin.php?tab=keamanan&err=" . urlencode("Konfirmasi password baru tidak cocok!"));
            exit;
        }

        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user['id']]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:ganti-password', 'Ganti password admin');

        header("Location: admin.php?tab=keamanan&msg=" . urlencode("Password administrator berhasil diubah."));
        exit;
    }
}

// ============================================================
// DATA UNTUK DITAMPILKAN DI PANEL
// ============================================================
$profile = $pdo->query("SELECT * FROM profiles WHERE id = 1")->fetch();

// Statistik
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$memberUsers = max(0, $totalUsers - $adminUsers);
$totalAlbums = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
$totalStories = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$totalComments = (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalLikes = (int)$pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();

// Daftar Pengguna
$users = $pdo->query("
    SELECT u.id, u.email, u.display_name, u.role, u.is_banned, u.last_login_at, u.created_at,
           (SELECT COUNT(*) FROM albums WHERE user_id = u.id) AS album_count,
           (SELECT COUNT(*) FROM stories WHERE user_id = u.id) AS story_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

// Log Aktivitas 10 Terakhir
$recentLogs = $pdo->query("SELECT * FROM login_logs ORDER BY id DESC LIMIT 10")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main>
    <!-- Header Panel -->
    <div class="page-header page-anim">
        <div>
            <h1>&#9881; Panel Administrator</h1>
            <p class="page-subtitle">Kelola profil portal, data pengguna, galeri konten, dan log keamanan.</p>
        </div>
        <div class="user-cell">
            <span class="user-avatar <?= avatarClass($user['displayName']) ?>"><?= initials($user['displayName']) ?></span>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['displayName']) ?></div>
                <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Statistik Ringkas -->
    <section class="stat-grid page-anim">
        <div class="stat-box v-blue">
            <div class="stat-icon">&#128101;</div>
            <div>
                <span class="stat-value"><?= $totalUsers ?></span>
                <span class="stat-caption">Total Pengguna</span>
            </div>
        </div>
        <div class="stat-box v-violet">
            <div class="stat-icon">&#128737;</div>
            <div>
                <span class="stat-value"><?= $adminUsers ?></span>
                <span class="stat-caption">Administrator</span>
            </div>
        </div>
        <div class="stat-box v-cyan">
            <div class="stat-icon">&#128188;</div>
            <div>
                <span class="stat-value"><?= $memberUsers ?></span>
                <span class="stat-caption">Anggota Komunitas</span>
            </div>
        </div>
        <div class="stat-box v-green">
            <div class="stat-icon">&#128248;</div>
            <div>
                <span class="stat-value"><?= $totalAlbums ?></span>
                <span class="stat-caption">Album Foto</span>
            </div>
        </div>
        <div class="stat-box v-amber">
            <div class="stat-icon">&#128214;</div>
            <div>
                <span class="stat-value"><?= $totalStories ?></span>
                <span class="stat-caption">Cerita &amp; Artikel</span>
            </div>
        </div>
        <div class="stat-box v-rose">
            <div class="stat-icon">❤️</div>
            <div>
                <span class="stat-value"><?= $totalLikes ?></span>
                <span class="stat-caption">Total Suka</span>
            </div>
        </div>
    </section>

    <!-- Tab Navigasi -->
    <div class="tab-wrap" data-tabs data-default-tab="<?= htmlspecialchars($activeTab) ?>">
        <nav class="tabs" role="tablist">
            <button type="button" class="tab-btn" data-tab="ringkasan" role="tab">&#128202; Ringkasan</button>
            <button type="button" class="tab-btn" data-tab="profil" role="tab">&#128221; Profil Portal</button>
            <button type="button" class="tab-btn" data-tab="pengguna" role="tab">&#128101; Pengguna (<?= count($users) ?>)</button>
            <button type="button" class="tab-btn" data-tab="keamanan" role="tab">&#128274; Keamanan</button>
        </nav>

        <!-- PANEL 1: RINGKASAN -->
        <div class="tab-panel" data-panel="ringkasan">
            <div class="admin-card">
                <h2>&#128200; Ringkasan Portal</h2>
                <p class="admin-hint">Informasi umum mengenai profil portal dan aktivitas login terakhir.</p>

                <div class="info-grid" style="margin-bottom:1.5rem;">
                    <div>
                        <span class="label">Nama Portal / Kelas</span>
                        <span class="value"><?= htmlspecialchars($profile['class_name'] ?? '-') ?></span>
                    </div>
                    <div>
                        <span class="label">Pembina / Wali</span>
                        <span class="value"><?= htmlspecialchars($profile['teacher_name'] ?? '-') ?></span>
                    </div>
                    <div>
                        <span class="label">Tahun Ajaran</span>
                        <span class="value"><?= htmlspecialchars($profile['school_year'] ?? '-') ?></span>
                    </div>
                    <div>
                        <span class="label">Total Interaksi</span>
                        <span class="value"><?= $totalComments ?> komentar &middot; <?= $totalLikes ?> suka</span>
                    </div>
                </div>

                <div class="section-header" style="margin-top:1.5rem;">
                    <h2>&#128203; Aktivitas Terakhir</h2>
                    <a href="admin-logs.php" class="btn btn-outline btn-sm">Buka Semua Log &rarr;</a>
                </div>

                <?php if (empty($recentLogs)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">&#128164;</span>
                        <p>Belum ada aktivitas tercatat.</p>
                    </div>
                <?php else: ?>
                    <div class="log-list">
                        <?php foreach ($recentLogs as $l): ?>
                            <div class="log-item">
                                <div class="log-dot <?= ($l['action'] === 'login' || $l['action'] === 'admin-login') ? 'ok' : ((strpos($l['action'], 'gagal') !== false || strpos($l['action'], 'ditolak') !== false) ? 'bad' : 'warn') ?>">
                                    <?= ($l['action'] === 'login' || $l['action'] === 'admin-login') ? '&#128275;' : ((strpos($l['action'], 'gagal') !== false || strpos($l['action'], 'ditolak') !== false) ? '&#10060;' : '&#9881;') ?>
                                </div>
                                <div class="log-body">
                                    <div class="log-title">
                                        <?= htmlspecialchars($l['display_name'] ?: ($l['email'] ?: 'Tamu')) ?>
                                        <span class="admin-hint">(<?= htmlspecialchars($l['action']) ?>)</span>
                                    </div>
                                    <div class="log-meta">
                                        <?= htmlspecialchars($l['email'] ?: '-') ?> &middot; <?= htmlspecialchars($l['ip'] ?: '-') ?>
                                        <?php if (!empty($l['detail'])): ?> &middot; <?= htmlspecialchars($l['detail']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <span class="log-time"><?= timeAgo($l['created_at']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL 2: PROFIL PORTAL -->
        <div class="tab-panel" data-panel="profil">
            <div class="admin-card">
                <h2>&#128221; Pengaturan Profil Portal</h2>
                <p class="admin-hint">Perubahan data di sini akan langsung tampil pada halaman Beranda dan Profil.</p>

                <form action="admin.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="admin_action" value="save_profile">
                    <div class="admin-grid">
                        <div class="form-group">
                            <label for="class_name">Nama Portal / Kelas <span class="required">*</span></label>
                            <input type="text" id="class_name" name="class_name" value="<?= htmlspecialchars($profile['class_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="teacher_name">Pembina / Wali Kelas <span class="required">*</span></label>
                            <input type="text" id="teacher_name" name="teacher_name" value="<?= htmlspecialchars($profile['teacher_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="school_year">Tahun Ajaran / Periode</label>
                            <input type="text" id="school_year" name="school_year" value="<?= htmlspecialchars($profile['school_year'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="classphoto">Foto Profil Utama</label>
                            <input type="file" id="classphoto" name="classphoto" accept="image/*">
                            <label class="checkbox-inline" style="margin-top:0.4rem;">
                                <input type="checkbox" name="reset_photo" value="1"> Kembalikan ke logo bawaan
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Deskripsi Singkat</label>
                        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="vision">Visi</label>
                        <textarea id="vision" name="vision" rows="3"><?= htmlspecialchars($profile['vision'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="mission">Misi</label>
                        <textarea id="mission" name="mission" rows="4"><?= htmlspecialchars($profile['mission'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">&#128190; Simpan Perubahan Profil</button>
                </form>
            </div>
        </div>

        <!-- PANEL 3: KELOLA PENGGUNA -->
        <div class="tab-panel" data-panel="pengguna">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2>&#128101; Kelola Pengguna</h2>
                        <p class="admin-hint">Atur peran pengguna, ubah nama, reset password, atau hapus akun.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-modal-open="modalBuatAkun">&#10133; Buat Akun Baru</button>
                </div>

                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="col-user">Pengguna</th>
                                <th class="col-role">Peran</th>
                                <th class="col-content">Konten</th>
                                <th class="col-login">Login Terakhir</th>
                                <th class="col-actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="col-user">
                                    <div class="user-cell">
                                        <span class="user-avatar <?= avatarClass($u['display_name']) ?>"><?= initials($u['display_name']) ?></span>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?= htmlspecialchars($u['display_name']) ?>
                                                <?php if ($u['id'] == $user['id']): ?><span class="badge">Anda</span><?php endif; ?>
                                                <?php if ($u['is_banned']): ?><span class="role-badge role-banned">Nonaktif</span><?php endif; ?>
                                            </div>
                                            <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-role">
                                    <span class="role-badge <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                                        <?= $u['role'] === 'admin' ? '&#128737; Admin' : '&#128100; Anggota' ?>
                                    </span>
                                </td>
                                <td class="col-content">
                                    <span class="cell-note">
                                        <strong><?= (int)$u['album_count'] ?></strong> album &middot;
                                        <strong><?= (int)$u['story_count'] ?></strong> cerita
                                    </span>
                                </td>
                                <td class="col-login">
                                    <span class="cell-note">
                                        <?= timeAgo($u['last_login_at']) ?>
                                        <br><small class="admin-hint"><?= fmtDateTime($u['last_login_at']) ?></small>
                                    </span>
                                </td>
                                <td class="col-actions">
                                    <?php if ($u['id'] == $user['id']): ?>
                                        <span class="admin-hint">Akun sendiri (atur di tab Keamanan)</span>
                                    <?php else: ?>
                                        <div class="table-actions">
                                            <!-- Ganti Peran -->
                                            <form action="admin.php" method="POST" class="inline-form">
                                                <input type="hidden" name="admin_action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                                <button type="submit" class="btn btn-sm btn-outline">
                                                    <?= $u['role'] === 'admin' ? 'Jadikan Anggota' : 'Jadikan Admin' ?>
                                                </button>
                                            </form>

                                            <!-- Ban / Unban -->
                                            <form action="admin.php" method="POST" class="inline-form">
                                                <input type="hidden" name="admin_action" value="toggle_ban">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $u['is_banned'] ? 'btn-success' : 'btn-warning' ?>">
                                                    <?= $u['is_banned'] ? 'Aktifkan' : 'Nonaktifkan' ?>
                                                </button>
                                            </form>

                                            <!-- Hapus Akun -->
                                            <form action="admin.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['display_name']) ?> beserta seluruh kontennya?');">
                                                <input type="hidden" name="admin_action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-delete">&#128465; Hapus</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PANEL 4: KEAMANAN -->
        <div class="tab-panel" data-panel="keamanan">
            <div class="admin-card">
                <h2>&#128274; Ganti Password Administrator</h2>
                <p class="admin-hint">Gunakan password yang kuat dengan minimal 4 karakter.</p>

                <form action="admin.php" method="POST" style="max-width:520px;">
                    <input type="hidden" name="admin_action" value="change_my_password">
                    <div class="form-group">
                        <label for="old_password">Password Lama</label>
                        <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" required minlength="4" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm" name="confirm" required minlength="4" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">&#128273; Simpan Password Baru</button>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Modal Buat Akun Baru -->
<div class="modal" id="modalBuatAkun">
    <div class="modal-box">
        <div class="modal-head">
            <h3>&#10133; Buat Akun Pengguna Baru</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="admin.php" method="POST">
            <input type="hidden" name="admin_action" value="create_user">
            <div class="modal-body">
                <div class="form-group">
                    <label for="new_email">Email <span class="required">*</span></label>
                    <input type="email" id="new_email" name="email" required placeholder="user@email.com">
                </div>
                <div class="form-group">
                    <label for="new_display_name">Nama Tampilan</label>
                    <input type="text" id="new_display_name" name="display_name" placeholder="Contoh: Kelvin">
                </div>
                <div class="form-group">
                    <label for="new_pass">Password <span class="required">*</span></label>
                    <input type="password" id="new_pass" name="password" required minlength="4">
                </div>
                <div class="form-group">
                    <label for="new_role">Peran Akun</label>
                    <select id="new_role" name="role">
                        <option value="user">Anggota (User Biasa)</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">&#10133; Buat Akun</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
