/*
  Tool admin untuk melihat & mengubah data profil kelas TKJ.
  Database SQLite (database.db), tabel: profiles

  Cara pakai:
    node edit-profile.js                                   -> tampilkan data saat ini
    node edit-profile.js --teacher "Bapak Sutrisno, S.Kom."  -> ubah wali kelas
    node edit-profile.js --class "XII TKJ 2"                -> ubah nama kelas
    node edit-profile.js --year "2025/2026"                 -> ubah tahun ajaran
    node edit-profile.js --description "..."                -> ubah deskripsi
    node edit-profile.js --vision "..."                     -> ubah visi
    node edit-profile.js --mission "..."                    -> ubah misi
    node edit-profile.js --photo foto-kelas.jpg             -> ubah nama file foto profil

  Bisa kombinasi beberapa opsi sekaligus.
*/
const path = require('path');
const sqlite3 = require('sqlite3').verbose();
const { writeProfileToServerFile } = require('./profile-sync');

const dbPath = path.join(__dirname, 'database.db');
const db = new sqlite3.Database(dbPath);

const FIELDS = {
    '--class':       'class_name',
    '--description': 'description',
    '--teacher':     'teacher_name',
    '--year':        'school_year',
    '--photo':       'photo',
    '--vision':      'vision',
    '--mission':     'mission'
};

const LABELS = {
    class_name:   'Nama Kelas',
    description: 'Deskripsi',
    teacher_name: 'Wali Kelas',
    school_year:  'Tahun Ajaran',
    photo:        'Foto Profil',
    vision:       'Visi',
    mission:      'Misi'
};

function printProfile(row) {
    console.log('');
    console.log('============ PROFIL KELAS TKJ ============');
    console.log('');
    Object.keys(LABELS).forEach(k => {
        const val = row[k] === null || row[k] === undefined ? '(kosong)' : row[k];
        console.log('  ' + LABELS[k].padEnd(14) + ': ' + val);
    });
    console.log('');
    console.log('========================================');
    console.log('');
}

// Kumpulkan opsi dari argumen baris perintah
const args = process.argv.slice(2);
const updates = {};

for (let i = 0; i < args.length; i++) {
    const key = args[i];
    if (!FIELDS[key]) {
        console.log('Opsi tidak dikenal: ' + key);
        console.log('Opsi tersedia: ' + Object.keys(FIELDS).join(', '));
        process.exit(1);
    }
    const value = args[i + 1];
    if (value === undefined) {
        console.log('Nilai untuk ' + key + ' tidak diberikan.');
        process.exit(1);
    }
    updates[FIELDS[key]] = value;
    i++;
}

db.get("SELECT * FROM profiles WHERE id = 1", (err, row) => {
    if (err) {
        console.error('Gagal membaca database: ' + err.message);
        process.exit(1);
    }

    // Tidak ada opsi -> hanya tampilkan
    if (Object.keys(updates).length === 0) {
        printProfile(row);
        db.close();
        return;
    }

    // Ada opsi -> update
    const keys = Object.keys(updates);
    const setClause = keys.map(k => k + ' = ?').join(', ');
    const values = keys.map(k => updates[k]);

    db.run("UPDATE profiles SET " + setClause + ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        values.concat([row.id]), function(err2) {
            if (err2) {
                console.error('Gagal menyimpan: ' + err2.message);
                process.exit(1);
            }
            console.log('');
            console.log('  Data profil berhasil disimpan (' + this.changes + ' baris).');
            db.get("SELECT * FROM profiles WHERE id = 1", (e3, newRow) => {
                // Samakan dengan server.js supaya perubahan tidak hilang saat restart
                if (newRow) {
                    const ok = writeProfileToServerFile(newRow);
                    console.log(ok
                        ? '  Data profil disinkronkan ke server.js.'
                        : '  Peringatan: gagal menyinkronkan ke server.js.');
                }
                printProfile(newRow);
                db.close();
            });
        }
    );
});
