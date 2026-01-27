<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'API Gateway Manager';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $simulationMode = isset($_POST['apiSimulationMode']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE settings SET
        nellobyteUserId = ?, nellobyteApiKey = ?,
        dataGiftingApiKey = ?, examApiKey = ?,
        vtPassApiKey = ?, vtPassPublicKey = ?,
        vtPassEmail = ?, vtPassPassword = ?,
        kudiSmsToken = ?, stripeSecretKey = ?,
        tremendousApiKey = ?, juicywayApiKey = ?,
        reloadlyClientId = ?, reloadlyClientSecret = ?,
        paystackPublicKey = ?, paystackSecretKey = ?,
        apiSimulationMode = ?
        WHERE id = 1");
    $stmt->execute([
        $_POST['nellobyteUserId'], $_POST['nellobyteApiKey'],
        $_POST['dataGiftingApiKey'], $_POST['examApiKey'],
        $_POST['vtPassApiKey'], $_POST['vtPassPublicKey'],
        $_POST['vtPassEmail'], $_POST['vtPassPassword'],
        $_POST['kudiSmsToken'], $_POST['stripeSecretKey'],
        $_POST['tremendousApiKey'], $_POST['juicywayApiKey'],
        $_POST['reloadlyClientId'], $_POST['reloadlyClientSecret'],
        $_POST['paystackPublicKey'], $_POST['paystackSecretKey'],
        $simulationMode
    ]);
    $success = "Gateways updated!";
    $settings = fetchSettings($pdo);
}

require_once __DIR__ . '/header.php';
?>
<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black uppercase tracking-widest flex items-center gap-3"><i data-lucide="cpu" class="text-purple-500"></i> Global Settings</h3>
                <label class="flex items-center cursor-pointer">
                    <div class="mr-3 text-[10px] font-black uppercase text-gray-400">API Simulation Mode</div>
                    <div class="relative">
                        <input type="checkbox" name="apiSimulationMode" class="sr-only" <?php echo !empty($settings['apiSimulationMode']) ? 'checked' : ''; ?>>
                        <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                    </div>
                </label>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase leading-relaxed">When simulation mode is ON, the system will mock successful responses from providers without making actual API calls or spending your balance.</p>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="phone" class="text-yellow-500"></i> Nellobyte (Airtime & Data)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">User ID</label><input type="text" name="nellobyteUserId" value="<?php echo $settings['nellobyteUserId']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" name="nellobyteApiKey" value="<?php echo $settings['nellobyteApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Data Gifting API Key</label><input type="password" name="dataGiftingApiKey" value="<?php echo $settings['dataGiftingApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="tv" class="text-red-500"></i> VTpass (Cable/Power/Betting/Exam)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" name="vtPassApiKey" value="<?php echo $settings['vtPassApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Public Key</label><input type="password" name="vtPassPublicKey" value="<?php echo $settings['vtPassPublicKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Login Email</label><input type="text" name="vtPassEmail" value="<?php echo $settings['vtPassEmail']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Login Password</label><input type="password" name="vtPassPassword" value="<?php echo $settings['vtPassPassword']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="credit-card" class="text-emerald-500"></i> Financial Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Paystack Public Key</label><input type="text" name="paystackPublicKey" value="<?php echo $settings['paystackPublicKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Paystack Secret Key</label><input type="password" name="paystackSecretKey" value="<?php echo $settings['paystackSecretKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">JuicyWay API Key (Virtual Cards)</label><input type="password" name="juicywayApiKey" value="<?php echo $settings['juicywayApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Stripe Secret Key</label><input type="password" name="stripeSecretKey" value="<?php echo $settings['stripeSecretKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Reloadly Client ID (Gift Cards)</label><input type="text" name="reloadlyClientId" value="<?php echo $settings['reloadlyClientId']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Reloadly Client Secret</label><input type="password" name="reloadlyClientSecret" value="<?php echo $settings['reloadlyClientSecret']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Tremendous API Key (Legacy Gift Cards)</label><input type="password" name="tremendousApiKey" value="<?php echo $settings['tremendousApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Exam PIN API Key</label><input type="password" name="examApiKey" value="<?php echo $settings['examApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="message-square" class="text-blue-500"></i> KudiSMS (Bulk SMS)</h3>
            <div><label class="text-[10px] font-black text-gray-400 uppercase">API Token</label><input type="password" name="kudiSmsToken" value="<?php echo $settings['kudiSmsToken']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Update API Gateways</button>
    </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
