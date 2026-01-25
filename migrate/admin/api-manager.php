<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'API Gateway Manager';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $stmt = $pdo->prepare("UPDATE settings SET
        nellobyteUserId = ?, nellobyteApiKey = ?,
        vtPassApiKey = ?, vtPassPublicKey = ?,
        kudiSmsToken = ?, stripeSecretKey = ?
        WHERE id = 1");
    $stmt->execute([
        $_POST['nellobyteUserId'], $_POST['nellobyteApiKey'],
        $_POST['vtPassApiKey'], $_POST['vtPassPublicKey'],
        $_POST['kudiSmsToken'], $_POST['stripeSecretKey']
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
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="phone" class="text-blue-500"></i> Nellobyte (Airtime)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">User ID</label><input type="text" name="nellobyteUserId" value="<?php echo $settings['nellobyteUserId']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" name="nellobyteApiKey" value="<?php echo $settings['nellobyteApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="tv" class="text-red-500"></i> VTpass (Cable/Power)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" name="vtPassApiKey" value="<?php echo $settings['vtPassApiKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Public Key</label><input type="password" name="vtPassPublicKey" value="<?php echo $settings['vtPassPublicKey']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="message-square" class="text-emerald-500"></i> KudiSMS (Bulk SMS)</h3>
            <div><label class="text-[10px] font-black text-gray-400 uppercase">API Token</label><input type="password" name="kudiSmsToken" value="<?php echo $settings['kudiSmsToken']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Update API Gateways</button>
    </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
