<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Akses Ditolak';
include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="profile-detail page-anim">
        <div class="container" style="padding:3rem 1rem;">
            <div class="empty-state">
                <h1 style="font-size:3.5rem;margin-bottom:0.5rem;">🚫</h1>
                <h2 style="color:var(--dark);margin-bottom:0.5rem;">Akses Ditolak (403)</h2>
                <p>Halaman ini hanya dapat diakses oleh akun Administrator.</p>
                <p style="margin-top:1.5rem;">
                    <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
                </p>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
