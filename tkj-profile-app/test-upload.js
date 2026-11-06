/*
  Uji fitur upload foto album (multer).
  Membuat gambar PNG dummy, lalu meng-upload lewat form /albums/add
  dan memastikan file tersimpan di folder uploads/ serta tampil di galeri.
*/
const fs = require('fs');
const path = require('path');
const BASE = 'http://localhost:3000';

let cookie = '';

// PNG 1x1 pixel (base64)
const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

async function req(p, options = {}) {
    const res = await fetch(BASE + p, {
        redirect: 'manual',
        headers: { ...(cookie ? { Cookie: cookie } : {}) },
        ...options
    });
    const sc = res.headers.get('set-cookie');
    if (sc) cookie = sc.split(';')[0];
    return { status: res.status, body: await res.text() };
}

(async () => {
    const stamp = Date.now();
    const email = `upload_${stamp}@test.local`;

    console.log('\n============ TEST UPLOAD FOTO ALBUM ============\n');

    await req('/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `email=${encodeURIComponent(email)}&display_name=Upload ${stamp}&password=rahasia123&confirm=rahasia123`
    });
    await req('/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `email=${encodeURIComponent(email)}&password=rahasia123`
    });

    // Bangun form-data multipart secara manual
    const boundary = '----tkjtest' + Date.now();
    const fileName = 'foto-uji.png';
    const fileBuffer = Buffer.from(PNG_BASE64, 'base64');

    const head = Buffer.from(
        `--${boundary}\r\n` +
        `Content-Disposition: form-data; name="title"\r\n\r\n` +
        `Album dengan Foto\r\n` +
        `--${boundary}\r\n` +
        `Content-Disposition: form-data; name="description"\r\n\r\n` +
        `Album uji upload foto\r\n` +
        `--${boundary}\r\n` +
        `Content-Disposition: form-data; name="photo"; filename="${fileName}"\r\n` +
        `Content-Type: image/png\r\n\r\n`
    );
    const tail = Buffer.from(`\r\n--${boundary}--\r\n`);

    const res = await fetch(BASE + '/albums/add', {
        method: 'POST',
        redirect: 'manual',
        headers: {
            'Content-Type': `multipart/form-data; boundary=${boundary}`,
            ...(cookie ? { Cookie: cookie } : {})
        },
        body: Buffer.concat([head, fileBuffer, tail])
    });

    console.log(`  Upload album dengan foto -> HTTP ${res.status}`);

    // Cek file tersimpan di uploads/
    const uploadsDir = path.join(__dirname, 'uploads');
    const files = fs.readdirSync(uploadsDir).filter(f => f.startsWith('photo-'));
    console.log(`  File tersimpan di uploads/: ${files.length} file`);
    files.forEach(f => console.log(`    - ${f} (${fs.statSync(path.join(uploadsDir, f)).size} bytes)`));

    // Cek tampil di galeri
    const albums = await req('/albums');
    const hasTitle = albums.body.includes('Album dengan Foto');
    console.log(`  Judul album tampil di galeri : ${hasTitle ? 'YA' : 'TIDAK'}`);

    const imgMatch = albums.body.match(/\/uploads\/(photo-[^"']+)/);
    if (imgMatch) {
        const imgRes = await fetch(BASE + '/uploads/' + imgMatch[1]);
        console.log(`  Gambar bisa diakses           : HTTP ${imgRes.status}`);
    } else {
        console.log('  Gambar bisa diakses           : TIDAK (src gambar tidak ditemukan)');
    }

    console.log('\n=============================================\n');
})();
