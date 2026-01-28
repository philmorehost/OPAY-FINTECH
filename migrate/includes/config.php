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
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/migrations.php';

startSecureSession();

$settings = isset($pdo) ? fetchSettings($pdo) : [];
$csrf_token = generateCsrfToken();

// Maintenance Mode Enforcement
if (!empty($settings['isMaintenanceMode'])) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    $isAdminPath = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;

    if (!$isAdminPath && $currentFile !== 'maintenance.php' && $currentFile !== 'login.php' && $currentFile !== 'logout.php' && $currentFile !== 'install.php') {
        $mPath = __DIR__ . '/../maintenance.php';
        if (file_exists($mPath)) {
            include $mPath;
            exit;
        }
    }
}

$currentUser = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Allow login for impersonation (original_admin_id bypasses suspension)
    if (!$currentUser || ($currentUser['isSuspended'] && !isset($_SESSION['original_admin_id']))) {
        session_destroy();
        $loginPath = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../login' : 'login';
        header("Location: $loginPath");
        exit;
    }

    // Login Security Compliance Enforcement
    if ($currentUser['role'] !== 'admin' && !isset($_SESSION['original_admin_id'])) {
        $missing = getMissingLoginSecurity($settings, $currentUser);
        if (!empty($missing)) {
            $allowedPaths = ['/dashboard', '/login-settings', '/logout', '/login-verify', '/maintenance', '/2fa-verify', '/kyc', '/security'];
            $currentPath = explode('?', $_SERVER['REQUEST_URI'])[0];

            $isAllowed = false;
            foreach ($allowedPaths as $ap) {
                if ($currentPath === $ap) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                header("Location: /dashboard");
                exit;
            }
        }
    }
}
