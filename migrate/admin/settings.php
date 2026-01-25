<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'Global Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $stmt = $pdo->prepare("UPDATE settings SET
        bankAccount = ?, bankName = ?, accountName = ?,
        minDepositAmount = ?, minAirtimePurchase = ?,
        bonusPerDay = ?, conversionRate = ?,
        isMaintenanceMode = ?
        WHERE id = 1");
    $stmt->execute([
        sanitize($_POST['bankAccount']), sanitize($_POST['bankName']), sanitize($_POST['accountName']),
        sanitize($_POST['minDepositAmount']), sanitize($_POST['minAirtimePurchase']),
        sanitize($_POST['bonusPerDay'] ?? 20), sanitize($_POST['conversionRate'] ?? 20),
        isset($_POST['isMaintenanceMode']) ? 1 : 0
    ]);

    // Refresh settings
    $settings = fetchSettings($pdo);
    $success = "Settings updated successfully!";
}

require_once __DIR__ . '/header.php';
?>
<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Global System Control -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="shield-alert" class="text-red-500"></i> Global System Control</h3>
            <div class="flex items-center justify-between p-6 bg-gray-50 rounded-3xl border border-gray-100">
                <div>
                    <div class="text-sm font-black text-gray-800 uppercase">Frontend Maintenance Mode</div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Disables all user features except login.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="isMaintenanceMode" class="sr-only peer" <?php echo $settings['isMaintenanceMode'] ? 'checked' : ''; ?>>
                    <div class="w-14 h-8 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-500"></div>
                </label>
            </div>
        </div>

        <!-- Bank Details -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="landmark" class="text-indigo-500"></i> Settlement Account</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Account Number</label><input type="text" name="bankAccount" value="<?php echo $settings['bankAccount']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Bank Name</label><input type="text" name="bankName" value="<?php echo $settings['bankName']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Account Name</label><input type="text" name="accountName" value="<?php echo $settings['accountName']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
            </div>
        </div>

        <!-- System Limits -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="shield-half" class="text-billpay-green"></i> Security & Limits</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Min. Deposit (₦)</label><input type="number" name="minDepositAmount" value="<?php echo $settings['minDepositAmount']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Min. Airtime (₦)</label><input type="number" name="minAirtimePurchase" value="<?php echo $settings['minAirtimePurchase']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none"></div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Configuration</button>
    </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
