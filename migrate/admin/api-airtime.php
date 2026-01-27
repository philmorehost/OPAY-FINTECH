<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Airtime API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $airtimeSettings = [
        'providers' => [
            'datagifting' => ['apiKey' => $_POST['dg_apiKey'], 'discount' => $_POST['dg_discount']],
            'nellobyte' => ['userId' => $_POST['nb_userId'], 'apiKey' => $_POST['nb_apiKey'], 'discount' => $_POST['nb_discount']],
            'hdkdata' => ['token' => $_POST['hdk_token'], 'discount' => $_POST['hdk_discount']]
        ],
        'routing' => [
            'MTN' => $_POST['route_mtn'],
            'Airtel' => $_POST['route_airtel'],
            'Glo' => $_POST['route_glo'],
            '9mobile' => $_POST['route_9mobile']
        ]
    ];

    $stmt = $pdo->prepare("UPDATE settings SET airtimeSettings = ? WHERE id = 1");
    $stmt->execute([json_encode($airtimeSettings)]);

    // Update user-facing discounts
    $userDiscounts = [
        'MTN' => $_POST['user_discount_mtn'],
        'Airtel' => $_POST['user_discount_airtel'],
        'Glo' => $_POST['user_discount_glo'],
        '9mobile' => $_POST['user_discount_9mobile']
    ];
    $stmt = $pdo->prepare("UPDATE settings SET airtimeDiscounts = ? WHERE id = 1");
    $stmt->execute([json_encode($userDiscounts)]);

    $success = "Airtime settings and routing updated!";
    $settings = fetchSettings($pdo);
}

$as = $settings['airtimeSettings'] ?? [];
if (is_string($as)) $as = json_decode($as, true) ?: [];

if (empty($as)) {
    $as = [
        'providers' => [
            'datagifting' => ['apiKey' => '', 'discount' => 2.06],
            'nellobyte' => ['userId' => '', 'apiKey' => '', 'discount' => 2],
            'hdkdata' => ['token' => '', 'discount' => 2]
        ],
        'routing' => ['MTN' => 'datagifting', 'Airtel' => 'datagifting', 'Glo' => 'datagifting', '9mobile' => 'datagifting']
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Airtime API Gateway</h2>
    </div>

    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Provider Credentials -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-billpay-green">
                    <i data-lucide="key" class="w-5 h-5"></i> DataGifting (1)
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="dg_apiKey" value="<?php echo $as['providers']['datagifting']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Your API Discount (%)</label>
                        <input type="number" step="0.01" name="dg_discount" value="<?php echo $as['providers']['datagifting']['discount'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-orange-500">
                    <i data-lucide="key" class="w-5 h-5"></i> Nellobyte (2)
                </h3>
                <div class="space-y-4">
                    <div><label class="text-[10px] font-black text-gray-400 uppercase ml-1">User ID</label><input type="text" name="nb_userId" value="<?php echo $as['providers']['nellobyte']['userId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none"></div>
                    <div><label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label><input type="password" name="nb_apiKey" value="<?php echo $as['providers']['nellobyte']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none"></div>
                    <div><label class="text-[10px] font-black text-gray-400 uppercase ml-1">Your API Discount (%)</label><input type="number" step="0.01" name="nb_discount" value="<?php echo $as['providers']['nellobyte']['discount'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none"></div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-blue-600">
                    <i data-lucide="key" class="w-5 h-5"></i> HDKData (3)
                </h3>
                <div class="space-y-4">
                    <div><label class="text-[10px] font-black text-gray-400 uppercase ml-1">Token</label><input type="password" name="hdk_token" value="<?php echo $as['providers']['hdkdata']['token'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none"></div>
                    <div><label class="text-[10px] font-black text-gray-400 uppercase ml-1">Your API Discount (%)</label><input type="number" step="0.01" name="hdk_discount" value="<?php echo $as['providers']['hdkdata']['discount'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none"></div>
                </div>
            </div>
        </div>

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
                            $apiDisc = (float)($as['providers'][$route]['discount'] ?? 0);
                            $userDisc = (float)($settings['airtimeDiscounts'][$net] ?? 0);
                        ?>
                        <tr x-data="{ userDisc: <?php echo $userDisc; ?>, apiDisc: <?php echo $apiDisc; ?> }">
                            <td class="py-6 font-black text-lg"><?php echo $net; ?></td>
                            <td class="py-6">
                                <select name="route_<?php echo strtolower($net); ?>" class="p-3 bg-gray-50 rounded-xl font-bold outline-none text-xs border border-transparent focus:border-billpay-green">
                                    <option value="datagifting" <?php echo $route === 'datagifting' ? 'selected' : ''; ?>>DataGifting</option>
                                    <option value="nellobyte" <?php echo $route === 'nellobyte' ? 'selected' : ''; ?>>Nellobyte</option>
                                    <option value="hdkdata" <?php echo $route === 'hdkdata' ? 'selected' : ''; ?>>HDKData</option>
                                </select>
                            </td>
                            <td class="py-6">
                                <div class="flex items-center gap-2 text-xs font-black text-gray-400">
                                    <span x-text="apiDisc.toFixed(2) + '%'"></span>
                                    <i data-lucide="info" class="w-3 h-3 opacity-30"></i>
                                </div>
                            </td>
                            <td class="py-6">
                                <input type="number" step="0.01" name="user_discount_<?php echo strtolower($net); ?>" x-model="userDisc" class="w-24 p-3 bg-gray-50 rounded-xl font-black text-xs outline-none border-2 border-transparent focus:border-billpay-green">
                            </td>
                            <td class="py-6">
                                <div class="inline-flex px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest" :class="(apiDisc - userDisc) >= 0 ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-500'">
                                    <span x-text="((apiDisc - userDisc) >= 0 ? '+' : '') + (apiDisc - userDisc).toFixed(2) + '%'"></span>
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
