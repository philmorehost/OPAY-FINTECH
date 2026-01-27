<?php
$dbPath = __DIR__ . '/db.php';
$installPath = 'install.php';

// Check if we are in a subdirectory (like /admin/)
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
    $installPath = '../install.php';
}

if (!file_exists($dbPath)) {
    header("Location: $installPath");
    exit;
}

require_once $dbPath;
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api.php';

startSecureSession();

$settings = isset($pdo) ? fetchSettings($pdo) : [];
$csrf_token = generateCsrfToken();

// Maintenance Mode Enforcement
if (!empty($settings['isMaintenanceMode'])) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    $isAdminPath = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    // error_log("Current File: $currentFile");

    if (!$isAdminPath && $currentFile !== 'maintenance.php' && $currentFile !== 'login.php' && $currentFile !== 'logout.php' && $currentFile !== 'install.php') {
        include __DIR__ . '/../maintenance.php';
        exit;
    }
}

$currentUser = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser || $currentUser['isSuspended']) {
        session_destroy();
        $loginPath = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../login' : 'login';
        header("Location: $loginPath");
        exit;
    }
}
