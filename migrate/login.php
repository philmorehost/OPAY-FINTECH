<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect('/dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    $bs = $settings['bruteforceSettings'] ?? [];
    if (is_string($bs)) $bs = json_decode($bs, true) ?: [];

    // Check if IP is blacklisted
    $stmt = $pdo->prepare("SELECT status FROM access_control WHERE type = 'ip' AND value = ?");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() === 'blacklisted') die('Access Denied: Your IP is restricted.');

    // Check for IP block
    $stmt = $pdo->prepare("SELECT expiry FROM access_control WHERE type = 'ip' AND value = ? AND status = 'blacklisted' AND expiry > NOW()");
    $stmt->execute([$ip]);
    if ($stmt->fetch()) die('Access Denied: Temporarily blocked due to security reasons.');

    if (isset($_POST['action']) && $_POST['action'] === 'biometric') {
        $credentialId = $_POST['credentialId'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE biometricCredentialId = ? AND biometricEnabled = 1");
        $stmt->execute([$credentialId]);
        $user = $stmt->fetch();

        if ($user && !empty($settings['isBiometricEnforced'])) {
            // Success
        } elseif ($user) {
            // User enabled it, but is it globally disabled?
            // If it's not "Enforced", is it "Disabled"?
            // Usually if there's a global toggle, OFF means disabled.
            // But user said "globally enforce... or globally disable".
            // If it's disabled, don't allow.
            if (!isset($settings['isBiometricEnforced']) || !$settings['isBiometricEnforced']) {
                $user = null; // Disabled globally
            }
        } else {
            $user = null;
        }

        if ($user) {
            if ($user['isSuspended']) {
                $error = 'ACCOUNT SUSPENDED. CONTACT SUPPORT.';
            } else {
                $_SESSION['pending_login_id'] = $user['id'];
                redirect('/login-verify');
            }
        } else {
            $error = 'Biometric authentication failed or feature disabled.';
        }
    } elseif (!checkRateLimit('login', 5, 300)) {
        $error = 'Too many attempts. Try again later.';
    } else {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['isSuspended']) {
            $error = 'ACCOUNT SUSPENDED. CONTACT SUPPORT.';
        } else {
            // Success: Reset failed attempts for IP/User if any, or increment success count
            $pdo->prepare("INSERT INTO access_control (type, value, successCount) VALUES ('ip', ?, 1) ON DUPLICATE KEY UPDATE successCount = successCount + 1")->execute([$ip]);

            // Log History
            $pdo->prepare("INSERT INTO login_history (userId, ip, userAgent, status) VALUES (?, ?, ?, 'success')")->execute([$user['id'], $ip, $_SERVER['HTTP_USER_AGENT']]);

            $_SESSION['pending_login_id'] = $user['id'];

            // Store user info in script for JS to save to localStorage
            $jsUser = [
                'username' => $user['username'],
                'biometricEnabled' => (bool)$user['biometricEnabled'],
                'hasBiometrics' => !empty($user['biometricCredentialId']),
                'biometricId' => $user['biometricCredentialId']
            ];

            echo "<script>
                localStorage.setItem('lastUser', '".json_encode($jsUser)."');
                window.location.href = '/login-verify';
            </script>";
            exit;
        }
    } else {
        $error = 'Invalid username or password';

        // Brute Force Tracking
        $pdo->prepare("INSERT INTO login_history (ip, userAgent, status, attemptedUsername) VALUES (?, ?, 'failed', ?)")->execute([$ip, $_SERVER['HTTP_USER_AGENT'], $username]);

        if ($user) {
            // Track failures for existing user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_history WHERE userId = ? AND status = 'failed' AND createdAt > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$user['id']]);
            $failedRetries = (int)($bs['failed_retries'] ?? 3);
            if ($stmt->fetchColumn() >= $failedRetries) {
                $pdo->prepare("UPDATE users SET isSuspended = 1 WHERE id = ?")->execute([$user['id']]);
                $error = 'ACCOUNT SUSPENDED DUE TO MULTIPLE FAILED ATTEMPTS.';
            }
        } else {
            // Non-existent user: Block IP (Configurable duration)
            $durationMap = ['one-day' => '1 DAY', 'one-week' => '7 DAY', 'one-month' => '1 MONTH', 'one-year' => '1 YEAR'];
            $interval = $durationMap[$bs['block_duration'] ?? 'one-day'] ?? '1 DAY';

            $pdo->prepare("INSERT INTO access_control (type, value, status, expiry) VALUES ('ip', ?, 'blacklisted', DATE_ADD(NOW(), INTERVAL $interval)) ON DUPLICATE KEY UPDATE status = 'blacklisted', expiry = DATE_ADD(NOW(), INTERVAL $interval)")->execute([$ip]);
            $error = 'Security Alert: Access restricted due to suspicious activity.';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $settings['primaryColor'] ?? '#00c689'; ?>">
    <?php if (!empty($settings['pwaEnabled'])): ?>
    <link rel="manifest" href="/manifest.json.php">
    <link rel="apple-touch-icon" href="<?php echo !empty($settings['pwaIcon']) ? '/'.$settings['pwaIcon'].'?v='.($settings['siteVersion'] ?? '1.0.0') : '/uploads/logo.png'; ?>">
    <?php endif; ?>
    <title>Login - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (!empty($settings['pwaEnabled'])): ?>
    <script>
        window.addEventListener('load', () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>', { scope: '/' });
            }
        });
    </script>
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>;
        }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: var(--primary-color); }
        .bg-billpay-green { background-color: var(--primary-color); }
        .focus\:border-billpay-green:focus { border-color: var(--primary-color); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100">
        <div class="text-center mb-10">
            <img src="/<?php echo !empty($settings['pwaIcon']) ? $settings['pwaIcon'] : 'uploads/logo.png'; ?>" class="w-16 h-16 object-contain mx-auto mb-4 rounded-2xl shadow-lg">
            <h1 class="text-2xl font-black text-gray-800"><?php echo $settings['senderName'] ?? 'Billpay'; ?></h1>
            <h1 class="text-lg font-bold text-gray-600 mt-2">Welcome Back</h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Secure Login to your wallet</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase tracking-tight"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Username or Email</label>
                <input type="text" name="username" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="passwordInput" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                    <button type="button" onclick="togglePassword('passwordInput', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-billpay-green transition-colors">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Sign In</button>
        </form>

        <?php if (!empty($settings['googleAuthEnabled']) && !empty($settings['googleClientId'])): ?>
            <?php
            $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                'client_id' => $settings['googleClientId'],
                'redirect_uri' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/google-callback.php',
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'online',
                'prompt' => 'select_account'
            ]);
            ?>
            <div class="mt-6">
                <a href="<?php echo $googleAuthUrl; ?>" class="w-full py-5 bg-white border-2 border-gray-100 rounded-[24px] font-black uppercase tracking-widest flex items-center justify-center gap-3 hover:bg-gray-50 transition-all text-xs">
                    <img src="https://www.google.com/favicon.ico" class="w-4 h-4">
                    Sign in with Google
                </a>
            </div>
        <?php endif; ?>

        <div id="biometric-area" class="hidden mt-8 text-center animate-slide-up">
            <div class="flex items-center gap-4 mb-8">
                <div class="flex-1 h-px bg-gray-50"></div>
                <span class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em]">Quick Access</span>
                <div class="flex-1 h-px bg-gray-50"></div>
            </div>

            <div class="flex flex-col items-center gap-4">
                <button type="button" onclick="loginWithBiometrics()" class="group relative">
                    <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center border-2 border-gray-100 group-hover:border-billpay-green group-active:scale-90 transition-all duration-300">
                        <i data-lucide="fingerprint" class="w-10 h-10 text-gray-300 group-hover:text-billpay-green transition-colors"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-6 h-6 bg-billpay-green rounded-full border-4 border-white flex items-center justify-center shadow-sm">
                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                    </div>
                </button>
                <div>
                    <p class="text-[10px] font-black text-gray-900 uppercase tracking-widest mb-1" id="biometric-user-text">Continue as User</p>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Touch ID / Face ID (Demo)</p>
                </div>
            </div>

            <form id="biometricForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="biometric">
                <input type="hidden" name="credentialId" id="biometricIdInput">
            </form>
        </div>
        <script>
            function initBiometricArea() {
                const lastUser = JSON.parse(localStorage.getItem('lastUser') || 'null');
                const isGloballyEnforced = <?php echo !empty($settings['isBiometricEnforced']) ? 'true' : 'false'; ?>;

                if ((lastUser && lastUser.biometricEnabled) || isGloballyEnforced) {
                    document.getElementById('biometric-area').classList.remove('hidden');
                    if (lastUser && lastUser.username) {
                        document.getElementById('biometric-user-text').innerText = 'Continue as ' + lastUser.username;
                    }
                }
            }
            window.addEventListener('DOMContentLoaded', initBiometricArea);

            async function loginWithBiometrics() {
                const lastUser = JSON.parse(localStorage.getItem('lastUser') || 'null');
                if (!lastUser || !lastUser.hasBiometrics || !lastUser.biometricId) {
                    alert("Please login with your username and password first to enable biometric login on this device.");
                    return;
                }

                if (!window.PublicKeyCredential) {
                    alert("Biometrics not supported by this browser.");
                    return;
                }

                const challenge = new Uint8Array(32);
                window.crypto.getRandomValues(challenge);

                // Convert base64 to Uint8Array
                const binaryId = atob(lastUser.biometricId);
                const bytes = new Uint8Array(binaryId.length);
                for (let i = 0; i < binaryId.length; i++) bytes[i] = binaryId.charCodeAt(i);

                const getCredentialOptions = {
                    publicKey: {
                        challenge: challenge,
                        timeout: 60000,
                        userVerification: "required",
                        allowCredentials: [{
                            id: bytes,
                            type: 'public-key',
                            transports: ['internal', 'usb', 'nfc', 'ble']
                        }]
                    }
                };

                try {
                    const assertion = await navigator.credentials.get(getCredentialOptions);
                    if (assertion) {
                        const rawId = new Uint8Array(assertion.rawId);
                        let binary = '';
                        for (let i = 0; i < rawId.byteLength; i++) binary += String.fromCharCode(rawId[i]);
                        const base64Id = btoa(binary);

                        document.getElementById('biometricIdInput').value = base64Id;
                        document.getElementById('biometricForm').submit();
                    }
                } catch (err) {
                    console.error(err);
                    alert("Biometric login failed: " + err.message);
                }
            }
        </script>

        <div class="mt-8 text-center">
            <p class="text-[10px] font-black text-gray-400 uppercase">Don't have an account? <a href="/register" class="text-billpay-green">Create One</a></p>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
</body>
</html>
