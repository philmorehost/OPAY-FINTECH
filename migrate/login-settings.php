<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Login Settings';

$qrUrl = '';
$secret = '';
if (isset($_GET['setup']) && $_GET['setup'] === '2fa') {
    if (empty($currentUser['google2faSecret'])) {
        $secret = TOTP::generateSecret();
        $stmt = $pdo->prepare("UPDATE users SET google2faSecret = ? WHERE id = ?");
        $stmt->execute([$secret, $currentUser['id']]);
        $currentUser['google2faSecret'] = $secret;
    } else {
        $secret = $currentUser['google2faSecret'];
    }
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode(TOTP::getQrCodeUrl($currentUser['username'], $settings['senderName'] ?? 'BillPay', $secret));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $loginAlerts = isset($_POST['loginAlertsEnabled']) ? 1 : 0;
    $biometric = isset($_POST['biometricEnabled']) ? 1 : 0;
    $marketing = isset($_POST['marketingEmailsEnabled']) ? 1 : 0;
    $smsAlerts = isset($_POST['smsAlertsEnabled']) ? 1 : 0;
    $email2fa = isset($_POST['email2faEnabled']) ? 1 : 0;
    $google2fa = isset($_POST['google2faEnabled']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE users SET loginAlertsEnabled = ?, biometricEnabled = ?, marketingEmailsEnabled = ?, smsAlertsEnabled = ?, email2faEnabled = ?, google2faEnabled = ? WHERE id = ?");
    $stmt->execute([$loginAlerts, $biometric, $marketing, $smsAlerts, $email2fa, $google2fa, $currentUser['id']]);

    if (isset($_POST['biometricCredentialId']) && !empty($_POST['biometricCredentialId'])) {
        $stmt = $pdo->prepare("UPDATE users SET biometricCredentialId = ?, biometricPublicKey = ? WHERE id = ?");
        $stmt->execute([$_POST['biometricCredentialId'], $_POST['biometricPublicKey'], $currentUser['id']]);
    }

    if (!empty($_POST['loginSecurityPin'])) {
        $stmt = $pdo->prepare("UPDATE users SET loginSecurityPin = ? WHERE id = ?");
        $stmt->execute([sanitize($_POST['loginSecurityPin']), $currentUser['id']]);
    }

    // Update Configured Methods Cache
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
    $u = $stmt->fetch();
    $configured = [];
    if (!empty($u['biometricCredentialId'])) $configured[] = 'biometric';
    if (!empty($u['loginSecurityPin'])) $configured[] = 'pin';
    if (!empty($u['email2faEnabled'])) $configured[] = 'email';
    if (!empty($u['google2faEnabled'])) $configured[] = 'google2fa';
    $pdo->prepare("UPDATE users SET configuredSecurityMethods = ? WHERE id = ?")->execute([json_encode($configured), $currentUser['id']]);

    $success = "Security settings updated!";
    // Refresh
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $currentUser = $stmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/profile"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Security Center</h1>
    </div>

    <div class="p-6 space-y-6 flex-1">
        <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <?php if ($qrUrl): ?>
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-6 animate-slide-up">
            <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Link Authenticator</h3>
            <div class="bg-gray-50 p-6 rounded-[32px] inline-block border border-gray-100 shadow-inner">
                <img src="<?php echo $qrUrl; ?>" class="w-48 h-48 rounded-xl shadow-lg">
            </div>
            <div class="space-y-2">
                <p class="text-[10px] font-black text-gray-400 uppercase">Secret Key</p>
                <code class="px-4 py-2 bg-gray-50 rounded-lg font-mono text-sm border border-gray-100 block"><?php echo $secret; ?></code>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase leading-relaxed max-w-xs mx-auto">Scan the QR code with Google Authenticator or Authy, then enable Google 2FA below and save.</p>
            <a href="/login-settings" class="block w-full py-4 bg-gray-900 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest">Done Scanning</a>
        </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800">Login Alerts</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Email notification on new login</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="loginAlertsEnabled" class="sr-only peer" <?php echo $currentUser['loginAlertsEnabled'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800">Biometric Login</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Use FaceID/TouchID</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if (empty($currentUser['biometricCredentialId'])): ?>
                            <button type="button" onclick="registerBiometrics()" class="text-[9px] font-black uppercase text-billpay-green bg-green-50 px-3 py-1.5 rounded-full border border-green-100">Setup</button>
                        <?php else: ?>
                            <span class="text-[8px] font-black text-green-500 uppercase bg-green-50 px-2 py-1 rounded-md">Linked</span>
                        <?php endif; ?>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="biometricEnabled" class="sr-only peer" <?php echo $currentUser['biometricEnabled'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                </div>
                <input type="hidden" name="biometricCredentialId" id="biometricCredentialId">
                <input type="hidden" name="biometricPublicKey" id="biometricPublicKey">

                <div class="pt-4 border-t border-gray-50">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Set Security PIN (6 Digits)</label>
                    <input type="password" name="loginSecurityPin" placeholder="<?php echo !empty($currentUser['loginSecurityPin']) ? '••••••' : 'Enter 6-digit PIN'; ?>" maxlength="6" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg mt-2 outline-none border-2 border-transparent focus:border-billpay-green text-center tracking-[0.5em]">
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800">Email Auth</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Login code via email</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="email2faEnabled" class="sr-only peer" <?php echo $currentUser['email2faEnabled'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800">Google 2FA</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Authenticator App</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if (empty($currentUser['google2faSecret'])): ?>
                            <a href="?setup=2fa" class="text-[9px] font-black uppercase text-billpay-green bg-green-50 px-3 py-1.5 rounded-full border border-green-100">Setup</a>
                        <?php else: ?>
                            <span class="text-[8px] font-black text-green-500 uppercase bg-green-50 px-2 py-1 rounded-md">Configured</span>
                        <?php endif; ?>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="google2faEnabled" class="sr-only peer" <?php echo $currentUser['google2faEnabled'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800">Marketing Emails</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Receive promo & updates</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="marketingEmailsEnabled" class="sr-only peer" <?php echo $currentUser['marketingEmailsEnabled'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Save Preferences</button>
        </form>
    </div>
</div>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('setup') === 'biometric') {
            registerBiometrics();
        }
    });

    async function registerBiometrics() {
        if (!window.PublicKeyCredential) {
            alert("Biometrics not supported on this device.");
            return;
        }

        const siteName = "<?php echo $settings['senderName'] ?? 'Billpay'; ?>";
        const username = "<?php echo $currentUser['username']; ?>";
        const challenge = new Uint8Array(32);
        window.crypto.getRandomValues(challenge);

        const createCredentialOptions = {
            publicKey: {
                challenge: challenge,
                rp: { name: siteName },
                user: {
                    id: Uint8Array.from(username, c => c.charCodeAt(0)),
                    name: username,
                    displayName: username
                },
                pubKeyCredParams: [{ alg: -7, type: "public-key" }],
                authenticatorSelection: { authenticatorAttachment: "platform" },
                timeout: 60000,
                attestation: "direct"
            }
        };

        try {
            const credential = await navigator.credentials.create(createCredentialOptions);
            if (credential) {
                document.getElementById('biometricCredentialId').value = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
                document.getElementById('biometricPublicKey').value = "webauthn-placeholder";
                alert("Biometrics linked! Please save your preferences.");
            }
        } catch (err) {
            console.error(err);
            alert("Failed to setup biometrics: " + err.message);
        }
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
