<?php
$isChatPage = (($currentPage ?? '') === 'chat');
$currentUserFooter = currentUser();
?>
    <?php if (!$isChatPage): ?>
    <!-- Floating Mini Global Live Chat Drawer -->
    <div class="floating-chat-root" id="floatingChatRoot">
        <div class="floating-chat-panel" id="floatingChatPanel" aria-hidden="true">
            <div class="floating-chat-head">
                <div style="display:flex;align-items:center;gap:0.55rem;">
                    <span class="nav-live-dot"></span>
                    <div>
                        <strong style="display:block;font-size:0.92rem;line-height:1.15;">Global Live Chat</strong>
                        <small style="font-size:0.72rem;opacity:0.8;" id="floatingOnlineText">Terhubung langsung</small>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.35rem;">
                    <a href="chat.php" class="floating-head-btn" title="Buka Ruang Chat Penuh" data-no-transition>⛶</a>
                    <button type="button" id="floatingChatClose" class="floating-head-btn" title="Tutup">&times;</button>
                </div>
            </div>
            <div class="floating-chat-body" id="floatingChatBody">
                <div class="admin-hint" style="text-align:center;padding:1.5rem 0;">Memuat obrolan...</div>
            </div>
            <?php if ($currentUserFooter): ?>
            <form id="floatingChatForm" class="floating-chat-foot" data-no-loading>
                <input type="text" id="floatingChatInput" maxlength="400" placeholder="Ketik pesan cepat..." autocomplete="off" required>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:0.45rem 0.8rem;">Kirim</button>
            </form>
            <?php else: ?>
            <div class="floating-chat-foot" style="justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--text-light);">Masuk untuk ikut mengobrol</span>
                <a href="login.php?r=chat.php" class="btn btn-primary btn-sm" style="padding:0.35rem 0.75rem;font-size:0.75rem;">Masuk</a>
            </div>
            <?php endif; ?>
        </div>

        <button type="button" id="floatingChatFab" class="floating-chat-fab" aria-label="Buka Live Chat">
            <span class="fab-live-dot"></span>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            <span class="fab-label">Live Chat</span>
        </button>
    </div>
    <?php endif; ?>

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
                    <div class="logo" style="margin-bottom:0.85rem;">
                        <span class="logo-icon" style="width:36px;height:36px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        </span>
                        <span class="logo-text"><?= APP_NAME ?></span>
                    </div>
                    <p class="footer-desc">
                        Platform kreatif digital untuk mendokumentasikan portofolio foto, berbagi karya tulis &amp; artikel, serta berinteraksi secara langsung melalui Global Live Chat.
                    </p>
                    <div class="footer-status-pill">
                        <span class="status-dot online"></span>
                        <span>Sistem &amp; Live Chat Aktif</span>
                    </div>
                </div>

                <div class="footer-links-col">
                    <h4>Eksplorasi</h4>
                    <ul>
                        <li><a href="index.php">Beranda Utama</a></li>
                        <li><a href="profile.php">Profil &amp; Direktori Anggota</a></li>
                        <li><a href="albums.php">Galeri Album &amp; Portofolio</a></li>
                        <li><a href="stories.php">Cerita &amp; Artikel Blog</a></li>
                    </ul>
                </div>

                <div class="footer-links-col">
                    <h4>Komunitas</h4>
                    <ul>
                        <li><a href="chat.php">Global Live Chat</a></li>
                        <?php if ($currentUserFooter): ?>
                            <li><a href="dashboard.php">Dasbor Akun Saya</a></li>
                            <li><a href="album-add.php">Unggah Karya Foto</a></li>
                            <li><a href="story-add.php">Tulis Artikel Baru</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Masuk Akun</a></li>
                            <li><a href="register.php">Pendaftaran Anggota</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> (vinstudio.my.id). Seluruh hak cipta dilindungi.</p>
                <p class="footer-tech">Ditenagai arsitektur modern yang cepat, responsif &amp; aman.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js?v=2.0"></script>
</body>
</html>
