<?php
/**
 * Halaman login disatukan ke login.php.
 * Akun Administrator cukup masuk melalui login.php biasa dan hanya dapat dibuat/diangkat melalui Panel Admin.
 */
require_once __DIR__ . '/config.php';

$user = currentUser();
if ($user && $user['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

header("Location: login.php");
exit;
