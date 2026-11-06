/*
  profile-sync.js
  Menulis balik data profil kelas ke blok DEFAULT_PROFILE di server.js
  supaya file server.js dan database tidak pernah berbeda isi.

  Blok yangmanaged server.js:
      // <<< PROFIL_KELAS_START >>>
      const DEFAULT_PROFILE = { ... };
      // <<< PROFIL_KELAS_END >>>
*/
const fs = require('fs');
const path = require('path');

const SERVER_FILE = path.join(__dirname, 'server.js');
const START_MARK = '// <<< PROFIL_KELAS_START >>>';
const END_MARK = '// <<< PROFIL_KELAS_END >>>';

const ORDER = ['class_name', 'description', 'teacher_name', 'school_year', 'vision', 'mission', 'photo'];

function escapeSingleQuote(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r?\n/g, ' ');
}

function buildBlock(profile) {
    const lines = ORDER.map(key => {
        const label = key.padEnd(13);
        return `    ${label}: '${escapeSingleQuote(profile[key])}'`;
    });
    return [
        START_MARK,
        'const DEFAULT_PROFILE = {',
        lines.join(',\n'),
        '};',
        END_MARK
    ].join('\n');
}

/**
 * Tulis data profil ke blok DEFAULT_PROFILE di server.js.
 * Mengembalikan true jika berhasil, false jika blok penanda tidak ditemukan.
 */
function writeProfileToServerFile(profile) {
    try {
        if (!fs.existsSync(SERVER_FILE)) return false;

        const content = fs.readFileSync(SERVER_FILE, 'utf8');
        const start = content.indexOf(START_MARK);
        const end = content.indexOf(END_MARK);

        if (start === -1 || end === -1 || end < start) {
            console.warn('[profile-sync] Penanda PROFIL_KELAS tidak ditemukan di server.js, file tidak diubah.');
            return false;
        }

        const newContent = content.slice(0, start) + buildBlock(profile) + content.slice(end + END_MARK.length);
        fs.writeFileSync(SERVER_FILE, newContent, 'utf8');
        console.log('[profile-sync] Data profil ditulis ke server.js.');
        return true;
    } catch (err) {
        console.error('[profile-sync] Gagal menulis server.js: ' + err.message);
        return false;
    }
}

module.exports = { writeProfileToServerFile, buildBlock, START_MARK, END_MARK };
