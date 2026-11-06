<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Halaman Tidak Ditemukan';
include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="profile-detail page-anim">
        <div class="container" style="padding:3rem 1rem;">
            <div class="empty-state">
                <h1 style="font-size:3.5rem;margin-bottom:0.5rem;color:var(--primary);">&#128269;</h1>
                <h2 style="color:var(--dark);margin-bottom:0.5rem;">Halaman Tidak Ditemukan (404)</h2>
                <p>Halaman atau data yang Anda cari tidak tersedia atau telah dipindahkan.</p>
                <p style="margin-top:1.5rem;">
                    <a href="index.php" class="btn btn-primary">&larr; Kembali ke Beranda</a>
                </p>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
