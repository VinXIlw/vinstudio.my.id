# XI TKJ 1 — Digital Hub & Community Portal

Portal komunitas, portofolio proyek praktikum, publikasi artikel teknis, pengumuman kelas, dan Global Live Chat real-time untuk **Kelas XI TKJ 1** (`vinstudio.my.id`).

## Fitur Utama
- **Desain Glassmorphic Enterprise (Dark & Light Mode)** dengan navigasi instan PJAX/Turbo dan indikator kapsul geser (*Continuous Sliding Glider*).
- **Papan Pengumuman & Agenda Kelas Resmi** di Beranda yang dikelola langsung melalui Panel Administrator.
- **Galeri Proyek & Artikel Teknis** lengkap dengan filter kategori instan, pencarian langsung, fitur **Edit Karya/Artikel**, penghitung tayangan (*views*), dan tombol **Like AJAX** tanpa reload.
- **Direktori Anggota & Portofolio Publik** termasuk bidang keahlian TI (*Specialty*), bio, dan foto profil kustom.
- **Global Live Chat Real-Time** dengan dukungan balasan pesan (*quote reply*), indikator anggota online, dan laci mini chat melayang.

## Arsitektur Keamanan Ketat (Zero Database Exposure)
1. **Isolasi Brankas Database**: Mendukung MySQL/MariaDB via `.env` (di luar `public_html`) serta fallback SQLite yang disimpan di luar webroot (`../.xitkj1_vault/`) atau di dalam folder terkunci `storage/` dengan nama berkas acak kriptografis 224-bit serta pembersihan otomatis file database lama dari root publik.
2. **Perlindungan Berlapis `.htaccess`**: Memblokir akses HTTP langsung ke `storage/`, `includes/`, `config.php`, dotfiles, serta seluruh ekstensi database/log/konfigurasi.
3. **Proteksi CSRF & Sesi Ketat**: Seluruh formulir dan endpoint API mutasi dilindungi oleh token CSRF 256-bit (`hash_equals`), cookie sesi `HttpOnly`, `SameSite=Lax`, dan `session_regenerate_id(true)`.
4. **Rate Limiting Anti-Brute-Force & Anti-Spam**: Pembatasan otomatis pada percobaan login gagal, pendaftaran akun, pengiriman komentar, dan obrolan Live Chat.
5. **Upload RCE Shield**: Validasi MIME biner asli (`finfo_file` + `getimagesize`), penamaan acak, serta penonaktifan mesin eksekusi PHP di dalam folder `uploads/`.
