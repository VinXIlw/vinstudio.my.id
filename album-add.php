<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user        = currentUser();
$currentPage = 'albums';
$error       = '';

$editId      = isset($_GET['edit']) ? (int)$_GET['edit'] : (int)($_POST['edit_id'] ?? 0);
$editingData = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ?");
    $stmt->execute([$editId]);
    $editingData = $stmt->fetch();

    if (!$editingData || ((int)$editingData['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        safeRedirect('albums.php?err=' . urlencode('Karya tidak ditemukan atau Anda tidak memiliki izin edit.'));
    }
}

$pageTitle = $editingData ? 'Edit Karya Galeri' : 'Unggah Karya Baru';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $category    = trim($_POST['category'] ?? 'Dokumentasi Kelas');
        $description = trim($_POST['description'] ?? '');

        if (!in_array($category, ALBUM_CATEGORIES, true)) {
            $category = 'Dokumentasi Kelas';
        }

        if ($title === '' || mb_strlen($title) < 3) {
            $error = 'Judul karya minimal harus terdiri dari 3 karakter.';
        } else {
            $photoName = $editingData ? ($editingData['photo'] ?: 'no-image.png') : 'no-image.png';

            if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upRes = saveUploadedImage($_FILES['photo'], 'photo-' . $user['id']);
                if (!$upRes['ok']) {
                    $error = $upRes['error'];
                } else {
                    if ($editingData && !empty($editingData['photo']) && $editingData['photo'] !== 'no-image.png') {
                        $oldPath = __DIR__ . '/uploads/' . basename($editingData['photo']);
                        if (is_file($oldPath)) @unlink($oldPath);
                    }
                    $photoName = $upRes['filename'];
                }
            } elseif (!$editingData) {
                $error = 'Silakan pilih berkas foto dokumentasi atau karya yang ingin diunggah.';
            }

            if ($error === '') {
                if ($editingData) {
                    $stmt = $pdo->prepare("UPDATE albums SET title = ?, category = ?, description = ?, photo = ? WHERE id = ?");
                    $stmt->execute([$title, $category, $description, $photoName, $editingData['id']]);
                    logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'album:edit', 'Memperbarui karya #' . $editingData['id']);
                    safeRedirect('album-detail.php?id=' . $editingData['id'] . '&msg=' . urlencode('Perubahan karya berhasil disimpan!'));
                } else {
                    $stmt = $pdo->prepare("INSERT INTO albums (user_id, title, category, description, photo) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $title, $category, $description, $photoName]);
                    $newId = (int)$pdo->lastInsertId();
                    logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'album:tambah', 'Mengunggah karya #' . $newId . ': ' . $title);
                    safeRedirect('album-detail.php?id=' . $newId . '&msg=' . urlencode('Karya berhasil dipublikasikan!'));
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main>
    <div style="max-width:740px;margin:0 auto 1rem;">
        <a href="<?= $editingData ? 'album-detail.php?id=' . (int)$editingData['id'] : 'albums.php' ?>" class="btn btn-ghost btn-sm">
            <?= svgIcon('arrow-left', 15) ?> Kembali
        </a>
    </div>

    <section class="form-card page-anim">
        <div style="margin-bottom:1.5rem;">
            <span class="pill-eyebrow" style="margin-bottom:0.45rem;"><?= svgIcon('image', 13) ?> Portofolio Visual</span>
            <h1 style="font-size:1.65rem;color:var(--dark);margin-bottom:0.25rem;">
                <?= $editingData ? 'Edit Karya &amp; Dokumentasi' : 'Unggah Karya / Dokumentasi Baru' ?>
            </h1>
            <p class="admin-hint">Lengkapi informasi karya serta unggah gambar beresolusi baik (JPG, PNG, WEBP, GIF maks. 5 MB).</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="album-add.php<?= $editingData ? '?edit=' . (int)$editingData['id'] : '' ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php if ($editingData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editingData['id'] ?>">
            <?php endif; ?>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="title">Judul Karya / Dokumentasi <span class="required">*</span></label>
                    <input type="text" id="title" name="title" maxlength="160" required
                           value="<?= htmlspecialchars($_POST['title'] ?? ($editingData['title'] ?? '')) ?>"
                           data-counter="albumTitleCount"
                           placeholder="Contoh: Topologi VLAN & Routing MikroTik">
                    <div class="field-extra">
                        <span>Judul ringkas &amp; jelas</span>
                        <span id="albumTitleCount" class="char-count"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Kategori</label>
                    <select id="category" name="category">
                        <?php
                        $curCat = $_POST['category'] ?? ($editingData['category'] ?? 'Dokumentasi Kelas');
                        foreach (ALBUM_CATEGORIES as $cat):
                        ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $curCat === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="photo"><?= $editingData ? 'Ganti Foto Karya (Opsional)' : 'Berkas Foto Karya' ?> <?= !$editingData ? '<span class="required">*</span>' : '' ?></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" <?= !$editingData ? 'required' : '' ?>>
                <div id="albumLivePreviewWrap" style="<?= ($editingData && !empty($editingData['photo']) && $editingData['photo'] !== 'no-image.png') ? 'display:block;' : 'display:none;' ?>margin-top:0.85rem;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);background:#070b14;max-height:300px;text-align:center;">
                    <img id="albumLivePreviewImg"
                         src="<?= ($editingData && !empty($editingData['photo']) && $editingData['photo'] !== 'no-image.png') ? 'uploads/' . htmlspecialchars(basename($editingData['photo'])) : '' ?>"
                         alt="Pratinjau" style="max-height:300px;width:auto;margin:0 auto;object-fit:contain;">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Deskripsi &amp; Catatan Teknis</label>
                <textarea id="description" name="description" rows="4" maxlength="1500" data-counter="albumDescCount"
                          placeholder="Ceritakan konteks kegiatan, spesifikasi proyek, atau proses pengerjaan karya ini..."><?= htmlspecialchars($_POST['description'] ?? ($editingData['description'] ?? '')) ?></textarea>
                <div class="field-extra">
                    <span>Mendukung baris baru otomatis</span>
                    <span id="albumDescCount" class="char-count"></span>
                </div>
            </div>

            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.25rem;">
                <a href="<?= $editingData ? 'album-detail.php?id=' . (int)$editingData['id'] : 'albums.php' ?>" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <?= svgIcon('check', 16) ?>
                    <span><?= $editingData ? 'Simpan Perubahan' : 'Publikasikan Karya' ?></span>
                </button>
            </div>
        </form>
    </section>
</main>

<script>
(function() {
    const inp  = document.getElementById('photo');
    const wrap = document.getElementById('albumLivePreviewWrap');
    const img  = document.getElementById('albumLivePreviewImg');
    if (inp && wrap && img) {
        inp.addEventListener('change', function() {
            const f = this.files && this.files[0];
            if (!f) return;
            if (f.size > 5 * 1024 * 1024) {
                alert('Ukuran berkas maksimal 5 MB.');
                this.value = '';
                return;
            }
            const r = new FileReader();
            r.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
            r.readAsDataURL(f);
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
