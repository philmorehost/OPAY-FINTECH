<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Login Security Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $securitySettings = [
        'biometric' => isset($_POST['sec_biometric']) ? 1 : 0,
        'pin' => isset($_POST['sec_pin']) ? 1 : 0,
        'email' => isset($_POST['sec_email']) ? 1 : 0,
        'google2fa' => isset($_POST['sec_google2fa']) ? 1 : 0
    ];

    $stmt = $pdo->prepare("UPDATE settings SET loginSecuritySettings = ? WHERE id = 1");
    $stmt->execute([json_encode($securitySettings)]);

    // Also sync global biometric enforcement toggle if it's part of this
    $stmt = $pdo->prepare("UPDATE settings SET isBiometricEnforced = ? WHERE id = 1");
    $stmt->execute([$securitySettings['biometric']]);

    $success = "Login security settings updated successfully!";
    $settings = fetchSettings($pdo); // Refresh
}

$lss = $settings['loginSecuritySettings'] ?? [];
if (is_string($lss)) $lss = json_decode($lss, true) ?: [];

// Default off if empty (new install)
if (empty($lss)) {
    $lss = ['biometric' => 0, 'pin' => 0, 'email' => 0, 'google2fa' => 0];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tight">Login Security</h2>
            <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 tracking-widest">Global enforcement of multi-factor authentication</p>
        </div>
        <div class="p-3 bg-billpay-green/10 rounded-2xl">
            <i data-lucide="shield-lock" class="w-6 h-6 text-billpay-green"></i>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="max-w-4xl space-y-8">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Biometric -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center mb-6">
                        <i data-lucide="fingerprint" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-800">Biometric Auth</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-2 leading-relaxed">Require FaceID, TouchID or Windows Hello after password login.</p>
                </div>
                <div class="mt-8 flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <span class="text-[9px] font-black uppercase text-gray-400">Enforcement</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sec_biometric" class="sr-only peer" <?php echo !empty($lss['biometric']) ? 'checked' : ''; ?>>
                        <div class="w-12 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>
            </div>

            <!-- Security PIN -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mb-6">
                        <i data-lucide="keypad" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-800">Security PIN</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-2 leading-relaxed">Require a secondary 4-6 digit numeric PIN for every login session.</p>
                </div>
                <div class="mt-8 flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <span class="text-[9px] font-black uppercase text-gray-400">Enforcement</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sec_pin" class="sr-only peer" <?php echo !empty($lss['pin']) ? 'checked' : ''; ?>>
                        <div class="w-12 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>
            </div>

            <!-- Email Auth -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mb-6">
                        <i data-lucide="mail" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-800">Email Auth Code</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-2 leading-relaxed">Send a one-time verification code to the user's registered email address.</p>
                </div>
                <div class="mt-8 flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <span class="text-[9px] font-black uppercase text-gray-400">Enforcement</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sec_email" class="sr-only peer" <?php echo !empty($lss['email']) ? 'checked' : ''; ?>>
                        <div class="w-12 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>
            </div>

            <!-- Google 2FA -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mb-6">
                        <i data-lucide="smartphone" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-800">Google Authenticator</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-2 leading-relaxed">Require verification from Google Authenticator or similar TOTP apps.</p>
                </div>
                <div class="mt-8 flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <span class="text-[9px] font-black uppercase text-gray-400">Enforcement</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sec_google2fa" class="sr-only peer" <?php echo !empty($lss['google2fa']) ? 'checked' : ''; ?>>
                        <div class="w-12 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all active:scale-[0.98]">
            Save Security Configuration
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
