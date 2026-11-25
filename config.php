<?php
/**
 * XI TKJ 1 Digital Hub — Enterprise Configuration, Database Vault & Security Engine
 * Mendukung MySQL/MariaDB (via .env / konstanta) dengan Fallback Brankas SQLite Terproteksi.
 */

// 1. Tolak akses HTTP langsung ke file konfigurasi ini
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('403 Forbidden');
}

define('APP_BOOTSTRAPPED', true);

// 2. Matikan eksposur error PHP ke layar publik (Keamanan Produksi)
error_reporting(E_ALL);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');

// 3. Kirim HTTP Security Headers dari level aplikasi (Defense-in-Depth)
if (!headers_sent()) {
    @header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
}

// 4. Muat variabel lingkungan (.env) dari luar public_html atau root lokal jika tersedia
function loadAppEnv(): array {
    $env = [];
    $candidates = [
        dirname(__DIR__) . '/.env',
        __DIR__ . '/.env',
    ];
    foreach ($candidates as $path) {
        if (@is_file($path) && @is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if ($k !== '') $env[$k] = $v;
                }
            }
            break;
        }
    }
    return $env;
}
$APP_ENV_VARS = loadAppEnv();

// 5. Konfigurasi Sesi Ketat (Anti-Session Hijacking & Anti-CSRF)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');

    session_name('XITKJ1_SESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 6. Konstanta Konfigurasi Database & Identitas Portal
define('DB_HOST', $APP_ENV_VARS['DB_HOST'] ?? 'localhost');
define('DB_NAME', $APP_ENV_VARS['DB_NAME'] ?? 'vinstudio_db');
define('DB_USER', $APP_ENV_VARS['DB_USER'] ?? 'vinstudio_user');
define('DB_PASS', $APP_ENV_VARS['DB_PASS'] ?? '');
define('DB_PORT', $APP_ENV_VARS['DB_PORT'] ?? '3306');

define('APP_NAME', $APP_ENV_VARS['APP_NAME'] ?? 'XI TKJ 1');
define('APP_TAGLINE', 'Portal Digital & Ruang Karya Teknik Komputer dan Jaringan');

define('ALBUM_CATEGORIES', [
    'Praktikum TKJ',
    'Dokumentasi Kelas',
    'Project & Jaringan',
    'Kegiatan Sekolah',
    'Karya Kreatif',
]);

define('STORY_CATEGORIES', [
    'Tutorial & Jaringan',
    'Catatan Praktikum',
    'Pengalaman & Cerita',
    'Riset Teknologi',
    'Opini & Ide',
]);

define('MEMBER_SPECIALTIES', [
    'Network Engineering',
    'System Admin & Linux',
    'Cyber Security',
    'Web & Cloud Dev',
    'Hardware & IoT',
    'Creative & Media',
    'Anggota XI TKJ 1',
]);

// ============================================================================
// 7. RESOLUSI BRANKAS DATABASE AMAN (Anti-Eksposur Database)
// ============================================================================
function ensureVaultDirectoryProtection(string $dir): void {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $idx = $dir . '/index.php';
    if (!is_file($idx)) {
        @file_put_contents($idx, "<?php\nhttp_response_code(403);\nexit('403 Forbidden');\n");
    }
}

function resolveSecureSqlitePath(string &$vaultModeOut): string {
    $webrootName = strtolower(basename(__DIR__));
    $parentDir   = dirname(__DIR__);
    $vaultDir    = null;

    // Prioritas 1: Simpan di LUAR webroot (misal /home/user/.xitkj1_vault saat di public_html cPanel)
    if (in_array($webrootName, ['public_html', 'www', 'htdocs', 'httpdocs'], true) && @is_writable($parentDir)) {
        $outsideDir = $parentDir . '/.xitkj1_vault';
        if (!is_dir($outsideDir)) {
            @mkdir($outsideDir, 0700, true);
        }
        if (is_dir($outsideDir) && is_writable($outsideDir)) {
            ensureVaultDirectoryProtection($outsideDir);
            $vaultDir = $outsideDir;
            $vaultModeOut = 'outside-webroot';
        }
    }

    // Prioritas 2: Brankas terkunci storage/ dengan nama berkas acak kriptografis 256-bit
    if ($vaultDir === null) {
        $storageDir = __DIR__ . '/storage';
        ensureVaultDirectoryProtection($storageDir);
        $vaultDir = $storageDir;
        $vaultModeOut = 'locked-storage-vault';
    }

    $keyFile = $vaultDir . '/.vault_key.php';
    $dbFileName = null;

    if (is_file($keyFile)) {
        $loaded = @include $keyFile;
        if (is_string($loaded) && preg_match('/^vault_[a-f0-9]{48,64}\.sqlite$/', $loaded)) {
            $dbFileName = $loaded;
        }
    }

    if ($dbFileName === null) {
        // Cek apakah sudah ada file vault_*.sqlite di dalam folder brankas
        $existingVaults = @glob($vaultDir . '/vault_*.sqlite');
        if (!empty($existingVaults) && is_file($existingVaults[0])) {
            $dbFileName = basename($existingVaults[0]);
        } else {
            try {
                $randHex = bin2hex(random_bytes(28));
            } catch (Exception $e) {
                $randHex = hash('sha256', uniqid((string)mt_rand(), true) . __DIR__);
            }
            $dbFileName = 'vault_' . $randHex . '.sqlite';
        }
        $phpGuard = "<?php\nif (!defined('APP_BOOTSTRAPPED')) { http_response_code(403); exit; }\nreturn " . var_export($dbFileName, true) . ";\n";
        @file_put_contents($keyFile, $phpGuard);
        @chmod($keyFile, 0640);
    }

    $targetSqlite = $vaultDir . '/' . $dbFileName;

    // Auto-Migrasi dari lokasi lama (storage/ jika pindah ke outside-webroot, atau root database.sqlite)
    $legacyCandidates = [
        __DIR__ . '/storage',
        __DIR__ . '/database.sqlite',
    ];

    if (!is_file($targetSqlite) || filesize($targetSqlite) === 0) {
        // Cek jika ada vault di storage/ lokal sebelum pindah ke outside-webroot
        if ($vaultModeOut === 'outside-webroot' && is_dir(__DIR__ . '/storage')) {
            $oldStorageFiles = @glob(__DIR__ . '/storage/vault_*.sqlite');
            if (!empty($oldStorageFiles) && is_file($oldStorageFiles[0]) && filesize($oldStorageFiles[0]) > 0) {
                if (@copy($oldStorageFiles[0], $targetSqlite)) {
                    @unlink($oldStorageFiles[0]);
                }
            }
        }
        // Migrasi dari root database.sqlite lama lalu HAPUS dari root agar tidak pernah terekspos!
        $rootLegacy = __DIR__ . '/database.sqlite';
        if ((!is_file($targetSqlite) || filesize($targetSqlite) === 0) && is_file($rootLegacy) && filesize($rootLegacy) > 0) {
            if (@copy($rootLegacy, $targetSqlite) && is_file($targetSqlite) && filesize($targetSqlite) > 0) {
                @unlink($rootLegacy);
            }
        }
    }

    // Bersihkan paksa file database/dump rawan eksposur jika masih tersisa di webroot publik
    foreach (['database.sqlite', 'database.db', 'database.sql'] as $exposedFile) {
        $exposedPath = __DIR__ . '/' . $exposedFile;
        if (is_file($exposedPath)) {
            if ($exposedFile !== 'database.sqlite' || (is_file($targetSqlite) && filesize($targetSqlite) > 0)) {
                @unlink($exposedPath);
            }
        }
    }

    if (is_file($targetSqlite)) {
        @chmod($targetSqlite, 0640);
    }

    return $targetSqlite;
}

// ============================================================================
// 8. KONEKSI PDO (MySQL Eksplisit -> Secure SQLite Vault Fallback)
// ============================================================================
$pdo = null;
$dbDriver = 'sqlite';
$dbVaultMode = 'locked-storage-vault';
$hasExplicitMysql = !empty($APP_ENV_VARS['DB_NAME'])
    && !empty($APP_ENV_VARS['DB_USER'])
    && (($APP_ENV_VARS['DB_DRIVER'] ?? 'mysql') !== 'sqlite');

if ($hasExplicitMysql) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_TIMEOUT            => 2,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $dbDriver    = 'mysql';
        $dbVaultMode = 'mysql-server';
    } catch (PDOException $e) {
        $pdo = null;
        $dbDriver = 'sqlite';
    }
}

if ($pdo === null) {
    $sqliteVaultPath = resolveSecureSqlitePath($dbVaultMode);
    try {
        $pdo = new PDO("sqlite:" . $sqliteVaultPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA journal_mode = WAL;");
    } catch (PDOException $e2) {
        error_log('[XI TKJ 1 DB Error] ' . $e2->getMessage());
        http_response_code(503);
        exit('Layanan database sedang dalam pemeliharaan keamanan. Silakan muat ulang sesaat lagi.');
    }
}

// Pastikan proteksi folder uploads selalu aktif
ensureUploadDirectoryProtection();

// Inisialisasi & migrasi skema database otomatis
initDatabase($pdo, $dbDriver);

// Sinkronisasi sesi aktif & deteksi akun diblokir secara real-time
if (isset($_SESSION['user_id'])) {
    $now = time();
    if (!isset($_SESSION['last_sync']) || ($now - $_SESSION['last_sync']) > 30) {
        $_SESSION['last_sync'] = $now;
        try {
            $stmtSync = $pdo->prepare("SELECT display_name, role, avatar, bio, specialty, is_banned FROM users WHERE id = ?");
            $stmtSync->execute([$_SESSION['user_id']]);
            $uRow = $stmtSync->fetch();
            if (!$uRow || (int)$uRow['is_banned'] === 1) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();
                session_start();

                $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
                $isApiReq   = in_array($scriptName, ['chat-api.php', 'like.php'], true)
                    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
                if (!$isApiReq) {
                    header("Location: login.php?err=" . urlencode("Sesi berakhir atau akun dinonaktifkan."));
                    exit;
                }
            } else {
                $_SESSION['display_name'] = $uRow['display_name'];
                $_SESSION['role']         = $uRow['role'];
                $_SESSION['avatar']       = $uRow['avatar'];
                $_SESSION['bio']          = $uRow['bio'] ?? '';
                $_SESSION['specialty']    = $uRow['specialty'] ?? '';

                $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$_SESSION['user_id']]);
            }
        } catch (Exception $e) {}
    }
}

function ensureUploadDirectoryProtection(): void {
    $upDir = __DIR__ . '/uploads';
    if (!is_dir($upDir)) {
        @mkdir($upDir, 0755, true);
    }
    $idx = $upDir . '/index.php';
    if (!is_file($idx)) {
        @file_put_contents($idx, "<?php\nhttp_response_code(403);\nexit('403 Forbidden');\n");
    }
}

// ============================================================================
// 9. SKEMA DATABASE & AUTO-MIGRATION
// ============================================================================
function initDatabase(PDO $pdo, string $driver): void {
    $isSqlite  = ($driver === 'sqlite');
    $autoInc   = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $timestamp = 'DATETIME DEFAULT CURRENT_TIMESTAMP';

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
            schedule_note TEXT NULL,
            instagram_url VARCHAR(255) NULL,
            github_url VARCHAR(255) NULL,
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
            specialty VARCHAR(80) NULL,
            is_banned TINYINT DEFAULT 0,
            last_login_at DATETIME NULL,
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id {$autoInc},
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            priority VARCHAR(20) DEFAULT 'info',
            is_pinned TINYINT DEFAULT 0,
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS albums (
            id {$autoInc},
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            category VARCHAR(60) DEFAULT 'Dokumentasi Kelas',
            photo VARCHAR(255),
            views INT DEFAULT 0,
            created_at {$timestamp}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stories (
            id {$autoInc},
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(60) DEFAULT 'Catatan Praktikum',
            views INT DEFAULT 0,
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id {$autoInc},
            action_key VARCHAR(120) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            hit_time INT NOT NULL
        )
    ");

    // Auto-migrations untuk kolom baru pada database yang sudah ada sebelumnya
    $migrations = [
        "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN bio TEXT NULL",
        "ALTER TABLE users ADD COLUMN specialty VARCHAR(80) NULL",
        "ALTER TABLE profiles ADD COLUMN schedule_note TEXT NULL",
        "ALTER TABLE profiles ADD COLUMN instagram_url VARCHAR(255) NULL",
        "ALTER TABLE profiles ADD COLUMN github_url VARCHAR(255) NULL",
        "ALTER TABLE albums ADD COLUMN category VARCHAR(60) DEFAULT 'Dokumentasi Kelas'",
        "ALTER TABLE albums ADD COLUMN views INT DEFAULT 0",
        "ALTER TABLE stories ADD COLUMN category VARCHAR(60) DEFAULT 'Catatan Praktikum'",
        "ALTER TABLE stories ADD COLUMN views INT DEFAULT 0",
    ];
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {}
    }

    // Pastikan profil default id=1 selalu ada
    $checkProf = $pdo->query("SELECT id FROM profiles WHERE id = 1")->fetch();
    if (!$checkProf) {
        $stmt = $pdo->prepare("
            INSERT INTO profiles (id, class_name, description, teacher_name, school_year, photo, vision, mission, schedule_note)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Kelas XI TKJ 1',
            'Komunitas akademik & ruang inovasi digital siswa Teknik Komputer dan Jaringan — berfokus pada infrastruktur jaringan, administrasi server, keamanan siber, dan pengembangan perangkat lunak.',
            'Bu Herlina, S.Pd.',
            '2026/2027',
            'tkj-logo.png',
            'Menjadi komunitas pembelajar TI yang unggul, berintegritas, adaptif terhadap teknologi masa depan, serta aktif berkarya secara kolaboratif.',
            "1. Mengembangkan kompetensi praktis di bidang jaringan komputer, cloud, dan keamanan sistem.\n2. Mendokumentasikan portofolio proyek, riset praktikum, dan literasi teknologi secara terbuka.\n3. Membangun budaya diskusi yang suportif, kreatif, dan profesional.",
            'Praktikum Lab Jaringan & Administrasi Server setiap minggu aktif.'
        ]);
    }

    // Bootstrap akun admin awal HANYA jika tabel users benar-benar kosong (tanpa menyimpan plaintext password!)
    $userTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userTotal === 0) {
        $adminHash = '$2y$10$TI8N7rFze5Pl2agWYBJsh.iQp4hXXYidZJMYW9eW5jQ1ieMo38ChO';
        $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password, role, specialty) VALUES (?, ?, ?, 'admin', ?)");
        $stmt->execute(['kiritohatsuno@gmail.com', 'Kelvin', $adminHash, 'System Admin & Network']);
    }
}

// ============================================================================
// 10. CSRF PROTECTION, RATE LIMITER & SAFE REDIRECTS
// ============================================================================
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = hash('sha256', uniqid((string)mt_rand(), true) . session_id());
        }
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(bool $jsonResponse = false, string $redirectFallback = 'index.php'): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $overflowMsg = 'Ukuran berkas terlalu besar (melebihi batas server). Gunakan gambar maksimal 5 MB.';
        if ($jsonResponse) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $overflowMsg]);
            exit;
        }
        $back = sanitizeReturnUrl($redirectFallback, 'index.php');
        $sep  = (strpos($back, '?') !== false) ? '&' : '?';
        header("Location: {$back}{$sep}err=" . urlencode($overflowMsg));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken()) {
        if ($jsonResponse) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Sesi keamanan (CSRF) tidak valid atau kedaluwarsa. Muat ulang halaman.']);
            exit;
        }
        $back = sanitizeReturnUrl($redirectFallback, 'index.php');
        $sep = (strpos($back, '?') !== false) ? '&' : '?';
        header("Location: {$back}{$sep}err=" . urlencode('Token keamanan tidak valid. Silakan coba lagi.'));
        exit;
    }
}

function clientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Memeriksa apakah aksi melebihi batas percobaan dalam jendela waktu tertentu.
 * Mengembalikan true jika MASIH DIIZINKAN, false jika TERKUNCI (rate-limited).
 */
function checkRateLimit(PDO $pdo, string $actionKey, int $maxAttempts, int $windowSeconds): bool {
    $now = time();
    $ip  = clientIp();
    $cutoff = $now - $windowSeconds;

    try {
        // Bersihkan entri kedaluwarsa (> 2 jam) secara ringan
        if (mt_rand(1, 20) === 1) {
            $pdo->prepare("DELETE FROM rate_limits WHERE hit_time < ?")->execute([$now - 7200]);
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action_key = ? AND ip = ? AND hit_time >= ?");
        $stmt->execute([$actionKey, $ip, $cutoff]);
        $hits = (int)$stmt->fetchColumn();
        return $hits < $maxAttempts;
    } catch (Exception $e) {
        return true;
    }
}

function recordRateLimit(PDO $pdo, string $actionKey): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action_key, ip, hit_time) VALUES (?, ?, ?)");
        $stmt->execute([$actionKey, clientIp(), time()]);
    } catch (Exception $e) {}
}

function clearRateLimit(PDO $pdo, string $actionKey): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE action_key = ? AND ip = ?");
        $stmt->execute([$actionKey, clientIp()]);
    } catch (Exception $e) {}
}

function sanitizeReturnUrl(?string $url, string $fallback = 'index.php'): string {
    if (!is_string($url)) return $fallback;
    $url = trim($url);
    if ($url === '' || ($url[0] === '/' && isset($url[1]) && $url[1] === '/')) return $fallback;
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $url)) return $fallback;
    if (strpos($url, "\r") !== false || strpos($url, "\n") !== false || strpos($url, '\\') !== false) return $fallback;

    $parts = parse_url(ltrim($url, '/'));
    if (!is_array($parts)) return $fallback;

    $file = basename($parts['path'] ?? '');
    if ($file === '') $file = 'index.php';
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $file) || in_array($file, ['config.php', 'logout.php'], true)) {
        return $fallback;
    }

    return $file . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
}

function safeRedirect(?string $url, string $fallback = 'index.php'): void {
    header('Location: ' . sanitizeReturnUrl($url, $fallback));
    exit;
}

function verifyUserPassword(PDO $pdo, int $userId, string $plainPassword, string $storedHash): bool {
    if ($storedHash !== '' && password_verify($plainPassword, $storedHash)) {
        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            try {
                $rehash = password_hash($plainPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$rehash, $userId]);
            } catch (Exception $e) {}
        }
        return true;
    }
    if ($storedHash !== '' && strpos($storedHash, '$2') !== 0 && strpos($storedHash, '$argon') !== 0 && hash_equals($storedHash, $plainPassword)) {
        try {
            $rehash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$rehash, $userId]);
        } catch (Exception $e) {}
        return true;
    }
    return false;
}

// ============================================================================
// 11. AUTENTIKASI & OTORISASI KETAT
// ============================================================================
function currentUser(): ?array {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;

    if (!isset($_SESSION['avatar']) || !isset($_SESSION['bio']) || !isset($_SESSION['specialty'])) {
        try {
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT display_name, role, avatar, bio, specialty FROM users WHERE id = ? AND is_banned = 0");
                $stmt->execute([$_SESSION['user_id']]);
                if ($row = $stmt->fetch()) {
                    $_SESSION['display_name'] = $row['display_name'];
                    $_SESSION['role']         = $row['role'];
                    $_SESSION['avatar']       = $row['avatar'];
                    $_SESSION['bio']          = $row['bio'] ?? '';
                    $_SESSION['specialty']    = $row['specialty'] ?? '';
                } else {
                    return null;
                }
            }
        } catch (Exception $e) {}
    }

    return [
        'id'          => (int)$_SESSION['user_id'],
        'email'       => $_SESSION['email'] ?? '',
        'displayName' => $_SESSION['display_name'] ?? ($_SESSION['email'] ?? 'Anggota'),
        'role'        => $_SESSION['role'] ?? 'user',
        'avatar'      => $_SESSION['avatar'] ?? null,
        'bio'         => $_SESSION['bio'] ?? '',
        'specialty'   => $_SESSION['specialty'] ?? '',
    ];
}

function requireLogin(): void {
    if (!currentUser()) {
        $rawUri   = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $uriPath  = basename((string)parse_url($rawUri, PHP_URL_PATH)) ?: 'index.php';
        $uriQuery = (string)parse_url($rawUri, PHP_URL_QUERY);
        $req      = $uriPath . ($uriQuery !== '' ? '?' . $uriQuery : '');
        $target   = sanitizeReturnUrl($req, 'index.php');
        header("Location: login.php?msg=" . urlencode("Silakan masuk terlebih dahulu.") . "&r=" . urlencode($target));
        exit;
    }
}

function requireAdmin(PDO $pdo): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?msg=" . urlencode("Silakan masuk terlebih dahulu.") . "&r=admin.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT role, is_banned FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['is_banned'] === 1 || $row['role'] !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
    $_SESSION['role'] = 'admin';
}

function logActivity(PDO $pdo, $userId, string $email, string $displayName, string $action, string $detail = ''): void {
    try {
        $ip = clientIp();
        $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 250);
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, email, display_name, action, detail, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId ?: null, $email, $displayName, $action, $detail, $ip, $ua]);
    } catch (Exception $e) {}
}

// ============================================================================
// 12. UPLOAD GAMBAR KETAT (Validasi MIME Asli + Anti-Polyglot/RCE)
// ============================================================================
function saveUploadedImage(array $file, string $prefix = 'img'): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran berkas melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran berkas melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL    => 'Berkas hanya terunggah sebagian. Silakan ulangi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada berkas yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori sementara server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis berkas ke penyimpanan server.',
            UPLOAD_ERR_EXTENSION  => 'Unggahan diblokir oleh kebijakan keamanan ekstensi.',
        ];
        return ['ok' => false, 'error' => $phpErrors[$file['error'] ?? -1] ?? 'Gagal memproses berkas unggahan.'];
    }

    $maxSize = 5 * 1024 * 1024; // 5 MB
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return ['ok' => false, 'error' => 'Ukuran gambar maksimal adalah 5 MB.'];
    }

    // Validasi MIME asli via finfo + getimagesize (bukan sekadar ekstensi nama file!)
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $detectedMime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = (string)@finfo_file($finfo, $file['tmp_name']);
            @finfo_close($finfo);
        }
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo || empty($imgInfo['mime'])) {
        return ['ok' => false, 'error' => 'Berkas yang dipilih bukan gambar valid.'];
    }

    $realMime = $detectedMime !== '' ? $detectedMime : $imgInfo['mime'];
    if (!isset($mimeMap[$realMime]) || !isset($mimeMap[$imgInfo['mime']])) {
        return ['ok' => false, 'error' => 'Format gambar tidak diizinkan. Gunakan JPG, PNG, WEBP, atau GIF.'];
    }

    // Paksa ekstensi aman berdasarkan MIME asli (mencegah double-extension seperti shell.php.jpg)
    $safeExt = $mimeMap[$realMime];

    $uploadDir = __DIR__ . '/uploads';
    ensureUploadDirectoryProtection();
    @chmod($uploadDir, 0755);

    $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $prefix) ?: 'img';
    try {
        $randSuffix = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $randSuffix = (string)mt_rand(100000, 999999);
    }
    $filename = $safePrefix . '-' . time() . '-' . $randSuffix . '.' . $safeExt;
    $dest     = $uploadDir . '/' . $filename;

    $saved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        if (@move_uploaded_file($file['tmp_name'], $dest)) {
            $saved = true;
        } elseif (@copy($file['tmp_name'], $dest)) {
            $saved = true;
            @unlink($file['tmp_name']);
        } elseif (($raw = @file_get_contents($file['tmp_name'])) !== false && @file_put_contents($dest, $raw) !== false) {
            $saved = true;
            @unlink($file['tmp_name']);
        }
    }

    if (!$saved) {
        if (!is_writable($uploadDir)) {
            return ['ok' => false, 'error' => 'Direktori uploads/ belum memiliki izin tulis (pastikan chmod 755 di hosting).'];
        }
        return ['ok' => false, 'error' => 'Gagal menyimpan gambar ke server. Silakan coba lagi.'];
    }

    @chmod($dest, 0644);
    return ['ok' => true, 'filename' => $filename];
}

// ============================================================================
// 13. UI, AVATAR, FORMATTER & VECTOR ICON HELPERS
// ============================================================================
function renderAvatar(?string $name, ?string $avatar = null, string $size = '36px', string $extraClass = '', string $extraStyle = '', string $id = ''): string {
    $init     = initials($name);
    $cls      = avatarClass($name);
    $safeName = htmlspecialchars($name ?: 'Anggota', ENT_QUOTES, 'UTF-8');
    $idAttr   = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
    $cleanFav = $avatar ? basename($avatar) : '';

    if ($cleanFav !== '' && is_file(__DIR__ . '/uploads/' . $cleanFav)) {
        $safeAvatar = htmlspecialchars($cleanFav, ENT_QUOTES, 'UTF-8');
        return '<img src="uploads/' . $safeAvatar . '" alt="' . $safeName . '" class="user-avatar-img ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;flex-shrink:0;' . $extraStyle . '"' . $idAttr . ' loading="lazy" onerror="this.onerror=null;this.src=\'img/placeholder.svg\';">';
    }
    return '<span class="user-avatar ' . $cls . ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" style="width:' . $size . ';height:' . $size . ';font-size:calc(' . $size . ' * 0.39);flex-shrink:0;' . $extraStyle . '"' . $idAttr . '>' . $init . '</span>';
}

function timeAgo(?string $datetime): string {
    if (!$datetime) return 'Belum pernah';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $diff = max(0, time() - $time);

    if ($diff < 60) return 'Baru saja';
    $min = (int)floor($diff / 60);
    if ($min < 60) return $min . ' mnt lalu';
    $hour = (int)floor($min / 60);
    if ($hour < 24) return $hour . ' jam lalu';
    $day = (int)floor($hour / 24);
    if ($day === 1) return 'Kemarin';
    if ($day < 30) return $day . ' hari lalu';
    $mon = (int)floor($day / 30);
    if ($mon < 12) return $mon . ' bln lalu';
    return (int)floor($mon / 12) . ' thn lalu';
}

function fmtDate(?string $datetime): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $time) . ' ' . $months[(int)date('n', $time) - 1] . ' ' . date('Y', $time);
}

function fmtDateTime(?string $datetime): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $time) . ' ' . $months[(int)date('n', $time) - 1] . ' ' . date('Y', $time) . ' · ' . date('H:i', $time);
}

function initials(?string $name): string {
    $parts = preg_split('/\s+/u', trim($name ?: '?'));
    if (empty($parts) || $parts[0] === '') return '?';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
}

function avatarClass(?string $name): string {
    $sum = 0;
    $str = (string)$name;
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $sum += ord($str[$i]);
    }
    return 'avatar-' . ($sum % 5);
}

function albumPhotoUrl(?string $photo): string {
    $clean = $photo ? basename($photo) : '';
    if ($clean !== '' && $clean !== 'no-image.png' && is_file(__DIR__ . '/uploads/' . $clean)) {
        return 'uploads/' . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
    }
    return 'img/placeholder.svg';
}

function readingTime(?string $text): string {
    $trimmed = trim(strip_tags((string)$text));
    $words   = $trimmed === '' ? 0 : count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY));
    $mins    = max(1, (int)ceil($words / 185));
    return $mins . ' mnt baca';
}

function svgIcon(string $name, int $size = 18): string {
    $paths = [
        'home'        => '<path d="M3 9.5L12 3l9 6.5V20a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 20V9.5z"/><polyline points="9 21.5 9 13 15 13 15 21.5"/>',
        'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'image'       => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'book'        => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'chat'        => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'megaphone'   => '<path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'heart'       => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
        'eye'         => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'plus'        => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'        => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'       => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'search'      => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'sparkles'    => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3Z"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-left'  => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'share'       => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'user'        => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'check'       => '<polyline points="20 6 9 17 4 12"/>',
        'pin'         => '<line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>',
        'terminal'    => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'activity'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
    ];
    $inner = $paths[$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;">' . $inner . '</svg>';
}
