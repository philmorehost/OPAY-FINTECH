<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Login Security Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $securitySettings = [
        'biometric' => [
            'enabled' => isset($_POST['biometric_enabled']) ? 1 : 0,
            'forced' => isset($_POST['biometric_forced']) ? 1 : 0
        ],
        'pin' => [
            'enabled' => isset($_POST['pin_enabled']) ? 1 : 0,
            'forced' => isset($_POST['pin_forced']) ? 1 : 0
        ],
        'email' => [
            'enabled' => isset($_POST['email_enabled']) ? 1 : 0,
            'forced' => isset($_POST['email_forced']) ? 1 : 0
        ],
        'google2fa' => [
            'enabled' => isset($_POST['google2fa_enabled']) ? 1 : 0,
            'forced' => isset($_POST['google2fa_forced']) ? 1 : 0
        ]
    ];

    $stmt = $pdo->prepare("UPDATE settings SET loginSecuritySettings = ? WHERE id = 1");
    $stmt->execute([json_encode($securitySettings)]);

    $success = "Login security settings updated successfully!";
    $settings = fetchSettings($pdo);
}

$ls = $settings['loginSecuritySettings'] ?? [];
if (empty($ls)) {
    $ls = [
        'biometric' => ['enabled' => 0, 'forced' => 0],
        'pin' => ['enabled' => 0, 'forced' => 0],
        'email' => ['enabled' => 0, 'forced' => 0],
        'google2fa' => ['enabled' => 0, 'forced' => 0]
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Login Security Manager</h2>
    </div>

    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Biometric -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center">
                        <i data-lucide="fingerprint" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Biometric Login</h3>
                        <p class="text-[9px] font-bold text-gray-400 uppercase">FaceID / TouchID / Windows Hello</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Enable Method</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="biometric_enabled" class="sr-only peer" <?php echo !empty($ls['biometric']['enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Force Configuration</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="biometric_forced" class="sr-only peer" <?php echo !empty($ls['biometric']['forced']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Security PIN -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center">
                        <i data-lucide="lock" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Security PIN</h3>
                        <p class="text-[9px] font-bold text-gray-400 uppercase">Mandatory 6-Digit Login PIN</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Enable Method</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="pin_enabled" class="sr-only peer" <?php echo !empty($ls['pin']['enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Force Configuration</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="pin_forced" class="sr-only peer" <?php echo !empty($ls['pin']['forced']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Email Auth -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center">
                        <i data-lucide="mail" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Email Auth</h3>
                        <p class="text-[9px] font-bold text-gray-400 uppercase">One-Time Code via Email</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Enable Method</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_enabled" class="sr-only peer" <?php echo !empty($ls['email']['enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Force Configuration</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_forced" class="sr-only peer" <?php echo !empty($ls['email']['forced']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Google 2FA -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Google 2FA</h3>
                        <p class="text-[9px] font-bold text-gray-400 uppercase">Authenticator App (TOTP)</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Enable Method</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="google2fa_enabled" class="sr-only peer" <?php echo !empty($ls['google2fa']['enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Force Configuration</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="google2fa_forced" class="sr-only peer" <?php echo !empty($ls['google2fa']['forced']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-6 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Security Policy</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
