/*
  Membersihkan data uji (user/album/cerita uji) dari database
  dan file foto uji di folder uploads/.
*/
const path = require('path');
const fs = require('fs');
const sqlite3 = require('sqlite3').verbose();

const dbPath = path.join(__dirname, 'database.db');
const db = new sqlite3.Database(dbPath);

db.serialize(() => {
    db.run("DELETE FROM albums WHERE title LIKE '%Uji Coba%' OR title LIKE '%dengan Foto%'", (e1) => {
        if (e1) console.error('  Gagal hapus album uji:', e1.message);
        db.run("DELETE FROM stories WHERE title LIKE '%Uji Coba%'", (e2) => {
            if (e2) console.error('  Gagal hapus cerita uji:', e2.message);
            db.run("DELETE FROM users WHERE email LIKE 'uji_%@%' OR email LIKE 'upload_%@%' OR email LIKE 'nonadmin_%@%' OR email LIKE 'siswa_test%@%' OR email LIKE '%@legacy.local'", function (e3) {
                if (e3) console.error('  Gagal hapus user uji:', e3.message);
                else console.log(`  User uji dihapus: ${this.changes}`);
                db.get("SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM albums) a, (SELECT COUNT(*) FROM stories) s", (err, row) => {
                    if (err) console.error('  Gagal menghitung sisa data:', err.message);
                    else console.log(`  Sisa data -> user: ${row.u}, album: ${row.a}, cerita: ${row.s}`);
                    db.close();
                });
            });
        });
    });
});

// Hapus file foto uji
const uploadsDir = path.join(__dirname, 'uploads');
let removed = 0;
fs.readdirSync(uploadsDir).forEach(f => {
    if (f.startsWith('photo-') || f.startsWith('classphoto-')) {
        fs.unlinkSync(path.join(uploadsDir, f));
        removed++;
    }
});
console.log(`  File foto uji dihapus: ${removed}`);
