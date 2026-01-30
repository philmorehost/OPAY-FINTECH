<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Google Auth Setup Guide';
require_once __DIR__ . '/header.php';
?>

<div class="max-w-4xl mx-auto space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center gap-4">
        <a href="login-security.php" class="p-3 bg-white rounded-2xl border border-gray-100 shadow-sm hover:bg-gray-50">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tight">Google API Configuration</h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Step-by-step guide to enable Google SSO</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
            <div class="space-y-4">
                <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black">01</div>
                <h3 class="text-lg font-black uppercase">Google Cloud Console</h3>
                <p class="text-xs font-bold text-gray-500 leading-relaxed uppercase">Visit the <a href="https://console.cloud.google.com/" target="_blank" class="text-billpay-green underline">Google Cloud Console</a> and create a new project (e.g., "BillPay SSO").</p>
            </div>

            <div class="space-y-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center font-black">02</div>
                <h3 class="text-lg font-black uppercase">OAuth Consent Screen</h3>
                <p class="text-xs font-bold text-gray-500 leading-relaxed uppercase">Go to "APIs & Services" > "OAuth consent screen". Choose "External", fill in your App Name and Support Email. Add "email" and "profile" scopes.</p>
            </div>

            <div class="space-y-4">
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center font-black">03</div>
                <h3 class="text-lg font-black uppercase">Create Credentials</h3>
                <p class="text-xs font-bold text-gray-500 leading-relaxed uppercase">Go to "Credentials" > "Create Credentials" > "OAuth client ID". Select "Web application".</p>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
            <div class="space-y-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center font-black">04</div>
                <h3 class="text-lg font-black uppercase">Authorized Redirect URIs</h3>
                <p class="text-xs font-bold text-gray-500 leading-relaxed uppercase mb-4">You MUST add the following URL to the "Authorized redirect URIs" section:</p>
                <div class="p-4 bg-gray-900 text-billpay-green rounded-2xl font-mono text-[10px] break-all shadow-lg border-l-4 border-billpay-green">
                    https://<?php echo $_SERVER['HTTP_HOST']; ?>/google-callback.php
                </div>
            </div>

            <div class="space-y-4">
                <div class="w-12 h-12 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center font-black">05</div>
                <h3 class="text-lg font-black uppercase">Copy Keys</h3>
                <p class="text-xs font-bold text-gray-500 leading-relaxed uppercase">Once created, copy the "Client ID" and "Client Secret" and paste them into the Login Security settings in your Admin Hub.</p>
            </div>

            <div class="p-6 bg-green-50 rounded-3xl border border-green-100">
                <div class="flex gap-4">
                    <i data-lucide="check-circle" class="text-green-500 w-6 h-6"></i>
                    <div>
                        <h4 class="text-xs font-black uppercase text-green-900">Ready to go</h4>
                        <p class="text-[9px] font-bold text-green-700 uppercase mt-1">Once enabled, users will see the "Sign in with Google" button on both Login and Register pages.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
