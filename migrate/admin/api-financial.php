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
        }
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
                'cryptoCharges' => [
                    'buy' => (float)$_POST['jw_crypto_buy_charge'],
                    'sell' => (float)$_POST['jw_crypto_sell_charge'],
                    'swap' => (float)$_POST['jw_crypto_swap_charge'],
                    'withdraw' => (float)$_POST['jw_crypto_withdraw_charge']
                ]
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
        'juicyway' => ['apiKey' => '', 'merchantId' => ''],
        'virtual_card_fee' => 1000
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Financial & Banking API</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
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

            <!-- JuicyWay -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-purple-600">
                        <i data-lucide="layout-grid" class="w-5 h-5"></i> JuicyWay (Crypto & Cards)
                    </h3>
                    <button type="submit" name="action" value="test_jw" class="text-[10px] font-black uppercase text-billpay-green hover:underline">Test API</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="jw_apiKey" value="<?php echo $fs['juicyway']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Business ID (For Webhook verification)</label>
                        <input type="text" name="jw_businessId" value="<?php echo $fs['juicyway']['businessId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1 text-purple-500">Webhook URL (Set in JuicyWay Dashboard)</label>
                        <input type="text" readonly value="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/webhook-juicyway.php"; ?>" class="w-full p-4 bg-gray-100 rounded-2xl font-mono text-[10px] mt-1 outline-none border border-transparent">
                    </div>

                    <div class="pt-4 border-t border-gray-50">
                        <h4 class="text-[10px] font-black uppercase text-gray-400 mb-4">Crypto Service Charges (%)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Buy Charge</label>
                                <input type="number" step="0.01" name="jw_crypto_buy_charge" value="<?php echo $fs['juicyway']['cryptoCharges']['buy'] ?? 0; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Sell Charge</label>
                                <input type="number" step="0.01" name="jw_crypto_sell_charge" value="<?php echo $fs['juicyway']['cryptoCharges']['sell'] ?? 0; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Swap Charge</label>
                                <input type="number" step="0.01" name="jw_crypto_swap_charge" value="<?php echo $fs['juicyway']['cryptoCharges']['swap'] ?? 0; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Withdraw Charge</label>
                                <input type="number" step="0.01" name="jw_crypto_withdraw_charge" value="<?php echo $fs['juicyway']['cryptoCharges']['withdraw'] ?? 0; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-50">
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Card Issuance Fee (NGN)</label>
                        <input type="number" name="vc_fee" value="<?php echo $settings['vcardIssuanceFee'] ?? 1000; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Financial Configuration</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
