<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Data API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'bulk_apply') {
        $bulkApiDisc = (float)$_POST['bulk_api_discount'];
        $bulkUserDisc = (float)$_POST['bulk_user_discount'];

        $allPlans = $settings['dataProducts'] ?? [];
        if (is_string($allPlans)) $allPlans = json_decode($allPlans, true) ?: [];

        foreach ($allPlans as &$p) {
            $p['apiDiscount'] = $bulkApiDisc;
            $p['userDiscount'] = $bulkUserDisc;
            // Recalculate prices if base price exists
            if (isset($p['basePrice'])) {
                $p['apiPrice'] = $p['basePrice'] * (1 - ($bulkApiDisc / 100));
                $p['userPrice'] = $p['basePrice'] * (1 - ($bulkUserDisc / 100));
            }
        }

        $stmt = $pdo->prepare("UPDATE settings SET dataProducts = ? WHERE id = 1");
        $stmt->execute([json_encode($allPlans)]);
        $success = "Bulk discounts applied to all data plans!";
        $settings = fetchSettings($pdo);
    } else {

    $dataSettings = [
        'providers' => [
            'datagifting' => ['apiKey' => $_POST['dg_apiKey']],
            'nellobyte' => ['userId' => $_POST['nb_userId'], 'apiKey' => $_POST['nb_apiKey']],
            'hdkdata' => ['token' => $_POST['hdk_token']]
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
    $settings = fetchSettings($pdo);
    }
}

$ds = $settings['dataSettings'] ?? [];
if (is_string($ds)) $ds = json_decode($ds, true) ?: [];

if (empty($ds)) {
    $ds = [
        'providers' => ['datagifting' => ['apiKey' => ''], 'nellobyte' => ['userId' => '', 'apiKey' => ''], 'hdkdata' => ['token' => '']],
        'routing' => ['MTN' => 'datagifting', 'Airtel' => 'datagifting', 'Glo' => 'datagifting', '9mobile' => 'datagifting']
    ];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Data API Gateway</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Bulk Discount Section -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-indigo-600">
                    <i data-lucide="percent" class="w-5 h-5"></i> Bulk Pricing Update
                </h3>
                <button type="submit" name="action" value="bulk_apply" class="text-[10px] font-black uppercase bg-indigo-50 text-indigo-600 px-6 py-2.5 rounded-xl border border-indigo-100 hover:bg-indigo-100 transition-all">Apply to All Plans</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Bulk API Disc (%)</label>
                    <input type="number" step="0.01" name="bulk_api_discount" placeholder="e.g. 5.0" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Bulk User Disc (%)</label>
                    <input type="number" step="0.01" name="bulk_user_discount" placeholder="e.g. 2.0" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                </div>
            </div>
            <p class="text-[9px] font-bold text-gray-400 mt-4 uppercase italic">* This will overwrite the individual discount fields for ALL data plans at once.</p>
        </div>

        <!-- Provider Credentials -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-billpay-green">
                    <i data-lucide="key" class="w-5 h-5"></i> DataGifting (1)
                </h3>
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">API Key</label>
                <input type="password" name="dg_apiKey" value="<?php echo $ds['providers']['datagifting']['apiKey'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-orange-500">
                    <i data-lucide="key" class="w-5 h-5"></i> Nellobyte (2)
                </h3>
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
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3 text-blue-600">
                    <i data-lucide="key" class="w-5 h-5"></i> HDKData (3)
                </h3>
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Token</label>
                <input type="password" name="hdk_token" value="<?php echo $ds['providers']['hdkdata']['token'] ?? ''; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-1 outline-none border border-transparent focus:border-billpay-green">
                <p class="text-[8px] font-bold text-gray-400 mt-2">Authorization: Token [your_token]</p>
            </div>
        </div>

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
                        <option value="hdkdata" <?php echo ($ds['routing'][$net] ?? '') === 'hdkdata' ? 'selected' : ''; ?>>HDKData</option>
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
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
