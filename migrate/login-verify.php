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
$as = $settings['adminSecuritySettings'] ?? [];
if (is_string($as)) $as = json_decode($as, true) ?: [];
$error = '';

$isAdmin = ($pendingUser['role'] === 'admin');

// Determine required steps for this user
$requiredSteps = [];

// Biometric (Only for users or if enabled for all)
if (!empty($ls['biometric']['enabled']) && !empty($pendingUser['biometricEnabled']) && !empty($pendingUser['biometricCredentialId'])) $requiredSteps[] = 'biometric';

// PIN: Respect global user setting OR mandatory admin setting
$pinMandatory = !empty($ls['pin']['enabled']) || ($isAdmin && !empty($as['pin']['enabled']));
if ($pinMandatory && !empty($pendingUser['loginSecurityPin'])) $requiredSteps[] = 'pin';

// Email: Respect global user setting OR mandatory admin setting
$emailMandatory = !empty($ls['email']['enabled']) || ($isAdmin && !empty($as['email']['enabled']));
if ($emailMandatory || !empty($pendingUser['email2faEnabled'])) {
     $requiredSteps[] = 'email';
}

// Google 2FA
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
    $action = $_POST['action'] ?? '';

    if ($action === 'forgot_pin' && $currentStep === 'pin') {
        // Trigger Email OTP for PIN Reset
        sendEmail2fa($pdo, $pendingUser);
        $_SESSION['resetting_pin'] = true;
        $success = "A verification code has been sent to your email to reset your PIN.";
    } elseif ($action === 'verify_reset_otp' && !empty($_SESSION['resetting_pin'])) {
        if (isset($_SESSION['email_2fa_code']) && $_POST['code'] == $_SESSION['email_2fa_code'] && time() < $_SESSION['email_2fa_expiry']) {
            $_SESSION['pin_reset_authorized'] = true;
            unset($_SESSION['email_2fa_code'], $_SESSION['email_2fa_expiry']);
        } else {
            $error = "Invalid or expired verification code.";
        }
    } elseif ($action === 'complete_pin_reset' && !empty($_SESSION['pin_reset_authorized'])) {
        $newPin = sanitize($_POST['new_pin']);
        if (strlen($newPin) === 6 && is_numeric($newPin)) {
            $hashedPin = password_hash($newPin, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET loginSecurityPin = ? WHERE id = ?")->execute([$hashedPin, $pendingUser['id']]);

            // Log this security change
            $pdo->prepare("INSERT INTO login_history (userId, ip, userAgent, status) VALUES (?, ?, ?, 'pin_reset')")->execute([$pendingUser['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            unset($_SESSION['resetting_pin'], $_SESSION['pin_reset_authorized']);
            $success = "PIN reset successful! You can now continue.";
            $verified = true; // Mark as verified for the current PIN step
        } else {
            $error = "PIN must be exactly 6 digits.";
        }
    } elseif ($currentStep === 'pin') {
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
            $credentialId = $_POST['credentialId'];
            if ($credentialId === $pendingUser['biometricCredentialId']) $verified = true;
            else $error = "Biometric authentication failed. Please try again.";
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
                <p class="text-[8px] font-black text-billpay-green uppercase mt-2 bg-green-50 p-2 rounded-lg border border-green-100">Secure Biometric Verification (Live Mode)</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <?php if ($currentStep === 'pin' && empty($_SESSION['resetting_pin']) && empty($_SESSION['pin_reset_authorized'])): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Enter Security PIN</label>
                    <input type="password" name="pin" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.5em]" required>
                    <button type="submit" name="action" value="forgot_pin" class="text-[9px] font-black text-billpay-green uppercase hover:underline">Forgot PIN?</button>
                </div>

            <?php elseif (!empty($_SESSION['resetting_pin']) && empty($_SESSION['pin_reset_authorized'])): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Verify Email OTP</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Enter the 6-digit code sent to your email to reset your PIN.</p>
                    <input type="text" name="code" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus placeholder="000000" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.2em]" required>
                    <input type="hidden" name="action" value="verify_reset_otp">
                </div>

            <?php elseif (!empty($_SESSION['pin_reset_authorized'])): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Set New Security PIN</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Choose a new 6-digit PIN.</p>
                    <input type="password" name="new_pin" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus placeholder="••••••" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.5em]" required>
                    <input type="hidden" name="action" value="complete_pin_reset">
                </div>

            <?php elseif ($currentStep === 'email'): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Email Verification Code</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">A 6-digit code was sent to your email.</p>
                    <input type="text" name="code" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus placeholder="000000" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.2em]" required>
                </div>

            <?php elseif ($currentStep === 'google2fa'): ?>
                <div class="space-y-4 text-center">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Authenticator App Code</label>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Enter the 6-digit code from your app.</p>
                    <input type="text" name="code" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus placeholder="000000" class="w-full text-center p-5 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-black text-3xl tracking-[0.2em]" required>
                </div>

            <?php elseif ($currentStep === 'biometric'): ?>
                <div class="text-center space-y-6">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Biometric Identity Check</p>
                    <button type="button" onclick="verifyBiometrics('<?php echo $pendingUser['biometricCredentialId']; ?>')" class="w-24 h-24 bg-billpay-green/10 text-billpay-green rounded-3xl flex items-center justify-center mx-auto hover:scale-110 transition-all">
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
        async function verifyBiometrics(storedId) {
            if (!window.PublicKeyCredential) { alert("Biometrics not supported"); return; }
            if (!storedId) { alert("No biometric credential found for this user."); return; }

            const challenge = new Uint8Array(32); window.crypto.getRandomValues(challenge);

            // Convert base64 to Uint8Array
            const binaryId = atob(storedId);
            const bytes = new Uint8Array(binaryId.length);
            for (let i = 0; i < binaryId.length; i++) bytes[i] = binaryId.charCodeAt(i);

            try {
                const assertion = await navigator.credentials.get({
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
                });
                if (assertion) {
                    const rawId = new Uint8Array(assertion.rawId);
                    let binary = '';
                    for (let i = 0; i < rawId.byteLength; i++) binary += String.fromCharCode(rawId[i]);
                    const base64Id = btoa(binary);

                    document.getElementById('biometricIdInput').value = base64Id;
                    document.querySelector('form').submit();
                }
            } catch (err) {
                console.error(err);
                alert("Verification failed: " + err.message);
            }
        }
    </script>
</body>
</html>
