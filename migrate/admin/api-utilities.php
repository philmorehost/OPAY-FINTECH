<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Utility API Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test') {
    header('Content-Type: application/json');
    echo json_encode(testUtilityProvider($pdo, $_POST['provider']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $utilSettings = [
        'vtpass' => [
            'apiKey' => $_POST['vt_apiKey'],
            'secretKey' => $_POST['vt_secretKey'],
            'publicKey' => $_POST['vt_publicKey'],
            'username' => $_POST['vt_username'],
            'password' => $_POST['vt_password'],
            'liveMode' => isset($_POST['vt_liveMode']) ? 1 : 0
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

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- VTPass -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-3 text-blue-600">
                        <i data-lucide="zap" class="w-5 h-5"></i> VTPass Integration
                    </h3>
                    <button type="button" onclick="testProvider('vtpass')" class="text-[9px] font-black uppercase bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border border-blue-100 hover:bg-blue-100 transition-all">Test API</button>
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
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-2xl">
                        <input type="checkbox" name="vt_liveMode" value="1" <?php echo !empty($us['vtpass']['liveMode']) ? 'checked' : ''; ?> class="w-5 h-5 accent-billpay-green">
                        <label class="text-[10px] font-black uppercase">Live Mode</label>
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
                    <button type="button" onclick="testProvider('nellobyte')" class="text-[9px] font-black uppercase bg-orange-50 text-orange-600 px-3 py-1.5 rounded-lg border border-orange-100 hover:bg-orange-100 transition-all">Test API</button>
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

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Utility Configuration</button>
    </form>
</div>

<script>
async function testProvider(provider) {
    const btn = event.currentTarget;
    const originalText = btn.innerText;
    btn.innerText = 'Testing...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'test');
        formData.append('provider', provider);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');

        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();
        alert(data.message);
    } catch (e) {
        alert('Test failed: ' + e.message);
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
