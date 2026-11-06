<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Tulis Cerita Baru';
$currentPage = 'stories';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = 'Judul dan konten cerita wajib diisi!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO stories (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $title, $content]);
        $newId = $pdo->lastInsertId();
        header("Location: story-detail.php?id=" . $newId);
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="form-page">
        <div class="container">
            <div class="form-card page-anim">
                <h2 style="margin-bottom:0.5rem;color:var(--dark);">&#128214; Tulis Cerita Baru</h2>
                <p class="admin-hint" style="margin-bottom:1.5rem;">Bagikan cerita, pengalaman, tutorial, atau artikelmu.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="story-add.php" method="POST">
                    <div class="form-group">
                        <label for="title">Judul Cerita <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required placeholder="Contoh: Pengalaman Belajar Jaringan Komputer">
                    </div>
                    <div class="form-group">
                        <label for="content">Isi Cerita <span class="required">*</span></label>
                        <textarea id="content" name="content" rows="8" required placeholder="Tuliskan pengalaman atau artikelmu di sini..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">&#128640; Terbitkan Cerita</button>
                </form>

                <p style="text-align:center;margin-top:1.25rem;">
                    <a href="stories.php">&larr; Kembali ke Daftar Cerita</a>
                </p>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
