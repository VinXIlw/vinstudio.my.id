/*
  db-migrate.js
  Migrasi skema tabel `users` dari identitas berbasis `username` menjadi
  berbasis `email` + `display_name`.

  - `email`       : identitas login (unik)
  - `display_name`: nama tampilan yang bisa diganti sendiri oleh pemiliknya
  - `last_login_at`: waktu login terakhir (dipakai panel admin)
  - `is_banned`   : akun dinonaktifkan admin (1 = dinonaktifkan)

  DisINI juga dibuat tabel `login_logs` untuk mencatat riwayat aktivitas login.

  Modul ini dipakai bersama oleh `server.js` dan `make-admin.js` supaya
  skema database selalu konsisten.
*/
const CREATE_USERS = `CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    display_name TEXT NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    is_banned INTEGER DEFAULT 0,
    last_login_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)`;

// Riwayat aktivitas login (dipakai panel admin)
const CREATE_LOGIN_LOGS = `CREATE TABLE IF NOT EXISTS login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    email TEXT,
    display_name TEXT,
    action TEXT,
    detail TEXT,
    ip TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)`;

// Kolom tambahan yang perlu ditambahkan ke tabel users lama
const EXTRA_COLUMNS = [
    { name: 'is_banned',    ddl: "ALTER TABLE users ADD COLUMN is_banned INTEGER DEFAULT 0" },
    { name: 'last_login_at', ddl: "ALTER TABLE users ADD COLUMN last_login_at DATETIME" }
];

// Kolom tambahan untuk tabel login_logs lama
const LOG_COLUMNS = [
    { name: 'detail', ddl: "ALTER TABLE login_logs ADD COLUMN detail TEXT" }
];

// Jalankan daftar statement satu per satu (berurutan, bukan paralel)
function runSeries(db, statements, done) {
    let i = 0;
    function next(err) {
        if (err) return done(err);
        if (i >= statements.length) return done(null);
        const sql = statements[i++];
        db.run(sql, function (e) {
            next(e || null);
        });
    }
    next(null);
}

/**
 * Pastikan tabel `users` memakai skema email + display_name (+ kolom admin),
 * dan tabel `login_logs` sudah ada.
 * @param {object} db  koneksi sqlite3
 * @param {function} done callback(err)
 */
function migrateUsers(db, done) {
    db.all("PRAGMA table_info(users)", (err, cols) => {
        if (err) return done(err);

        const names = (cols || []).map(c => c.name);

        // Tabel belum pernah dibuat -> cukup buat dengan skema baru
        if (names.length === 0) {
            return db.run(CREATE_USERS, (e) => {
                if (e) return done(e);
                db.run(CREATE_LOGIN_LOGS, (e2) => done(e2 || null));
            });
        }

        // Skema lama (username) -> bangun ulang tabel
        if (!names.includes('email') || !names.includes('display_name')) {
            const steps = [
                "PRAGMA foreign_keys = OFF",
                `CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT UNIQUE NOT NULL,
                    display_name TEXT NOT NULL,
                    password TEXT NOT NULL,
                    role TEXT DEFAULT 'user',
                    is_banned INTEGER DEFAULT 0,
                    last_login_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )`,
                `INSERT INTO users_new (id, email, display_name, password, role, created_at)
                 SELECT id,
                        CASE WHEN instr(username, '@') > 0
                             THEN username ELSE username || '@legacy.local' END,
                        username,
                        password,
                        COALESCE(role, 'user'),
                        created_at
                 FROM users`,
                "DROP TABLE users",
                "ALTER TABLE users_new RENAME TO users",
                "PRAGMA foreign_keys = ON"
            ];
            return runSeries(db, steps, (e) => {
                if (e) return done(e);
                db.run(CREATE_LOGIN_LOGS, (e2) => done(e2 || null));
            });
        }

        // Skema sudah baru -> pastikan kolom tambahan & tabel log ada
        const steps = EXTRA_COLUMNS
            .filter(c => !names.includes(c.name))
            .map(c => c.ddl);

        runSeries(db, steps, (e) => {
            if (e) return done(e);
            db.run(CREATE_LOGIN_LOGS, (e2) => {
                if (e2) return done(e2);
                // Pastikan tabel log punya kolom `detail` (untuk log versi lama)
                db.all("PRAGMA table_info(login_logs)", (e3, logCols) => {
                    if (e3) return done(e3);
                    const logNames = (logCols || []).map(c => c.name);
                    const logSteps = LOG_COLUMNS
                        .filter(c => !logNames.includes(c.name))
                        .map(c => c.ddl);
                    runSeries(db, logSteps, (e4) => done(e4 || null));
                });
            });
        });
    });
}

/**
 * Catat aktivitas login ke tabel `login_logs`.
 * @param {object} db koneksi sqlite3
 * @param {object} data { user_id, email, display_name, action, detail, ip, user_agent }
 */
function logActivity(db, data) {
    try {
        db.run(
            `INSERT INTO login_logs (user_id, email, display_name, action, detail, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)`,
            [
                data.user_id || null,
                data.email || '',
                data.display_name || '',
                data.action || 'login',
                data.detail || '',
                data.ip || '',
                String(data.user_agent || '').slice(0, 255)
            ],
            () => { /* pencatatan log tidak boleh menggagalkan login */ }
        );
    } catch (e) {
        console.error('[db-migrate] Gagal menulis log aktivitas:', e.message);
    }
}

module.exports = { migrateUsers, logActivity, CREATE_USERS, CREATE_LOGIN_LOGS };
