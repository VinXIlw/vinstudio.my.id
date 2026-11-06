/*
  admin-test.js
  Uji end-to-end panel admin + autentikasi berbasis email.
  Jalankan: node admin-test.js   (server harus sudah jalan di port 3000)
*/
const BASE = 'http://localhost:3000';
const ADMIN = { email: 'kiritohatsuno@gmail.com', password: 'pontianak1' };

let passed = 0, failed = 0;
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
    const ok = actual === expected;
    if (ok) { passed++; console.log(`  [OK]   ${name.padEnd(40)} ${actual}`); }
    else { failed++; console.log(`  [FAIL] ${name.padEnd(40) + 'got=' + actual} (harapan ${expected})`); }
}

function checkBody(name, body, needle, expected = true) {
    const ok = body.includes(needle) === expected;
    if (ok) { passed++; console.log(`  [OK]   ${name.padEnd(40)} "${needle}"`); }
    else { failed++; console.log(`  [FAIL] ${name.padEnd(40)} "${needle}" tidak ditemukan`); }
}

// Cek pesan pada redirect (location di-URL-encode)
function checkRedirect(name, location, needle) {
    const decoded = decodeURIComponent(location || '');
    const ok = decoded.includes(needle);
    if (ok) { passed++; console.log(`  [OK]   ${name.padEnd(40)} "${needle}"`); }
    else { failed++; console.log(`  [FAIL] ${name.padEnd(40)} redirect="${decoded}" tidak memuat "${needle}"`); }
}

(async () => {
    console.log('\n============ UJI END-TO-END ADMIN (email + rename) ============\n');

    console.log('-- Halaman Login --');
    check('GET /admin/login', (await req('/admin/login')).status, 200);
    check('GET /login', (await req('/login')).status, 200);
    const loginPage = await req('/login');
    checkBody('Form login punya field email', loginPage.body, 'name="email"');
    checkBody('Form login punya field display_name', loginPage.body, 'name="display_name"');
    checkBody('Form login punya link admin', loginPage.body, '/admin/login');

    console.log('\n-- Proteksi /admin tanpa login --');
    const prot = await req('/admin');
    check('GET /admin tanpa login', prot.status, 302);
    checkBody('Arahkan ke login admin', prot.location || '', '/admin/login');

    console.log('\n-- Login Admin (email) --');
    const badLogin = await req('/admin/login', {
        method: 'POST',
        body: `email=${ADMIN.email}&password=salahpassword`
    });
    check('Password salah ditolak', badLogin.status, 200);
    checkBody('Pesan error tampil', badLogin.body, 'Email atau password salah');

    const okLogin = await req('/admin/login', {
        method: 'POST',
        body: `email=${ADMIN.email}&password=${ADMIN.password}`
    });
    check('Login admin email berhasil', okLogin.status, 302);
    check('Redirect ke /admin', okLogin.location, '/admin');

    console.log('\n-- Panel Admin --');
    const panel = await req('/admin');
    check('GET /admin', panel.status, 200);
    checkBody('Tampilkan email admin', panel.body, ADMIN.email);
    checkBody('Tampilkan form ganti password', panel.body, '/admin/password');
    checkBody('Tampilkan form edit profil', panel.body, '/admin/profile');
    // Nama tampilan admin bisa saja sudah diganti user, jadi cukup cek ter-render
    const badge = panel.body.match(/<span class="badge">Admin:\s*([^<]*)<\/span>/);
    check('Badge nama admin ter-render', !!(badge && badge[1].trim()), true);
    if (badge) console.log(`         -> nama admin saat ini: "${badge[1].trim()}"`);

    console.log('\n-- Simpan Profil Kelas --');
    // Ambil data profil asli dari panel supaya bisa dikembalikan di akhir test
    const decodeHtml = (s) => String(s)
        .replace(/&lt;/g, '<').replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"').replace(/&#39;/g, "'")
        .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&');
    const grabInput = (html, name) => {
        const m = html.match(new RegExp('name="' + name + '"[^>]*value="([^"]*)"'));
        return m ? decodeHtml(m[1]) : '';
    };
    const grabArea = (html, name) => {
        const m = html.match(new RegExp('<textarea[^>]*name="' + name + '"[^>]*>([\\s\\S]*?)</textarea>'));
        return m ? decodeHtml(m[1]) : '';
    };
    const originalProfile = {
        class_name:   grabInput(panel.body, 'class_name'),
        teacher_name: grabInput(panel.body, 'teacher_name'),
        school_year:  grabInput(panel.body, 'school_year'),
        description:  grabArea(panel.body, 'description'),
        vision:       grabArea(panel.body, 'vision'),
        mission:      grabArea(panel.body, 'mission')
    };

    const saveProfile = await req('/admin/profile', {
        method: 'POST',
        body: 'class_name=Kelas X TKJ 1&teacher_name=Bu Herlina, S.Pd.&school_year=2026/2027' +
              '&description=Deskripsi uji admin&vision=Visi uji&mission=Misi uji'
    });
    check('POST /admin/profile', saveProfile.status, 302);
    checkRedirect('Pesan sukses profil', saveProfile.location, 'Profil kelas berhasil disimpan');
    const panelAfter = await req('/admin');
    checkBody('Deskripsi tersimpan di DB', panelAfter.body, 'Deskripsi uji admin');

    console.log('\n-- Ganti Password Admin --');
    const wrongOld = await req('/admin/password', {
        method: 'POST',
        body: 'old_password=salah&new_password=baru123&confirm=baru123'
    });
    checkRedirect('Password lama salah ditolak', wrongOld.location, 'Password lama salah');

    // Uji ganti password sungguhan, lalu kembalikan ke password asli
    const changed = await req('/admin/password', {
        method: 'POST',
        body: `old_password=${ADMIN.password}&new_password=baru123&confirm=baru123`
    });
    checkRedirect('Password berhasil diganti', changed.location, 'Password berhasil diubah');
    const relogin = await req('/admin/login', {
        method: 'POST',
        body: `email=${ADMIN.email}&password=baru123`
    });
    check('Login dengan password baru', relogin.status, 302);
    await req('/admin/password', {
        method: 'POST',
        body: 'old_password=baru123&new_password=' + ADMIN.password + '&confirm=' + ADMIN.password
    });
    const relogin2 = await req('/admin/login', {
        method: 'POST',
        body: `email=${ADMIN.email}&password=${ADMIN.password}`
    });
    check('Password lama dikembalikan', relogin2.status, 302);

    console.log('\n-- Logout & proteksi ulang --');
    check('Logout', (await req('/logout')).status, 302);
    check('GET /admin setelah logout', (await req('/admin')).status, 302);

    console.log('\n-- Non-admin tidak boleh akses /admin --');
    const userEmail = `nonadmin_${Date.now()}@test.local`;
    await req('/register', {
        method: 'POST',
        body: `email=${encodeURIComponent(userEmail)}&display_name=User Biasa&password=rahasia123&confirm=rahasia123`
    });
    const userLogin = await req('/login', {
        method: 'POST',
        body: `email=${encodeURIComponent(userEmail)}&password=rahasia123`
    });
    check('Login user biasa', userLogin.status, 302);
    const userPanel = await req('/admin');
    check('GET /admin oleh non-admin', userPanel.status, 403);
    checkBody('Halaman 403 tampil', userPanel.body, 'Akses Ditolak');

    console.log('\n-- Rename Nama Saat Login --');
    const newName = 'Nama Baru Via Login';
    await req('/logout');
    await req('/login', {
        method: 'POST',
        body: `email=${encodeURIComponent(userEmail)}&password=rahasia123&display_name=${encodeURIComponent(newName)}`
    });
    const dash = await req('/dashboard');
    check('Dashboard terbuka', dash.status, 200);
    checkBody('Nama baru tampil di dashboard', dash.body, newName);

    console.log('\n-- Rename Nama via /account/name --');
    const renamed = await req('/account/name', {
        method: 'POST',
        body: `display_name=${encodeURIComponent('Nama Via Account')}`
    });
    check('POST /account/name', renamed.status, 302);
    const dash2 = await req('/dashboard');
    checkBody('Nama baru tersimpan', dash2.body, 'Nama Via Account');
    checkBody('Email tetap ditampilkan', dash2.body, userEmail);

    console.log('\n-- Ubah Role Pengguna Jadi Admin --');
    await req('/logout');
    await req('/admin/login', {
        method: 'POST',
        body: `email=${ADMIN.email}&password=${ADMIN.password}`
    });
    const listPage = await req('/admin');
    checkBody('Tombol ubah role tampil (untuk user lain)', listPage.body, '/role');
    // Cari baris tabel milik user uji saja (created_at bisa sama detiknya)
    const rowOfUser = listPage.body.split('<tr>').find(tr => tr.includes(userEmail));
    const idMatch = rowOfUser ? rowOfUser.match(/\/admin\/users\/(\d+)\/role/) : null;
    check('Ada tombol ubah role di panel', !!idMatch, true);
    let targetId = null;
    if (idMatch) {
        targetId = idMatch[1];
        const roleRes = await req(`/admin/users/${targetId}/role`, { method: 'POST', body: 'role=admin' });
        check('POST ubah role', roleRes.status, 302);
        const listAfter = await req('/admin');
        const rowAfter = listAfter.body.split('<tr>').find(tr => tr.includes(userEmail));
        checkBody('Role user uji jadi admin', rowAfter || '', 'role-admin');
    }

    console.log('\n-- Hapus Pengguna Uji --');
    if (targetId) {
        const delRes = await req(`/admin/users/${targetId}/delete`, { method: 'POST' });
        check('POST hapus pengguna', delRes.status, 302);
        checkRedirect('Pesan hapus pengguna', delRes.location, 'Pengguna berhasil dihapus');
        const listFinal = await req('/admin');
        checkBody('Pengguna uji hilang dari daftar', listFinal.body, userEmail, false);
    }

    console.log('\n-- Restore Data Profil Kelas --');
    const restoreBody = Object.keys(originalProfile)
        .map(k => encodeURIComponent(k) + '=' + encodeURIComponent(originalProfile[k]))
        .join('&');
    const restoreRes = await req('/admin/profile', { method: 'POST', body: restoreBody });
    check('Restore profil', restoreRes.status, 302);
    const panelRestored = await req('/admin');
    checkBody('Deskripsi asli kembali', panelRestored.body, originalProfile.description || 'Kelas Teknik Komputer dan Jaringan');

    console.log('\n=================== HASIL ===================');
    console.log(`  Lulus : ${passed}`);
    console.log(`  Gagal : ${failed}`);
    console.log('============================================\n');
    process.exit(failed === 0 ? 0 : 1);
})();
