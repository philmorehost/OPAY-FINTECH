<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Utility API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
        $provider = sanitize($_POST['provider']);
        $testRes = testUtilityConnection($pdo, $provider);
        if ($testRes['status'] === 'success') $success = "Test Successful: " . $testRes['message'];
        else $error = "Test Failed: " . $testRes['message'];
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_packages') {
        $serviceId = sanitize($_POST['service_id']);
        $category = sanitize($_POST['category']);
        $res = vtpassGetVariations($pdo, $serviceId);
        if (isset($res['content']['varations'])) {
            $vars = $res['content']['varations'];
            foreach ($vars as $v) {
                // Use $serviceId as provider name (e.g., dstv, gotv, ikeja-electric)
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, package_id, name, api_price, user_price) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                $stmt->execute([$category, $serviceId, $v['variation_code'], $v['name'], (float)$v['variation_amount'], (float)$v['variation_amount']]);
            }
            $success = "Fetched " . count($vars) . " packages for $serviceId";
        } else {
            $error = "Failed to fetch packages: " . ($res['response_description'] ?? 'Unknown error');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_package') {
        $id = (int)$_POST['package_id'];
        $apiDisc = (float)$_POST['api_discount'];
        $userDisc = (float)$_POST['user_discount'];
        $userPrice = (float)$_POST['user_price'];
        $stmt = $pdo->prepare("UPDATE utility_packages SET api_discount = ?, user_discount = ?, user_price = ? WHERE id = ?");
        $stmt->execute([$apiDisc, $userDisc, $userPrice, $id]);
        $success = "Package updated!";
    }

    $utilSettings = [
        'vtpass' => [
            'apiKey' => $_POST['vt_apiKey'],
            'secretKey' => $_POST['vt_secretKey'],
            'publicKey' => $_POST['vt_publicKey'],
            'username' => $_POST['vt_username'],
            'password' => $_POST['vt_password']
        ],
        'nellobyte' => [
            'userId' => $_POST['nb_userId'],
            'apiKey' => $_POST['nb_apiKey']
        ],
        'routing' => [
            'cable' => $_POST['route_cable'],
            'electric' => $_POST['route_electric'],
            'betting' => $_POST['route_betting'],
            'exam' => $_POST['route_exam']
        ]
    ];

    $stmt = $pdo->prepare("UPDATE settings SET utilitySettings = ? WHERE id = 1");
    $stmt->execute([json_encode($utilSettings)]);
    $success = "Utility settings updated!";
    $settings = fetchSettings($pdo);
}

$us = $settings['utilitySettings'] ?? [];
if (is_string($us)) $us = json_decode($us, true) ?: [];

if (empty($us)) {
    $us = [
        'vtpass' => ['apiKey' => '', 'secretKey' => '', 'publicKey' => ''],
        'nellobyte' => ['userId' => '', 'apiKey' => ''],
        'routing' => ['cable' => 'vtpass', 'electric' => 'vtpass', 'betting' => 'vtpass', 'exam' => 'vtpass']
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Utility API Gateway</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- VTPass -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-blue-600">
                        <i data-lucide="zap" class="w-5 h-5"></i> VTPass Integration
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='vtpass'" class="text-[10px] font-black text-blue-600 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="vt_apiKey" value="<?php echo $us['vtpass']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Secret Key</label>
                        <input type="password" name="vt_secretKey" value="<?php echo $us['vtpass']['secretKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Public Key</label>
                        <input type="text" name="vt_publicKey" value="<?php echo $us['vtpass']['publicKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Username (Alt)</label>
                            <input type="text" name="vt_username" value="<?php echo $us['vtpass']['username'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Password (Alt)</label>
                            <input type="password" name="vt_password" value="<?php echo $us['vtpass']['password'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nellobyte Utilities -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-orange-500">
                        <i data-lucide="database" class="w-5 h-5"></i> Nellobyte Utilities
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='nellobyte'" class="text-[10px] font-black text-orange-500 uppercase hover:underline">Test</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">User ID</label>
                        <input type="text" name="nb_userId" value="<?php echo $us['nellobyte']['userId'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                        <input type="password" name="nb_apiKey" value="<?php echo $us['nellobyte']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                    </div>
                </div>
            </div>
        </div>

        <!-- Routing -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3">
                <i data-lucide="route" class="text-indigo-500"></i> Service Routing
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach (['Cable', 'Electric', 'Betting', 'Exam'] as $svc): ?>
                <?php $key = strtolower($svc); ?>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1"><?php echo $svc; ?> Route</label>
                    <select name="route_<?php echo $key; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                        <option value="vtpass" <?php echo ($us['routing'][$key] ?? '') === 'vtpass' ? 'selected' : ''; ?>>VTPass</option>
                        <option value="nellobyte" <?php echo ($us['routing'][$key] ?? '') === 'nellobyte' ? 'selected' : ''; ?>>Nellobyte</option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <input type="hidden" name="provider" value="">
        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Utility Configuration</button>
    </form>

    <!-- Package Manager -->
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 mt-10">
        <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="package" class="text-orange-500"></i> Cable & Electricity Package Manager</h3>

        <form method="POST" class="flex items-center gap-4 mb-10 bg-gray-50 p-6 rounded-[28px] border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="fetch_packages">
            <select name="category" class="p-3 bg-white rounded-xl font-bold outline-none text-xs border border-gray-100">
                <option value="cable">Cable TV</option>
                <option value="electric">Electricity</option>
            </select>
            <select name="service_id" class="p-3 bg-white rounded-xl font-bold outline-none text-xs border border-gray-100">
                <optgroup label="Cable">
                    <option value="dstv">DSTV</option>
                    <option value="gotv">GOTV</option>
                    <option value="startimes">Startimes</option>
                    <option value="showmax">Showmax</option>
                </optgroup>
                <optgroup label="Electric">
                    <option value="ikeja-electric">Ikeja Electric</option>
                    <option value="eko-electric">Eko Electric</option>
                    <option value="kano-electric">Kano Electric</option>
                    <option value="portharcourt-electric">Port Harcourt Electric</option>
                    <option value="jos-electric">Jos Electric</option>
                    <option value="ibadan-electric">Ibadan Electric</option>
                    <option value="kaduna-electric">Kaduna Electric</option>
                    <option value="abuja-electric">Abuja Electric</option>
                    <option value="enugu-electric">Enugu Electric</option>
                    <option value="benin-electric">Benin Electric</option>
                    <option value="yola-electric">Yola Electric</option>
                </optgroup>
            </select>
            <button type="submit" class="px-6 py-3 bg-billpay-green text-white rounded-xl font-black uppercase text-[10px]">Fetch Packages from VTPass</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                        <th class="pb-6">Category</th>
                        <th class="pb-6">Provider/Name</th>
                        <th class="pb-6">API Price</th>
                        <th class="pb-6">API Disc (%)</th>
                        <th class="pb-6">User Price</th>
                        <th class="pb-6">User Disc (%)</th>
                        <th class="pb-6">Profit</th>
                        <th class="pb-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM utility_packages WHERE category IN ('cable', 'electric') ORDER BY category, provider, name");
                    while ($p = $stmt->fetch()):
                        $profit = ($p['user_price'] - ($p['api_price'] * (1 - $p['api_discount']/100))) + ($p['api_price'] * ($p['user_discount']/100)); // Simplified
                        // Actually, profit = (Selling Price) - (API Cost)
                        // API Cost = API Price * (1 - api_discount/100)
                        // Selling Price = User Price * (1 - user_discount/100) -- if it's a discount on price
                        // But usually User Discount means user pays less.
                        $apiCost = $p['api_price'] * (1 - $p['api_discount'] / 100);
                        $sellingPrice = $p['user_price'] * (1 - $p['user_discount'] / 100);
                        $profitVal = $sellingPrice - $apiCost;
                    ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_package">
                        <input type="hidden" name="package_id" value="<?php echo $p['id']; ?>">
                        <tr>
                            <td class="py-6 font-black text-[10px] uppercase text-gray-400"><?php echo $p['category']; ?></td>
                            <td class="py-6">
                                <div class="font-black text-xs uppercase"><?php echo $p['provider']; ?></div>
                                <div class="text-[10px] font-bold text-gray-400"><?php echo $p['name']; ?></div>
                            </td>
                            <td class="py-6 font-black text-xs">₦<?php echo number_format($p['api_price'], 2); ?></td>
                            <td class="py-6"><input type="number" step="0.01" name="api_discount" value="<?php echo $p['api_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><input type="number" step="0.01" name="user_price" value="<?php echo $p['user_price']; ?>" class="w-24 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><input type="number" step="0.01" name="user_discount" value="<?php echo $p['user_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><span class="px-2 py-1 <?php echo $profitVal >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-[9px] font-black rounded-md">₦<?php echo number_format($profitVal, 2); ?></span></td>
                            <td class="py-6 text-right"><button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-[8px] font-black uppercase">Save</button></td>
                        </tr>
                    </form>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
