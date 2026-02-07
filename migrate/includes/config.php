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
            $allowedFiles = ['login-settings.php', 'logout.php', 'login-verify.php', 'maintenance.php', '2fa-verify.php', 'kyc.php', 'security.php', 'index.php', 'profile.php', 'dashboard.php'];
            $currentFile = basename($_SERVER['SCRIPT_NAME']);

            // Special case: If trying to access any service page, redirect to settings
            $servicePages = ['airtime.php', 'data.php', 'cable.php', 'electric.php', 'betting.php', 'exam.php', 'giftcards.php', 'finance.php', 'transfer.php', 'sms.php', 'vcard.php', 'services.php', 'crypto.php', 'pay-hub.php'];

            if (in_array($currentFile, $servicePages)) {
                header("Location: /login-settings?error=security_required");
                exit;
            }

            if (!in_array($currentFile, $allowedFiles)) {
                header("Location: /dashboard");
                exit;
            }
        }
    }
}
