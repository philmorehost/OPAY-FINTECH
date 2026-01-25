<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Login Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $loginAlerts = isset($_POST['loginAlertsEnabled']) ? 1 : 0;
    $biometric = isset($_POST['biometricEnabled']) ? 1 : 0;
    $marketing = isset($_POST['marketingEmailsEnabled']) ? 1 : 0;
    $smsAlerts = isset($_POST['smsAlertsEnabled']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE users SET loginAlertsEnabled = ?, biometricEnabled = ?, marketingEmailsEnabled = ?, smsAlertsEnabled = ? WHERE id = ?");
    $stmt->execute([$loginAlerts, $biometric, $marketing, $smsAlerts, $currentUser['id']]);
    $success = "Security settings updated!";
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
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="biometricEnabled" class="sr-only peer" <?php echo $currentUser['biometricEnabled'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
