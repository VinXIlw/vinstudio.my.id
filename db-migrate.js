/*
  db-migrate.js
  Migrasi skema database SQLite:
  - users (email, display_name, password bcrypt, role, is_banned, last_login_at, created_at)
  - login_logs (riwayat login & audit log)
  - comments (komentar untuk album & cerita)
  - likes (suka untuk album & cerita)
*/
const bcrypt = require('bcryptjs');

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

const CREATE_COMMENTS = `CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
)`;

const CREATE_LIKES = `CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, target_type, target_id),
    FOREIGN KEY(user_id) REFERENCES users(id)
)`;

const EXTRA_COLUMNS = [
    { name: 'is_banned',    ddl: "ALTER TABLE users ADD COLUMN is_banned INTEGER DEFAULT 0" },
    { name: 'last_login_at', ddl: "ALTER TABLE users ADD COLUMN last_login_at DATETIME" }
];

const LOG_COLUMNS = [
    { name: 'detail', ddl: "ALTER TABLE login_logs ADD COLUMN detail TEXT" }
];

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
 * Migrasi password polos yang belum di-hash dengan bcrypt
 */
function upgradePlainPasswords(db, callback) {
    db.all("SELECT id, password FROM users", (err, rows) => {
        if (err || !rows || rows.length === 0) return callback(null);
        const unhashed = rows.filter(r => !r.password || !r.password.startsWith('$2'));
        if (unhashed.length === 0) return callback(null);

        let count = 0;
        let doneCount = 0;
        unhashed.forEach(u => {
            const hashed = bcrypt.hashSync(u.password, 10);
            db.run("UPDATE users SET password = ? WHERE id = ?", [hashed, u.id], (updErr) => {
                doneCount++;
                if (!updErr) count++;
                if (doneCount === unhashed.length) {
                    console.log(`  [SECURITY] Berhasil mengamankan ${count} password pengguna dengan enkripsi bcrypt.`);
                    callback(null);
                }
            });
        });
    });
}

function migrateUsers(db, done) {
    db.all("PRAGMA table_info(users)", (err, cols) => {
        if (err) return done(err);

        const names = (cols || []).map(c => c.name);

        function ensureExtraTables(cb) {
            db.run(CREATE_COMMENTS, (e1) => {
                if (e1) return cb(e1);
                db.run(CREATE_LIKES, (e2) => {
                    if (e2) return cb(e2);
                    upgradePlainPasswords(db, cb);
                });
            });
        }

        // Tabel users belum pernah dibuat -> buat semua tabel
        if (names.length === 0) {
            return db.run(CREATE_USERS, (e) => {
                if (e) return done(e);
                db.run(CREATE_LOGIN_LOGS, (e2) => {
                    if (e2) return done(e2);
                    ensureExtraTables(done);
                });
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
                db.run(CREATE_LOGIN_LOGS, (e2) => {
                    if (e2) return done(e2);
                    ensureExtraTables(done);
                });
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
                db.all("PRAGMA table_info(login_logs)", (e3, logCols) => {
                    if (e3) return done(e3);
                    const logNames = (logCols || []).map(c => c.name);
                    const logSteps = LOG_COLUMNS
                        .filter(c => !logNames.includes(c.name))
                        .map(c => c.ddl);
                    runSeries(db, logSteps, (e4) => {
                        if (e4) return done(e4);
                        ensureExtraTables(done);
                    });
                });
            });
        });
    });
}

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
            () => {}
        );
    } catch (e) {
        console.error('[db-migrate] Gagal menulis log aktivitas:', e.message);
    }
}

module.exports = {
    migrateUsers,
    logActivity,
    CREATE_USERS,
    CREATE_LOGIN_LOGS,
    CREATE_COMMENTS,
    CREATE_LIKES
};
