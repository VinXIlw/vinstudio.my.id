/*
  admin-routes.js
  Rute khusus admin:
    - GET  /admin/login             -> halaman login admin
    - POST /admin/login             -> proses login admin (harus role admin)
    - GET  /admin                   -> panel admin (statistik + kelola pengguna)
    - GET  /admin/logs              -> riwayat aktivitas login & audit
    - POST /admin/profile           -> simpan perubahan profil kelas (teks + foto)
    - POST /admin/users             -> buat akun baru dari panel admin
    - POST /admin/users/:id/role    -> ubah peran pengguna (admin / user)
    - POST /admin/users/:id/rename  -> ubah nama tampilan pengguna
    - POST /admin/users/:id/password-> reset password pengguna (bcrypt)
    - POST /admin/users/:id/ban     -> nonaktifkan / aktifkan akun
    - POST /admin/users/:id/delete  -> hapus pengguna beserta kontennya
    - POST /admin/password          -> ganti password admin sendiri (bcrypt)
*/
const path = require('path');
const fs = require('fs');
const bcrypt = require('bcryptjs');
const { logActivity } = require('./db-migrate');

const PROFILE_FIELDS = ['class_name', 'description', 'teacher_name', 'school_year', 'vision', 'mission'];
const DEFAULT_PHOTO = 'tkj-logo.png';

// Urutan pengurutan tabel pengguna (whitelist agar aman dari injeksi SQL)
const SORT_OPTIONS = {
    terbaru:  'u.created_at DESC',
    terlama:  'u.created_at ASC',
    nama:     'u.display_name COLLATE NOCASE ASC',
    email:    'u.email COLLATE NOCASE ASC',
    peran:    "CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.display_name COLLATE NOCASE ASC",
    aktivitas:'CASE WHEN u.last_login_at IS NULL THEN 1 ELSE 0 END, u.last_login_at DESC',
    konten:   '(album_count + story_count) DESC, u.display_name COLLATE NOCASE ASC'
};

function verifyPassword(inputPassword, storedPassword) {
    if (!storedPassword || !inputPassword) return false;
    if (storedPassword.startsWith('$2')) {
        return bcrypt.compareSync(inputPassword, storedPassword);
    }
    return inputPassword === storedPassword;
}

function registerAdminRoutes(app, deps) {
    const { db, upload, requireAdmin, currentUser, writeProfileToServerFile } = deps;

    function back(res, key, message, tab) {
        const target = '/admin?' + key + '=' + encodeURIComponent(message) + (tab ? '&tab=' + tab : '');
        return res.redirect(target);
    }

    function removeUpload(filename) {
        if (!filename || filename === DEFAULT_PHOTO || filename === 'no-image.png') return;
        try {
            const p = path.join(__dirname, 'uploads', filename);
            if (fs.existsSync(p)) fs.unlinkSync(p);
        } catch (e) {}
    }

    function logAdmin(req, action, detail) {
        logActivity(db, {
            user_id: req.session.userId,
            email: req.session.email || '',
            display_name: req.session.displayName || '',
            action: action,
            detail: detail || '',
            ip: req.ip,
            user_agent: req.get('user-agent')
        });
    }

    function qAll(sql, params) {
        return new Promise(resolve => db.all(sql, params || [], (err, rows) => resolve(err ? [] : (rows || []))));
    }
    function qGet(sql, params) {
        return new Promise(resolve => db.get(sql, params || [], (err, row) => resolve(err ? null : (row || null))));
    }

    async function loadUsers(q, role, sort) {
        const like = '%' + q + '%';
        const orderBy = SORT_OPTIONS[sort] || SORT_OPTIONS.terbaru;
        return qAll(
            `SELECT u.id, u.email, u.display_name, u.role, u.is_banned, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM albums a WHERE a.user_id = u.id) AS album_count,
                    (SELECT COUNT(*) FROM stories s WHERE s.user_id = u.id) AS story_count
             FROM users u
             WHERE (? = '' OR u.email LIKE ? COLLATE NOCASE OR u.display_name LIKE ? COLLATE NOCASE)
               AND (? = '' OR u.role = ?)
             ORDER BY ${orderBy}`,
            [q, like, like, role, role]
        );
    }

    async function loadStats() {
        const [totals, loginToday, newWeek, banned, albumRow, storyRow, activeWeek, commentRow, likeRow] = await Promise.all([
            qGet("SELECT COUNT(*) AS total, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins FROM users"),
            qGet("SELECT COUNT(*) AS c FROM login_logs WHERE action = 'login' AND date(created_at) = date('now', 'localtime')"),
            qGet("SELECT COUNT(*) AS c FROM users WHERE created_at >= datetime('now', '-7 days')"),
            qGet("SELECT COUNT(*) AS c FROM users WHERE is_banned = 1"),
            qGet("SELECT COUNT(*) AS c FROM albums"),
            qGet("SELECT COUNT(*) AS c FROM stories"),
            qGet("SELECT COUNT(DISTINCT user_id) AS c FROM login_logs WHERE action = 'login' AND created_at >= datetime('now', '-7 days')"),
            qGet("SELECT COUNT(*) AS c FROM comments"),
            qGet("SELECT COUNT(*) AS c FROM likes")
        ]);
        return {
            totalUsers:   (totals && totals.total) || 0,
            adminUsers:   (totals && totals.admins) || 0,
            memberUsers:  Math.max(0, ((totals && totals.total) || 0) - ((totals && totals.admins) || 0)),
            bannedUsers:  (banned && banned.c) || 0,
            loginToday:   (loginToday && loginToday.c) || 0,
            newThisWeek:  (newWeek && newWeek.c) || 0,
            activeWeek:   (activeWeek && activeWeek.c) || 0,
            totalAlbums:  (albumRow && albumRow.c) || 0,
            totalStories: (storyRow && storyRow.c) || 0,
            totalComments:(commentRow && commentRow.c) || 0,
            totalLikes:   (likeRow && likeRow.c) || 0
        };
    }

    // --- Login Admin ---
    app.get('/admin/login', (req, res) => {
        if (req.session.userId && req.session.role === 'admin') return res.redirect('/admin');
        res.render('admin-login', { msg: req.query.msg || '', error: req.query.err || '' });
    });

    app.post('/admin/login', (req, res) => {
        const email = String(req.body.email || '').trim().toLowerCase();
        const { password } = req.body;
        if (!email || !password) {
            return res.render('admin-login', { msg: '', error: 'Email dan password wajib diisi.' });
        }

        db.get("SELECT * FROM users WHERE email = ?", [email], (err, user) => {
            if (err || !user || !verifyPassword(password, user.password)) {
                logActivity(db, {
                    email, display_name: '', action: 'admin-login-gagal',
                    ip: req.ip, user_agent: req.get('user-agent')
                });
                return res.render('admin-login', { msg: '', error: 'Email atau password salah.' });
            }
            if (user.role !== 'admin') {
                return res.render('admin-login', {
                    msg: '',
                    error: 'Akun ini bukan akun admin. Silakan login sebagai pengguna biasa.'
                });
            }

            // Migrasi password ke bcrypt jika masih plain text
            if (!user.password.startsWith('$2')) {
                const newHash = bcrypt.hashSync(password, 10);
                db.run("UPDATE users SET password = ? WHERE id = ?", [newHash, user.id]);
            }

            const newName = String(req.body.display_name || '').trim();
            if (newName && newName !== user.display_name) {
                db.run("UPDATE users SET display_name = ?, last_login_at = CURRENT_TIMESTAMP WHERE id = ?", [newName, user.id], (e2) => {
                    if (e2) return res.render('admin-login', { msg: '', error: 'Gagal menyimpan nama tampilan.' });
                    req.session.userId = user.id;
                    req.session.email = user.email;
                    req.session.displayName = newName;
                    req.session.role = user.role;
                    logAdmin(req, 'admin-login', 'Berhasil login sebagai admin');
                    res.redirect('/admin');
                });
                return;
            }

            req.session.userId = user.id;
            req.session.email = user.email;
            req.session.displayName = user.display_name;
            req.session.role = user.role;
            db.run("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?", [user.id], () => {
                logAdmin(req, 'admin-login', 'Berhasil login sebagai admin');
                res.redirect('/admin');
            });
        });
    });

    // --- Panel Admin ---
    app.get('/admin', requireAdmin, async (req, res) => {
        const q     = String(req.query.q || '').trim();
        const role  = ['admin', 'user'].includes(req.query.role) ? req.query.role : '';
        const sort  = String(req.query.sort || 'terbaru');

        const [profile, users, stats, logs] = await Promise.all([
            qGet("SELECT * FROM profiles WHERE id = 1"),
            loadUsers(q, role, sort),
            loadStats(),
            qAll("SELECT * FROM login_logs ORDER BY id DESC LIMIT 10")
        ]);

        res.render('admin', {
            profile: profile || null,
            users: users || [],
            stats: stats,
            logs: logs || [],
            filters: { q: q, role: role, sort: sort },
            msg: req.query.msg || '',
            err: req.query.err || '',
            user: currentUser(req)
        });
    });

    // --- Riwayat aktivitas log ---
    app.get('/admin/logs', requireAdmin, async (req, res) => {
        const action = String(req.query.action || '').trim();
        const limit  = Math.min(parseInt(req.query.limit, 10) || 50, 200);

        const where = action ? "WHERE action = ?" : '';
        const logs = await qAll(
            `SELECT * FROM login_logs ${where} ORDER BY id DESC LIMIT ?`,
            action ? [action, limit] : [limit]
        );

        res.render('admin-logs', {
            logs: logs || [],
            filters: { action: action, limit: limit },
            msg: req.query.msg || '',
            err: req.query.err || '',
            user: currentUser(req)
        });
    });

    // --- Simpan Profil Kelas ---
    app.post('/admin/profile', requireAdmin, upload.single('classphoto'), (req, res) => {
        const body = req.body || {};
        const className = (body.class_name || '').trim();
        const teacherName = (body.teacher_name || '').trim();

        if (!className || !teacherName) {
            return back(res, 'err', 'Nama kelas dan wali kelas wajib diisi.');
        }

        const values = {
            class_name:   className,
            description:  (body.description  || '').trim(),
            teacher_name: teacherName,
            school_year:  (body.school_year || '').trim(),
            vision:       (body.vision       || '').trim(),
            mission:      (body.mission      || '').trim()
        };

        db.get("SELECT photo FROM profiles WHERE id = 1", (err, current) => {
            let photo = current && current.photo ? current.photo : DEFAULT_PHOTO;

            const resetPhoto = String(body.reset_photo || '') === '1';

            if (resetPhoto) {
                removeUpload(photo);
                photo = DEFAULT_PHOTO;
                if (req.file) removeUpload(req.file.filename);
            } else if (req.file) {
                removeUpload(photo);
                photo = req.file.filename;
            }

            const fields = PROFILE_FIELDS.concat(['photo']);
            const setClauses = fields.map(k => k + ' = ?').join(', ');
            const params = fields.map(f => (f === 'photo' ? photo : values[f]));

            db.run(
                `UPDATE profiles SET ${setClauses}, updated_at = CURRENT_TIMESTAMP WHERE id = 1`,
                params,
                (err2) => {
                    if (err2) return back(res, 'err', 'Gagal menyimpan profil kelas.');
                    writeProfileToServerFile(Object.assign({}, values, { photo: photo }));
                    logAdmin(req, 'admin:simpan-profil', 'Foto: ' + photo);
                    back(res, 'msg', 'Profil kelas berhasil disimpan.', 'profil');
                }
            );
        });
    });

    // --- Buat Akun Baru dari Panel Admin ---
    app.post('/admin/users', requireAdmin, async (req, res) => {
        const body = req.body || {};
        const email = String(body.email || '').trim().toLowerCase();
        const displayName = String(body.display_name || '').trim();
        const password = String(body.password || '');
        const role = body.role === 'admin' ? 'admin' : 'user';

        if (!email || !password) {
            return back(res, 'err', 'Email dan password wajib diisi.', 'pengguna');
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return back(res, 'err', 'Format email tidak valid.', 'pengguna');
        }
        if (password.length < 4) {
            return back(res, 'err', 'Password minimal 4 karakter.', 'pengguna');
        }

        const finalName = displayName || email.split('@')[0];
        const existing = await qGet("SELECT id FROM users WHERE email = ?", [email]);
        if (existing) {
            return back(res, 'err', 'Email sudah digunakan oleh akun lain.', 'pengguna');
        }

        const hashedPassword = bcrypt.hashSync(password, 10);

        db.run(
            "INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, ?)",
            [email, finalName, hashedPassword, role],
            function(err) {
                if (err) return back(res, 'err', 'Gagal membuat akun baru.', 'pengguna');
                logAdmin(req, 'admin:buat-akun', finalName + ' <' + email + '> sebagai ' + role);
                back(res, 'msg', 'Akun ' + finalName + ' berhasil dibuat.', 'pengguna');
            }
        );
    });

    // --- Ubah Peran Pengguna ---
    app.post('/admin/users/:id/role', requireAdmin, (req, res) => {
        const id = parseInt(req.params.id, 10);
        const targetRole = req.body.role === 'admin' ? 'admin' : 'user';

        if (isNaN(id)) return back(res, 'err', 'ID pengguna tidak valid.', 'pengguna');
        if (id === req.session.userId) return back(res, 'err', 'Anda tidak bisa mengubah peran akun sendiri.', 'pengguna');

        db.run("UPDATE users SET role = ? WHERE id = ?", [targetRole, id], (err) => {
            if (err) return back(res, 'err', 'Gagal mengubah peran pengguna.', 'pengguna');
            logAdmin(req, 'admin:ubah-peran', 'user #' + id + ' -> ' + targetRole);
            back(res, 'msg', 'Peran pengguna berhasil diubah.', 'pengguna');
        });
    });

    // --- Ubah Nama Tampilan Pengguna ---
    app.post('/admin/users/:id/rename', requireAdmin, (req, res) => {
        const id = parseInt(req.params.id, 10);
        const newName = String((req.body && req.body.display_name) || '').trim();

        if (isNaN(id)) return back(res, 'err', 'ID pengguna tidak valid.', 'pengguna');
        if (!newName) return back(res, 'err', 'Nama tampilan tidak boleh kosong.', 'pengguna');

        db.run("UPDATE users SET display_name = ? WHERE id = ?", [newName, id], (err) => {
            if (err) return back(res, 'err', 'Gagal mengubah nama tampilan.', 'pengguna');
            logAdmin(req, 'admin:ubah-nama', 'user #' + id + ' -> ' + newName);
            back(res, 'msg', 'Nama tampilan berhasil diubah.', 'pengguna');
        });
    });

    // --- Reset Password Pengguna ---
    app.post('/admin/users/:id/password', requireAdmin, (req, res) => {
        const id = parseInt(req.params.id, 10);
        const newPassword = String((req.body && req.body.new_password) || '');

        if (isNaN(id)) return back(res, 'err', 'ID pengguna tidak valid.', 'pengguna');
        if (newPassword.length < 4) return back(res, 'err', 'Password baru minimal 4 karakter.', 'pengguna');

        const hashedPassword = bcrypt.hashSync(newPassword, 10);

        db.run("UPDATE users SET password = ? WHERE id = ?", [hashedPassword, id], (err) => {
            if (err) return back(res, 'err', 'Gagal me-reset password.', 'pengguna');
            logAdmin(req, 'admin:reset-password', 'user #' + id);
            back(res, 'msg', 'Password pengguna berhasil di-reset.', 'pengguna');
        });
    });

    // --- Nonaktifkan / Aktifkan Akun ---
    app.post('/admin/users/:id/ban', requireAdmin, (req, res) => {
        const id = parseInt(req.params.id, 10);
        if (isNaN(id)) return back(res, 'err', 'ID pengguna tidak valid.', 'pengguna');
        if (id === req.session.userId) return back(res, 'err', 'Anda tidak bisa menonaktifkan akun sendiri.', 'pengguna');

        db.get("SELECT is_banned FROM users WHERE id = ?", [id], (err, row) => {
            if (err || !row) return back(res, 'err', 'Pengguna tidak ditemukan.', 'pengguna');
            const nextState = row.is_banned ? 0 : 1;
            db.run("UPDATE users SET is_banned = ? WHERE id = ?", [nextState, id], (e2) => {
                if (e2) return back(res, 'err', 'Gagal mengubah status akun.', 'pengguna');
                logAdmin(req, nextState ? 'admin:nonaktifkan' : 'admin:aktifkan', 'user #' + id);
                back(res, 'msg', nextState
                    ? 'Akun berhasil dinonaktifkan.'
                    : 'Akun berhasil diaktifkan kembali.', 'pengguna');
            });
        });
    });

    // --- Hapus Pengguna (beserta album, cerita, dan komentarnya) ---
    app.post('/admin/users/:id/delete', requireAdmin, (req, res) => {
        const id = parseInt(req.params.id, 10);
        if (isNaN(id)) return back(res, 'err', 'ID pengguna tidak valid.', 'pengguna');
        if (id === req.session.userId) return back(res, 'err', 'Anda tidak bisa menghapus akun sendiri.', 'pengguna');

        db.get("SELECT display_name, email FROM users WHERE id = ?", [id], (err, row) => {
            if (err || !row) return back(res, 'err', 'Pengguna tidak ditemukan.', 'pengguna');

            db.serialize(() => {
                db.run("DELETE FROM comments WHERE user_id = ?", [id]);
                db.run("DELETE FROM likes WHERE user_id = ?", [id]);
                db.run("DELETE FROM albums WHERE user_id = ?", [id]);
                db.run("DELETE FROM stories WHERE user_id = ?", [id]);
                db.run("DELETE FROM users WHERE id = ?", [id], (e2) => {
                    if (e2) return back(res, 'err', 'Gagal menghapus pengguna.', 'pengguna');
                    logAdmin(req, 'admin:hapus-akun', row.display_name + ' <' + row.email + '>');
                    back(res, 'msg', 'Pengguna berhasil dihapus.', 'pengguna');
                });
            });
        });
    });

    // --- Ganti Password Admin Sendiri ---
    app.post('/admin/password', requireAdmin, (req, res) => {
        const { old_password, new_password, confirm } = req.body || {};

        if (!new_password || new_password.length < 4) {
            return back(res, 'err', 'Password baru minimal 4 karakter.', 'keamanan');
        }
        if (new_password !== confirm) {
            return back(res, 'err', 'Konfirmasi password tidak cocok.', 'keamanan');
        }

        db.get("SELECT password FROM users WHERE id = ?", [req.session.userId], (err, u) => {
            if (err || !u || !verifyPassword(old_password, u.password)) {
                return back(res, 'err', 'Password lama salah.', 'keamanan');
            }

            const hashedPassword = bcrypt.hashSync(new_password, 10);
            db.run("UPDATE users SET password = ? WHERE id = ?", [hashedPassword, req.session.userId], (e2) => {
                if (e2) return back(res, 'err', 'Gagal mengganti password.', 'keamanan');
                logAdmin(req, 'admin:ganti-password', 'password admin diubah');
                back(res, 'msg', 'Password berhasil diubah.', 'keamanan');
            });
        });
    });
}

module.exports = { registerAdminRoutes };
