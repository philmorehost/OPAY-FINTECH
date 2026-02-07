<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Other API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
        $provider = sanitize($_POST['provider']);
        $testRes = testOtherConnection($pdo, $provider);
        if ($testRes['status'] === 'success') $success = "Test Successful: " . $testRes['message'];
        else $error = "Test Failed: " . $testRes['message'];
    }

    $os = $settings['otherApiSettings'] ?? [];
    if (is_string($os)) $os = json_decode($os, true) ?: [];

    $os['kudisms'] = [
        'token' => $_POST['ks_token'],
        'sender' => $_POST['ks_sender']
    ];
    $os['reloadly'] = [
        'clientId' => $_POST['rl_clientId'],
        'clientSecret' => $_POST['rl_clientSecret']
    ];
    $os['paypal'] = [
        'clientId' => $_POST['pp_clientId'],
        'clientSecret' => $_POST['pp_clientSecret'],
        'liveMode' => isset($_POST['pp_liveMode']) ? 1 : 0
    ];
    $os['flutterwave'] = [
        'publicKey' => $_POST['fw_publicKey'],
        'secretKey' => $_POST['fw_secretKey'],
        'encryptionKey' => $_POST['fw_encryptionKey']
    ];
    $os['coingecko'] = [
        'apiKey' => $_POST['cg_apiKey']
    ];
    $os['simulationMode'] = isset($_POST['simulationMode']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ?, smsRate = ?, smsApiRate = ? WHERE id = 1");
    $stmt->execute([json_encode($os), (float)$_POST['smsRate'], (float)$_POST['smsApiRate']]);
    $success = "Other API settings updated!";
    $settings = fetchSettings($pdo);
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

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

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
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Rate (Cost)</label>
                            <input type="number" step="0.01" name="smsApiRate" value="<?php echo $settings['smsApiRate'] ?? 3.50; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">User Rate (Sale)</label>
                            <input type="number" step="0.01" name="smsRate" value="<?php echo $settings['smsRate'] ?? 4.50; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                    </div>
                </div>
            </div>

            <!-- PayPal -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-blue-700">
                        <i data-lucide="credit-card" class="w-5 h-5"></i> PayPal (Global)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='paypal'" class="text-[10px] font-black text-blue-700 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client ID</label>
                        <input type="text" name="pp_clientId" value="<?php echo $os['paypal']['clientId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Client Secret</label>
                        <input type="password" name="pp_clientSecret" value="<?php echo $os['paypal']['clientSecret'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                        <span class="text-[10px] font-black uppercase text-gray-500">Live Mode</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="pp_liveMode" value="1" class="sr-only peer" <?php echo ($os['paypal']['liveMode'] ?? 0) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-billpay-green"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Flutterwave -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-orange-500">
                        <i data-lucide="zap" class="w-5 h-5"></i> Flutterwave
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='flutterwave'" class="text-[10px] font-black text-orange-500 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Public Key</label>
                        <input type="text" name="fw_publicKey" value="<?php echo $os['flutterwave']['publicKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Secret Key</label>
                        <input type="password" name="fw_secretKey" value="<?php echo $os['flutterwave']['secretKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Encryption Key</label>
                        <input type="password" name="fw_encryptionKey" value="<?php echo $os['flutterwave']['encryptionKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <!-- Reloadly -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-red-600">
                        <i data-lucide="gift" class="w-5 h-5"></i> Reloadly (Gift Cards)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='reloadly'" class="text-[10px] font-black text-red-600 uppercase hover:underline">Test</button>
                </div>
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

        <input type="hidden" name="provider" value="">
        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Global API Settings</button>
    </form>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
