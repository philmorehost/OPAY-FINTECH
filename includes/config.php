<?php
if (!file_exists(__DIR__ . '/db.php')) {
    $currentScript = $_SERVER['SCRIPT_NAME'];
    if (strpos($currentScript, '/admin/') !== false) {
        header('Location: ../install.php');
    } else {
        header('Location: install.php');
    }
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

startSecureSession();
$settings = isset($pdo) ? fetchSettings($pdo) : [];
$csrf_token = generateCsrfToken();
