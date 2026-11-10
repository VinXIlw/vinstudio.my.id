<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Tambah Album';
$currentPage = 'albums';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $error = 'Judul album wajib diisi!';
    } else {
        $photoName = 'no-image.png';

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upRes = saveUploadedImage($_FILES['photo'], 'photo-' . $user['id']);
            if (!$upRes['ok']) {
                $error = $upRes['error'];
            } else {
                $photoName = $upRes['filename'];
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO albums (user_id, title, description, photo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $description, $photoName]);
            $newId = $pdo->lastInsertId();
            header("Location: album-detail.php?id=" . $newId);
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="form-page">
        <div class="container">
            <div class="form-card page-anim">
                <h2 style="margin-bottom:0.5rem;color:var(--dark);">&#128248; Tambah Album Baru</h2>
                <p class="admin-hint" style="margin-bottom:1.5rem;">Unggah foto dokumentasi atau portofolio karyamu.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="album-add.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Judul Album <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required placeholder="Contoh: Kunjungan Industri Komputer 2026">
                    </div>
                    <div class="form-group">
                        <label for="description">Deskripsi Album</label>
                        <textarea id="description" name="description" rows="4" placeholder="Ceritakan tentang momen atau karya ini..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="photo">Pilih Foto Sampul (Maks 8MB)</label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">&#128190; Simpan &amp; Terbitkan Album</button>
                </form>

                <p style="text-align:center;margin-top:1.25rem;">
                    <a href="albums.php">&larr; Kembali ke Galeri Album</a>
                </p>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
