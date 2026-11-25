<?php
require_once __DIR__ . '/config.php';
requireAdmin($pdo);

$user        = currentUser();
$pageTitle   = 'Pusat Kendali Administrator';
$currentPage = 'admin';
$msg         = $_GET['msg'] ?? '';
$err         = $_GET['err'] ?? '';
$activeTab   = $_GET['tab'] ?? 'ringkasan';

// ============================================================================
// TANGANI SELURUH AKSI ADMINISTRATOR (Wajib POST + Validasi CSRF Ketat)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(false, 'admin.php');
    $action = $_POST['admin_action'] ?? '';

    // 1. Simpan Profil Kelas
    if ($action === 'save_profile') {
        $className    = trim($_POST['class_name'] ?? '');
        $teacherName  = trim($_POST['teacher_name'] ?? '');
        $schoolYear   = trim($_POST['school_year'] ?? '');
        $scheduleNote = trim($_POST['schedule_note'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $vision       = trim($_POST['vision'] ?? '');
        $mission      = trim($_POST['mission'] ?? '');
        $resetPhoto   = !empty($_POST['reset_photo']);

        if ($className === '' || $teacherName === '') {
            safeRedirect('admin.php?tab=profil&err=' . urlencode('Nama kelas dan wali/pembina wajib diisi.'));
        }

        $curPhoto   = $pdo->query("SELECT photo FROM profiles WHERE id = 1")->fetchColumn() ?: 'tkj-logo.png';
        $finalPhoto = $curPhoto;

        if ($resetPhoto) {
            $finalPhoto = 'tkj-logo.png';
        } elseif (isset($_FILES['classphoto']) && ($_FILES['classphoto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upRes = saveUploadedImage($_FILES['classphoto'], 'profile');
            if (!$upRes['ok']) {
                safeRedirect('admin.php?tab=profil&err=' . urlencode($upRes['error']));
            }
            $finalPhoto = $upRes['filename'];
        }

        $stmt = $pdo->prepare("
            UPDATE profiles SET
                class_name = ?, description = ?, teacher_name = ?,
                school_year = ?, schedule_note = ?, vision = ?, mission = ?, photo = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = 1
        ");
        $stmt->execute([$className, $description, $teacherName, $schoolYear, $scheduleNote, $vision, $mission, $finalPhoto]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:simpan-profil', 'Memperbarui profil portal kelas');
        safeRedirect('admin.php?tab=profil&msg=' . urlencode('Profil komunitas kelas berhasil diperbarui!'));
    }

    // 2. Tambah Pengumuman Resmi Kelas
    if ($action === 'add_announcement') {
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $priority = $_POST['priority'] ?? 'info';
        $isPinned = !empty($_POST['is_pinned']) ? 1 : 0;

        if (!in_array($priority, ['info', 'important', 'event'], true)) {
            $priority = 'info';
        }
        if ($title === '' || $content === '') {
            safeRedirect('admin.php?tab=pengumuman&err=' . urlencode('Judul dan isi pengumuman wajib diisi.'));
        }

        $stmt = $pdo->prepare("INSERT INTO announcements (user_id, title, content, priority, is_pinned) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $title, $content, $priority, $isPinned]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:pengumuman', 'Menerbitkan pengumuman: ' . $title);
        safeRedirect('admin.php?tab=pengumuman&msg=' . urlencode('Pengumuman resmi berhasil diterbitkan di Beranda!'));
    }

    // 3. Toggle Pin / Hapus Pengumuman
    if ($action === 'toggle_pin_announcement') {
        $annId = (int)($_POST['announcement_id'] ?? 0);
        if ($annId > 0) {
            $pdo->prepare("UPDATE announcements SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$annId]);
        }
        safeRedirect('admin.php?tab=pengumuman&msg=' . urlencode('Status sematan pengumuman diperbarui.'));
    }

    if ($action === 'delete_announcement') {
        $annId = (int)($_POST['announcement_id'] ?? 0);
        if ($annId > 0) {
            $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$annId]);
            logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:hapus-pengumuman', 'Menghapus pengumuman #' . $annId);
        }
        safeRedirect('admin.php?tab=pengumuman&msg=' . urlencode('Pengumuman berhasil dihapus.'));
    }

    // 4. Buat Akun Baru (Anggota / Administrator)
    if ($action === 'create_user') {
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $specialty   = trim($_POST['specialty'] ?? 'Anggota XI TKJ 1');
        $password    = $_POST['password'] ?? '';
        $role        = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Format email tidak valid atau kata sandi kosong.'));
        }
        if (strlen($password) < 6) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Kata sandi minimal 6 karakter.'));
        }

        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Email tersebut sudah terdaftar.'));
        }

        $finalName = $displayName !== '' ? $displayName : explode('@', $email)[0];
        $hashed    = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (email, display_name, password, role, specialty) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$email, $finalName, $hashed, $role, $specialty]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:buat-akun', "Membuat akun {$finalName} ({$role})");
        safeRedirect('admin.php?tab=pengguna&msg=' . urlencode("Akun {$finalName} ({$role}) berhasil dibuat."));
    }

    // 5. Ubah Peran Pengguna
    if ($action === 'change_role') {
        $id      = (int)($_POST['user_id'] ?? 0);
        $newRole = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($id === $user['id']) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Tidak dapat mengubah peran akun Anda sendiri.'));
        }
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $id]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:ubah-peran', "User #{$id} menjadi {$newRole}");
        safeRedirect('admin.php?tab=pengguna&msg=' . urlencode('Peran pengguna berhasil diperbarui.'));
    }

    // 6. Reset Kata Sandi Pengguna oleh Admin
    if ($action === 'reset_user_password') {
        $id      = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if ($id <= 0 || strlen($newPass) < 6) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Kata sandi baru minimal harus 6 karakter.'));
        }
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:reset-sandi', "Reset kata sandi user #{$id}");
        safeRedirect('admin.php?tab=pengguna&msg=' . urlencode('Kata sandi pengguna berhasil di-reset.'));
    }

    // 7. Ban / Unban Pengguna
    if ($action === 'toggle_ban') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $user['id']) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Tidak dapat menonaktifkan akun sendiri.'));
        }
        $pdo->prepare("UPDATE users SET is_banned = CASE WHEN is_banned = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:status-akun', "Toggle status aktif user #{$id}");
        safeRedirect('admin.php?tab=pengguna&msg=' . urlencode('Status akses akun pengguna diperbarui.'));
    }

    // 8. Hapus Akun Pengguna Beserta Kontennya
    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $user['id']) {
            safeRedirect('admin.php?tab=pengguna&err=' . urlencode('Tidak dapat menghapus akun sendiri.'));
        }
        if ($id > 0) {
            // Hapus file fisik foto album & avatar pengguna
            $uAv = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $uAv->execute([$id]);
            if ($avFile = $uAv->fetchColumn()) {
                $p = __DIR__ . '/uploads/' . basename($avFile);
                if (is_file($p)) @unlink($p);
            }
            $aPhotos = $pdo->prepare("SELECT photo FROM albums WHERE user_id = ?");
            $aPhotos->execute([$id]);
            foreach ($aPhotos->fetchAll(PDO::FETCH_COLUMN) as $ph) {
                if ($ph && $ph !== 'no-image.png') {
                    $p = __DIR__ . '/uploads/' . basename($ph);
                    if (is_file($p)) @unlink($p);
                }
            }

            $albumIdsStmt = $pdo->prepare("SELECT id FROM albums WHERE user_id = ?");
            $albumIdsStmt->execute([$id]);
            $albumIds = $albumIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($albumIds)) {
                $ph = implode(',', array_fill(0, count($albumIds), '?'));
                $pdo->prepare("DELETE FROM comments WHERE target_type = 'album' AND target_id IN ($ph)")->execute($albumIds);
                $pdo->prepare("DELETE FROM likes WHERE target_type = 'album' AND target_id IN ($ph)")->execute($albumIds);
            }

            $storyIdsStmt = $pdo->prepare("SELECT id FROM stories WHERE user_id = ?");
            $storyIdsStmt->execute([$id]);
            $storyIds = $storyIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($storyIds)) {
                $ph = implode(',', array_fill(0, count($storyIds), '?'));
                $pdo->prepare("DELETE FROM comments WHERE target_type = 'story' AND target_id IN ($ph)")->execute($storyIds);
                $pdo->prepare("DELETE FROM likes WHERE target_type = 'story' AND target_id IN ($ph)")->execute($storyIds);
            }

            $chatIdsStmt = $pdo->prepare("SELECT id FROM chat_messages WHERE user_id = ?");
            $chatIdsStmt->execute([$id]);
            $chatIds = $chatIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($chatIds)) {
                $ph = implode(',', array_fill(0, count($chatIds), '?'));
                $pdo->prepare("UPDATE chat_messages SET reply_to_id = NULL WHERE reply_to_id IN ($ph)")->execute($chatIds);
            }

            $pdo->prepare("DELETE FROM announcements WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM chat_messages WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM likes WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM albums WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM stories WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

            logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:hapus-akun', "Menghapus permanen user #{$id}");
            safeRedirect('admin.php?tab=pengguna&msg=' . urlencode('Akun beserta seluruh kontennya telah dihapus.'));
        }
    }

    // 9. Ganti Sandi Admin Sendiri
    if ($action === 'change_my_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if ($newPass !== $confirm || strlen($newPass) < 6) {
            safeRedirect('admin.php?tab=keamanan&err=' . urlencode('Konfirmasi sandi tidak cocok atau kurang dari 6 karakter.'));
        }

        $st = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $st->execute([$user['id']]);
        $hash = $st->fetchColumn();

        if (!$hash || !verifyUserPassword($pdo, (int)$user['id'], $oldPass, (string)$hash)) {
            safeRedirect('admin.php?tab=keamanan&err=' . urlencode('Kata sandi lama tidak sesuai.'));
        }

        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'admin:ganti-sandi', 'Memperbarui kata sandi administrator');
        safeRedirect('admin.php?tab=keamanan&msg=' . urlencode('Kata sandi administrator berhasil diperbarui!'));
    }
}

// ============================================================================
// QUERY DATA DASHBOARD ADMIN & AUDIT KEAMANAN
// ============================================================================
$profile       = $pdo->query("SELECT * FROM profiles WHERE id = 1")->fetch();
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalAlbums   = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
$totalStories  = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$totalComments = (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalChats    = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();

$announcements = $pdo->query("
    SELECT a.*, u.display_name AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
")->fetchAll();

$users = $pdo->query("
    SELECT u.id, u.email, u.display_name, u.role, u.avatar, u.specialty, u.is_banned, u.last_login_at, u.created_at,
           (SELECT COUNT(*) FROM albums WHERE user_id = u.id) AS album_count,
           (SELECT COUNT(*) FROM stories WHERE user_id = u.id) AS story_count
    FROM users u
    ORDER BY CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.created_at DESC
")->fetchAll();

$recentLogs = $pdo->query("SELECT * FROM login_logs ORDER BY id DESC LIMIT 8")->fetchAll();

// Cek Status Keamanan Sistem Secara Real-Time
$rootExposedFiles = [];
foreach (['database.sqlite', 'database.db', 'database.sql'] as $f) {
    if (is_file(__DIR__ . '/' . $f)) $rootExposedFiles[] = $f;
}
$vaultLabel = $dbVaultMode === 'mysql-server'
    ? 'MySQL Server Terisolasi'
    : ($dbVaultMode === 'outside-webroot' ? 'Brankas Luar Webroot (Optimal)' : 'Brankas Terkunci 224-Bit + .htaccess');

include __DIR__ . '/includes/header.php';
?>

<main>
    <div class="page-header page-anim">
        <div>
            <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('shield', 13) ?> Otorisasi Administrator</span>
            <h1>Pusat Kendali &amp; Keamanan Sistem</h1>
            <p class="page-subtitle">Kelola pengumuman resmi, profil komunitas, hak akses anggota, serta pantau postur keamanan database.</p>
        </div>
        <div class="user-cell">
            <?= renderAvatar($user['displayName'], $user['avatar'] ?? null, '40px') ?>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['displayName']) ?> <span class="role-badge role-admin">Admin</span></div>
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

    <!-- Statistik Portal -->
    <section class="stat-grid page-anim">
        <div class="stat-box">
            <div class="stat-icon"><?= svgIcon('users', 20) ?></div>
            <div>
                <span class="stat-value"><?= $totalUsers ?></span>
                <span class="stat-caption">Total Akun (<?= $adminUsers ?> Admin)</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><?= svgIcon('image', 20) ?></div>
            <div>
                <span class="stat-value"><?= $totalAlbums ?></span>
                <span class="stat-caption">Galeri &amp; Proyek</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><?= svgIcon('book', 20) ?></div>
            <div>
                <span class="stat-value"><?= $totalStories ?></span>
                <span class="stat-caption">Artikel &amp; Tutorial</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><?= svgIcon('chat', 20) ?></div>
            <div>
                <span class="stat-value"><?= $totalChats + $totalComments ?></span>
                <span class="stat-caption">Chat &amp; Komentar</span>
            </div>
        </div>
    </section>

    <!-- Tab Navigasi Admin -->
    <div class="tab-wrap" data-tabs data-default-tab="<?= htmlspecialchars($activeTab) ?>">
        <nav class="tabs" role="tablist">
            <button type="button" class="tab-btn" data-tab="ringkasan" role="tab"><?= svgIcon('activity', 16) ?> <span>Ringkasan &amp; Audit</span></button>
            <button type="button" class="tab-btn" data-tab="pengumuman" role="tab"><?= svgIcon('megaphone', 16) ?> <span>Pengumuman (<?= count($announcements) ?>)</span></button>
            <button type="button" class="tab-btn" data-tab="profil" role="tab"><?= svgIcon('settings', 16) ?> <span>Profil Kelas</span></button>
            <button type="button" class="tab-btn" data-tab="pengguna" role="tab"><?= svgIcon('users', 16) ?> <span>Pengguna (<?= count($users) ?>)</span></button>
            <button type="button" class="tab-btn" data-tab="keamanan" role="tab"><?= svgIcon('lock', 16) ?> <span>Sandi Admin</span></button>
        </nav>

        <!-- PANEL 1: RINGKASAN & MONITOR KEAMANAN -->
        <div class="tab-panel" data-panel="ringkasan">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('shield', 20) ?> Monitor Postur Keamanan Database &amp; Aplikasi</h2>
                        <p class="admin-hint">Pemeriksaan otomatis lapisan pertahanan server agar database dan berkas tidak pernah terekspos ke publik.</p>
                    </div>
                    <span class="role-badge btn-success">PROTEKSI KETAT AKTIF</span>
                </div>

                <div class="security-health-grid">
                    <div class="sec-pill-card">
                        <span class="sec-pill-icon"><?= svgIcon('shield', 17) ?></span>
                        <div>
                            <strong>Penyimpanan Database</strong>
                            <span><?= htmlspecialchars($vaultLabel) ?> &middot; <?= empty($rootExposedFiles) ? 'Bebas file terekspos di webroot' : 'Peringatan file root' ?></span>
                        </div>
                    </div>
                    <div class="sec-pill-card">
                        <span class="sec-pill-icon"><?= svgIcon('lock', 17) ?></span>
                        <div>
                            <strong>CSRF &amp; Session Guard</strong>
                            <span>Token 256-bit wajib di seluruh POST, HttpOnly + SameSite=Lax aktif</span>
                        </div>
                    </div>
                    <div class="sec-pill-card">
                        <span class="sec-pill-icon"><?= svgIcon('check', 17) ?></span>
                        <div>
                            <strong>Anti-Brute-Force &amp; Flood</strong>
                            <span>Kunci IP otomatis setelah 5x gagal login &amp; pembatas spam chat/komentar</span>
                        </div>
                    </div>
                    <div class="sec-pill-card">
                        <span class="sec-pill-icon"><?= svgIcon('image', 17) ?></span>
                        <div>
                            <strong>Upload MIME &amp; RCE Shield</strong>
                            <span>Inspeksi finfo MIME asli + blokir eksekusi PHP/script di folder uploads/</span>
                        </div>
                    </div>
                </div>

                <div class="section-header" style="margin-top:1.75rem;">
                    <h2><?= svgIcon('activity', 19) ?> Log Aktivitas &amp; Keamanan Terbaru</h2>
                    <a href="admin-logs.php" class="btn btn-outline btn-sm">Lihat Semua Log <?= svgIcon('arrow-right', 14) ?></a>
                </div>

                <?php if (!empty($recentLogs)): ?>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Aksi</th>
                                <th>Keterangan</th>
                                <th>Alamat IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $l): ?>
                            <tr>
                                <td><span class="cell-note"><?= fmtDateTime($l['created_at']) ?></span></td>
                                <td><strong><?= htmlspecialchars($l['display_name'] ?: ($l['email'] ?: 'Tamu')) ?></strong></td>
                                <td><span class="mono-tag"><?= htmlspecialchars($l['action']) ?></span></td>
                                <td><span class="cell-note"><?= htmlspecialchars($l['detail'] ?: '-') ?></span></td>
                                <td><span class="mono-tag"><?= htmlspecialchars($l['ip'] ?: '-') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state"><p>Belum ada catatan aktivitas.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL 2: PAPAN PENGUMUMAN & AGENDA KELAS -->
        <div class="tab-panel" data-panel="pengumuman">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('megaphone', 20) ?> Terbitkan Pengumuman / Agenda Baru</h2>
                        <p class="admin-hint">Pengumuman akan langsung ditampilkan pada bagian atas Beranda untuk seluruh pengunjung dan anggota.</p>
                    </div>
                </div>

                <form action="admin.php" method="POST" style="max-width:720px;margin-bottom:2rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="admin_action" value="add_announcement">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="ann_title">Judul Pengumuman <span class="required">*</span></label>
                            <input type="text" id="ann_title" name="title" maxlength="160" required placeholder="Contoh: Jadwal Ujian Praktikum Administrasi Server">
                        </div>
                        <div class="form-group">
                            <label for="ann_prio">Kategori Prioritas</label>
                            <select id="ann_prio" name="priority">
                                <option value="info">Info Kelas Umum</option>
                                <option value="event">Agenda / Kegiatan</option>
                                <option value="important">Penting / Mendesak</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ann_content">Isi Pengumuman <span class="required">*</span></label>
                        <textarea id="ann_content" name="content" rows="3" maxlength="1000" required placeholder="Tuliskan detail informasi, waktu pelaksanaan, atau instruksi..."></textarea>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_pinned" value="1" checked>
                            <span>Sematkan (Pin) di urutan teratas Beranda</span>
                        </label>
                        <button type="submit" class="btn btn-primary"><?= svgIcon('megaphone', 16) ?> Terbitkan Pengumuman</button>
                    </div>
                </form>

                <h3 style="font-size:1.05rem;color:var(--dark);margin-bottom:0.9rem;">Daftar Pengumuman Aktif (<?= count($announcements) ?>)</h3>
                <?php if (!empty($announcements)): ?>
                    <div class="dashboard-list">
                        <?php foreach ($announcements as $ann): ?>
                        <div class="list-item">
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:0.45rem;margin-bottom:0.2rem;flex-wrap:wrap;">
                                    <?php if ((int)$ann['is_pinned'] === 1): ?>
                                        <span class="badge"><?= svgIcon('pin', 11) ?> Pinned</span>
                                    <?php endif; ?>
                                    <span class="mono-tag"><?= strtoupper(htmlspecialchars($ann['priority'])) ?></span>
                                    <strong style="color:var(--dark);"><?= htmlspecialchars($ann['title']) ?></strong>
                                </div>
                                <p class="admin-hint" style="margin-bottom:0.2rem;"><?= htmlspecialchars($ann['content']) ?></p>
                                <small class="admin-hint">Oleh <?= htmlspecialchars($ann['author_name'] ?: 'Admin') ?> &middot; <?= fmtDateTime($ann['created_at']) ?></small>
                            </div>
                            <div style="display:flex;gap:0.4rem;flex-shrink:0;">
                                <form action="admin.php" method="POST" class="inline-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="admin_action" value="toggle_pin_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= (int)$ann['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Ubah status Pin">
                                        <?= (int)$ann['is_pinned'] === 1 ? 'Lepas Pin' : 'Pin' ?>
                                    </button>
                                </form>
                                <form action="admin.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus pengumuman ini?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="admin_action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= (int)$ann['id'] ?>">
                                    <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('trash', 14) ?></button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><p>Belum ada pengumuman yang diterbitkan.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL 3: EDIT PROFIL KELAS -->
        <div class="tab-panel" data-panel="profil">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('settings', 20) ?> Konfigurasi Profil Komunitas Kelas</h2>
                        <p class="admin-hint">Informasi ini ditampilkan pada Beranda dan halaman Profil Kelas.</p>
                    </div>
                </div>

                <form action="admin.php" method="POST" enctype="multipart/form-data" style="max-width:760px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="admin_action" value="save_profile">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="class_name">Nama Kelas / Komunitas <span class="required">*</span></label>
                            <input type="text" id="class_name" name="class_name" required value="<?= htmlspecialchars($profile['class_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="teacher_name">Wali Kelas / Pembina <span class="required">*</span></label>
                            <input type="text" id="teacher_name" name="teacher_name" required value="<?= htmlspecialchars($profile['teacher_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="school_year">Tahun Ajaran</label>
                            <input type="text" id="school_year" name="school_year" value="<?= htmlspecialchars($profile['school_year'] ?? '') ?>" placeholder="2026/2027">
                        </div>
                        <div class="form-group">
                            <label for="schedule_note">Catatan Jadwal / Info Lab</label>
                            <input type="text" id="schedule_note" name="schedule_note" value="<?= htmlspecialchars($profile['schedule_note'] ?? '') ?>" placeholder="Contoh: Praktikum Lab Jaringan Rabu & Jumat">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="classphoto">Logo / Foto Identitas Kelas (Opsional)</label>
                        <input type="file" id="classphoto" name="classphoto" accept="image/jpeg,image/png,image/webp,image/gif">
                        <label class="checkbox-inline" style="margin-top:0.55rem;">
                            <input type="checkbox" name="reset_photo" value="1">
                            <span>Kembalikan ke lambang ikon terminal bawaan</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="description">Deskripsi Singkat Komunitas</label>
                        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="vision">Visi</label>
                            <textarea id="vision" name="vision" rows="4"><?= htmlspecialchars($profile['vision'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="mission">Misi</label>
                            <textarea id="mission" name="mission" rows="4"><?= htmlspecialchars($profile['mission'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= svgIcon('check', 16) ?> Simpan Profil Kelas</button>
                </form>
            </div>
        </div>

        <!-- PANEL 4: KELOLA PENGGUNA -->
        <div class="tab-panel" data-panel="pengguna">
            <div class="admin-card">
                <div class="section-header">
                    <div>
                        <h2><?= svgIcon('users', 20) ?> Manajemen Akun &amp; Hak Akses</h2>
                        <p class="admin-hint">Pembuatan akun Administrator baru dikunci secara eksklusif hanya melalui panel ini.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-modal-open="modalBuatAkun">
                        <?= svgIcon('plus', 15) ?> Buat Akun Baru
                    </button>
                </div>

                <div class="toolbar">
                    <div class="search-box">
                        <span class="search-icon"><?= svgIcon('search', 16) ?></span>
                        <input type="search" data-table-search placeholder="Cari nama, email, atau spesialisasi anggota...">
                    </div>
                    <span class="admin-hint" data-table-count><?= count($users) ?> pengguna</span>
                </div>

                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Peran</th>
                                <th>Konten</th>
                                <th>Aktivitas Terakhir</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr data-table-row data-search="<?= strtolower(htmlspecialchars($u['display_name'] . ' ' . $u['email'] . ' ' . ($u['specialty'] ?? ''))) ?>">
                                <td>
                                    <div class="user-cell">
                                        <?= renderAvatar($u['display_name'], $u['avatar'] ?? null, '36px') ?>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <a href="profile.php?member=<?= (int)$u['id'] ?>" style="color:var(--dark);"><?= htmlspecialchars($u['display_name']) ?></a>
                                                <?php if ((int)$u['id'] === $user['id']): ?><span class="badge">Anda</span><?php endif; ?>
                                                <?php if ((int)$u['is_banned'] === 1): ?><span class="role-badge role-banned">Nonaktif</span><?php endif; ?>
                                            </div>
                                            <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                                        <?= $u['role'] === 'admin' ? 'Admin' : 'Anggota' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-note">
                                        <strong><?= (int)$u['album_count'] ?></strong> karya &middot;
                                        <strong><?= (int)$u['story_count'] ?></strong> artikel
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-note"><?= timeAgo($u['last_login_at']) ?></span>
                                </td>
                                <td>
                                    <?php if ((int)$u['id'] === $user['id']): ?>
                                        <span class="admin-hint">Akun aktif Anda</span>
                                    <?php else: ?>
                                        <div class="table-actions">
                                            <form action="admin.php" method="POST" class="inline-form">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="admin_action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                                <button type="submit" class="btn btn-outline btn-sm">
                                                    <?= $u['role'] === 'admin' ? 'Jadikan Anggota' : 'Jadikan Admin' ?>
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-outline btn-sm js-open-reset-pw"
                                                    data-uid="<?= (int)$u['id'] ?>"
                                                    data-uname="<?= htmlspecialchars($u['display_name']) ?>">
                                                Reset Sandi
                                            </button>

                                            <form action="admin.php" method="POST" class="inline-form">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="admin_action" value="toggle_ban">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= (int)$u['is_banned'] === 1 ? 'btn-success' : 'btn-warning' ?>">
                                                    <?= (int)$u['is_banned'] === 1 ? 'Aktifkan' : 'Nonaktifkan' ?>
                                                </button>
                                            </form>

                                            <form action="admin.php" method="POST" class="inline-form" onsubmit="return confirm('Hapus permanen akun <?= htmlspecialchars(addslashes($u['display_name'])) ?> beserta seluruh karyanya?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="admin_action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-delete btn-sm" title="Hapus Akun"><?= svgIcon('trash', 14) ?></button>
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

        <!-- PANEL 5: KEAMANAN SANDI ADMIN -->
        <div class="tab-panel" data-panel="keamanan">
            <div class="admin-card">
                <h2><?= svgIcon('lock', 20) ?> Perbarui Kata Sandi Administrator</h2>
                <p class="admin-hint" style="margin-bottom:1.4rem;">Gunakan kata sandi kuat minimal 6 karakter untuk menjaga keamanan akses pusat kendali.</p>

                <form action="admin.php" method="POST" style="max-width:480px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="admin_action" value="change_my_password">
                    <div class="form-group">
                        <label for="old_password">Kata Sandi Lama</label>
                        <div class="input-wrap">
                            <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" data-pw-target="old_password" aria-label="Tampilkan sandi"><?= svgIcon('eye', 18) ?></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Kata Sandi Baru</label>
                        <div class="input-wrap">
                            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-pw-target="new_password" aria-label="Tampilkan sandi"><?= svgIcon('eye', 18) ?></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm">Konfirmasi Kata Sandi Baru</label>
                        <div class="input-wrap">
                            <input type="password" id="confirm" name="confirm" required minlength="6" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-pw-target="confirm" aria-label="Tampilkan sandi"><?= svgIcon('eye', 18) ?></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('shield', 16) ?> Simpan Kata Sandi Baru</button>
                </form>
            </div>
        </div>
    </div>
</main>

    <!-- Modal Buat Akun Baru -->
    <div class="modal" id="modalBuatAkun">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Buat Akun Pengguna / Admin Baru</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <form action="admin.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="admin_action" value="create_user">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_display_name">Nama Tampilan <span class="required">*</span></label>
                        <input type="text" id="new_display_name" name="display_name" required placeholder="Contoh: Andi Saputra">
                    </div>
                    <div class="form-group">
                        <label for="new_email">Alamat Email <span class="required">*</span></label>
                        <input type="email" id="new_email" name="email" required placeholder="andi@email.com">
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="new_role">Hak Akses</label>
                            <select id="new_role" name="role">
                                <option value="user">Anggota (User Biasa)</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_spec">Fokus Bidang TI</label>
                            <select id="new_spec" name="specialty">
                                <?php foreach (MEMBER_SPECIALTIES as $sp): ?>
                                    <option value="<?= htmlspecialchars($sp) ?>"><?= htmlspecialchars($sp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="new_pass">Kata Sandi Awal <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="new_pass" name="password" required minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="new_pass" aria-label="Tampilkan kata sandi"><?= svgIcon('eye', 16) ?></button>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Kata Sandi Pengguna -->
    <div class="modal" id="modalResetSandi">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Reset Kata Sandi: <span id="resetTargetName"></span></h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <form action="admin.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="admin_action" value="reset_user_password">
                <input type="hidden" name="user_id" id="resetTargetUid" value="">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="reset_new_pw">Kata Sandi Baru <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="reset_new_pw" name="new_password" required minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="reset_new_pw" aria-label="Tampilkan kata sandi"><?= svgIcon('eye', 16) ?></button>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Sandi Baru</button>
                </div>
            </form>
        </div>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
