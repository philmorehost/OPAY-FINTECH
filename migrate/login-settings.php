<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Login Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $loginAlerts = isset($_POST['loginAlertsEnabled']) ? 1 : 0;
    $biometric = isset($_POST['biometricEnabled']) ? 1 : 0;
    $marketing = isset($_POST['marketingEmailsEnabled']) ? 1 : 0;
    $smsAlerts = isset($_POST['smsAlertsEnabled']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE users SET loginAlertsEnabled = ?, biometricEnabled = ?, marketingEmailsEnabled = ?, smsAlertsEnabled = ? WHERE id = ?");
    $stmt->execute([$loginAlerts, $biometric, $marketing, $smsAlerts, $currentUser['id']]);

    if (isset($_POST['biometricCredentialId'])) {
        $stmt = $pdo->prepare("UPDATE users SET biometricCredentialId = ?, biometricPublicKey = ? WHERE id = ?");
        $stmt->execute([$_POST['biometricCredentialId'], $_POST['biometricPublicKey'], $currentUser['id']]);
    }

    $success = "Security settings updated!";
    // Refresh
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $currentUser = $stmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/profile"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Security Center</h1>
    </div>

    <div class="p-6 space-y-6 flex-1">
        <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

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
