# TKJ Profile Web App

Web sederhana untuk **profil kelas Teknik Komputer dan Jaringan (TKJ)**.
Pengguna yang sudah login dapat menambahkan **album** (foto + deskripsi) dan
**cerita** (artikel singkat) sendiri.

## Fitur

| Fitur | Keterangan |
|-------|------------|
| Beranda | Hero, statistik, album & cerita terbaru |
| Profil Kelas | Informasi kelas, wali kelas, visi, misi |
| Album | Galeri foto, tambah/hapus album (butuh login) |
| Cerita | Artikel singkat, tambah/hapus cerita (butuh login) |
| Autentikasi | Registrasi & login memakai **email** + password (session) |
| Nama tampilan | Pengguna bisa mengganti nama sendiri saat login atau dari dashboard |
| Dashboard | Kelola album & cerita milik sendiri |
| Panel Admin | `/admin` — edit profil kelas, upload foto, kelola user, ganti password |

## Teknologi

- **Backend**: Node.js + Express
- **Database**: SQLite (`sqlite3`)
- **Template**: EJS
- **Upload**: Multer (foto, maks 5MB)
- **Session**: express-session

## Cara Menjalankan

```bash
cd tkj-profile-app
npm install
npm start
```

Buka browser di **http://localhost:3000**

Di Windows bisa juga klik ganda `run-server.cmd` (atau `run-install.cmd` untuk instalasi dependensi).

## Cara Pakai

1. Klik **Daftar** di menu atas, buat akun (email, nama, & password).
2. Login dengan email + password tersebut.
3. Mau mengganti nama tampilan? Isi kolom **Nama Tampilan** di form login,
   atau buka **Dashboard** → bagian profil → **Simpan**.
4. Masuk ke menu **Album** → **Tambah Album**, unggah foto + isi judul/deskripsi.
5. Masuk ke menu **Cerita** → **Tambah Cerita**, tulis judul dan isi cerita.
6. Semua konten yang kamu buat akan tampil di halaman publik.
7. Buka **Dashboard** untuk melihat & menghapus konten milikmu.

## Panel Admin

Halaman admin ada di **http://localhost:3000/admin/login** (tautan juga tersedia
di halaman login biasa). Akun admin bisa dibuat lewat CLI:

```bash
# Buat akun admin baru
npm run make-admin -- --email kiritohatsuno@gmail.com --password "rahasia123" --name "Nama Admin"

# Jadikan user yang sudah ada jadi admin
npm run make-admin -- --email siswa@sekolah.sch.id --promote

# Lihat daftar pengguna + perannya
npm run make-admin -- --list
```

Yang bisa dilakukan di panel admin:

| Bagian | Kemampuan |
|---|---|
| Data Profil Kelas | Edit nama kelas, wali, tahun ajaran, deskripsi, visi, misi |
| Foto Kelas | Upload foto baru, atau centang **Kembalikan ke logo bawaan** |
| Kelola Pengguna | Ubah peran (admin/user) dan hapus akun (beserta album & ceritanya) |
| Ganti Password | Ganti password akun admin sendiri |

> Setiap perubahan profil di panel admin langsung disimpan ke `database.db`
> **dan** disalin balik ke blok `DEFAULT_PROFILE` di `server.js` (di antara penanda
> `<<< PROFIL_KELAS_START >>>` dan `<<< PROFIL_KELAS_END >>>`), sehingga kedua
> sumber data selalu sama. Saat server start, `server.js` yang jadi acuan dan
> nilainya disalin ke database.
>
> Foto default memakai nilai sentinel `tkj-logo.png` yang berarti "belum ada
> foto custom" — halaman profil menampilkan avatar, bukan gambar.

## Mengubah Data Profil Kelas (Nama Wali Kelas, dll.)

Data profil tersimpan di tabel `profiles` pada `database.db` dan **dipakai oleh
halaman `/` dan `/profile` secara bersamaan**. Gunakan tool CLI:

```bash
# Lihat data profil saat ini
npm run profile

# Ubah wali kelas
npm run profile -- --teacher "Bapak Sutrisno, S.Kom."

# Ubah beberapa sekaligus
npm run profile -- --class "XII TKJ 2" --teacher "Bapak Sutrisno, S.Kom." --year "2025/2026"

# Ubah visi / misi / deskripsi
npm run profile -- --vision "Visi baru kelas" --mission "Misi baru kelas"
npm run profile -- --description "Deskripsi baru kelas"
```

| Opsi | Bagian yang diubah |
|---|---|
| `--class` | Nama kelas |
| `--teacher` | **Nama wali kelas** |
| `--year` | Tahun ajaran |
| `--description` | Deskripsi kelas |
| `--vision` | Visi |
| `--mission` | Misi |
| `--photo` | Nama file foto profil (letakkan file di `uploads/`) |

Perubahan langsung berlaku, **tanpa perlu restart server**.

> **Catatan:** CLI `npm run profile` dan panel admin (`/admin`) sama-sama
> menulis ke `database.db` **dan** menyalin balik nilainya ke blok
> `DEFAULT_PROFILE` di `server.js`, jadi keduanya selalu sinkron dan perubahan
> tidak hilang saat server direstart. Saat server start, `server.js` jadi acuan
> dan nilainya disalin ke database.

## Pengujian

Jalankan server lebih dulu di terminal lain (`npm start`), lalu:

```bash
npm test              # 30 pengujian route: publik, proteksi login, auth, CRUD
npm run test:admin    # 40 pengujian panel admin: login email, rename, role, hapus, profil
npm run test:upload   # uji upload foto (multer)
npm run cleanup       # bersihkan data uji dari database
```

Hasil pengujian terakhir: **smoke 30/30 lulus**, **admin 40/40 lulus**, upload foto lulus.

## Struktur Folder

```
tkj-profile-app/
├── server.js              # Server & routing Express
├── admin-routes.js        # Route panel admin (login, profil, user, password)
├── db-migrate.js          # Modul migrasi skema (dipakai server & CLI)
├── profile-sync.js        # Tulis data profil balik ke DEFAULT_PROFILE di server.js
├── make-admin.js          # CLI buat/promote/list akun admin
├── admin-test.js          # Pengujian otomatis panel admin
├── smoke-test.js          # Pengujian otomatis route utama
├── test-upload.js         # Pengujian upload foto
├── verify-final.js        # Pengujian upload foto profil + reset ke logo bawaan
├── cleanup-test-data.js   # Pembersih data uji
├── run-install.cmd        # Launcher instalasi dependensi (Windows)
├── run-server.cmd         # Launcher server (Windows)
├── views/                 # Template EJS
│   ├── index.ejs          # Beranda
│   ├── profile.ejs        # Profil kelas
│   ├── albums.ejs         # Daftar album
│   ├── album-add.ejs      # Form tambah album
│   ├── stories.ejs        # Daftar cerita
│   ├── story-add.ejs      # Form tambah cerita
│   ├── dashboard.ejs      # Dashboard pengguna
│   ├── login.ejs          # Halaman login
│   ├── register.ejs       # Halaman daftar
│   ├── admin-login.ejs    # Halaman login admin
│   ├── admin.ejs          # Panel admin
│   ├── 403.ejs            # Halaman akses ditolak
│   └── 404.ejs            # Halaman tidak ditemukan
├── public/
│   ├── css/style.css      # Stylesheet
│   ├── js/main.js         # Interaksi frontend
│   └── img/placeholder.svg# Placeholder album tanpa foto
├── uploads/               # Folder penyimpanan foto
└── database.db            # Database SQLite (otomatis dibuat)
```

## Catatan Teknis

- **Skema `users`**: kolom `username` diganti menjadi `email` (unik, wajib) +
  `display_name`. Username lama yang bukan email otomatis jadi `*@legacy.local`
  saat migrasi. Migrasi dijalankan otomatis oleh `db-migrate.js` (dipakai
  `server.js` dan `make-admin.js`), jadi tidak perlu langkah manual.
- **Proteksi konten**: `requireLogin` middleware mengalihkan tamu ke halaman login.
  Route `/albums/add`, `/stories/add`, `/dashboard` dilindungi.
- **Proteksi admin**: `requireAdmin` mengalihkan tamu ke `/admin/login` dan
  memberi **403** untuk user biasa yang sudah login.
- **Hak hapus**: pengguna hanya bisa menghapus album/cerita miliknya
  (kecuali bertipe `admin`).
- **Upload**: Multer, maksimal 5MB, hanya file gambar, nama file di-random
  agar tidak bentrok. Foto tanpa sampul memakai placeholder SVG.
- **Hak akses file**: `uploads/` hanya bisa ditulis oleh server; nama file
  di-random dan ekstensi divalidasi.
- **Password** disimpan apa adanya di database (untuk keperluan belajar/demo).
  Pada aplikasi produksi gunakan hashing (bcrypt) dan tambahkan rate limit.
- Ganti `secret` pada session di `server.js` sebelum deploy.

