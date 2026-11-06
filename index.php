<?php 
// Logika PHP Anda (include database, session, dll) tetap di sini
include 'includes/header.php'; 
?>

<!-- Header Baru -->
<header class="header-modern">
    <div class="logo">
        <h2 style="font-weight: 800; color: #111827;">VinStudio</h2>
    </div>
    <nav>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn-action">Dashboard</a>
            <a href="logout.php" class="btn-action" style="color: #EF4444;">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-primary">Login / Masuk</a>
        <?php endif; ?>
    </nav>
</header>

<!-- Pembungkus Grid Album -->
<main class="album-container">
    <?php 
    // Asumsi loop PHP Anda berjalan di sini
    // $albums = mysqli_query($conn, "SELECT * FROM albums ORDER BY id DESC");
    // while($row = mysqli_fetch_assoc($albums)): 
    ?>
    
    <article class="album-card">
        <a href="album-detail.php?id=<?= $row['id'] ?>">
            <div class="album-img-wrapper">
                <!-- Gunakan placeholder SVG/gambar default jika tidak ada gambar -->
                <img src="img/<?= !empty($row['cover_image']) ? $row['cover_image'] : 'placeholder.svg' ?>" alt="Album Cover">
            </div>
        </a>
        <div class="album-content">
            <a href="album-detail.php?id=<?= $row['id'] ?>" style="text-decoration: none;">
                <h3 class="album-title"><?= htmlspecialchars($row['title']) ?></h3>
            </a>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                <?= htmlspecialchars(substr($row['description'], 0, 80)) ?>...
            </p>
            
            <div class="album-meta">
                <!-- Fitur Like yang sudah ada -->
                <form action="like.php" method="POST" style="display:inline;">
                    <input type="hidden" name="album_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn-action">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        <span><?= $row['likes_count'] ?></span>
                    </button>
                </form>

                <!-- Fitur Komentar yang sudah ada -->
                <a href="album-detail.php?id=<?= $row['id'] ?>#comments" class="btn-action">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <span>Komentar</span>
                </a>
            </div>
        </div>
    </article>
    
    <?php // endwhile; ?>
</main>

<?php include 'includes/footer.php'; ?>