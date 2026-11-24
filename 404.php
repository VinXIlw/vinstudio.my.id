<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Halaman Tidak Ditemukan (404)';
include __DIR__ . '/includes/header.php';
?>
<main>
    <div class="empty-state page-anim" style="max-width:580px;margin:3rem auto;padding:3.2rem 2rem;">
        <div style="color:var(--primary-light);margin-bottom:0.85rem;"><?= svgIcon('search', 46) ?></div>
        <h1 style="font-size:1.75rem;color:var(--dark);margin-bottom:0.5rem;">Halaman Tidak Ditemukan (404)</h1>
        <p style="margin-bottom:1.6rem;">Tautan atau karya yang Anda tuju tidak ditemukan atau telah dihapus oleh pemiliknya.</p>
        <a href="index.php" class="btn btn-primary"><?= svgIcon('arrow-left', 16) ?> Kembali ke Beranda</a>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
