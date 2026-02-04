<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Rewards';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if ($_POST['action'] === 'checkin') {
        $error = "Reward is now automatically claimed upon your first service purchase of the day!";
    } elseif ($_POST['action'] === 'convert') {
        $coinsToConvert = (int)$_POST['coins'];
        if ($coinsToConvert <= 0) {
            $error = "Invalid amount of coins.";
        } elseif ($currentUser['bonusCoins'] < $coinsToConvert) {
            $error = "Insufficient coins.";
        } elseif (($settings['conversionRate'] ?? 0) <= 0) {
            $error = "Conversion is currently disabled.";
        } else {
            $nairaAmount = $coinsToConvert / $settings['conversionRate'];
            if ($nairaAmount < 0.01) {
                $error = "Coin amount too small for conversion. Minimum " . $settings['conversionRate'] . " coins required.";
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins - ?, walletBalance = walletBalance + ? WHERE id = ?");
                    $stmt->execute([$coinsToConvert, $nairaAmount, $currentUser['id']]);
                    logTransaction($pdo, $currentUser['id'], 'Coin Conversion', $nairaAmount, 'successful', "Converted $coinsToConvert coins to wallet balance", 'Wallet', 'System');
                    $pdo->commit();
                    $success = "Successfully converted $coinsToConvert coins to " . formatCurrency($nairaAmount) . "!";
                    // Refresh
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$currentUser['id']]);
                    $currentUser = $stmt->fetch();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Conversion failed.";
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Rewards Hub</h1>
    </div>

    <div class="p-6 space-y-6 flex-1">
        <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <div class="bg-gradient-to-br from-yellow-400 to-orange-500 p-8 rounded-[40px] text-white shadow-xl text-center relative overflow-hidden">
            <i data-lucide="coins" class="w-20 h-20 text-white/20 absolute -right-4 -bottom-4"></i>
            <div class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">Available Coins</div>
            <div class="text-5xl font-black"><?php echo $currentUser['bonusCoins']; ?></div>
            <p class="text-[9px] font-bold uppercase mt-4 opacity-70">1 Coin = <?php echo ($settings['conversionRate'] ?? 0) > 0 ? formatCurrency(1/$settings['conversionRate']) : 'N/A'; ?></p>
        </div>

        <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Daily Reward Info</h3>
            <div class="flex items-start gap-4 bg-blue-50 p-6 rounded-3xl border border-blue-100">
                <div class="w-10 h-10 bg-blue-500 rounded-2xl flex items-center justify-center text-white shrink-0">
                    <i data-lucide="info" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-blue-900">Automatic Rewards</div>
                    <p class="text-[10px] text-blue-700 font-bold uppercase mt-1 leading-relaxed">
                        Earn <?php echo $settings['bonusPerDay']; ?> coins automatically upon your first successful service purchase every day.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="convert">
            <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Redeem Coins</h3>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount of Coins to Convert</label>
                <input type="number" name="coins" placeholder="Min <?php echo $settings['conversionRate']; ?> coins" min="<?php echo $settings['conversionRate']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <div class="flex justify-between items-center bg-gray-50 p-6 rounded-3xl border border-gray-100">
                <div>
                    <div class="text-sm font-black text-gray-800">Conversion Rate</div>
                    <div class="text-[10px] text-gray-400 font-bold uppercase"><?php echo $settings['conversionRate']; ?> Coins = ₦1.00</div>
                </div>
                <button type="submit" class="bg-gray-900 text-white px-6 py-4 rounded-2xl font-black text-[10px] uppercase shadow-lg active:scale-95 transition-all">Redeem Now</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
