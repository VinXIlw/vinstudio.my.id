/*
  TKJ Profile Web App
  Web sederhana untuk profil kelas Teknik Komputer dan Jaringan
  Fitur: Profil, Album, Cerita (untuk pengguna login)
  Teknologi: Node.js + Express + SQLite + EJS
  Jalankan: npm install && node server.js
*/
const express = require('express');
const path = require('path');
const session = require('express-session');
const sqlite3 = require('sqlite3').verbose();
const multer = require('multer');
const fs = require('fs');
const { writeProfileToServerFile } = require('./profile-sync');
const { registerAdminRoutes } = require('./admin-routes');
const { migrateUsers, logActivity, CREATE_USERS } = require('./db-migrate');

const app = express();
const PORT = process.env.PORT || 3000;

// ============================================================
//  DATA PROFIL KELAS - UBAH NILAI DI BAGIAN INI SAJA
//  Setelah diubah, restart server (Ctrl+C lalu npm start)
//  Data di bawah otomatis tersinkron ke database.
//  Bisa juga diubah lewat halaman /admin (khusus admin).
// ============================================================
// <<< PROFIL_KELAS_START >>>
const DEFAULT_PROFILE = {
    class_name   : 'Kelas XI TKJ 1',
    description  : 'Kelas Teknik Komputer dan Jaringan',
    teacher_name : 'Bu Herlina, S.Pd.',
    school_year  : '2026/2027',
    vision       : 'Menjadi kelas unggulan dalam bidang TI dan jaringan',
    mission      : 'Menyelenggara pembelajaran inovatif dan praktis',
    photo        : 'tkj-logo.png'
};
// <<< PROFIL_KELAS_END >>>
// ============================================================

// --- Database Setup ---
const dbPath = path.join(__dirname, 'database.db');
const db = new sqlite3.Database(dbPath);

// --- Migrasi skema tabel users ---
// Skema lama memakai kolom `username` sebagai identitas login.
// Skema baru: `email` (identitas login) + `display_name` (nama tampilan yang
// bisa diganti sendiri oleh pemiliknya).
migrateUsers(db, (migErr) => {
    if (migErr) {
        console.error('  [ERROR] Migrasi database gagal: ' + migErr.message);
        process.exit(1);
    }

db.serialize(() => {
    db.run(CREATE_USERS);

    db.run(`CREATE TABLE IF NOT EXISTS profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT,
        description TEXT,
        teacher_name TEXT,
        school_year TEXT,
        photo TEXT,
        vision TEXT,
        mission TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS albums (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        photo TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS stories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )`);

    // Sinkronkan data profil kelas dari DEFAULT_PROFILE ke database.
    // Dijalankan setiap server start, jadi perubahan di server.js langsung berlaku.
    db.run(
        `INSERT INTO profiles (id, class_name, description, teacher_name, school_year, vision, mission, photo)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(id) DO UPDATE SET
            class_name   = excluded.class_name,
            description  = excluded.description,
            teacher_name = excluded.teacher_name,
            school_year  = excluded.school_year,
            vision       = excluded.vision,
            mission      = excluded.mission,
            photo        = excluded.photo,
            updated_at   = CURRENT_TIMESTAMP`,
        [DEFAULT_PROFILE.class_name, DEFAULT_PROFILE.description,
         DEFAULT_PROFILE.teacher_name, DEFAULT_PROFILE.school_year,
         DEFAULT_PROFILE.vision, DEFAULT_PROFILE.mission, DEFAULT_PROFILE.photo]
    );

    // Pastikan hanya ada satu baris profil
    db.run("DELETE FROM profiles WHERE id != 1");

    // Buat akun admin default bila belum ada admin sama sekali
    db.get("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'", (err, row) => {
        if (!err && row.cnt === 0) {
            const adminEmail = process.env.ADMIN_EMAIL || 'admin@tkj.local';
            const adminPass = process.env.ADMIN_PASSWORD || 'admin123';
            const adminName = process.env.ADMIN_NAME || 'Admin TKJ';
            db.run("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'admin')",
                [adminEmail, adminName, adminPass], (e2) => {
                    if (!e2) {
                        console.log('');
                        console.log('  [INFO] Akun admin default dibuat:');
                        console.log('         email   : ' + adminEmail);
                        console.log('         password: ' + adminPass);
                        console.log('         login   : http://localhost:' + PORT + '/admin/login');
                        console.log('         Segera ganti password dari dalam panel admin.');
                        console.log('');
                    }
                });
        }
    });
});
});

// --- Middleware ---
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

app.use(session({
    secret: 'tkj-profile-secret-2024',
    resave: false,
    saveUninitialized: false,
    cookie: { maxAge: 86400000 }
}));

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// --- Helper tampilan yang dipakai bersama semua view ---
const BULAN_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function parseTanggal(value) {
    if (!value) return null;
    // SQLite "YYYY-MM-DD HH:MM:SS" dibaca sebagai waktu lokal (bukan UTC)
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
}

app.locals.fmtDate = function (value) {
    const d = parseTanggal(value);
    if (!d) return '-';
    return String(d.getDate()).padStart(2, '0') + ' ' + BULAN_ID[d.getMonth()] + ' ' + d.getFullYear();
};

app.locals.fmtDateTime = function (value) {
    const d = parseTanggal(value);
    if (!d) return '-';
    return String(d.getDate()).padStart(2, '0') + ' ' + BULAN_ID[d.getMonth()] + ' ' + d.getFullYear()
        + ', ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
};

app.locals.timeAgo = function (value) {
    const d = parseTanggal(value);
    if (!d) return 'Belum pernah';
    const diff = Date.now() - d.getTime();
    const menit = Math.floor(diff / 60000);
    if (menit < 1) return 'Baru saja';
    if (menit < 60) return menit + ' menit lalu';
    const jam = Math.floor(menit / 60);
    if (jam < 24) return jam + ' jam lalu';
    const hari = Math.floor(jam / 24);
    if (hari === 1) return 'Kemarin';
    if (hari < 30) return hari + ' hari lalu';
    const bulan = Math.floor(hari / 30);
    if (bulan < 12) return bulan + ' bulan lalu';
    return Math.floor(bulan / 12) + ' tahun lalu';
};

// Inisial untuk avatar (maksimal 2 huruf)
app.locals.initials = function (name) {
    const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

// Warna avatar Deterministik berdasarkan nama
app.locals.avatarClass = function (name) {
    const s = String(name || '');
    let sum = 0;
    for (let i = 0; i < s.length; i++) sum += s.charCodeAt(i);
    return 'avatar-' + (sum % 5);
};

// --- Multer Storage ---
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        const dir = './uploads';
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        cb(null, dir);
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
    }
});
const upload = multer({
    storage,
    fileFilter: (req, file, cb) => {
        if (file.mimetype.startsWith('image/')) cb(null, true);
        else cb(new Error('Hanya file gambar yang diperbolehkan!'), false);
    },
    limits: { fileSize: 5 * 1024 * 1024 }
});

// --- Auth Middleware ---
function requireLogin(req, res, next) {
    if (!req.session.userId) {
        return res.redirect('/login?msg=Silakan login terlebih dahulu&r=' + encodeURIComponent(req.originalUrl));
    }
    next();
}

// Ganti nama tampilan sendiri (bisa diakses semua pengguna login)
app.post('/account/name', requireLogin, (req, res) => {
    const newName = String(req.body.display_name || '').trim();
    if (!newName) {
        return res.redirect('/dashboard?err=Nama tampilan tidak boleh kosong.');
    }
    db.run("UPDATE users SET display_name = ? WHERE id = ?",
        [newName, req.session.userId], (err) => {
            if (err) return res.redirect('/dashboard?err=Gagal menyimpan nama.');
            req.session.displayName = newName;
            res.redirect('/dashboard?msg=Nama tampilan berhasil diubah.');
        });
});

// --- Admin Middleware ---
// Hanya admin yang boleh mengakses panel /admin
function requireAdmin(req, res, next) {
    if (!req.session.userId) {
        return res.redirect('/admin/login?msg=Silakan login sebagai admin&r=' + encodeURIComponent(req.originalUrl));
    }
    // Peran dicek langsung ke database agar perubahan role langsung berlaku
    db.get("SELECT role FROM users WHERE id = ?", [req.session.userId], (err, row) => {
        if (err || !row) {
            return req.session.destroy(() => res.redirect('/admin/login?err=Sesi tidak valid, silakan login ulang'));
        }
        req.session.role = row.role;
        if (row.role !== 'admin') {
            return res.status(403).render('403', { user: currentUser(req) });
        }
        next();
    });
}

// Helper: data user yang sedang login (termasuk role)
function currentUser(req) {
    if (!req.session.userId) return null;
    return {
        id: req.session.userId,
        email: req.session.email || '',
        displayName: req.session.displayName || req.session.email || '',
        role: req.session.role || 'user'
    };
}

// Helper: catat aktivitas login ke tabel login_logs (dipakai panel admin)
function catatLogin(req, db, userId, email, displayName) {
    logActivity(db, {
        user_id: userId,
        email,
        display_name: displayName,
        action: 'login',
        ip: req.ip,
        user_agent: req.get('user-agent')
    });
}

// --- Routes ---

// Home page
app.get('/', (req, res) => {
    const profileQuery = 'SELECT * FROM profiles LIMIT 1';
    const albumCountQuery = 'SELECT COUNT(*) as cnt FROM albums';
    const storyCountQuery = 'SELECT COUNT(*) as cnt FROM stories';
    const recentAlbumsQuery = `SELECT a.*, u.display_name AS username FROM albums a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 6`;
    const recentStoriesQuery = `SELECT s.*, u.display_name AS username FROM stories s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 6`;

    db.get(profileQuery, (err, profile) => {
        db.get(albumCountQuery, (e1, a1) => {
            db.get(storyCountQuery, (e2, a2) => {
                db.all(recentAlbumsQuery, (e3, albums) => {
                    db.all(recentStoriesQuery, (e4, stories) => {
                        res.render('index', {
                            profile: profile || null,
                            albumCount: a1 ? a1.cnt : 0,
                            storyCount: a2 ? a2.cnt : 0,
                            recentAlbums: albums || [],
                            recentStories: stories || [],
                            user: currentUser(req)
                        });
                    });
                });
            });
        });
    });
});

// Authentication
app.get('/login', (req, res) => {
    if (req.session.userId) return res.redirect('/');
    res.render('login', { msg: req.query.msg || '' });
});

app.get('/register', (req, res) => {
    if (req.session.userId) return res.redirect('/');
    res.render('register', { msg: req.query.msg || '' });
});

app.post('/register', (req, res) => {
    const email = String(req.body.email || '').trim().toLowerCase();
    const displayName = String(req.body.display_name || '').trim();
    const { password, confirm } = req.body;

    if (!email || !password) {
        return res.render('register', { msg: 'Email dan password wajib diisi!' });
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return res.render('register', { msg: 'Format email tidak valid!' });
    }
    if (password !== confirm) {
        return res.render('register', { msg: 'Password tidak cocok!' });
    }
    if (password.length < 4) {
        return res.render('register', { msg: 'Password minimal 4 karakter!' });
    }

    // Nama tampilan default: bagian email sebelum "@"
    const finalName = displayName || email.split('@')[0];

    db.run("INSERT INTO users (email, display_name, password) VALUES (?, ?, ?)",
        [email, finalName, password], function(err) {
            if (err) return res.render('register', { msg: 'Email sudah digunakan!' });
            logActivity(db, {
                user_id: this.lastID,
                email,
                display_name: finalName,
                action: 'register',
                ip: req.ip,
                user_agent: req.get('user-agent')
            });
            res.redirect('/login?msg=Pendaftaran berhasil! Silakan login.');
        }
    );
});

app.post('/login', (req, res) => {
    const email = String(req.body.email || '').trim().toLowerCase();
    const { password } = req.body;
    const newName = String(req.body.display_name || '').trim();

    db.get("SELECT * FROM users WHERE email = ? AND password = ?",
        [email, password], (err, user) => {
        if (err || !user) {
            logActivity(db, {
                email, display_name: '', action: 'login-gagal',
                ip: req.ip, user_agent: req.get('user-agent')
            });
            return res.render('login', { msg: 'Email atau password salah!' });
        }

        // Akun yang dinonaktifkan admin tidak boleh masuk
        if (user.is_banned) {
            logActivity(db, {
                user_id: user.id, email, display_name: user.display_name,
                action: 'login-ditolak', ip: req.ip, user_agent: req.get('user-agent')
            });
            return res.render('login', { msg: 'Akun ini dinonaktifkan oleh admin. Hubungi admin kelas.' });
        }

        // Nama tampilan opsional: kalau diisi, nama pengguna langsung di-rename
        if (newName && newName !== user.display_name) {
            db.run("UPDATE users SET display_name = ?, last_login_at = CURRENT_TIMESTAMP WHERE id = ?",
                [newName, user.id], (e2) => {
                if (e2) return res.render('login', { msg: 'Gagal menyimpan nama tampilan.' });
                req.session.userId = user.id;
                req.session.email = user.email;
                req.session.displayName = newName;
                req.session.role = user.role;
                catatLogin(req, db, user.id, email, newName);
                res.redirect(req.query.r || '/');
            });
            return;
        }

        req.session.userId = user.id;
        req.session.email = user.email;
        req.session.displayName = user.display_name;
        req.session.role = user.role;
        db.run("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?", [user.id], () => {
            catatLogin(req, db, user.id, email, user.display_name);
            res.redirect(req.query.r || '/');
        });
    });
});

app.get('/logout', (req, res) => {
    req.session.destroy(() => res.redirect('/'));
});

// Profile page
app.get('/profile', (req, res) => {
    db.get("SELECT * FROM profiles LIMIT 1", (err, profile) => {
        res.render('profile', {
            profile: profile || null,
            user: currentUser(req)
        });
    });
});

// --- Album routes ---
app.get('/albums', (req, res) => {
    const user = currentUser(req);
    db.all(`SELECT a.*, u.display_name AS username FROM albums a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC`, (err, albums) => {
        res.render('albums', { albums: albums || [], user: user });
    });
});

app.get('/albums/add', requireLogin, (req, res) => {
    res.render('album-add', { user: currentUser(req) });
});

app.post('/albums/add', requireLogin, upload.single('photo'), (req, res) => {
    const { title, description } = req.body;
    if (!title) {
        return res.render('album-add', { error: 'Judul album wajib diisi!', user: currentUser(req) });
    }
    const photo = req.file ? req.file.filename : 'no-image.png';
    db.run("INSERT INTO albums (title, description, photo, user_id) VALUES (?, ?, ?, ?)",
        [title, description || '', photo, req.session.userId],
        () => res.redirect('/albums')
    );
});

app.get('/albums/delete/:id', requireLogin, (req, res) => {
    const id = req.params.id;
    db.get("SELECT * FROM albums WHERE id = ?", [id], (err, album) => {
        if (err || !album) return res.redirect('/albums');
        if (album.user_id !== req.session.userId && req.session.role !== 'admin') return res.redirect('/albums');
        if (album.photo && album.photo !== 'no-image.png') {
            const p = path.join(__dirname, 'uploads', album.photo);
            if (fs.existsSync(p)) fs.unlinkSync(p);
        }
        db.run("DELETE FROM albums WHERE id = ?", [id], () => res.redirect('/albums'));
});
});


// --- Stories routes ---
app.get('/stories', (req, res) => {
    const user = currentUser(req);
    db.all(`SELECT s.*, u.display_name AS username FROM stories s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC`, (err, stories) => {
        res.render('stories', { stories: stories || [], user: user });
    });
});

app.get('/stories/add', requireLogin, (req, res) => {
    res.render('story-add', { user: currentUser(req) });
});

app.post('/stories/add', requireLogin, (req, res) => {
    const { title, content } = req.body;
    if (!title || !content) {
        return res.render('story-add', { error: 'Judul dan konten wajib diisi!', user: currentUser(req) });
    }
    db.run("INSERT INTO stories (title, content, user_id) VALUES (?, ?, ?)",
        [title, content, req.session.userId],
        () => res.redirect('/stories')
    );
});

app.get('/stories/delete/:id', requireLogin, (req, res) => {
    const id = req.params.id;
    db.get("SELECT * FROM stories WHERE id = ?", [id], (err, story) => {
        if (err || !story) return res.redirect('/stories');
        if (story.user_id !== req.session.userId && req.session.role !== 'admin') return res.redirect('/stories');
        db.run("DELETE FROM stories WHERE id = ?", [id], () => res.redirect('/stories'));
    });
});

// --- Dashboard ---
app.get('/dashboard', requireLogin, (req, res) => {
    const userId = req.session.userId;
    db.get("SELECT COUNT(*) as cnt FROM albums WHERE user_id = ?", [userId], (e1, a1) => {
        db.get("SELECT COUNT(*) as cnt FROM stories WHERE user_id = ?", [userId], (e2, a2) => {
            db.all("SELECT * FROM albums WHERE user_id = ? ORDER BY created_at DESC", [userId], (e3, albums) => {
                db.all("SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC", [userId], (e4, stories) => {
                    res.render('dashboard', {
                        user: currentUser(req),
                        albumCount: a1 ? a1.cnt : 0,
                        storyCount: a2 ? a2.cnt : 0,
                        myAlbums: albums || [],
                        myStories: stories || [],
                        msg: req.query.msg || '',
                        err: req.query.err || ''
                    });
                });
            });
        });
    });
});

// --- Admin Routes ---
registerAdminRoutes(app, {
    db,
    upload,
    requireAdmin,
    currentUser,
    writeProfileToServerFile
});

// 404
app.use((req, res) => {
    res.status(404).render('404', { user: currentUser(req) });
});

// Start server
app.listen(PORT, () => {
    console.log('========================================');
    console.log('   TKJ Profile Web App');
    console.log('   Server berjalan di http://localhost:' + PORT);
    console.log('   Tekan Ctrl+C untuk berhenti');
    console.log('========================================');
});