<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Other API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'bulk_apply') {
        $bulkApiDisc = (float)$_POST['bulk_api_discount'];
        $bulkUserDisc = (float)$_POST['bulk_user_discount'];

        $os = $settings['otherApiSettings'] ?? [];
        if (is_string($os)) $os = json_decode($os, true) ?: [];

        $os['smsApiDiscount'] = $bulkApiDisc;
        $os['smsUserDiscount'] = $bulkUserDisc;

        $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ? WHERE id = 1");
        $stmt->execute([json_encode($os)]);
        $success = "Bulk SMS discounts applied!";
        $settings = fetchSettings($pdo);
    } else {

    $otherSettings = [
        'kudisms' => [
            'token' => $_POST['ks_token'],
            'sender' => $_POST['ks_sender']
        ],
        'reloadly' => [
            'clientId' => $_POST['rl_clientId'],
            'clientSecret' => $_POST['rl_clientSecret']
        ],
        'coingecko' => [
            'apiKey' => $_POST['cg_apiKey']
        ],
        'simulationMode' => isset($_POST['simulationMode']) ? 1 : 0
    ];

    $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ? WHERE id = 1");
    $stmt->execute([json_encode($otherSettings)]);
    $success = "Other API settings updated!";
    $settings = fetchSettings($pdo);
    }
}

$os = $settings['otherApiSettings'] ?? [];
if (is_string($os)) $os = json_decode($os, true) ?: [];

if (empty($os)) {
    $os = [
        'kudisms' => ['token' => '', 'sender' => 'BillPay'],
        'reloadly' => ['clientId' => '', 'clientSecret' => ''],
        'coingecko' => ['apiKey' => ''],
        'simulationMode' => 0
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Miscellaneous API Services</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Bulk Discount Section -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-indigo-600">
                    <i data-lucide="percent" class="w-5 h-5"></i> Bulk SMS Pricing
                </h3>
                <button type="submit" name="action" value="bulk_apply" class="text-[10px] font-black uppercase bg-indigo-50 text-indigo-600 px-6 py-2.5 rounded-xl border border-indigo-100 hover:bg-indigo-100 transition-all">Apply to Bulk SMS</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Bulk API Disc (%)</label>
                    <input type="number" step="0.01" name="bulk_api_discount" placeholder="e.g. 0.5" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Bulk User Disc (%)</label>
                    <input type="number" step="0.01" name="bulk_user_discount" placeholder="e.g. 0.0" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- KudiSMS -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-orange-600">
                    <i data-lucide="message-square" class="w-5 h-5"></i> KudiSMS (Bulk SMS)
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Token</label>
                        <input type="password" name="ks_token" value="<?php echo $os['kudisms']['token'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Default Sender Name</label>
                        <input type="text" name="ks_sender" value="<?php echo $os['kudisms']['sender'] ?? 'BillPay'; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <!-- Reloadly -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-red-600">
                    <i data-lucide="gift" class="w-5 h-5"></i> Reloadly (Gift Cards)
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client ID</label>
                        <input type="text" name="rl_clientId" value="<?php echo $os['reloadly']['clientId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client Secret</label>
                        <input type="password" name="rl_clientSecret" value="<?php echo $os['reloadly']['clientSecret'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <!-- CoinGecko -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-green-600">
                    <i data-lucide="trending-up" class="w-5 h-5"></i> CoinGecko (Crypto Rates)
                </h3>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key (Optional for Demo)</label>
                    <input type="password" name="cg_apiKey" value="<?php echo $os['coingecko']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                </div>
            </div>

            <!-- Simulation -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-900">API Simulation Mode</h3>
                    <p class="text-[10px] font-bold text-gray-400 mt-1 uppercase">Bypass real API calls for testing</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="simulationMode" value="1" class="sr-only peer" <?php echo ($os['simulationMode'] ?? 0) ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                </label>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Global API Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
