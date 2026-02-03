<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Login Security Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    // Handle Admin Personal PIN Update
    if (!empty($_POST['admin_personal_pin'])) {
        $hashedPin = password_hash(sanitize($_POST['admin_personal_pin']), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET loginSecurityPin = ? WHERE id = ?");
        $stmt->execute([$hashedPin, $_SESSION['user_id']]);
        $success = "Your personal Security PIN has been updated!";

        // Update Configured Methods Cache
        $u = fetchUser($pdo, $_SESSION['user_id']);
        $configured = $u['configuredSecurityMethods'] ?? [];
        if (!in_array('pin', $configured)) {
            $configured[] = 'pin';
            $pdo->prepare("UPDATE users SET configuredSecurityMethods = ? WHERE id = ?")->execute([json_encode($configured), $_SESSION['user_id']]);
        }
    }

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

    $adminSecuritySettings = [
        'pin' => [
            'enabled' => isset($_POST['admin_pin_enabled']) ? 1 : 0,
        ],
        'email' => [
            'enabled' => isset($_POST['admin_email_enabled']) ? 1 : 0,
        ]
    ];

    // Safety Check: Preventing Lockout
    $currentAdmin = fetchUser($pdo, $_SESSION['user_id']);
    if ($adminSecuritySettings['pin']['enabled'] && empty($currentAdmin['loginSecurityPin']) && empty($_POST['admin_personal_pin'])) {
        $error = "CRITICAL: You must set a personal Security PIN before enabling mandatory PIN for admins to prevent lockout.";
    } else {
        $stmt = $pdo->prepare("UPDATE settings SET loginSecuritySettings = ?, googleClientId = ?, googleClientSecret = ?, googleAuthEnabled = ?, adminSecuritySettings = ? WHERE id = 1");
        $stmt->execute([
            json_encode($securitySettings),
            sanitize($_POST['google_client_id']),
            sanitize($_POST['google_client_secret']),
            isset($_POST['google_auth_enabled']) ? 1 : 0,
            json_encode($adminSecuritySettings)
        ]);

        $success = ($success ?? '') . " Login security policy updated successfully!";
    }
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

$as = $settings['adminSecuritySettings'] ?? [];
if (is_string($as)) $as = json_decode($as, true) ?: [];
if (empty($as)) {
    $as = [
        'pin' => ['enabled' => 0],
        'email' => ['enabled' => 0]
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
    <?php if (isset($error)): ?>
        <div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Google Login API -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 col-span-1 md:col-span-2">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center">
                            <i data-lucide="chrome" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest">Google Auth (SSO)</h3>
                            <p class="text-[9px] font-bold text-gray-400 uppercase">One-Tap Login & Registration</p>
                        </div>
                    </div>
                    <a href="google-auth-guide.php" class="text-[10px] font-black text-billpay-green uppercase hover:underline flex items-center gap-1">
                        <i data-lucide="help-circle" class="w-3 h-3"></i> Setup Guide
                    </a>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 h-full">
                            <span class="text-[10px] font-black uppercase text-gray-500">Enable Google Login</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="google_auth_enabled" class="sr-only peer" <?php echo !empty($settings['googleAuthEnabled']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client ID</label>
                        <input type="text" name="google_client_id" value="<?php echo $settings['googleClientId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-xs mt-1">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client Secret</label>
                        <input type="password" name="google_client_secret" value="<?php echo $settings['googleClientSecret'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-xs mt-1">
                    </div>
                </div>
            </div>

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

        <!-- Google SSO Configuration -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-10">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gray-100 text-gray-900 rounded-2xl flex items-center justify-center">
                    <i data-lucide="chrome" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Google SSO (Login/Register)</h3>
                    <p class="text-[9px] font-bold text-gray-400 uppercase">Allow users to sign in with their Google account</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <span class="text-[10px] font-black uppercase text-gray-500">Enable Google SSO</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="google_auth_enabled" class="sr-only peer" <?php echo !empty($settings['googleAuthEnabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>

                    <div class="p-6 bg-blue-50 rounded-3xl border border-blue-100">
                        <h4 class="text-[10px] font-black uppercase text-blue-600 mb-2 flex items-center gap-2">
                            <i data-lucide="help-circle" class="w-4 h-4"></i> Configuration Guide
                        </h4>
                        <ol class="text-[9px] font-bold text-blue-900/60 uppercase space-y-2 list-decimal ml-4">
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a>.</li>
                            <li>Create a new project or select an existing one.</li>
                            <li>Navigate to <strong>APIs & Services > Credentials</strong>.</li>
                            <li>Click <strong>Create Credentials > OAuth client ID</strong>.</li>
                            <li>Choose <strong>Web application</strong>.</li>
                            <li>Add your domain to <strong>Authorized JavaScript origins</strong>.</li>
                            <li>Add <code><?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/google-callback.php"; ?></code> to <strong>Authorized redirect URIs</strong>.</li>
                            <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> here.</li>
                        </ol>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase text-gray-400 ml-1">Google Client ID</label>
                        <input type="text" name="google_client_id" value="<?php echo $settings['googleClientId'] ?? ''; ?>" placeholder="Enter Client ID from Google Console" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-xs">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase text-gray-400 ml-1">Google Client Secret</label>
                        <input type="password" name="google_client_secret" value="<?php echo $settings['googleClientSecret'] ?? ''; ?>" placeholder="Enter Client Secret from Google Console" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-xs">
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Dashboard Security -->
        <div class="bg-gray-900 p-10 rounded-[40px] shadow-2xl text-white space-y-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/10 text-white rounded-2xl flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-white">Admin Account Security</h3>
                    <p class="text-[9px] font-bold text-white/40 uppercase">Protect the management portal from unauthorized access</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/10">
                    <div>
                        <div class="text-sm font-black uppercase">Mandatory Security PIN</div>
                        <p class="text-[8px] text-white/40 font-bold uppercase">Require 6-digit PIN for all admins</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="admin_pin_enabled" class="sr-only peer" <?php echo !empty($as['pin']['enabled']) ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-gray-900 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/10">
                    <div>
                        <div class="text-sm font-black uppercase">Mandatory Email Auth</div>
                        <p class="text-[8px] text-white/40 font-bold uppercase">OTP via Email required for dashboard access</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="admin_email_enabled" class="sr-only peer" <?php echo !empty($as['email']['enabled']) ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-gray-900 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                </div>

                <div class="col-span-1 md:col-span-2 p-6 bg-white/5 rounded-3xl border border-white/10 space-y-4">
                    <label class="text-[10px] font-black uppercase text-white/60 ml-1">Your Personal Security PIN (Required to enable Mandatory PIN)</label>
                    <input type="password" name="admin_personal_pin" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="••••••" class="w-full p-4 bg-white/10 rounded-2xl border border-white/10 text-white font-black text-xl text-center tracking-[0.5em] outline-none focus:border-billpay-green">
                    <p class="text-[8px] text-white/40 font-bold uppercase text-center italic">Only fill this to set or update your own login PIN.</p>
                </div>
            </div>

            <div class="p-6 bg-white/5 rounded-3xl border border-white/10 flex items-start gap-4">
                <i data-lucide="info" class="w-5 h-5 text-billpay-green shrink-0 mt-0.5"></i>
                <p class="text-[9px] font-bold text-white/60 uppercase leading-relaxed">Admin security settings apply to all administrator accounts. Ensure you have configured your individual Security PIN and have access to your email before enabling these features.</p>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-6 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all border-4 border-transparent hover:border-billpay-green">Save Security Policy</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
