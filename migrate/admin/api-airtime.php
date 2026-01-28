<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Airtime API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
        $provider = sanitize($_POST['provider']);
        $testRes = testAirtimeConnection($pdo, $provider);
        if ($testRes['status'] === 'success') $success = "Test Successful: " . $testRes['message'];
        else $error = "Test Failed: " . $testRes['message'];
    } else {
        $airtimeSettings = [
            'providers' => [
                'datagifting' => ['apiKey' => $_POST['dg_apiKey']],
                'nellobyte' => ['userId' => $_POST['nb_userId'], 'apiKey' => $_POST['nb_apiKey']],
                'datastation' => ['token' => $_POST['ds_token']],
                'vtpass' => ['username' => $_POST['vtp_username'], 'password' => $_POST['vtp_password']]
            ],
            'routing' => [
                'MTN' => $_POST['route_mtn'],
                'Airtel' => $_POST['route_airtel'],
                'Glo' => $_POST['route_glo'],
                '9mobile' => $_POST['route_9mobile']
            ],
            'networkDiscounts' => [
                'MTN' => (float)$_POST['api_discount_mtn'],
                'Airtel' => (float)$_POST['api_discount_airtel'],
                'Glo' => (float)$_POST['api_discount_glo'],
                '9mobile' => (float)$_POST['api_discount_9mobile']
            ]
        ];

        $stmt = $pdo->prepare("UPDATE settings SET airtimeSettings = ? WHERE id = 1");
        $stmt->execute([json_encode($airtimeSettings)]);

        // Update user-facing discounts
        $userDiscounts = [
            'MTN' => (float)$_POST['user_discount_mtn'],
            'Airtel' => (float)$_POST['user_discount_airtel'],
            'Glo' => (float)$_POST['user_discount_glo'],
            '9mobile' => (float)$_POST['user_discount_9mobile']
        ];
        $stmt = $pdo->prepare("UPDATE settings SET airtimeDiscounts = ? WHERE id = 1");
        $stmt->execute([json_encode($userDiscounts)]);

        $success = "Airtime settings updated!";
        $settings = fetchSettings($pdo);
    }
}

$as = $settings['airtimeSettings'] ?? [];
if (is_string($as)) $as = json_decode($as, true) ?: [];

if (empty($as)) {
    $as = [
        'providers' => [
            'datagifting' => ['apiKey' => ''],
            'nellobyte' => ['userId' => '', 'apiKey' => ''],
            'datastation' => ['token' => ''],
            'vtpass' => ['username' => '', 'password' => '']
        ],
        'routing' => ['MTN' => 'datagifting', 'Airtel' => 'datagifting', 'Glo' => 'datagifting', '9mobile' => 'datagifting'],
        'networkDiscounts' => ['MTN' => 2.0, 'Airtel' => 2.0, 'Glo' => 2.0, '9mobile' => 2.0]
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Airtime API Gateway</h2>
    </div>

    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Provider Credentials -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[10px] font-black uppercase tracking-widest flex items-center gap-2 text-billpay-green"><i data-lucide="key" class="w-4 h-4"></i> DataGifting</h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='datagifting'" class="text-[8px] font-black text-billpay-green uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[8px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="dg_apiKey" value="<?php echo $as['providers']['datagifting']['apiKey'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green text-xs">
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[10px] font-black uppercase tracking-widest flex items-center gap-2 text-orange-500"><i data-lucide="key" class="w-4 h-4"></i> Nellobyte</h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='nellobyte'" class="text-[8px] font-black text-orange-500 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div><label class="text-[8px] font-black text-gray-400 uppercase ml-1">User ID</label><input type="text" name="nb_userId" value="<?php echo $as['providers']['nellobyte']['userId'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs"></div>
                    <div><label class="text-[8px] font-black text-gray-400 uppercase ml-1">API Key</label><input type="password" name="nb_apiKey" value="<?php echo $as['providers']['nellobyte']['apiKey'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs"></div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[10px] font-black uppercase tracking-widest flex items-center gap-2 text-blue-600"><i data-lucide="key" class="w-4 h-4"></i> Datastationapi</h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='datastation'" class="text-[8px] font-black text-blue-600 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div><label class="text-[8px] font-black text-gray-400 uppercase ml-1">Token</label><input type="password" name="ds_token" value="<?php echo $as['providers']['datastation']['token'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs"></div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[10px] font-black uppercase tracking-widest flex items-center gap-2 text-purple-600"><i data-lucide="key" class="w-4 h-4"></i> VTpass</h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='vtpass'" class="text-[8px] font-black text-purple-600 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div><label class="text-[8px] font-black text-gray-400 uppercase ml-1">Username (Email)</label><input type="text" name="vtp_username" value="<?php echo $as['providers']['vtpass']['username'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs"></div>
                    <div><label class="text-[8px] font-black text-gray-400 uppercase ml-1">Password</label><input type="password" name="vtp_password" value="<?php echo $as['providers']['vtpass']['password'] ?? ''; ?>" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs"></div>
                </div>
            </div>
        </div>
        <input type="hidden" name="provider" value="">

        <!-- Routing & Pricing Table -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="route" class="text-indigo-500"></i> Network Routing & Pricing</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                            <th class="pb-6">Network</th>
                            <th class="pb-6">Route Provider</th>
                            <th class="pb-6">API Discount (%)</th>
                            <th class="pb-6">User Discount (%)</th>
                            <th class="pb-6">Profit (%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach (['MTN', 'Airtel', 'Glo', '9mobile'] as $net): ?>
                        <?php
                            $route = $as['routing'][$net] ?? 'datagifting';
                            $apiDisc = (float)($as['networkDiscounts'][$net] ?? 0);
                            $userDisc = (float)($settings['airtimeDiscounts'][$net] ?? 0);
                        ?>
                        <tr x-data="{ userDisc: <?php echo $userDisc; ?>, apiDisc: <?php echo $apiDisc; ?> }">
                            <td class="py-6 font-black text-lg"><?php echo $net; ?></td>
                            <td class="py-6">
                                <select name="route_<?php echo strtolower($net); ?>" class="p-3 bg-gray-50 rounded-xl font-bold outline-none text-xs border border-transparent focus:border-billpay-green">
                                    <option value="datagifting" <?php echo $route === 'datagifting' ? 'selected' : ''; ?>>DataGifting</option>
                                    <option value="nellobyte" <?php echo $route === 'nellobyte' ? 'selected' : ''; ?>>Nellobyte</option>
                                    <option value="datastation" <?php echo $route === 'datastation' ? 'selected' : ''; ?>>Datastationapi</option>
                                    <option value="vtpass" <?php echo $route === 'vtpass' ? 'selected' : ''; ?>>VTpass</option>
                                </select>
                            </td>
                            <td class="py-6">
                                <input type="number" step="0.01" name="api_discount_<?php echo strtolower($net); ?>" x-model="apiDisc" class="w-24 p-3 bg-gray-50 rounded-xl font-black text-xs outline-none border-2 border-transparent focus:border-billpay-green">
                            </td>
                            <td class="py-6">
                                <input type="number" step="0.01" name="user_discount_<?php echo strtolower($net); ?>" x-model="userDisc" class="w-24 p-3 bg-gray-50 rounded-xl font-black text-xs outline-none border-2 border-transparent focus:border-billpay-green">
                            </td>
                            <td class="py-6">
                                <div class="inline-flex px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest" :class="(parseFloat(apiDisc) - parseFloat(userDisc)) >= 0 ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-500'">
                                    <span x-text="((parseFloat(apiDisc) - parseFloat(userDisc)) >= 0 ? '+' : '') + (parseFloat(apiDisc) - parseFloat(userDisc)).toFixed(2) + '%'"></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-6 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Airtime Configuration</button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
