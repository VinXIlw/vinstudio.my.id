<?php
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['action'] = 'update_profile';
    require __DIR__ . '/profile-update.php';
    exit;
}

header("Location: dashboard.php");
exit;
