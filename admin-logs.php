<?php
require_once __DIR__ . '/config.php';
requireAdmin($pdo);

$user = currentUser();
$pageTitle = 'Log Aktivitas';
$currentPage = 'admin';

$actionFilter = trim($_GET['action'] ?? '');
$limit = min(max((int)($_GET['limit'] ?? 50), 10), 200);

if ($actionFilter) {
    $stmt = $pdo->prepare("SELECT * FROM login_logs WHERE action = ? ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $actionFilter, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM login_logs ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<main>
    <div class="page-header page-anim">
        <div>
            <h1>&#128203; Log Aktivitas &amp; Keamanan</h1>
            <p class="page-subtitle">Riwayat autentikasi, aktivitas pengguna, dan audit keamanan sistem.</p>
        </div>
        <div>
            <a href="admin.php" class="btn btn-outline">&larr; Kembali ke Panel Admin</a>
        </div>
    </div>

    <div class="admin-card page-anim">
        <div class="toolbar">
            <form method="GET" action="admin-logs.php" class="inline-form" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                <select name="action" onchange="this.form.submit()" aria-label="Filter aksi">
                    <option value="">Semua Aksi</option>
                    <option value="login" <?= $actionFilter === 'login' ? 'selected' : '' ?>>Login Berhasil</option>
                    <option value="login-gagal" <?= $actionFilter === 'login-gagal' ? 'selected' : '' ?>>Login Gagal</option>
                    <option value="login-ditolak" <?= $actionFilter === 'login-ditolak' ? 'selected' : '' ?>>Login Ditolak (Banned)</option>
                    <option value="register" <?= $actionFilter === 'register' ? 'selected' : '' ?>>Pendaftaran Akun</option>
                    <option value="admin-login" <?= $actionFilter === 'admin-login' ? 'selected' : '' ?>>Admin Login</option>
                    <option value="admin:simpan-profil" <?= $actionFilter === 'admin:simpan-profil' ? 'selected' : '' ?>>Ubah Profil</option>
                    <option value="admin:buat-akun" <?= $actionFilter === 'admin:buat-akun' ? 'selected' : '' ?>>Buat Akun</option>
                    <option value="admin:ubah-peran" <?= $actionFilter === 'admin:ubah-peran' ? 'selected' : '' ?>>Ubah Peran</option>
                </select>

                <select name="limit" onchange="this.form.submit()" aria-label="Batas baris">
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 Terakhir</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 Terakhir</option>
                    <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200 Terakhir</option>
                </select>

                <?php if ($actionFilter): ?>
                    <a href="admin-logs.php" class="btn btn-ghost btn-sm">&#10005; Reset Filter</a>
                <?php endif; ?>
            </form>
            <span class="admin-hint" style="margin-left:auto;"><?= count($logs) ?> log ditampilkan</span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <span class="empty-icon">&#128203;</span>
                <p>Tidak ada riwayat log aktivitas yang sesuai.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:22%;">Waktu</th>
                            <th style="width:18%;">Pengguna</th>
                            <th style="width:18%;">Aksi</th>
                            <th style="width:22%;">Detail</th>
                            <th style="width:20%;">IP &amp; Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td>
                                    <strong><?= fmtDateTime($l['created_at']) ?></strong>
                                    <small class="admin-hint"><?= timeAgo($l['created_at']) ?></small>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <span class="user-avatar <?= avatarClass($l['display_name'] ?: $l['email']) ?>" style="width:30px;height:30px;font-size:0.75rem;">
                                            <?= initials($l['display_name'] ?: $l['email']) ?>
                                        </span>
                                        <div class="user-info">
                                            <div class="user-name" style="font-size:0.85rem;"><?= htmlspecialchars($l['display_name'] ?: 'Tamu') ?></div>
                                            <span class="user-email" style="font-size:0.72rem;"><?= htmlspecialchars($l['email'] ?: '-') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'role-user';
                                    if ($l['action'] === 'login' || $l['action'] === 'admin-login') $badgeClass = 'badge btn-success';
                                    if (strpos($l['action'], 'gagal') !== false || strpos($l['action'], 'ditolak') !== false) $badgeClass = 'role-banned';
                                    ?>
                                    <span class="role-badge <?= $badgeClass ?>" style="font-size:0.72rem;">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-note"><?= htmlspecialchars($l['detail'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <span class="cell-note">
                                        <strong><?= htmlspecialchars($l['ip'] ?: '-') ?></strong>
                                        <br>
                                        <small class="admin-hint" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($l['user_agent'] ?? '') ?>">
                                            <?= htmlspecialchars(mb_strimwidth($l['user_agent'] ?? '', 0, 35, '...')) ?>
                                        </small>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
