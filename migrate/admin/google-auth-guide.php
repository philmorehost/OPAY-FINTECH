<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Google Auth Setup Guide';
require_once __DIR__ . '/header.php';
?>

<div class="max-w-4xl mx-auto space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h2 class="text-2xl font-black uppercase tracking-tight mb-6">Google OAuth2 Configuration Guide</h2>

        <div class="space-y-8">
            <section class="space-y-4">
                <h3 class="text-sm font-black uppercase text-billpay-green flex items-center gap-2">
                    <span class="w-8 h-8 bg-billpay-green/10 rounded-full flex items-center justify-center text-xs">1</span>
                    Create a Google Cloud Project
                </h3>
                <p class="text-xs font-medium text-gray-600 leading-relaxed ml-10">
                    Go to the <a href="https://console.cloud.google.com/" target="_blank" class="text-billpay-green underline">Google Cloud Console</a>.
                    Click on the project dropdown at the top and select "New Project". Give it a name like "BillPay App" and click "Create".
                </p>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-black uppercase text-billpay-green flex items-center gap-2">
                    <span class="w-8 h-8 bg-billpay-green/10 rounded-full flex items-center justify-center text-xs">2</span>
                    Configure OAuth Consent Screen
                </h3>
                <p class="text-xs font-medium text-gray-600 leading-relaxed ml-10">
                    Navigate to <strong>APIs & Services > OAuth consent screen</strong>.
                    Select "External" and click "Create".
                    Fill in the "App information" (App name, User support email) and "Developer contact info".
                    In the "Scopes" step, add <code>.../auth/userinfo.email</code> and <code>.../auth/userinfo.profile</code>.
                </p>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-black uppercase text-billpay-green flex items-center gap-2">
                    <span class="w-8 h-8 bg-billpay-green/10 rounded-full flex items-center justify-center text-xs">3</span>
                    Create Credentials
                </h3>
                <p class="text-xs font-medium text-gray-600 leading-relaxed ml-10">
                    Go to <strong>APIs & Services > Credentials</strong>.
                    Click <strong>Create Credentials > OAuth client ID</strong>.
                </p>
                <div class="bg-gray-50 p-6 rounded-3xl border border-gray-100 ml-10 space-y-4">
                    <div>
                        <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Application Type</div>
                        <div class="font-bold text-sm">Web application</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Authorized JavaScript origins</div>
                        <div class="font-mono text-xs text-billpay-green"><?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Authorized redirect URIs</div>
                        <div class="font-mono text-xs text-billpay-green"><?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/google-callback.php"; ?></div>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-black uppercase text-billpay-green flex items-center gap-2">
                    <span class="w-8 h-8 bg-billpay-green/10 rounded-full flex items-center justify-center text-xs">4</span>
                    Save Client ID & Secret
                </h3>
                <p class="text-xs font-medium text-gray-600 leading-relaxed ml-10">
                    Once created, Google will show your <strong>Client ID</strong> and <strong>Client Secret</strong>.
                    Copy these and paste them into the <a href="/admin/login-security" class="text-billpay-green underline">Login Security Settings</a> page and enable Google SSO.
                </p>
            </section>
        </div>
    </div>

    <div class="flex justify-center">
        <a href="/admin/login-security" class="px-10 py-5 bg-gray-900 text-white rounded-[32px] font-black uppercase text-xs shadow-xl hover:bg-black transition-all">Go to Security Settings</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
