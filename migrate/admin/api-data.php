<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Data API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
        $provider = sanitize($_POST['provider']);
        $testRes = testDataConnection($pdo, $provider);
        if ($testRes['status'] === 'success') $success = "Test Successful: " . $testRes['message'];
        else $error = "Test Failed: " . $testRes['message'];
    }

    $dataSettings = [
        'providers' => [
            'datagifting' => ['apiKey' => $_POST['dg_apiKey']],
            'nellobyte' => ['userId' => $_POST['nb_userId'], 'apiKey' => $_POST['nb_apiKey']],
            'datastation' => ['token' => $_POST['ds_token']]
        ],
        'routing' => [
            'MTN' => $_POST['route_mtn'],
            'Airtel' => $_POST['route_airtel'],
            'Glo' => $_POST['route_glo'],
            '9mobile' => $_POST['route_9mobile']
        ]
    ];

    if (isset($_POST['action']) && $_POST['action'] === 'add_plan') {
        $stmt = $pdo->prepare("INSERT INTO data_plans (network, plan_id, data_size, type, api_price, user_price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($_POST['plan_network']),
            sanitize($_POST['plan_apiCode']),
            sanitize($_POST['plan_size']),
            sanitize($_POST['plan_type']),
            (float)$_POST['plan_apiPrice'],
            (float)$_POST['plan_userPrice']
        ]);
        $success = "New data plan added!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_vtpass_data') {
        $network = sanitize($_POST['fetch_network']);
        $serviceId = strtolower($network) . '-data';
        $res = vtpassGetVariations($pdo, $serviceId);
        $vars = (is_array($res) && isset($res['content'])) ? ($res['content']['varations'] ?? $res['content']['variations'] ?? null) : null;
        if (is_array($vars)) {
            $count = 0;
            foreach ($vars as $v) {
                $stmt = $pdo->prepare("INSERT INTO data_plans (network, plan_id, data_size, type, api_price, user_price) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE data_size = VALUES(data_size), api_price = VALUES(api_price)");
                $stmt->execute([
                    $network,
                    $v['variation_code'] ?? $v['id'] ?? '',
                    $v['name'] ?? '',
                    'DataBundle',
                    (float)($v['variation_amount'] ?? 0),
                    (float)($v['variation_amount'] ?? 0)
                ]);
                $count++;
            }
            $success = "Successfully fetched and updated $count plans for $network!";
        } else {
            $error = "Failed to fetch from VTpass: " . (is_array($res) ? ($res['response_description'] ?? 'No variations found') : 'Unknown error');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_nb_data') {
        $res = nellobyteGetDataPlans($pdo);
        if (is_array($res) && isset($res['MOBILE_NETWORK'])) {
            $count = 0;
            $networks = ['MTN', 'AIRTEL', 'GLO', '9MOBILE'];
            foreach ($networks as $net) {
                if (isset($res['MOBILE_NETWORK'][$net]) && is_array($res['MOBILE_NETWORK'][$net])) {
                    foreach ($res['MOBILE_NETWORK'][$net] as $cat) {
                        if (isset($cat['PRODUCT']) && is_array($cat['PRODUCT'])) {
                            foreach ($cat['PRODUCT'] as $p) {
                                $stmt = $pdo->prepare("INSERT INTO data_plans (network, plan_id, data_size, type, api_price, user_price) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE data_size = VALUES(data_size), api_price = VALUES(api_price)");
                                $stmt->execute([
                                    ucfirst(strtolower($net)),
                                    $p['PRODUCT_CODE'] ?? '',
                                    $p['PRODUCT_NAME'] ?? '',
                                    'DataBundle',
                                    (float)($p['PRODUCT_AMOUNT'] ?? 0),
                                    (float)($p['PRODUCT_AMOUNT'] ?? 0)
                                ]);
                                $count++;
                            }
                        }
                    }
                }
            }
            $success = "Successfully fetched and updated $count Nellobyte plans across all networks!";
        } else {
            $desc = is_array($res) ? ($res['status'] ?? 'Invalid Response') : (is_string($res) ? substr($res, 0, 100) : 'Unknown error');
            $error = "Failed to fetch from Nellobyte: " . $desc;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_plan') {
        $stmt = $pdo->prepare("DELETE FROM data_plans WHERE id = ?");
        $stmt->execute([$_POST['plan_id']]);
        $success = "Data plan deleted!";
    } else {
        $dataSettings = [
            'providers' => [
                'datagifting' => ['apiKey' => $_POST['dg_apiKey']],
                'nellobyte' => ['userId' => $_POST['nb_userId'], 'apiKey' => $_POST['nb_apiKey']],
                'datastation' => ['token' => $_POST['ds_token']]
            ],
            'routing' => [
                'MTN' => $_POST['route_mtn'],
                'Airtel' => $_POST['route_airtel'],
                'Glo' => $_POST['route_glo'],
                '9mobile' => $_POST['route_9mobile']
            ]
        ];

        $stmt = $pdo->prepare("UPDATE settings SET dataSettings = ? WHERE id = 1");
        $stmt->execute([json_encode($dataSettings)]);
        $success = "Data settings updated!";
    }
    $settings = fetchSettings($pdo);
}

$ds = $settings['dataSettings'] ?? [];
if (is_string($ds)) $ds = json_decode($ds, true) ?: [];

if (empty($ds)) {
    $ds = [
        'providers' => ['datagifting' => ['apiKey' => ''], 'nellobyte' => ['userId' => '', 'apiKey' => ''], 'datastation' => ['token' => '']],
        'routing' => ['MTN' => 'datagifting', 'Airtel' => 'datagifting', 'Glo' => 'datagifting', '9mobile' => 'datagifting']
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Data API Gateway</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Provider Credentials -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-billpay-green">
                        <i data-lucide="key" class="w-5 h-5"></i> DataGifting (1)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='datagifting'" class="text-[10px] font-black text-billpay-green uppercase hover:underline">Test</button>
                </div>
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                <input type="password" name="dg_apiKey" value="<?php echo $ds['providers']['datagifting']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-orange-500">
                        <i data-lucide="key" class="w-5 h-5"></i> Nellobyte (2)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='nellobyte'" class="text-[10px] font-black text-orange-500 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">User ID</label>
                        <input type="text" name="nb_userId" value="<?php echo $ds['providers']['nellobyte']['userId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="nb_apiKey" value="<?php echo $ds['providers']['nellobyte']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-blue-600">
                        <i data-lucide="key" class="w-5 h-5"></i> Datastationapi (3)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='datastation'" class="text-[10px] font-black text-blue-600 uppercase hover:underline">Test</button>
                </div>
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Token</label>
                <input type="password" name="ds_token" value="<?php echo $ds['providers']['datastation']['token'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
            </div>
        </div>
        <input type="hidden" name="provider" value="">

        <!-- Routing -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3">
                <i data-lucide="route" class="text-indigo-500"></i> Global Network Routing
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach (['MTN', 'Airtel', 'Glo', '9mobile'] as $net): ?>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1"><?php echo $net; ?> Route</label>
                    <select name="route_<?php echo strtolower($net); ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        <option value="datagifting" <?php echo ($ds['routing'][$net] ?? '') === 'datagifting' ? 'selected' : ''; ?>>DataGifting</option>
                        <option value="nellobyte" <?php echo ($ds['routing'][$net] ?? '') === 'nellobyte' ? 'selected' : ''; ?>>Nellobyte</option>
                        <option value="datastation" <?php echo ($ds['routing'][$net] ?? '') === 'datastation' ? 'selected' : ''; ?>>Datastationapi</option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-amber-50 p-8 rounded-[40px] border border-amber-100">
            <div class="flex gap-4">
                <i data-lucide="info" class="w-6 h-6 text-amber-500"></i>
                <div>
                    <h4 class="text-sm font-black text-amber-900 uppercase">Data Pricing Note</h4>
                    <p class="text-xs font-bold text-amber-700 leading-relaxed mt-1">Data prices and bundles are managed per product in the main Data Service settings. Ensure the Product Codes match the respective provider requirements.</p>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Data Configuration</button>
    </form>

    <!-- Data Package Manager -->
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 mt-10">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3"><i data-lucide="package" class="text-orange-500"></i> Data Package Manager</h3>

            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_vtpass_data">
                <select name="fetch_network" class="p-2 bg-gray-50 rounded-lg font-bold outline-none text-[10px] border border-gray-100">
                    <option value="MTN">MTN</option>
                    <option value="Airtel">Airtel</option>
                    <option value="Glo">Glo</option>
                    <option value="9mobile">9mobile</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-billpay-green text-white rounded-lg font-black uppercase text-[8px] whitespace-nowrap">Fetch VTpass</button>
            </form>

            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_nb_data">
                <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-black uppercase text-[8px] whitespace-nowrap">Fetch All Nellobyte</button>
            </form>
        </div>

        <!-- Add Plan Form -->
        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-12 p-8 bg-gray-50 rounded-[32px] border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_plan">
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">Network</label>
                <select name="plan_network" class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
                    <option value="MTN">MTN</option>
                    <option value="Airtel">Airtel</option>
                    <option value="Glo">Glo</option>
                    <option value="9mobile">9mobile</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">Product ID (API Code)</label>
                <input type="text" name="plan_apiCode" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">Size (e.g. 1GB)</label>
                <input type="text" name="plan_size" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">Type (SME, CG, Gifting)</label>
                <input type="text" name="plan_type" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">API Cost (₦)</label>
                <input type="number" step="0.01" name="plan_apiPrice" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase">User Price (₦)</label>
                <input type="number" step="0.01" name="plan_userPrice" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-xs">
            </div>
            <div class="md:col-span-3 lg:col-span-2 flex items-end">
                <button type="submit" class="w-full bg-orange-500 text-white py-3 rounded-xl font-black uppercase text-xs shadow-lg hover:bg-orange-600 transition-all">Add New Plan</button>
            </div>
        </form>

        <!-- Plans Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                        <th class="pb-6">Network</th>
                        <th class="pb-6">Size/Type</th>
                        <th class="pb-6">API Code</th>
                        <th class="pb-6">Cost</th>
                        <th class="pb-6">User Price</th>
                        <th class="pb-6">Profit</th>
                        <th class="pb-6">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM data_plans ORDER BY network ASC, user_price ASC");
                    $allPlansCount = 0;
                    while ($p = $stmt->fetch()):
                        $allPlansCount++;
                        $profit = (float)$p['user_price'] - (float)$p['api_price'];
                    ?>
                    <tr>
                        <td class="py-6 font-black text-sm uppercase"><?php echo $p['network']; ?></td>
                        <td class="py-6">
                            <div class="font-black text-xs"><?php echo $p['data_size']; ?></div>
                            <div class="text-[8px] font-bold text-gray-400 uppercase"><?php echo $p['type']; ?></div>
                        </td>
                        <td class="py-6 font-mono text-xs"><?php echo $p['plan_id']; ?></td>
                        <td class="py-6 text-xs font-bold text-gray-500">₦<?php echo number_format($p['api_price'], 2); ?></td>
                        <td class="py-6 text-xs font-black">₦<?php echo number_format($p['user_price'], 2); ?></td>
                        <td class="py-6">
                            <span class="px-2 py-1 bg-green-50 text-green-600 text-[9px] font-black rounded-md">₦<?php echo number_format($profit, 2); ?></span>
                        </td>
                        <td class="py-6">
                            <form method="POST" onsubmit="return confirm('Delete this plan?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; if($allPlansCount === 0) echo '<tr><td colspan="7" class="py-10 text-center text-[10px] font-black text-gray-300 uppercase">No data plans configured</td></tr>'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
