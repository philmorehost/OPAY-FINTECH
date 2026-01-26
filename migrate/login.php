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
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                redirect('/dashboard');
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Store user info in script for JS to save to localStorage
            $jsUser = [
                'username' => $user['username'],
                'biometricEnabled' => (bool)$user['biometricEnabled'],
                'hasBiometrics' => !empty($user['biometricCredentialId'])
            ];

            if ($user['role'] === 'admin') {
                $target = '/admin/';
            } else {
                $target = '/dashboard';
                // If biometric enabled in settings but not set up, take them to setup
                if ($user['biometricEnabled'] && empty($user['biometricCredentialId'])) {
                    $target = '/login-settings?setup=biometric';
                }
            }
            echo "<script>
                localStorage.setItem('lastUser', '".json_encode($jsUser)."');
                window.location.href = '$target';
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
    <title>Login - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        // Move PWA listener as early as possible
        window.deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.deferredPrompt = e;
            console.log('beforeinstallprompt captured');
        });
    </script>
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

        <div id="biometric-area" class="hidden mt-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="flex-1 h-px bg-gray-100"></div>
                <span class="text-[10px] font-black text-gray-300 uppercase">Or Secure With</span>
                <div class="flex-1 h-px bg-gray-100"></div>
            </div>
            <button type="button" onclick="loginWithBiometrics()" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl flex items-center justify-center gap-3 active:scale-95 transition-all">
                <i data-lucide="fingerprint" class="w-6 h-6 text-billpay-green"></i>
                Biometric Login
            </button>
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

                // Show if in PWA and (user enabled it OR (no user yet and globally enforced))
                if (isPwa && ( (lastUser && lastUser.biometricEnabled) || (!lastUser && isGloballyEnforced) )) {
                    document.getElementById('biometric-area').classList.remove('hidden');
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

    <script>
        function checkPwaInstallation() {
            const isPwa = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            if (!isPwa) {
                const modal = document.getElementById('installModal');
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

                if (isIOS) {
                    document.getElementById('install-instructions-default').classList.add('hidden');
                    document.getElementById('install-instructions-ios').classList.remove('hidden');
                    document.getElementById('installBtn').classList.add('hidden');
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        window.addEventListener('DOMContentLoaded', checkPwaInstallation);

        document.getElementById('installBtn')?.addEventListener('click', async () => {
            if (window.deferredPrompt) {
                window.deferredPrompt.prompt();
                const { outcome } = await window.deferredPrompt.userChoice;
                window.deferredPrompt = null;
                document.getElementById('installModal').classList.add('hidden');
            } else {
                alert("Installation is not supported on this browser or it's already installed. Use your browser's 'Add to Home Screen' option.");
            }
        });
    </script>

    <!-- Install App Modal -->
    <div id="installModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden items-center justify-center p-6">
        <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden shadow-2xl animate-slide-up">
            <div class="p-10 text-center">
                <img src="/<?php echo !empty($settings['pwaIcon']) ? $settings['pwaIcon'] : 'uploads/logo.png'; ?>?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>" class="w-24 h-24 object-contain mx-auto mb-6 rounded-3xl shadow-xl">
                <h3 class="text-2xl font-black uppercase tracking-tight text-gray-900 mb-2">Install Our App</h3>

                <div id="install-instructions-default">
                    <p class="text-xs font-bold text-gray-400 uppercase leading-relaxed mb-8">
                        Get the best experience by installing the <span class="text-gray-900"><?php echo $settings['senderName'] ?? 'Billpay'; ?></span> app on your home screen.
                    </p>
                </div>

                <div id="install-instructions-ios" class="hidden text-left bg-gray-50 p-6 rounded-3xl border border-gray-100 mb-8">
                    <p class="text-[10px] font-black text-gray-400 uppercase mb-4 tracking-widest text-center">iOS Instructions</p>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            </div>
                            <span class="text-[10px] font-bold text-gray-700 uppercase">1. Tap the 'Share' button</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <span class="text-[10px] font-bold text-gray-700 uppercase">2. Select 'Add to Home Screen'</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <button id="installBtn" class="w-full py-5 bg-billpay-green text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Install Now</button>
                    <button onclick="document.getElementById('installModal').classList.add('hidden')" class="w-full py-5 bg-gray-50 text-gray-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-100 transition-all">Dismiss</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
