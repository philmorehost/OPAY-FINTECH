<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';

if (isLoggedIn()) redirect('/dashboard');
if (!isset($_SESSION['pending_login_id'])) redirect('/login');

$pendingUser = fetchUser($pdo, $_SESSION['pending_login_id']);
if (!$pendingUser) {
    session_destroy();
    redirect('/login');
}

$ls = $settings['loginSecuritySettings'] ?? [];
$error = '';

// Determine required steps for this user
$requiredSteps = [];
if (!empty($ls['biometric']['enabled']) && !empty($pendingUser['biometricEnabled']) && !empty($pendingUser['biometricCredentialId'])) $requiredSteps[] = 'biometric';
if (!empty($ls['pin']['enabled']) && !empty($pendingUser['loginSecurityPin'])) $requiredSteps[] = 'pin';
if (!empty($ls['email']['enabled']) && !empty($pendingUser['email2faEnabled'])) $requiredSteps[] = 'email';
if (!empty($ls['google2fa']['enabled']) && !empty($pendingUser['google2faEnabled'])) $requiredSteps[] = 'google2fa';

if (empty($requiredSteps)) {
    // Nothing to verify, complete login
    $_SESSION['user_id'] = $pendingUser['id'];
    $_SESSION['username'] = $pendingUser['username'];
    $_SESSION['role'] = $pendingUser['role'];
    unset($_SESSION['pending_login_id']);
    redirect($pendingUser['role'] === 'admin' ? '/admin/' : '/dashboard');
}

// Current step
if (!isset($_SESSION['mfa_step_idx'])) $_SESSION['mfa_step_idx'] = 0;
$currentStep = $requiredSteps[$_SESSION['mfa_step_idx']] ?? null;

if (!$currentStep) {
    // All steps completed
    $_SESSION['user_id'] = $pendingUser['id'];
    $_SESSION['username'] = $pendingUser['username'];
    $_SESSION['role'] = $pendingUser['role'];
    unset($_SESSION['pending_login_id'], $_SESSION['mfa_step_idx'], $_SESSION['email_2fa_code'], $_SESSION['email_2fa_expiry']);
    redirect($pendingUser['role'] === 'admin' ? '/admin/' : '/dashboard');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $verified = false;
    if ($currentStep === 'pin') {
        if (password_verify($_POST['pin'], $pendingUser['loginSecurityPin'])) $verified = true;
        else $error = "Invalid Security PIN";
    } elseif ($currentStep === 'email') {
        if (isset($_SESSION['email_2fa_code']) && $_POST['code'] == $_SESSION['email_2fa_code'] && time() < $_SESSION['email_2fa_expiry']) $verified = true;
        else $error = "Invalid or expired verification code";
    } elseif ($currentStep === 'google2fa') {
        if (TOTP::verifyCode($pendingUser['google2faSecret'], $_POST['code'])) $verified = true;
        else $error = "Invalid Authenticator code";
    } elseif ($currentStep === 'biometric') {
        if ($_POST['action'] === 'biometric_verify') {
            /**
             * SECURITY WARNING:
             * This biometric verification is a simplified demonstration.
             * In a production environment, you MUST use a WebAuthn library
             * to verify the cryptographic signature against the stored public key.
             * Simply comparing the credentialId is NOT secure.
             */
            $credentialId = $_POST['credentialId'];
            if ($credentialId === $pendingUser['biometricCredentialId']) $verified = true;
            else $error = "Biometric verification failed";
        }
    }

    if ($verified) {
        $_SESSION['mfa_step_idx']++;
        header("Location: /login-verify");
        exit;
    }
}

// Initialize Step (e.g. send email)
if ($currentStep === 'email' && !isset($_SESSION['email_2fa_code'])) {
    sendEmail2fa($pdo, $pendingUser);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
        .bg-billpay-green { background-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-gray-900">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100 animate-fade-in">
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-billpay-green">
                <i data-lucide="shield-lock" class="w-8 h-8"></i>
            </div>
            <h2 class="text-xl font-black uppercase tracking-tight">Security Check</h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Step <?php echo $_SESSION['mfa_step_idx'] + 1; ?> of <?php echo count($requiredSteps); ?></p>
            <?php if ($currentStep === 'biometric'): ?>
                <p class="text-[8px] font-black text-red-500 uppercase mt-2 bg-red-50 p-2 rounded-lg border border-red-100">Warning: Biometric logic is currently in Demo Mode (Simplified ID Match).</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <?php if ($currentStep === 'pin'): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Enter Security PIN</label>
                    <input type="password" name="pin" maxlength="6" autofocus class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.5em]" required>
                </div>

            <?php elseif ($currentStep === 'email'): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Email Verification Code</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">A 6-digit code was sent to your email.</p>
                    <input type="text" name="code" maxlength="6" autofocus placeholder="000000" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.2em]" required>
                </div>

            <?php elseif ($currentStep === 'google2fa'): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Authenticator App Code</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Enter the 6-digit code from your app.</p>
                    <input type="text" name="code" maxlength="6" autofocus placeholder="000000" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.2em]" required>
                </div>

            <?php elseif ($currentStep === 'biometric'): ?>
                <div class="text-center space-y-6">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Biometric Identity Check</p>
                    <button type="button" onclick="verifyBiometrics()" class="w-24 h-24 bg-billpay-green/10 text-billpay-green rounded-3xl flex items-center justify-center mx-auto hover:scale-110 transition-all">
                        <i data-lucide="fingerprint" class="w-12 h-12"></i>
                    </button>
                    <input type="hidden" name="action" value="biometric_verify">
                    <input type="hidden" name="credentialId" id="biometricIdInput">
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all">Verify & Continue</button>
        </form>

        <div class="mt-8 text-center">
            <a href="/logout" class="text-[9px] font-black text-red-500 uppercase tracking-widest">Cancel Login</a>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
        async function verifyBiometrics() {
            if (!window.PublicKeyCredential) { alert("Biometrics not supported"); return; }
            const challenge = new Uint8Array(32); window.crypto.getRandomValues(challenge);
            try {
                const assertion = await navigator.credentials.get({ publicKey: { challenge: challenge, timeout: 60000, userVerification: "required" } });
                if (assertion) {
                    document.getElementById('biometricIdInput').value = btoa(String.fromCharCode(...new Uint8Array(assertion.rawId)));
                    document.querySelector('form').submit();
                }
            } catch (err) { alert("Verification failed: " + err.message); }
        }
    </script>
</body>
</html>
