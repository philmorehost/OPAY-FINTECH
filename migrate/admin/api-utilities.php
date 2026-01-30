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
        $vars = (is_array($res) && isset($res['content'])) ? ($res['content']['varations'] ?? $res['content']['variations'] ?? null) : null;
        if (is_array($vars)) {
            foreach ($vars as $v) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name, api_price, user_price) VALUES (?, 'vtpass', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                $stmt->execute([$category, $serviceId, $v['variation_code'] ?? $v['id'] ?? $v['variation_id'] ?? '', $v['name'] ?? '', (float)($v['variation_amount'] ?? $v['amount'] ?? 0), (float)($v['variation_amount'] ?? $v['amount'] ?? 0)]);
            }
            $success = "Fetched " . count($vars) . " packages for $serviceId";
        } else {
            $desc = is_array($res) ? ($res['response_description'] ?? $res['message'] ?? 'No variations found') : (is_string($res) ? substr($res, 0, 100) : 'Unknown error');
            $error = "Failed to fetch packages: " . $desc;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_betting') {
        $res = nellobyteGetBettingCompanies($pdo);
        if (is_array($res) && isset($res['content'])) {
            foreach ($res['content'] as $p) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name) VALUES ('betting', 'nellobyte', ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                $stmt->execute([$p['ID'], $p['ID'], $p['Name']]);
            }
            $success = "Fetched " . count($res['content']) . " betting providers";
        } else {
            $error = "Failed to fetch betting providers: " . (is_string($res) ? $res : 'Unknown error');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_exams_nr') {
        $res = naijaresultpinsExams($pdo, 'packages');
        if (is_array($res) && isset($res['packages'])) {
            foreach ($res['packages'] as $p) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name, api_price, user_price) VALUES ('exam', 'naijaresultpins', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                $stmt->execute([$p['id'], $p['id'], $p['name'], (float)$p['price'], (float)$p['price']]);
            }
            $success = "Fetched " . count($res['packages']) . " exam products from NaijaResultPins";
        } else {
            $error = "Failed to fetch from NaijaResultPins";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_manual_package') {
        $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name, api_price, user_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($_POST['cat']),
            sanitize($_POST['prov']),
            sanitize($_POST['sid']),
            sanitize($_POST['pid']),
            sanitize($_POST['name']),
            (float)$_POST['api_p'],
            (float)$_POST['user_p']
        ]);
        $success = "Manual package added!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'clear_utility_data') {
        $category = sanitize($_POST['clear_cat']);
        $provider = sanitize($_POST['clear_prov']);
        $stmt = $pdo->prepare("DELETE FROM utility_packages WHERE category = ? AND provider = ?");
        $stmt->execute([$category, $provider]);
        $success = "Cleared all $category packages for $provider!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'apply_all_utility_discounts') {
        $category = sanitize($_POST['target_cat']);
        $apiDisc = (float)$_POST['all_api_discount'];
        $userDisc = (float)$_POST['all_user_discount'];
        $stmt = $pdo->prepare("UPDATE utility_packages SET api_discount = ?, user_discount = ? WHERE category = ?");
        $stmt->execute([$apiDisc, $userDisc, $category]);
        $success = "Applied discounts to all $category packages!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_package') {
        $stmt = $pdo->prepare("DELETE FROM utility_packages WHERE id = ?");
        $stmt->execute([$_POST['package_id']]);
        $success = "Package deleted!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_package') {
        $id = (int)$_POST['package_id'];
        $apiDisc = (float)$_POST['api_discount'];
        $userDisc = (float)$_POST['user_discount'];
        $userPrice = (float)$_POST['user_price'];
        $stmt = $pdo->prepare("UPDATE utility_packages SET api_discount = ?, user_discount = ?, user_price = ? WHERE id = ?");
        $stmt->execute([$apiDisc, $userDisc, $userPrice, $id]);
        $success = "Package updated!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
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

        // Update NR API key which is in otherApiSettings
        $os = $settings['otherApiSettings'] ?? [];
        if (is_string($os)) $os = json_decode($os, true) ?: [];
        $os['naijaresultpins']['apiKey'] = $_POST['nr_apiKey'];
        $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ? WHERE id = 1");
        $stmt->execute([json_encode($os)]);

        $success = "Utility settings updated!";
    }
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

            <!-- NaijaResultPins -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-indigo-600">
                        <i data-lucide="graduation-cap" class="w-5 h-5"></i> NaijaResultPins (Exams)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='naijaresultpins'" class="text-[10px] font-black text-indigo-600 uppercase hover:underline">Test</button>
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                    <input type="password" name="nr_apiKey" value="<?php echo $settings['otherApiSettings']['naijaresultpins']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
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
                        <?php if($key === 'exam'): ?>
                        <option value="naijaresultpins" <?php echo ($us['routing'][$key] ?? '') === 'naijaresultpins' ? 'selected' : ''; ?>>NaijaResultPins</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <input type="hidden" name="provider" value="">
        <button type="submit" name="action" value="save_settings" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Utility Configuration</button>
    </form>

    <?php
    $categories = [
        ['id' => 'cable', 'name' => 'Cable TV', 'icon' => 'tv', 'color' => 'text-blue-500'],
        ['id' => 'electric', 'name' => 'Electricity', 'icon' => 'zap', 'color' => 'text-orange-500'],
        ['id' => 'exam', 'name' => 'Exam PINs', 'icon' => 'graduation-cap', 'color' => 'text-indigo-500'],
        ['id' => 'betting', 'name' => 'Betting', 'icon' => 'target', 'color' => 'text-emerald-500']
    ];

    foreach ($categories as $cat):
    ?>
    <!-- <?php echo $cat['name']; ?> Manager -->
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 mt-10 space-y-8">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3">
                <i data-lucide="<?php echo $cat['icon']; ?>" class="<?php echo $cat['color']; ?>"></i> <?php echo $cat['name']; ?> Hub
            </h3>

            <div class="flex items-center gap-3">
                <?php if ($cat['id'] === 'cable' || $cat['id'] === 'electric' || $cat['id'] === 'exam'): ?>
                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="fetch_packages">
                    <input type="hidden" name="category" value="<?php echo $cat['id']; ?>">
                    <select name="service_id" class="p-2 bg-gray-50 rounded-lg font-bold outline-none text-[10px] border border-gray-100">
                        <?php if($cat['id'] === 'cable'): ?>
                            <option value="dstv">DSTV</option><option value="gotv">GOTV</option><option value="startimes">Startimes</option><option value="showmax">Showmax</option>
                        <?php elseif($cat['id'] === 'electric'): ?>
                            <option value="ikeja-electric">Ikeja</option><option value="eko-electric">Eko</option><option value="abuja-electric">Abuja</option><option value="kano-electric">Kano</option><option value="enugu-electric">Enugu</option><option value="ibadan-electric">Ibadan</option><option value="portharcourt-electric">Port Harcourt</option><option value="jos-electric">Jos</option><option value="kaduna-electric">Kaduna</option><option value="benin-electric">Benin</option><option value="yola-electric">Yola</option><option value="aba-electric">Aba</option>
                        <?php elseif($cat['id'] === 'exam'): ?>
                            <option value="waec">WAEC</option><option value="neco">NECO</option><option value="jamb">JAMB</option><option value="waec-registration">WAEC Reg</option><option value="nabteb">NABTEB</option>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-billpay-green text-white rounded-lg font-black uppercase text-[8px] whitespace-nowrap">Fetch VTPass</button>
                </form>
                <?php endif; ?>

                <?php if ($cat['id'] === 'exam'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="fetch_exams_nr">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-black uppercase text-[8px] whitespace-nowrap">Fetch NR</button>
                </form>
                <?php endif; ?>

                <?php if ($cat['id'] === 'betting'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="fetch_betting">
                    <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-black uppercase text-[8px] whitespace-nowrap">Fetch Nellobyte</button>
                </form>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="clear_utility_data">
                    <input type="hidden" name="clear_cat" value="<?php echo $cat['id']; ?>">
                    <div class="flex items-center gap-1">
                        <select name="clear_prov" class="p-2 bg-red-50 text-red-600 rounded-lg font-bold outline-none text-[8px] border border-red-100">
                            <option value="vtpass">VTpass</option>
                            <option value="nellobyte">Nellobyte</option>
                            <option value="naijaresultpins">NaijaResultPins</option>
                        </select>
                        <button type="submit" onclick="return confirm('Clear?')" class="px-3 py-2 bg-red-500 text-white rounded-lg font-black uppercase text-[8px]">Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Batch Discount Manager -->
        <div class="bg-indigo-50 p-6 rounded-[32px] border border-indigo-100 flex flex-wrap items-center justify-between gap-6">
            <div>
                <h4 class="text-[10px] font-black uppercase text-indigo-900">Batch Discount Manager</h4>
                <p class="text-[8px] font-bold text-indigo-700 uppercase">Update all <?php echo $cat['name']; ?> packages.</p>
            </div>
            <form method="POST" class="flex items-center gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="apply_all_utility_discounts">
                <input type="hidden" name="target_cat" value="<?php echo $cat['id']; ?>">
                <div class="flex items-center gap-2">
                    <label class="text-[8px] font-black uppercase text-indigo-400">API %</label>
                    <input type="number" step="0.01" name="all_api_discount" placeholder="0.00" class="w-20 p-3 bg-white rounded-xl font-black text-[10px] outline-none border border-indigo-200">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-[8px] font-black uppercase text-indigo-400">User %</label>
                    <input type="number" step="0.01" name="all_user_discount" placeholder="0.00" class="w-20 p-3 bg-white rounded-xl font-black text-[10px] outline-none border border-indigo-200">
                </div>
                <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-black uppercase text-[8px] shadow-lg hover:bg-indigo-700 transition-all">Apply All</button>
            </form>
        </div>

        <!-- Manual Add form for this category -->
        <div class="bg-gray-50 p-6 rounded-[32px] border border-gray-100">
            <h4 class="text-[9px] font-black uppercase text-gray-400 mb-4 ml-1">Add Manual <?php echo $cat['name']; ?></h4>
            <form method="POST" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_manual_package">
                <input type="hidden" name="cat" value="<?php echo $cat['id']; ?>">
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Gateway</label>
                    <input type="text" name="prov" placeholder="vtpass" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Service ID</label>
                    <input type="text" name="sid" placeholder="dstv" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Package Code</label>
                    <input type="text" name="pid" placeholder="p-1" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Display Name</label>
                    <input type="text" name="name" placeholder="Name" required class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">API Cost</label>
                    <input type="number" step="0.01" name="api_p" value="0" class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">User Price</label>
                    <input type="number" step="0.01" name="user_p" value="0" class="w-full p-3 bg-white rounded-xl font-bold mt-1 outline-none text-[10px]">
                </div>
                <button type="submit" class="bg-gray-900 text-white py-3 rounded-xl font-black uppercase text-[10px]">Add Package</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                        <th class="pb-4">Provider Info</th>
                        <th class="pb-4">Package ID</th>
                        <th class="pb-4">API Price</th>
                        <th class="pb-4">API Disc</th>
                        <th class="pb-4">User Price</th>
                        <th class="pb-4">User Disc</th>
                        <th class="pb-4">Profit</th>
                        <th class="pb-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM utility_packages WHERE category = ? ORDER BY provider, name");
                    $stmt->execute([$cat['id']]);
                    $pkgCount = 0;
                    $isDiscountMode = ($cat['id'] === 'electric' || $cat['id'] === 'betting');
                    while ($p = $stmt->fetch()):
                        $pkgCount++;
                        if ($isDiscountMode) {
                            $profitPct = (float)$p['api_discount'] - (float)$p['user_discount'];
                        } else {
                            $apiCost = $p['api_price'] * (1 - $p['api_discount'] / 100);
                            $sellingPrice = $p['user_price'] * (1 - $p['user_discount'] / 100);
                            $profitVal = $sellingPrice - $apiCost;
                        }
                    ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_package">
                        <input type="hidden" name="package_id" value="<?php echo $p['id']; ?>">
                        <tr>
                            <td class="py-4">
                                <div class="font-black text-xs uppercase"><?php echo $p['provider']; ?></div>
                                <div class="text-[10px] font-bold text-gray-400"><?php echo $p['name']; ?></div>
                            </td>
                            <td class="py-4 font-mono text-[10px]"><?php echo $p['package_id']; ?></td>
                            <td class="py-4 font-black text-xs"><?php echo !$isDiscountMode ? '₦'.number_format($p['api_price'], 2) : '-'; ?></td>
                            <td class="py-4"><input type="number" step="0.01" name="api_discount" value="<?php echo $p['api_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-4"><input type="number" step="0.01" name="user_price" value="<?php echo $p['user_price']; ?>" class="<?php echo $isDiscountMode ? 'hidden' : ''; ?> w-24 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"><?php echo $isDiscountMode ? '-' : ''; ?></td>
                            <td class="py-4"><input type="number" step="0.01" name="user_discount" value="<?php echo $p['user_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-4">
                                <?php if($isDiscountMode): ?>
                                    <span class="px-2 py-1 <?php echo $profitPct >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-[9px] font-black rounded-md"><?php echo number_format($profitPct, 2); ?>%</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 <?php echo $profitVal >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-[9px] font-black rounded-md">₦<?php echo number_format($profitVal, 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 text-right space-x-2 flex items-center justify-end">
                                <button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-[8px] font-black uppercase">Save</button>
                                <button type="submit" name="action" value="delete_package" onclick="return confirm('Delete?')" class="text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                    </form>
                    <?php endwhile; if($pkgCount === 0) echo '<tr><td colspan="8" class="py-10 text-center text-[10px] font-black text-gray-300 uppercase tracking-widest">No packages found for this hub</td></tr>'; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
