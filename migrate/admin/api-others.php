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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_betting') {
        $res = nellobyteBetting($pdo, 'GetProviders');
        if (is_array($res) && isset($res['content'])) {
            // Suppose content is array of providers
            foreach ($res['content'] as $p) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, package_id, name) VALUES ('betting', 'nellobyte', ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                $stmt->execute([$p['ID'], $p['Name']]);
            }
            $success = "Fetched " . count($res['content']) . " betting providers";
        } else {
            $error = "Failed to fetch betting providers: " . (is_string($res) ? $res : 'Unknown error');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_exams_vtp') {
        $exams = ['waec', 'neco', 'waec-registration', 'nabteb', 'jamb'];
        $count = 0;
        foreach ($exams as $e) {
            $res = vtpassGetVariations($pdo, $e);
            if (isset($res['content']['varations'])) {
                foreach ($res['content']['varations'] as $v) {
                    $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, package_id, name, api_price, user_price) VALUES ('exam', 'vtpass', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                    $stmt->execute([$v['variation_code'], strtoupper($e) . ' - ' . $v['name'], (float)$v['variation_amount'], (float)$v['variation_amount']]);
                    $count++;
                }
            }
        }
        $success = "Fetched $count exam products from VTPass";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'fetch_exams_nr') {
        $res = naijaresultpinsExams($pdo, 'packages');
        if (is_array($res) && isset($res['packages'])) {
            foreach ($res['packages'] as $p) {
                $stmt = $pdo->prepare("INSERT INTO utility_packages (category, provider, package_id, name, api_price, user_price) VALUES ('exam', 'naijaresultpins', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), api_price = VALUES(api_price)");
                $stmt->execute([$p['id'], $p['name'], (float)$p['price'], (float)$p['price']]);
            }
            $success = "Fetched " . count($res['packages']) . " exam products from NaijaResultPins";
        } else {
            $error = "Failed to fetch from NaijaResultPins";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_bet_package') {
        $id = (int)$_POST['package_id'];
        $apiDisc = (float)$_POST['api_discount'];
        $userDisc = (float)$_POST['user_discount'];
        $stmt = $pdo->prepare("UPDATE utility_packages SET api_discount = ?, user_discount = ? WHERE id = ?");
        $stmt->execute([$apiDisc, $userDisc, $id]);
        $success = "Betting provider updated!";
    }

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
        'naijaresultpins' => [
            'apiKey' => $_POST['nr_apiKey']
        ],
        'simulationMode' => isset($_POST['simulationMode']) ? 1 : 0
    ];

    $stmt = $pdo->prepare("UPDATE settings SET otherApiSettings = ? WHERE id = 1");
    $stmt->execute([json_encode($otherSettings)]);
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

            <!-- NaijaResultPins -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-purple-600">
                        <i data-lucide="graduation-cap" class="w-5 h-5"></i> NaijaResultPins (Exams)
                    </h3>
                    <button type="submit" name="action" value="test_api" onclick="this.form.provider.value='naijaresultpins'" class="text-[10px] font-black text-purple-600 uppercase hover:underline">Test</button>
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                    <input type="password" name="nr_apiKey" value="<?php echo $os['naijaresultpins']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
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

    <!-- Betting & Exam Manager -->
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 mt-10">
        <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="package" class="text-orange-500"></i> Betting & Exam Management</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_betting">
                <button type="submit" class="w-full px-6 py-4 bg-orange-500 text-white rounded-2xl font-black uppercase text-[10px] shadow-lg">Fetch Betting Providers (Nellobyte)</button>
            </form>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_exams_vtp">
                <button type="submit" class="w-full px-6 py-4 bg-purple-600 text-white rounded-2xl font-black uppercase text-[10px] shadow-lg">Fetch Exams (VTPass)</button>
            </form>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="fetch_exams_nr">
                <button type="submit" class="w-full px-6 py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase text-[10px] shadow-lg">Fetch Exams (NaijaResultPins)</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                        <th class="pb-6">Category</th>
                        <th class="pb-6">Provider Name</th>
                        <th class="pb-6">API Disc (%)</th>
                        <th class="pb-6">User Disc (%)</th>
                        <th class="pb-6">Profit Margin</th>
                        <th class="pb-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM utility_packages WHERE category IN ('betting', 'exam') ORDER BY category, name");
                    while ($p = $stmt->fetch()):
                        $profit = $p['api_discount'] - $p['user_discount'];
                    ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_bet_package">
                        <input type="hidden" name="package_id" value="<?php echo $p['id']; ?>">
                        <tr>
                            <td class="py-6 font-black text-[10px] uppercase text-gray-400"><?php echo $p['category']; ?></td>
                            <td class="py-6 font-black text-xs uppercase"><?php echo $p['name']; ?></td>
                            <td class="py-6"><input type="number" step="0.01" name="api_discount" value="<?php echo $p['api_discount']; ?>" class="w-20 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><input type="number" step="0.01" name="user_discount" value="<?php echo $p['user_discount']; ?>" class="w-20 p-2 bg-gray-50 rounded-lg text-[10px] font-black outline-none"></td>
                            <td class="py-6"><span class="px-2 py-1 <?php echo $profit >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-[9px] font-black rounded-md"><?php echo ($profit >= 0 ? '+' : '') . number_format($profit, 2); ?>%</span></td>
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
