<?php
require_once __DIR__ . '/config.php';

// Hanya izinkan POST dengan CSRF valid untuk mencegah serangan Force-Logout lintas situs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken()) {
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
