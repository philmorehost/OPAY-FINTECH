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

    if (isset($_POST['action']) && $_POST['action'] === 'biometric') {
        /**
         * SECURITY WARNING:
         * This is a simplified biometric authentication logic for functional demonstration.
         * In a PRODUCTION fintech environment, you MUST implement full WebAuthn verification
         * including challenge verification, origin checks, and cryptographic signature validation.
         * Do NOT use this simplified logic for high-value financial systems without upgrades.
         */
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
            $_SESSION['pending_login_id'] = $user['id'];

            // Store user info in script for JS to save to localStorage
            $jsUser = [
                'username' => $user['username'],
                'biometricEnabled' => (bool)$user['biometricEnabled'],
                'hasBiometrics' => !empty($user['biometricCredentialId'])
            ];

            echo "<script>
                localStorage.setItem('lastUser', '".json_encode($jsUser)."');
                window.location.href = '/login-verify';
            </script>";
            exit;
        }
    } else {
        $error = 'Invalid username or password';
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
                <input type="password" name="password" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>

            <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Sign In</button>
        </form>

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
                const isPwa = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
                const lastUser = JSON.parse(localStorage.getItem('lastUser') || 'null');
                const isGloballyEnforced = <?php echo !empty($settings['isBiometricEnforced']) ? 'true' : 'false'; ?>;

                if (isPwa && ( (lastUser && lastUser.biometricEnabled) || (!lastUser && isGloballyEnforced) )) {
                    document.getElementById('biometric-area').classList.remove('hidden');
                    if (lastUser && lastUser.username) {
                        document.getElementById('biometric-user-text').innerText = 'Continue as ' + lastUser.username;
                    }
                }
            }
            window.addEventListener('DOMContentLoaded', initBiometricArea);

            async function loginWithBiometrics() {
                const lastUser = JSON.parse(localStorage.getItem('lastUser') || 'null');
                if (!lastUser || !lastUser.hasBiometrics) {
                    alert("Please login with your username and password first to enable biometric login on this device.");
                    return;
                }

                if (!window.PublicKeyCredential) return;

                const challenge = new Uint8Array(32);
                window.crypto.getRandomValues(challenge);

                const getCredentialOptions = {
                    publicKey: {
                        challenge: challenge,
                        timeout: 60000,
                        userVerification: "required"
                    }
                };

                try {
                    const assertion = await navigator.credentials.get(getCredentialOptions);
                    if (assertion) {
                        document.getElementById('biometricIdInput').value = btoa(String.fromCharCode(...new Uint8Array(assertion.rawId)));
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
    <script>lucide.createIcons();</script>
</body>
</html>
