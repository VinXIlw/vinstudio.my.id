<?php
require_once __DIR__ . '/config.php';

// Izinkan POST dengan CSRF valid atau same-origin yang terverifikasi untuk mencegah Force-Logout lintas situs
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$csrfOk = $isPost && verifyCsrfToken();
$sameOrigin = false;
if ($isPost) {
    $srcUrl  = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $srcHost = $srcUrl !== '' ? strtolower((string)parse_url($srcUrl, PHP_URL_HOST)) : '';
    $srvHost = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $sameOrigin = ($srcHost !== '' && $srvHost !== '' && hash_equals($srvHost, $srcHost));
}

if ($isPost && ($csrfOk || $sameOrigin)) {
    $u = currentUser();
    if ($u) {
        logActivity($pdo, $u['id'], $u['email'], $u['displayName'], 'logout', 'Keluar dari sesi');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

safeRedirect('index.php');
