<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user        = currentUser();
$currentPage = 'stories';
$error       = '';

$editId      = isset($_GET['edit']) ? (int)$_GET['edit'] : (int)($_POST['edit_id'] ?? 0);
$editingData = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$editId]);
    $editingData = $stmt->fetch();

    if (!$editingData || ((int)$editingData['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        safeRedirect('stories.php?err=' . urlencode('Artikel tidak ditemukan atau Anda tidak memiliki izin edit.'));
    }
}

$pageTitle = $editingData ? 'Edit Artikel' : 'Tulis Artikel Baru';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title    = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Catatan Praktikum');
        $content  = trim($_POST['content'] ?? '');

        if (!in_array($category, STORY_CATEGORIES, true)) {
            $category = 'Catatan Praktikum';
        }

        if ($title === '' || mb_strlen($title) < 3) {
            $error = 'Judul artikel minimal harus terdiri dari 3 karakter.';
        } elseif ($content === '' || mb_strlen($content) < 15) {
            $error = 'Isi artikel minimal harus terdiri dari 15 karakter.';
        } else {
            if ($editingData) {
                $stmt = $pdo->prepare("UPDATE stories SET title = ?, category = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $category, $content, $editingData['id']]);
                logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'artikel:edit', 'Memperbarui artikel #' . $editingData['id']);
                safeRedirect('story-detail.php?id=' . $editingData['id'] . '&msg=' . urlencode('Artikel berhasil diperbarui!'));
            } else {
                $stmt = $pdo->prepare("INSERT INTO stories (user_id, title, category, content) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $title, $category, $content]);
                $newId = (int)$pdo->lastInsertId();
                logActivity($pdo, $user['id'], $user['email'], $user['displayName'], 'artikel:tambah', 'Menerbitkan artikel #' . $newId . ': ' . $title);
                safeRedirect('story-detail.php?id=' . $newId . '&msg=' . urlencode('Artikel berhasil diterbitkan!'));
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main>
    <div style="max-width:740px;margin:0 auto 1rem;">
        <a href="<?= $editingData ? 'story-detail.php?id=' . (int)$editingData['id'] : 'stories.php' ?>" class="btn btn-ghost btn-sm">
            <?= svgIcon('arrow-left', 15) ?> Kembali
        </a>
    </div>

    <section class="form-card page-anim">
        <div style="margin-bottom:1.5rem;">
            <span class="pill-eyebrow" style="margin-bottom:0.45rem;"><?= svgIcon('book', 13) ?> Ruang Redaksi Komunitas</span>
            <h1 style="font-size:1.65rem;color:var(--dark);margin-bottom:0.25rem;">
                <?= $editingData ? 'Edit Artikel &amp; Catatan' : 'Tulis Artikel / Catatan Baru' ?>
            </h1>
            <p class="admin-hint">Publikasikan tutorial konfigurasi, catatan praktikum TKJ, atau cerita inspiratifmu.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="story-add.php<?= $editingData ? '?edit=' . (int)$editingData['id'] : '' ?>">
            <?= csrfField() ?>
            <?php if ($editingData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editingData['id'] ?>">
            <?php endif; ?>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="title">Judul Artikel <span class="required">*</span></label>
                    <input type="text" id="title" name="title" maxlength="180" required
                           value="<?= htmlspecialchars($_POST['title'] ?? ($editingData['title'] ?? '')) ?>"
                           data-counter="storyTitleCount"
                           placeholder="Contoh: Panduan Konfigurasi Firewall NAT di MikroTik RouterOS">
                    <div class="field-extra">
                        <span>Buat judul yang informatif</span>
                        <span id="storyTitleCount" class="char-count"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Topik / Kategori</label>
                    <select id="category" name="category">
                        <?php
                        $curCat = $_POST['category'] ?? ($editingData['category'] ?? 'Catatan Praktikum');
                        foreach (STORY_CATEGORIES as $cat):
                        ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $curCat === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="content">Isi Artikel / Catatan <span class="required">*</span></label>
                <textarea id="content" name="content" rows="12" maxlength="15000" required data-counter="storyBodyCount"
                          placeholder="Tuliskan pembahasan lengkap, langkah-langkah teknis, atau ceritamu di sini..."><?= htmlspecialchars($_POST['content'] ?? ($editingData['content'] ?? '')) ?></textarea>
                <div class="field-extra">
                    <span>Paragraf dan baris baru akan dipertahankan otomatis</span>
                    <span id="storyBodyCount" class="char-count"></span>
                </div>
            </div>

            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.25rem;">
                <a href="<?= $editingData ? 'story-detail.php?id=' . (int)$editingData['id'] : 'stories.php' ?>" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <?= svgIcon('check', 16) ?>
                    <span><?= $editingData ? 'Simpan Perubahan' : 'Terbitkan Artikel' ?></span>
                </button>
            </div>
        </form>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
