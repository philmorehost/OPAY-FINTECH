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

    // Security Compliance Enforcement
    $ls = $settings['loginSecuritySettings'] ?? [];
    $configured = is_array($currentUser['configuredSecurityMethods']) ? $currentUser['configuredSecurityMethods'] : json_decode($currentUser['configuredSecurityMethods'] ?? '[]', true);
    $missingForced = [];

    if (!empty($ls['biometric']['forced']) && !in_array('biometric', $configured)) $missingForced[] = 'Biometric Login';
    if (!empty($ls['pin']['forced']) && !in_array('pin', $configured)) $missingForced[] = 'Security PIN';
    if (!empty($ls['email']['forced']) && !in_array('email', $configured)) $missingForced[] = 'Email Auth';
    if (!empty($ls['google2fa']['forced']) && !in_array('google2fa', $configured)) $missingForced[] = 'Google 2FA';

    if (!empty($missingForced) && $currentUser['role'] !== 'admin') {
        $currentFile = basename($_SERVER['PHP_SELF']);
        $allowedFiles = ['dashboard.php', 'profile.php', 'login-settings.php', 'logout.php'];
        if (!in_array($currentFile, $allowedFiles)) {
            header('Location: /dashboard?error=security_compliance');
            exit;
        }
        define('SECURITY_COMPLIANCE_ERROR', "Action Required: Please configure the following security methods to unlock all services: " . implode(', ', $missingForced));
    }
}
