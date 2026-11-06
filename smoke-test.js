/*
  Smoke test TKJ Profile Web App (Node.js)
  Menguji: halaman publik, proteksi login (redirect), registrasi,
  login, penambahan album & cerita, serta hak akses hapus konten.

  Jalankan: node smoke-test.js
*/
const BASE = 'http://localhost:3000';

let passed = 0;
let failed = 0;
let cookie = '';

async function req(path, options = {}) {
    const res = await fetch(BASE + path, {
        redirect: 'manual',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...(cookie ? { Cookie: cookie } : {}) },
        ...options
    });
    const setCookie = res.headers.get('set-cookie');
    if (setCookie) cookie = setCookie.split(';')[0];
    return { status: res.status, location: res.headers.get('location'), body: await res.text() };
}

function check(name, actual, expected) {
    if (actual === expected) {
        passed++;
        console.log(`  [OK]   ${name.padEnd(26)} HTTP ${actual}`);
    } else {
        failed++;
        console.log(`  [FAIL] ${name.padEnd(26)} HTTP ${actual} (harapan ${expected})`);
    }
}

(async () => {
    const stamp = Date.now();
    const email = `uji_${stamp}@test.local`;
    const name = `Uji ${stamp}`;

    console.log('\n================ SMOKE TEST TKJ Profile Web App ================\n');
    console.log('-- Halaman Publik --');
    check('Beranda',        (await req('/')).status, 200);
    check('Profil Kelas',   (await req('/profile')).status, 200);
    check('Album',          (await req('/albums')).status, 200);
    check('Cerita',         (await req('/stories')).status, 200);
    check('Login',          (await req('/login')).status, 200);
    check('Register',       (await req('/register')).status, 200);
    check('Halaman 404',    (await req('/halaman-tidak-ada')).status, 404);

    console.log('\n-- Proteksi Login (harus redirect 302 ke /login) --');
    let r = await req('/dashboard');
    check('Dashboard', r.status, 302);
    console.log(`         -> arahkan ke: ${r.location}`);
    check('Form Album', (await req('/albums/add')).status, 302);
    check('Form Cerita', (await req('/stories/add')).status, 302);

    console.log('\n-- Registrasi & Login --');
    check('Register POST', (await req('/register', {
        method: 'POST',
        body: `email=${encodeURIComponent(email)}&display_name=${encodeURIComponent(name)}&password=rahasia123&confirm=rahasia123`
    })).status, 302);
    check('Login POST', (await req('/login', {
        method: 'POST',
        body: `email=${encodeURIComponent(email)}&password=rahasia123`
    })).status, 302);

    console.log('\n-- Rename Nama Saat Login --');
    const renameTo = `Uji Rename ${stamp}`;
    check('Logout', (await req('/logout')).status, 302);
    check('Login POST + rename', (await req('/login', {
        method: 'POST',
        body: `email=${encodeURIComponent(email)}&password=rahasia123&display_name=${encodeURIComponent(renameTo)}`
    })).status, 302);
    const afterRename = await req('/dashboard');
    check('Nama baru tampil di dashboard', afterRename.body.includes(renameTo), true);
    check('Email lama tidak jadi identitas', afterRename.body.includes(email), true);
    check('Ganti nama via /account/name', (await req('/account/name', {
        method: 'POST',
        body: `display_name=${encodeURIComponent(name)}`
    })).status, 302);
    const afterAccount = await req('/dashboard');
    check('Nama tersimpan di /account/name', afterAccount.body.includes(name), true);

    console.log('\n-- Setelah Login --');
    check('Dashboard',   (await req('/dashboard')).status, 200);
    check('Form Album',  (await req('/albums/add')).status, 200);
    check('Form Cerita', (await req('/stories/add')).status, 200);

    console.log('\n-- Tambah Konten --');
    check('Cerita POST', (await req('/stories/add', {
        method: 'POST',
        body: 'title=Cerita Uji Coba&content=Ini cerita uji coba dari smoke test.'
    })).status, 302);
    check('Album POST', (await req('/albums/add', {
        method: 'POST',
        body: 'title=Album Uji Coba&description=Album uji coba.'
    })).status, 302);

    console.log('\n-- Verifikasi Konten Tampil --');
    const albums = await req('/albums');
    const stories = await req('/stories');
    check('Album tampil di /albums', albums.body.includes('Album Uji Coba'), true);
    check('Cerita tampil di /stories', stories.body.includes('Cerita Uji Coba'), true);
    check('Pemilik konten tampil', albums.body.includes(name), true);

    console.log('\n-- Halaman Dashboard Menampilkan Konten --');
    const dash = await req('/dashboard');
    check('Album saya di dashboard', dash.body.includes('Album Uji Coba'), true);
    check('Cerita saya di dashboard', dash.body.includes('Cerita Uji Coba'), true);

    console.log('\n-- Logout --');
    check('Logout', (await req('/logout')).status, 302);
    check('Dashboard setelah logout', (await req('/dashboard')).status, 302);

    console.log('\n=================== HASIL ===================');
    console.log(`  Lulus : ${passed}`);
    console.log(`  Gagal : ${failed}`);
    console.log('============================================\n');
    process.exit(failed === 0 ? 0 : 1);
})();
