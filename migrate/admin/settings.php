<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Global Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $stmt = $pdo->prepare("UPDATE settings SET
        bankAccount = ?, bankName = ?, accountName = ?,
        minDepositAmount = ?, minAirtimePurchase = ?,
        maxDailyTxPerId = ?,
        bonusPerDay = ?, referralBonus = ?, welcomeBonus = ?, conversionRate = ?,
        templateId = ?, primaryColor = ?,
        isMaintenanceMode = ?, isKycEnforced = ?, isMinDepositForced = ?,
        smtpHost = ?, smtpPort = ?, smtpUser = ?,
        smtpPass = ?, senderName = ?, fromEmail = ?
        WHERE id = 1");
    $stmt->execute([
        sanitize($_POST['bankAccount']), sanitize($_POST['bankName']), sanitize($_POST['accountName']),
        sanitize($_POST['minDepositAmount']), sanitize($_POST['minAirtimePurchase']),
        sanitize($_POST['maxDailyTxPerId'] ?? 5),
        sanitize($_POST['bonusPerDay'] ?? 20), sanitize($_POST['referralBonus'] ?? 100), sanitize($_POST['welcomeBonus'] ?? 50), sanitize($_POST['conversionRate'] ?? 20),
        sanitize($_POST['templateId'] ?? 1), sanitize($_POST['primaryColor'] ?? '#00c689'),
        isset($_POST['isMaintenanceMode']) ? 1 : 0,
        isset($_POST['isKycEnforced']) ? 1 : 0,
        isset($_POST['isMinDepositForced']) ? 1 : 0,
        sanitize($_POST['smtpHost']), sanitize($_POST['smtpPort']), sanitize($_POST['smtpUser']),
        sanitize($_POST['smtpPass']), sanitize($_POST['senderName']), sanitize($_POST['fromEmail'])
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
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
                <div class="flex items-center justify-between p-6 bg-gray-50 rounded-3xl border border-gray-100">
                    <div class="flex-1 mr-4">
                        <div class="text-sm font-black text-gray-800 uppercase">Site Primary Color</div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Updates the brand color globally.</p>
                    </div>
                    <input type="color" name="primaryColor" value="<?php echo $settings['primaryColor']; ?>" class="w-14 h-14 p-1 bg-white rounded-xl border border-gray-200 outline-none cursor-pointer">
                </div>
            </div>
        </div>

        <!-- Branding & Layout -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="layout" class="text-indigo-500"></i> Layout & Templates</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Dashboard Template</label>
                    <select name="templateId" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        <option value="1" <?php echo $settings['templateId'] == 1 ? 'selected' : ''; ?>>Classic Template (Standard)</option>
                        <option value="2" <?php echo $settings['templateId'] == 2 ? 'selected' : ''; ?>>Modern Template (Offers Top)</option>
                        <option value="3" <?php echo $settings['templateId'] == 3 ? 'selected' : ''; ?>>Fintech Template (Icons Top)</option>
                        <option value="4" <?php echo $settings['templateId'] == 4 ? 'selected' : ''; ?>>Crypto Hub Template (Live Prices)</option>
                    </select>
                </div>
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

        <!-- SMTP Configuration -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="mail" class="text-blue-500"></i> SMTP Configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Host</label><input type="text" name="smtpHost" value="<?php echo $settings['smtpHost']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Port</label><input type="text" name="smtpPort" value="<?php echo $settings['smtpPort']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">User</label><input type="text" name="smtpUser" value="<?php echo $settings['smtpUser']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Password</label><input type="password" name="smtpPass" value="<?php echo $settings['smtpPass']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Sender Name</label><input type="text" name="senderName" value="<?php echo $settings['senderName']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Sender Email</label><input type="text" name="fromEmail" value="<?php echo $settings['fromEmail']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
            </div>
        </div>

        <!-- Loyalty & Rewards Engine -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="gift" class="text-pink-500"></i> Loyalty & Rewards Engine</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Daily Check-in (Coins)</label><input type="number" name="bonusPerDay" value="<?php echo $settings['bonusPerDay']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Referral Reward (Coins)</label><input type="number" name="referralBonus" value="<?php echo $settings['referralBonus']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Welcome Bonus (Coins)</label><input type="number" name="welcomeBonus" value="<?php echo $settings['welcomeBonus']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Conversion Rate (X Coins = ₦1)</label><input type="number" name="conversionRate" value="<?php echo $settings['conversionRate']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
            </div>
        </div>

        <!-- Security & System Guard -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><i data-lucide="shield-half" class="text-billpay-green"></i> Security & System Guard</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Min. Wallet Deposit (₦)</label><input type="number" name="minDepositAmount" value="<?php echo $settings['minDepositAmount']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Min. Airtime Purchase (₦)</label><input type="number" name="minAirtimePurchase" value="<?php echo $settings['minAirtimePurchase']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
                <div><label class="text-[10px] font-black text-gray-400 uppercase">Max Daily Tx Per ID (Phone/IUC/Meter)</label><input type="number" name="maxDailyTxPerId" value="<?php echo $settings['maxDailyTxPerId']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></div>
            </div>

            <div class="mt-8 pt-8 border-t border-gray-100">
                <div class="flex items-center justify-between p-6 bg-amber-50 rounded-3xl border border-amber-100">
                    <div class="flex-1 mr-4">
                        <div class="text-sm font-black text-amber-800 uppercase">KYC Enforcement</div>
                        <p class="text-[10px] text-amber-600 font-bold uppercase">Require users to be verified before accessing Crypto & Transfers.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="isKycEnforced" class="sr-only peer" <?php echo isset($settings['isKycEnforced']) && $settings['isKycEnforced'] ? 'checked' : ''; ?>>
                        <div class="w-14 h-8 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-500"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-6 bg-blue-50 rounded-3xl border border-blue-100">
                    <div class="flex-1 mr-4">
                        <div class="text-sm font-black text-blue-800 uppercase">Force Initial Deposit</div>
                        <p class="text-[10px] text-blue-600 font-bold uppercase">Require users to fund at least the minimum amount before accessing services.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="isMinDepositForced" class="sr-only peer" <?php echo isset($settings['isMinDepositForced']) && $settings['isMinDepositForced'] ? 'checked' : ''; ?>>
                        <div class="w-14 h-8 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-500"></div>
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Configuration</button>
    </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
