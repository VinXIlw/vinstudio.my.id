/*
  verify-final.js
  Uji upload foto profil + restore data profil asli.
  Jalankan: node verify-final.js
*/
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost:3000';
const ADMIN = { email: 'kiritohatsuno@gmail.com', password: 'pontianak1' };

const ORIGINAL = {
    class_name:   'Kelas X TKJ 1',
    description:  'Kelas Teknik Komputer dan Jaringan',
    teacher_name: 'Bu Herlina, S.Pd.',
    school_year:  '2026/2027',
    vision:       'Menjadi kelas unggulan dalam bidang TI dan jaringan',
    mission:      'Menyelenggaraan pembelajaran inovatif dan praktis',
    photo:        'tkj-logo.png'
};

// PNG 1x1 transparan
const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

let cookie = '';

async function req(p, opts = {}) {
    const res = await fetch(BASE + p, {
        redirect: 'manual',
        headers: { ...(cookie ? { Cookie: cookie } : {}) },
        ...opts
    });
    const sc = res.headers.get('set-cookie');
    if (sc) cookie = sc.split(';')[0];
    return { status: res.status, location: res.headers.get('location'), body: await res.text() };
}

(async () => {
    console.log('\n========== UJI UPLOAD FOTO PROFIL + RESTORE ==========\n');

    await req('/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `email=${ADMIN.email}&password=${ADMIN.password}`
    });
    console.log('  Login admin: OK');

    // --- Upload foto (multipart) ---
    const fd = new FormData();
    Object.keys(ORIGINAL).forEach(k => {
        if (k !== 'photo') fd.append(k, ORIGINAL[k]);
    });
    fd.append('classphoto', new Blob([Buffer.from(PNG_BASE64, 'base64')], { type: 'image/png' }), 'foto-kelas.png');

    const up = await req('/admin/profile', { method: 'POST', body: fd });
    console.log('  Upload foto  -> HTTP ' + up.status + ' ' + (up.location || ''));

    // --- Cek foto tersimpan & file ada di uploads/ ---
    const panel = await req('/admin');
    const photoMatch = panel.body.match(/value="([^"]*classphoto[^"]*)"/);
    const uploaded = fs.readdirSync(path.join(__dirname, 'uploads')).filter(f => f.startsWith('classphoto-'));
    console.log('  Foto di DB   : ' + (uploaded.length ? uploaded[uploaded.length - 1] : '(tidak ada file baru)'));
    console.log('  Ada di uploads: ' + (uploaded.length ? 'YA' : 'TIDAK'));

    // --- Restore: simpan tanpa foto baru (foto lama dipertahankan) ---
    const fd2 = new FormData();
    Object.keys(ORIGINAL).forEach(k => {
        if (k !== 'photo') fd2.append(k, ORIGINAL[k]);
    });
    const restore = await req('/admin/profile', { method: 'POST', body: fd2 });
    console.log('  Restore data : HTTP ' + restore.status + ' ' + decodeURIComponent(restore.location || ''));

    // --- Reset foto ke logo bawaan (fitur baru) ---
    const fd3 = new FormData();
    Object.keys(ORIGINAL).forEach(k => {
        if (k !== 'photo') fd3.append(k, ORIGINAL[k]);
    });
    fd3.append('reset_photo', '1');
    const reset = await req('/admin/profile', { method: 'POST', body: fd3 });
    console.log('  Reset foto   : HTTP ' + reset.status + ' ' + decodeURIComponent(reset.location || ''));

    // --- Bersihkan file foto uji ---
    uploaded.forEach(f => {
        try { fs.unlinkSync(path.join(__dirname, 'uploads', f)); } catch (e) { /* abaikan */ }
    });
    const left = fs.readdirSync(path.join(__dirname, 'uploads')).filter(f => f.startsWith('classphoto-'));
    console.log('  Sisa file uji: ' + (left.length ? left.join(', ') : '(bersih)'));

    console.log('\n  Blok DEFAULT_PROFILE di server.js:\n');
    const content = fs.readFileSync(path.join(__dirname, 'server.js'), 'utf8');
    const s = content.indexOf('// <<< PROFIL_KELAS_START >>>');
    const e = content.indexOf('// <<< PROFIL_KELAS_END >>>');
    console.log(content.slice(s, e + '// <<< PROFIL_KELAS_END >>>'.length));
})();
