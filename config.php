<?php
/**
 * XI TKJ 1 - Konfigurasi Utama & Koneksi Database
 * Mendukung MySQL (cPanel) dengan auto-fallback SQLite jika MySQL belum di-setup.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// KONFIGURASI DATABASE MYSQL
// Ubah nilai di bawah sesuai database yang kamu buat di cPanel:
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'vinstudio_db');  // Contoh di cPanel: xhgacmku_vinstudio
define('DB_USER', 'vinstudio_user'); // Contoh di cPanel: xhgacmku_user
define('DB_PASS', '');              // Password user database MySQL
define('DB_PORT', '3306');

// Nama & Branding Aplikasi
define('APP_NAME', 'XI TKJ 1');
define('APP_TAGLINE', 'Portal Digital Teknik Komputer & Jaringan');

// ============================================================
// INISIALISASI KONEKSI PDO (MySQL dengan Fallback ke SQLite)
// ============================================================
$pdo = null;
$dbDriver = 'mysql';

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Jika MySQL belum disiapkan atau error, gunakan SQLite lokal otomatis agar website tetap berjalan
    $dbDriver = 'sqlite';
    $sqlitePath = __DIR__ . '/database.sqlite';
    try {
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } catch (PDOException $e2) {
        die("Koneksi database gagal: " . $e2->getMessage());
    }
}

// Inisialisasi tabel otomatis jika belum ada
initDatabase($pdo, $dbDriver);

// Perbarui aktivitas terakhir pengguna yang sedang login (maksimal 1x per 60 detik)
if (isset($_SESSION['user_id'])) {
    $now = time();
    if (!isset($_SESSION['last_ping']) || ($now - $_SESSION['last_ping']) > 60) {
        $_SESSION['last_ping'] = $now;
        try {
            $stmtPing = $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtPing->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {}
    }
}

function initDatabase($pdo, $driver) {
    $isSqlite = ($driver === 'sqlite');
    $autoInc = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $timestamp = $isSqlite ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profiles (
            id {$autoInc},
            class_name VARCHAR(150),
            description TEXT,
            teacher_name VARCHAR(150),
            school_year VARCHAR(50),
            photo VARCHAR(255),
            vision TEXT,
            mission TEXT,
            updated_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id {$autoInc},
            email VARCHAR(191) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            avatar VARCHAR(255) NULL,
            bio TEXT NULL,
            is_banned TINYINT DEFAULT 0,
            last_login_at DATETIME NULL,
            created_at {$timestamp}
        )
    ");

    // Auto-migration untuk kolom avatar dan bio di tabel users (jika database sudah dibuat sebelumnya)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL");
    } catch (Exception $e) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS albums (
            id {$autoInc},
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            photo VARCHAR(255),
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stories (
            id {$autoInc},
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id {$autoInc},
            user_id INT NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            id {$autoInc},
            user_id INT NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id INT NOT NULL,
            created_at {$timestamp},
            UNIQUE (user_id, target_type, target_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_logs (
            id {$autoInc},
            user_id INT NULL,
            email VARCHAR(191),
            display_name VARCHAR(100),
            action VARCHAR(50),
            detail TEXT,
            ip VARCHAR(45),
            user_agent VARCHAR(255),
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id {$autoInc},
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            reply_to_id INT NULL,
            created_at {$timestamp}
        )
    ");

    // Pastikan baris profile id=1 selalu ada
    $checkProf = $pdo->query("SELECT id FROM profiles WHERE id = 1")->fetch();
    if (!$checkProf) {
        $stmt = $pdo->prepare("
            INSERT INTO profiles (id, class_name, description, teacher_name, school_year, photo, vision, mission)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Kelas XI TKJ 1',
            'Kelas Teknik Komputer dan Jaringan',
            'Bu Herlina, S.Pd.',
            '2026/2027',
            'tkj-logo.png',
            'Menjadi kelas unggulan dalam bidang TI dan jaringan',
            'Menyelenggara pembelajaran inovatif dan praktis'
        ]);
    }

    // Pastikan akun admin default tersedia jika belum ada admin sama sekali
    $checkAdmin = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'")->fetch();
    if ($checkAdmin && $checkAdmin['cnt'] == 0) {
        $adminEmail = 'kiritohatsuno@gmail.com';
        $adminName = 'Kelvin';
        $adminPass = password_hash('pontianak1', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$adminEmail, $adminName, $adminPass]);
    }

    // Hapus pesan sambutan otomatis bawaan lama agar tidak muncul kembali setelah dihapus
    try {
        $pdo->exec("DELETE FROM chat_messages WHERE message LIKE 'Halo semuanya! Selamat datang di Global Live Chat%'");
    } catch (Exception $e) {}
}

// ============================================================
// HELPER FUNCTIONS (Waktu, Tampilan, Autentikasi)
// ============================================================

function currentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;

    // Pastikan avatar dan bio tersinkronisasi di sesi pengguna
    if (!isset($_SESSION['avatar']) || !isset($_SESSION['bio'])) {
        try {
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT display_name, role, avatar, bio FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if ($row = $stmt->fetch()) {
                    $_SESSION['display_name'] = $row['display_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['avatar'] = $row['avatar'];
                    $_SESSION['bio'] = $row['bio'];
                }
            }
        } catch (Exception $e) {}
    }

    return [
        'id'          => (int)$_SESSION['user_id'],
        'email'       => $_SESSION['email'] ?? '',
        'displayName' => $_SESSION['display_name'] ?? ($_SESSION['email'] ?? 'Pengguna'),
        'role'        => $_SESSION['role'] ?? 'user',
        'avatar'      => $_SESSION['avatar'] ?? null,
        'bio'         => $_SESSION['bio'] ?? ''
    ];
}

function renderAvatar($name, $avatar = null, $size = '36px', $extraClass = '', $extraStyle = '', $id = '') {
    $initials = initials($name);
    $cls = avatarClass($name);
    $safeName = htmlspecialchars($name ?? 'Pengguna');
    $idAttr = !empty($id) ? ' id="' . htmlspecialchars($id) . '"' : '';

    if (!empty($avatar) && file_exists(__DIR__ . '/uploads/' . $avatar)) {
        $safeAvatar = htmlspecialchars($avatar);
        return '<img src="uploads/' . $safeAvatar . '" alt="' . $safeName . '" class="user-avatar-img ' . $extraClass . '" style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;flex-shrink:0;' . $extraStyle . '"' . $idAttr . ' onerror="this.onerror=null;this.src=\'img/placeholder.svg\';">';
    }
    return '<span class="user-avatar ' . $cls . ' ' . $extraClass . '" style="width:' . $size . ';height:' . $size . ';font-size:calc(' . $size . ' * 0.4);flex-shrink:0;' . $extraStyle . '"' . $idAttr . '>' . $initials . '</span>';
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: login.php?msg=" . urlencode("Silakan masuk terlebih dahulu.") . "&r=" . urlencode($uri));
        exit;
    }
}

function requireAdmin($pdo) {
    if (!isset($_SESSION['user_id'])) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: login.php?msg=" . urlencode("Silakan masuk terlebih dahulu.") . "&r=" . urlencode($uri));
        exit;
    }

    // Cek ulang peran ke database
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();

    if ($role !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
    $_SESSION['role'] = 'admin';
}

function logActivity($pdo, $userId, $email, $displayName, $action, $detail = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, email, display_name, action, detail, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId ?: null, $email, $displayName, $action, $detail, $ip, $ua]);
    } catch (Exception $e) {}
}

function timeAgo($datetime) {
    if (!$datetime) return 'Belum pernah';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $diff = time() - $time;

    if ($diff < 60) return 'Baru saja';
    $min = floor($diff / 60);
    if ($min < 60) return $min . ' menit lalu';
    $hour = floor($min / 60);
    if ($hour < 24) return $hour . ' jam lalu';
    $day = floor($hour / 24);
    if ($day === 1) return 'Kemarin';
    if ($day < 30) return $day . ' hari lalu';
    $mon = floor($day / 30);
    if ($mon < 12) return $mon . ' bulan lalu';
    return floor($mon / 12) . ' tahun lalu';
}

function fmtDate($datetime) {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $time) . ' ' . $months[date('n', $time) - 1] . ' ' . date('Y', $time);
}

function fmtDateTime($datetime) {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $time) . ' ' . $months[date('n', $time) - 1] . ' ' . date('Y', $time) . ', ' . date('H:i', $time);
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?: '?'));
    if (empty($parts) || empty($parts[0])) return '?';
    if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 2));
    return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
}

function avatarClass($name) {
    $sum = 0;
    $len = strlen($name ?? '');
    for ($i = 0; $i < $len; $i++) {
        $sum += ord($name[$i]);
    }
    return 'avatar-' . ($sum % 5);
}

function readingTime($text) {
    $words = str_word_count(strip_tags((string)$text));
    $mins = max(1, (int)ceil($words / 180));
    return $mins . ' mnt baca';
}

/**
 * Menyimpan berkas gambar unggahan secara aman dan andal
 * Dilengkapi validasi ekstensi, ukuran, MIME-type, dan fallback writing multi-tier
 */
function saveUploadedImage($file, $prefix = 'photo') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi upload_max_filesize di php.ini hosting.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan ulangi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary PHP di server hilang.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server (Izin direktori atau kuota disk penuh).',
            UPLOAD_ERR_EXTENSION  => 'Ekstensi PHP memblokir unggahan file ini.',
        ];
        $msg = $phpErrors[$file['error']] ?? 'Terjadi kesalahan saat mengunggah file (Kode: ' . ($file['error'] ?? 'unknown') . ').';
        return ['ok' => false, 'error' => $msg];
    }

    $maxSize = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxSize) {
        return ['ok' => false, 'error' => 'Ukuran file foto maksimal adalah 5MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) {
        return ['ok' => false, 'error' => 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'];
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        return ['ok' => false, 'error' => 'Berkas yang dipilih bukan gambar yang valid.'];
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    @chmod($uploadDir, 0777);

    $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $prefix);
    $filename = $safePrefix . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
    $dest = $uploadDir . '/' . $filename;

    $saved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        if (@move_uploaded_file($file['tmp_name'], $dest)) {
            $saved = true;
        } elseif (@copy($file['tmp_name'], $dest)) {
            $saved = true;
            @unlink($file['tmp_name']);
        } elseif (($content = @file_get_contents($file['tmp_name'])) !== false) {
            if (@file_put_contents($dest, $content) !== false) {
                $saved = true;
                @unlink($file['tmp_name']);
            }
        }
    }

    if (!$saved) {
        $lastErr = error_get_last();
        $detail = '';
        if (!is_writable($uploadDir)) {
            $detail = ' — Folder uploads/ di server tidak memiliki izin tulis (write permission). Periksa izin folder (chmod 755/777) di File Manager hosting.';
        } elseif (!empty($lastErr['message'])) {
            $detail = ' (' . $lastErr['message'] . ')';
        }
        return ['ok' => false, 'error' => 'Gagal menyimpan foto ke server' . $detail];
    }

    @chmod($dest, 0664);
    return ['ok' => true, 'filename' => $filename];
}
