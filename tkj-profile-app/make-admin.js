/*
  make-admin.js
  Tool CLI untuk membuat akun admin atau menjadikan pengguna yang ada jadi admin.
  Identitas login memakai EMAIL. Nama tampilan bisa diatur lewat --name.

  Cara pakai:
    node make-admin.js --email admin@tkj.sch.id --password admin123
        -> membuat akun admin baru (atau memperbarui password bila email sudah ada)

    node make-admin.js --email siswa@tkj.sch.id --password admin123 --promote
        -> menjadikan pengguna yang sudah terdaftar menjadi admin

    node make-admin.js --email siswa@tkj.sch.id --name "Budi Santoso"
        -> mengganti nama tampilan pengguna

    node make-admin.js --list
        -> menampilkan daftar semua pengguna beserta perannya
*/
const path = require('path');
const sqlite3 = require('sqlite3').verbose();
const { migrateUsers } = require('./db-migrate');

const dbPath = path.join(__dirname, 'database.db');
const db = new sqlite3.Database(dbPath);

const args = process.argv.slice(2);

function getArg(name) {
    const i = args.indexOf(name);
    return i === -1 ? null : args[i + 1];
}

const emailArg = getArg('--email') || getArg('--username');
const password = getArg('--password');
const nameArg = getArg('--name');
const promote = args.includes('--promote');
const list = args.includes('--list');

// --- Pastikan skema tabel users sudah memakai email + display_name ---
// Memakai modul yang sama dengan server.js agar skema selalu konsisten.
migrateUsers(db, (migErr) => {
    if (migErr) {
        console.error('Gagal memigrasi database: ' + migErr.message);
        db.close();
        process.exit(1);
    }

    runCli();
});

function runCli() {
    const email = emailArg ? String(emailArg).trim().toLowerCase() : null;

    function printUsers() {
        db.all("SELECT id, email, display_name, role, created_at FROM users ORDER BY id", (err, rows) => {
            if (err) {
                console.error('Gagal membaca database: ' + err.message);
                db.close();
                process.exit(1);
            }
            if (!rows.length) {
                console.log('  (belum ada pengguna terdaftar)');
            } else {
                rows.forEach(u => {
                    console.log(`  #${String(u.id).padEnd(4)} ${(u.display_name || '-').padEnd(20)} ${(u.email || '-').padEnd(32)} ${u.role}`);
                });
            }
            console.log('====================================\n');
            db.close();
        });
    }

    if (list) {
        console.log('\n========== DAFTAR PENGGUNA ==========');
        printUsers();
        return;
    }

    if (!email) {
        console.error('\n  Opsi tidak lengkap.');
        console.error('  Cara pakai:');
        console.error('    node make-admin.js --email admin@tkj.sch.id --password admin123');
        console.error('    node make-admin.js --email siswa@tkj.sch.id --password admin123 --promote');
        console.error('    node make-admin.js --email siswa@tkj.sch.id --name "Budi Santoso"');
        console.error('    node make-admin.js --list\n');
        process.exit(1);
    }

    db.get("SELECT * FROM users WHERE email = ?", [email], (err, existing) => {
        if (err) {
            console.error('Gagal membaca database: ' + err.message);
            db.close();
            process.exit(1);
        }

        if (existing) {
            // Ganti nama tampilan saja
            if (nameArg && !password) {
                const newName = String(nameArg).trim();
                db.run("UPDATE users SET display_name = ? WHERE id = ?", [newName, existing.id], (e2) => {
                    if (e2) {
                        console.error('Gagal menyimpan nama: ' + e2.message);
                        db.close();
                        process.exit(1);
                    }
                    console.log('\n  ✔ Nama tampilan ' + email + ' diubah menjadi "' + newName + '".\n');
                    db.close();
                });
                return;
            }

            if (promote) {
                db.run("UPDATE users SET role = 'admin' WHERE id = ?", [existing.id], (e2) => {
                    if (e2) {
                        console.error('Gagal menjadi admin: ' + e2.message);
                        db.close();
                        process.exit(1);
                    }
                    console.log('\n  ✔ ' + email + ' sekarang berperan sebagai ADMIN.');
                    console.log('  Login ke panel admin: http://localhost:3000/admin/login\n');
                    db.close();
                });
                return;
            }

            // Email sudah ada -> perbarui password (dan pastikan role admin)
            db.run("UPDATE users SET password = ?, role = 'admin' WHERE id = ?",
                [password, existing.id], (e2) => {
                    if (e2) {
                        console.error('Gagal menyimpan: ' + e2.message);
                        db.close();
                        process.exit(1);
                    }
                    console.log('\n  ✔ Akun ' + email + ' diperbarui (password baru, role admin).');
                    console.log('  Login ke panel admin: http://localhost:3000/admin/login\n');
                    db.close();
                });
            return;
        }

        // Buat akun admin baru
        const finalName = (nameArg && String(nameArg).trim()) || email.split('@')[0];
        db.run("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'admin')",
            [email, finalName, password], function(e2) {
                if (e2) {
                    console.error('Gagal membuat akun admin: ' + e2.message);
                    db.close();
                    process.exit(1);
                }
                console.log('\n  ✔ Akun admin berhasil dibuat.');
                console.log('    Email    : ' + email);
                console.log('    Nama     : ' + finalName);
                console.log('    Password : ' + password);
                console.log('    Login    : http://localhost:3000/admin/login');
                console.log('  ⚠ Segera ganti password dari dalam panel admin.\n');
                db.close();
            });
    });
}
