<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Financial API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'test_jw') {
        $testRes = testJuicywayConnection($pdo);
        if ($testRes['status'] === 'success') {
            $success = "JuicyWay Connection Successful: " . ($testRes['message'] ?? 'Connected');
        } else {
            $error = "JuicyWay Connection Failed: " . ($testRes['message'] ?? 'Unknown Error');
            if (!empty($testRes['debug'])) {
                $error .= "<br><div class='mt-2 p-2 bg-black/10 rounded text-[8px] lowercase text-left overflow-auto max-h-40 font-mono'>" . print_r($testRes['debug'], true) . "</div>";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_bybit') {
        $testRes = testBybitConnection($pdo);
        if ($testRes['status'] === 'success') {
            $success = $testRes['message'];
        } else {
            $error = $testRes['message'];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_mexc') {
        $testRes = testMexcConnection($pdo);
        if ($testRes['status'] === 'success') $success = $testRes['message'];
        else $error = $testRes['message'];
    } else {
        $finSettings = [
            'paystack' => [
                'secretKey' => $_POST['ps_secretKey'],
                'publicKey' => $_POST['ps_publicKey'],
                'webhookUrl' => $_POST['ps_webhookUrl']
            ],
            'juicyway' => [
                'apiKey' => $_POST['jw_apiKey'],
                'businessId' => $_POST['jw_businessId'],
                'webhookUrl' => $_POST['jw_webhookUrl'],
                'liveMode' => isset($_POST['jw_liveMode']) ? 1 : 0
            ],
            'bybit' => [
                'apiKey' => $_POST['bb_apiKey'],
                'apiSecret' => $_POST['bb_apiSecret'],
                'testnet' => isset($_POST['bb_testnet']) ? 1 : 0
            ],
            'mexc' => [
                'apiKey' => $_POST['mx_apiKey'],
                'apiSecret' => $_POST['mx_apiSecret']
            ],
            'primaryCrypto' => $_POST['primary_crypto'],
            'globalCharges' => [
                'airtime' => (float)$_POST['charge_airtime'],
                'data' => (float)$_POST['charge_data'],
                'cable' => (float)$_POST['charge_cable'],
                'electric' => (float)$_POST['charge_electric'],
                'betting' => (float)$_POST['charge_betting'],
                'exam' => (float)$_POST['charge_exam'],
                'crypto_buy' => (float)$_POST['charge_crypto_buy'],
                'crypto_sell' => (float)$_POST['charge_crypto_sell'],
                'crypto_swap' => (float)$_POST['charge_crypto_swap'],
                'crypto_withdraw' => (float)$_POST['charge_crypto_withdraw']
            ]
        ];

        $stmt = $pdo->prepare("UPDATE settings SET financialSettings = ?, vcardIssuanceFee = ? WHERE id = 1");
        $stmt->execute([json_encode($finSettings), $_POST['vc_fee']]);
        $success = "Financial settings updated!";
        $settings = fetchSettings($pdo);
    }
}

$fs = $settings['financialSettings'] ?? [];
if (is_string($fs)) $fs = json_decode($fs, true) ?: [];

if (empty($fs)) {
    $fs = [
        'paystack' => ['secretKey' => '', 'publicKey' => '', 'webhookUrl' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/webhook-paystack.php"],
        'juicyway' => ['apiKey' => '', 'businessId' => '', 'webhookUrl' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/webhook-juicyway.php", 'liveMode' => 0],
        'virtual_card_fee' => 1000
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Financial API Service Charges</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
            <!-- Paystack -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-billpay-green">
                    <i data-lucide="credit-card" class="w-5 h-5"></i> Paystack (Funding)
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Secret Key</label>
                        <input type="password" name="ps_secretKey" value="<?php echo $fs['paystack']['secretKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Public Key</label>
                        <input type="text" name="ps_publicKey" value="<?php echo $fs['paystack']['publicKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1 text-red-500">Webhook URL (Set in Paystack Dashboard)</label>
                        <input type="text" readonly name="ps_webhookUrl" value="<?php echo $fs['paystack']['webhookUrl'] ?? ''; ?>" class="w-full p-4 bg-gray-100 rounded-2xl font-mono text-[10px] mt-1 outline-none border border-transparent">
                    </div>
                </div>
            </div>

            <!-- Bybit API -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-amber-500">
                        <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Bybit API
                    </h3>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center cursor-pointer gap-2">
                            <input type="checkbox" name="bb_testnet" value="1" <?php echo !empty($fs['bybit']['testnet']) ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-8 h-4 bg-gray-200 rounded-full peer peer-checked:bg-amber-500 relative transition-all after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                            <span class="text-[10px] font-black uppercase peer-checked:text-amber-500 text-gray-400">Testnet</span>
                        </label>
                        <button type="submit" name="action" value="test_bybit" class="text-[10px] font-black uppercase text-amber-500 hover:underline">Test API</button>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="bb_apiKey" value="<?php echo $fs['bybit']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-amber-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Secret</label>
                        <input type="password" name="bb_apiSecret" value="<?php echo $fs['bybit']['apiSecret'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-amber-500">
                    </div>
                </div>
            </div>

            <!-- MEXC API -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-emerald-500">
                        <i data-lucide="activity" class="w-5 h-5"></i> MEXC API
                    </h3>
                    <button type="submit" name="action" value="test_mexc" class="text-[10px] font-black uppercase text-emerald-500 hover:underline">Test API</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="mx_apiKey" value="<?php echo $fs['mexc']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Secret</label>
                        <input type="password" name="mx_apiSecret" value="<?php echo $fs['mexc']['apiSecret'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-emerald-500">
                    </div>
                </div>
            </div>

            <!-- JuicyWay -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-purple-600">
                        <i data-lucide="layout-grid" class="w-5 h-5"></i> JuicyWay
                    </h3>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center cursor-pointer gap-2">
                            <input type="checkbox" name="jw_liveMode" value="1" <?php echo !empty($fs['juicyway']['liveMode']) ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-8 h-4 bg-gray-200 rounded-full peer peer-checked:bg-billpay-green relative transition-all after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                            <span class="text-[10px] font-black uppercase peer-checked:text-billpay-green text-gray-400">Live</span>
                        </label>
                        <button type="submit" name="action" value="test_jw" class="text-[10px] font-black uppercase text-billpay-green hover:underline">Test API</button>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="jw_apiKey" value="<?php echo $fs['juicyway']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Business ID</label>
                        <input type="text" name="jw_businessId" value="<?php echo $fs['juicyway']['businessId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1 text-red-500">Webhook URL</label>
                        <input type="text" readonly name="jw_webhookUrl" value="<?php echo $fs['juicyway']['webhookUrl'] ?? ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/webhook-juicyway.php"); ?>" class="w-full p-4 bg-gray-100 rounded-2xl font-mono text-[10px] mt-1 outline-none border border-transparent">
                    </div>
                    <div class="pt-4 border-t border-gray-50">
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Card Issuance Fee (NGN)</label>
                        <input type="number" name="vc_fee" value="<?php echo $settings['vcardIssuanceFee'] ?? 1000; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <!-- Provider Selection -->
            <div class="bg-gray-900 p-8 rounded-[40px] shadow-2xl text-white">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3">
                    <i data-lucide="settings-2" class="w-5 h-5 text-billpay-green"></i> System Routing
                </h3>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Primary Crypto Provider</label>
                    <select name="primary_crypto" class="w-full p-4 bg-white/10 rounded-2xl font-black mt-1 outline-none border border-transparent focus:border-billpay-green text-white">
                        <option value="bybit" <?php echo ($fs['primaryCrypto'] ?? 'bybit') === 'bybit' ? 'selected' : ''; ?> class="text-gray-900">Bybit Exchange</option>
                        <option value="mexc" <?php echo ($fs['primaryCrypto'] ?? '') === 'mexc' ? 'selected' : ''; ?> class="text-gray-900">MEXC Global</option>
                        <option value="juicyway" <?php echo ($fs['primaryCrypto'] ?? '') === 'juicyway' ? 'selected' : ''; ?> class="text-gray-900">JuicyWay (Elite)</option>
                    </select>
                </div>
                <p class="text-[9px] text-gray-500 mt-4 uppercase font-bold leading-relaxed">This selection determines which API is used for the 'Crypto Hub' balances, trading, and withdrawals.</p>
            </div>
        </div>

        <!-- Service Charges Manager -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3">
                <i data-lucide="percent" class="text-indigo-500"></i> Financial Service Charges
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php
                $chargeKeys = [
                    'crypto_buy' => 'Crypto Buy',
                    'crypto_sell' => 'Crypto Sell',
                    'crypto_swap' => 'Crypto Swap',
                    'crypto_withdraw' => 'Crypto Withdraw',
                    'vcard_deposit_fee' => 'VCard Deposit (NGN)'
                ];
                // Maintain hidden values for removed keys to prevent settings wipe
                $removedKeys = ['airtime', 'data', 'cable', 'electric', 'betting', 'exam'];
                foreach($removedKeys as $rk) echo '<input type="hidden" name="charge_'.$rk.'" value="'.($fs['globalCharges'][$rk] ?? 0).'">';

                foreach ($chargeKeys as $key => $label):
                    $isFlat = ($key === 'vcard_deposit_fee');
                ?>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1"><?php echo $label; ?> <?php echo $isFlat ? '(Flat ₦)' : '(%)'; ?></label>
                    <input type="number" step="0.01" name="charge_<?php echo $key; ?>" value="<?php echo $fs['globalCharges'][$key] ?? 0; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-black mt-1 outline-none border-2 border-transparent focus:border-indigo-500 text-center">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-8 p-6 bg-indigo-50 rounded-3xl border border-indigo-100 flex gap-4">
                <i data-lucide="shield-alert" class="w-6 h-6 text-indigo-500"></i>
                <p class="text-[10px] font-bold text-indigo-700 uppercase leading-relaxed">These charges are applied specifically to financial operations managed on this page. VTU/Utility charges are now managed per network/provider in their respective API hubs.</p>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Financial Configuration</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
