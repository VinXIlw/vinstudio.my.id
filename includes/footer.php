<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('403 Forbidden');
}
$isChatPage        = (($currentPage ?? '') === 'chat');
$currentUserFooter = currentUser();
?>
    </div><!-- /#pjaxStage -->

    <!-- Floating Mini Global Live Chat Drawer -->
    <div class="floating-chat-root" id="floatingChatRoot" style="<?= $isChatPage ? 'display:none;' : '' ?>">
        <div class="floating-chat-panel" id="floatingChatPanel" aria-hidden="true">
            <div class="floating-chat-head">
                <div style="display:flex;align-items:center;gap:0.6rem;">
                    <span class="nav-live-dot"></span>
                    <div>
                        <strong style="display:block;font-size:0.9rem;line-height:1.2;">Live Chat <?= htmlspecialchars(APP_NAME) ?></strong>
                        <small style="font-size:0.72rem;opacity:0.8;" id="floatingOnlineText">Terhubung langsung</small>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.35rem;">
                    <a href="chat.php" class="floating-head-btn" title="Buka Ruang Chat Penuh">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    </a>
                    <button type="button" id="floatingChatClose" class="floating-head-btn" title="Tutup">&times;</button>
                </div>
            </div>
            <div class="floating-chat-body" id="floatingChatBody">
                <div class="admin-hint" style="text-align:center;padding:1.5rem 0;">Memuat obrolan...</div>
            </div>
            <?php if ($currentUserFooter): ?>
            <form id="floatingChatForm" class="floating-chat-foot" data-no-loading>
                <input type="text" id="floatingChatInput" maxlength="400" placeholder="Ketik pesan cepat..." autocomplete="off" required>
                <button type="submit" class="btn btn-primary btn-sm">Kirim</button>
            </form>
            <?php else: ?>
            <div class="floating-chat-foot" style="justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--text-light);">Masuk untuk ikut berdiskusi</span>
                <a href="login.php?r=chat.php" class="btn btn-primary btn-sm" data-no-transition>Masuk</a>
            </div>
            <?php endif; ?>
        </div>

        <button type="button" id="floatingChatFab" class="floating-chat-fab" aria-label="Buka Live Chat">
            <span class="fab-live-dot"></span>
            <?= svgIcon('chat', 20) ?>
            <span class="fab-label">Live Chat</span>
        </button>
    </div>

    <!-- Lightbox Modal untuk Zoom Gambar Resolusi Tinggi -->
    <div class="lightbox-backdrop" id="imageLightbox" aria-hidden="true">
        <button type="button" class="lightbox-close" id="lightboxCloseBtn" aria-label="Tutup pratinjau">&times;</button>
        <img src="" alt="Pratinjau Gambar Penuh" id="lightboxImage">
        <div class="lightbox-caption" id="lightboxCaption"></div>
    </div>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand-col">
                    <div class="logo" style="margin-bottom:0.9rem;">
                        <span class="logo-icon" style="width:36px;height:36px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        </span>
                        <div class="logo-brand-stack">
                            <span class="logo-text"><?= htmlspecialchars(APP_NAME) ?></span>
                            <span class="logo-sub">TKJ DIGITAL HUB</span>
                        </div>
                    </div>
                    <p class="footer-desc">
                        Ekosistem digital resmi komunitas <strong><?= htmlspecialchars(APP_NAME) ?></strong> — pusat dokumentasi proyek praktikum, publikasi artikel teknologi, pengumuman akademik, dan diskusi real-time.
                    </p>
                    <div class="footer-badges-row">
                        <div class="footer-status-pill">
                            <span class="status-dot online"></span>
                            <span>Sistem &amp; Live Chat Aktif</span>
                        </div>
                        <div class="footer-security-pill">
                            <?= svgIcon('shield', 13) ?>
                            <span>Vault DB &amp; CSRF Protected</span>
                        </div>
                    </div>
                </div>

                <div class="footer-links-col">
                    <h4>Navigasi Portal</h4>
                    <ul>
                        <li><a href="index.php">Beranda &amp; Pengumuman</a></li>
                        <li><a href="profile.php">Profil Kelas &amp; Direktori Anggota</a></li>
                        <li><a href="albums.php">Galeri Proyek &amp; Dokumentasi</a></li>
                        <li><a href="stories.php">Artikel &amp; Catatan Praktikum</a></li>
                    </ul>
                </div>

                <div class="footer-links-col">
                    <h4>Ruang Anggota</h4>
                    <ul>
                        <li><a href="chat.php">Global Live Chat</a></li>
                        <?php if ($currentUserFooter): ?>
                            <li><a href="dashboard.php">Dasbor &amp; Pengaturan Profil</a></li>
                            <li><a href="album-add.php">Unggah Karya / Dokumentasi</a></li>
                            <li><a href="story-add.php">Tulis Artikel Baru</a></li>
                        <?php else: ?>
                            <li><a href="login.php" data-no-transition>Masuk ke Akun</a></li>
                            <li><a href="register.php" data-no-transition>Pendaftaran Anggota</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(APP_NAME) ?></strong>. Seluruh hak cipta dilindungi.</p>
                <p class="footer-tech">Arsitektur Keamanan Berlapis &middot; Transisi Instan &middot; Responsif Modern</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js?v=<?= @filemtime(__DIR__ . '/../js/main.js') ?: '6.0' ?>"></script>
</body>
</html>
