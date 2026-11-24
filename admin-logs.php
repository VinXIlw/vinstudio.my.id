<?php
require_once __DIR__ . '/config.php';
requireAdmin($pdo);

$user         = currentUser();
$pageTitle    = 'Log Audit & Keamanan';
$currentPage  = 'admin';
$actionFilter = trim($_GET['action'] ?? '');
$limit        = min(max((int)($_GET['limit'] ?? 50), 10), 250);

if ($actionFilter !== '') {
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

$distinctActions = $pdo->query("SELECT DISTINCT action FROM login_logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/header.php';
?>

<main>
    <div class="page-header page-anim">
        <div>
            <span class="pill-eyebrow" style="margin-bottom:0.4rem;"><?= svgIcon('shield', 13) ?> Jejak Audit Keamanan</span>
            <h1>Log Aktivitas &amp; Keamanan Sistem</h1>
            <p class="page-subtitle">Catatan lengkap autentikasi, percobaan gagal, tindakan moderasi, dan aktivitas sistem.</p>
        </div>
        <a href="admin.php" class="btn btn-outline btn-sm"><?= svgIcon('arrow-left', 15) ?> Pusat Kendali Admin</a>
    </div>

    <div class="admin-card page-anim">
        <div class="toolbar">
            <form method="GET" action="admin-logs.php" style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;">
                <select name="action" onchange="this.form.submit()" aria-label="Filter jenis aksi" style="width:auto;min-width:190px;">
                    <option value="">Semua Jenis Aksi</option>
                    <?php foreach ($distinctActions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>>
                            <?= htmlspecialchars($act) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="limit" onchange="this.form.submit()" aria-label="Jumlah baris" style="width:auto;">
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 Baris</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 Baris</option>
                    <option value="250" <?= $limit === 250 ? 'selected' : '' ?>>250 Baris</option>
                </select>

                <?php if ($actionFilter !== ''): ?>
                    <a href="admin-logs.php" class="btn btn-ghost btn-sm">Reset Filter</a>
                <?php endif; ?>
            </form>
            <span class="admin-hint" style="margin-left:auto;"><?= count($logs) ?> entri audit ditampilkan</span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>Tidak ditemukan catatan log yang sesuai.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:20%;">Waktu</th>
                            <th style="width:20%;">Akun</th>
                            <th style="width:16%;">Kode Aksi</th>
                            <th style="width:24%;">Keterangan</th>
                            <th style="width:20%;">IP &amp; Agen Perangkat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <?php
                            $actStr = (string)($l['action'] ?? '');
                            $isWarn = strpos($actStr, 'gagal') !== false || strpos($actStr, 'ditolak') !== false || strpos($actStr, 'terkunci') !== false || strpos($actStr, 'fail') !== false;
                            ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--dark);font-size:0.83rem;display:block;"><?= fmtDateTime($l['created_at']) ?></strong>
                                    <small class="admin-hint"><?= timeAgo($l['created_at']) ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--dark);display:block;font-size:0.86rem;"><?= htmlspecialchars($l['display_name'] ?: 'Tamu / Anonim') ?></strong>
                                    <small class="admin-hint"><?= htmlspecialchars($l['email'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="role-badge <?= $isWarn ? 'role-banned' : 'role-user' ?>">
                                        <?= htmlspecialchars($actStr) ?>
                                    </span>
                                </td>
                                <td><span class="cell-note"><?= htmlspecialchars($l['detail'] ?: '-') ?></span></td>
                                <td>
                                    <span class="mono-tag"><?= htmlspecialchars($l['ip'] ?: '-') ?></span>
                                    <small class="admin-hint" style="display:block;margin-top:0.2rem;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($l['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($l['user_agent'] ?? '-', 0, 34, '...')) ?>
                                    </small>
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
