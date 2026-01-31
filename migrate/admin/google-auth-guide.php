<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Google Auth Setup Guide';
require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center gap-4">
        <a href="login-security.php" class="w-10 h-10 bg-white border border-gray-100 rounded-xl flex items-center justify-center text-gray-400 hover:text-billpay-green transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="text-2xl font-black uppercase tracking-tight">Google API Setup Guide</h2>
    </div>

    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-12">
        <section class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-black">1</div>
                <h3 class="text-lg font-black uppercase tracking-tight">Create Google Cloud Project</h3>
            </div>
            <div class="pl-16 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">Go to the <a href="https://console.cloud.google.com/" target="_blank" class="text-billpay-green font-bold underline">Google Cloud Console</a> and create a new project for your platform.</p>
            </div>
        </section>

        <section class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-black">2</div>
                <h3 class="text-lg font-black uppercase tracking-tight">Configure OAuth Consent Screen</h3>
            </div>
            <div class="pl-16 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">Navigate to <strong>APIs & Services > OAuth consent screen</strong>.</p>
                <ul class="list-disc pl-5 text-sm text-gray-500 space-y-2">
                    <li>Choose <strong>External</strong> User Type.</li>
                    <li>Fill in your App Name, User support email, and Developer contact information.</li>
                    <li>Add the scopes: <code>auth/userinfo.email</code> and <code>auth/userinfo.profile</code>.</li>
                </ul>
            </div>
        </section>

        <section class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-black">3</div>
                <h3 class="text-lg font-black uppercase tracking-tight">Create OAuth 2.0 Credentials</h3>
            </div>
            <div class="pl-16 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">Go to <strong>APIs & Services > Credentials</strong>. Click <strong>Create Credentials</strong> and select <strong>OAuth client ID</strong>.</p>
                <div class="p-6 bg-gray-50 rounded-3xl border border-gray-100 space-y-4">
                    <div>
                        <span class="text-[10px] font-black text-gray-400 uppercase block mb-1">Application Type</span>
                        <p class="text-sm font-bold text-gray-800">Web application</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-gray-400 uppercase block mb-1">Authorized JavaScript origins</span>
                        <code class="text-xs bg-white px-2 py-1 rounded border border-gray-200"><?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?></code>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-gray-400 uppercase block mb-1">Authorized redirect URIs</span>
                        <code class="text-xs bg-white px-2 py-1 rounded border border-gray-200"><?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/google-callback.php'; ?></code>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-black">4</div>
                <h3 class="text-lg font-black uppercase tracking-tight">Copy and Save Keys</h3>
            </div>
            <div class="pl-16 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">Once created, you will see your <strong>Client ID</strong> and <strong>Client Secret</strong>. Copy these values into the <a href="login-security.php" class="text-billpay-green font-bold underline">Login Security Settings</a> page and enable Google Login.</p>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
