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
        $vars = $res['content']['varations'] ?? $res['content']['variations'] ?? null;
        if ($vars) {
            foreach ($vars as $v) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name, api_price, user_price) VALUES (?, 'vtpass', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                $stmt->execute([$category, $serviceId, $v['variation_code'] ?? $v['id'], $v['name'], (float)($v['variation_amount'] ?? $v['amount']), (float)($v['variation_amount'] ?? $v['amount'])]);
            }
            $success = "Fetched " . count($vars) . " packages for $serviceId";
        } else {
            $error = "Failed to fetch packages: " . ($res['response_description'] ?? 'No variations found in response');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_betting') {
        $res = nellobyteBetting($pdo, 'GetProviders');
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_exams_vtp') {
        $exams = ['waec', 'neco', 'waec-registration', 'nabteb', 'jamb'];
        $count = 0;
        foreach ($exams as $e) {
            $res = vtpassGetVariations($pdo, $e);
            $vars = $res['content']['varations'] ?? $res['content']['variations'] ?? null;
            if ($vars) {
                foreach ($vars as $v) {
                    $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, service_id, package_id, name, api_price, user_price) VALUES ('exam', 'vtpass', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                    $stmt->execute([$e, $v['variation_code'] ?? $v['id'], strtoupper($e) . ' - ' . $v['name'], (float)($v['variation_amount'] ?? $v['amount']), (float)($v['variation_amount'] ?? $v['amount'])]);
                    $count++;
                }
            }
        }
        $success = "Fetched $count exam products from VTPass";
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

    // Update NR API key which is in otherApiSettings
    $os = $settings['otherApiSettings'] ?? [];
    if (is_string($os)) $os = json_decode($os, true) ?: [];
    $os['naijaresultpins']['apiKey'] = $_POST['nr_apiKey'];
    $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ? WHERE id = 1");
    $stmt->execute([json_encode($os)]);

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
        <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="package" class="text-orange-500"></i> Utility Package Manager</h3>

        <!-- Fetch Tools -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <form method="POST" class="bg-gray-50 p-6 rounded-[32px] border border-gray-100 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_packages">
                <label class="text-[9px] font-black text-gray-400 uppercase">VTPass Fetch</label>
                <select name="category" class="w-full p-3 bg-white rounded-xl font-bold outline-none text-xs">
                    <option value="cable">Cable TV</option>
                    <option value="electric">Electricity</option>
                    <option value="exam">Exams</option>
                </select>
                <select name="service_id" class="w-full p-3 bg-white rounded-xl font-bold outline-none text-xs">
                    <optgroup label="Cable">
                        <option value="dstv">DSTV</option>
                        <option value="gotv">GOTV</option>
                        <option value="startimes">Startimes</option>
                    </optgroup>
                    <optgroup label="Electric">
                        <option value="ikeja-electric">Ikeja Electric</option>
                        <option value="eko-electric">Eko Electric</option>
                        <option value="abuja-electric">Abuja Electric</option>
                        <option value="enugu-electric">Enugu Electric</option>
                        <option value="ibadan-electric">Ibadan Electric</option>
                    </optgroup>
                    <optgroup label="Exams">
                        <option value="waec">WAEC</option>
                        <option value="neco">NECO</option>
                        <option value="jamb">JAMB</option>
                    </optgroup>
                </select>
                <button type="submit" class="w-full py-3 bg-billpay-green text-white rounded-xl font-black uppercase text-[9px]">Fetch VTPass</button>
            </form>

            <form method="POST" class="bg-gray-50 p-6 rounded-[32px] border border-gray-100 flex flex-col justify-between">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_betting">
                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase">Nellobyte Fetch</label>
                    <p class="text-[8px] text-gray-400 mt-2 uppercase">Fetch all betting providers currently available on Nellobyte.</p>
                </div>
                <button type="submit" class="w-full py-4 bg-orange-500 text-white rounded-xl font-black uppercase text-[9px]">Fetch Betting</button>
            </form>

            <form method="POST" class="bg-gray-50 p-6 rounded-[32px] border border-gray-100 flex flex-col justify-between">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_exams_nr">
                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase">NaijaResultPins Fetch</label>
                    <p class="text-[8px] text-gray-400 mt-2 uppercase">Fetch all available exam tokens and prices.</p>
                </div>
                <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-xl font-black uppercase text-[9px]">Fetch Exams (NR)</button>
            </form>

            <div class="bg-blue-600 p-6 rounded-[32px] text-white flex flex-col justify-center text-center">
                <div class="text-[10px] font-black uppercase mb-1">Live Profit</div>
                <div class="text-2xl font-black">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM utility_packages WHERE enabled = 1");
                    echo $stmt->fetchColumn();
                    ?>
                </div>
                <div class="text-[8px] font-bold uppercase opacity-60">Active Packages</div>
            </div>
        </div>

        <!-- Manual Add Form -->
        <div class="bg-white p-8 rounded-[32px] border-2 border-dashed border-gray-100 mb-10">
            <h4 class="text-[10px] font-black uppercase text-gray-400 mb-6">Add Manual Package</h4>
            <form method="POST" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_manual_package">
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Category</label>
                    <select name="cat" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                        <option value="cable">Cable</option>
                        <option value="electric">Electric</option>
                        <option value="betting">Betting</option>
                        <option value="exam">Exam</option>
                    </select>
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Provider (Gateway)</label>
                    <input type="text" name="prov" placeholder="e.g. vtpass" required class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Service ID</label>
                    <input type="text" name="sid" placeholder="e.g. dstv" required class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Package ID (Code)</label>
                    <input type="text" name="pid" placeholder="Code" required class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">Name</label>
                    <input type="text" name="name" placeholder="Display Name" required class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">API Price</label>
                    <input type="number" step="0.01" name="api_p" value="0" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <div>
                    <label class="text-[8px] font-black text-gray-400 uppercase">User Price</label>
                    <input type="number" step="0.01" name="user_p" value="0" class="w-full p-3 bg-gray-50 rounded-xl font-bold mt-1 outline-none text-xs">
                </div>
                <button type="submit" class="bg-gray-900 text-white py-3 rounded-xl font-black uppercase text-[10px]">Add</button>
            </form>
        </div>

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
                    $stmt = $pdo->query("SELECT * FROM utility_packages ORDER BY category, provider, name");
                    while ($p = $stmt->fetch()):
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
                                <div class="font-black text-xs uppercase"><?php echo $p['provider']; ?> <span class="text-[8px] opacity-40"><?php echo $p['package_id']; ?></span></div>
                                <div class="text-[10px] font-bold text-gray-400"><?php echo $p['name']; ?></div>
                            </td>
                            <td class="py-6 font-black text-xs">₦<?php echo number_format($p['api_price'], 2); ?></td>
                            <td class="py-6"><input type="number" step="0.01" name="api_discount" value="<?php echo $p['api_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><input type="number" step="0.01" name="user_price" value="<?php echo $p['user_price']; ?>" class="w-24 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><input type="number" step="0.01" name="user_discount" value="<?php echo $p['user_discount']; ?>" class="w-16 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><span class="px-2 py-1 <?php echo $profitVal >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-[9px] font-black rounded-md">₦<?php echo number_format($profitVal, 2); ?></span></td>
                            <td class="py-6 text-right space-x-2 flex items-center justify-end">
                                <button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-[8px] font-black uppercase">Save</button>
                                <button type="submit" name="action" value="delete_package" onclick="return confirm('Delete?')" class="text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                    </form>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
