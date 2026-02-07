<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Rewards';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $coinsToConvert = (int)$_POST['coins'];
    if ($coinsToConvert <= 0 || $coinsToConvert > $currentUser['bonusCoins']) {
        $error = "Invalid coin amount.";
    } else {
        $nairaAmount = $coinsToConvert / ($settings['conversionRate'] ?: 20);
        if ($nairaAmount < 0.01) {
            $error = "Amount too small to convert.";
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins - ?, walletBalance = walletBalance + ? WHERE id = ?");
                $stmt->execute([$coinsToConvert, $nairaAmount, $currentUser['id']]);

                logTransaction($pdo, $currentUser['id'], 'Coin Conversion', $nairaAmount, 'successful', "Converted $coinsToConvert coins to wallet", 'Wallet', 'System');

                $pdo->commit();
                $success = "Successfully converted " . formatCurrency($nairaAmount) . " to your wallet!";

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

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
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
            <p class="text-[9px] font-bold uppercase mt-4 opacity-70">1 Coin = <?php echo formatCurrency(1/$settings['conversionRate']); ?></p>
        </div>

        <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Daily Reward Status</h3>
            <?php
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND type = 'Daily Reward' AND DATE(date) = ?");
            $stmt->execute([$currentUser['id'], $today]);
            $claimed = $stmt->fetchColumn() > 0;
            ?>
            <div class="flex justify-between items-center bg-gray-50 p-6 rounded-3xl border border-gray-100">
                <div>
                    <div class="text-sm font-black text-gray-800">Check-in Reward</div>
                    <p class="text-[9px] font-bold text-gray-400 uppercase mt-1 leading-relaxed">
                        <?php if ($claimed): ?>
                            Reward claimed for today!
                        <?php else: ?>
                            Make any purchase today to automatically claim <?php echo $settings['bonusPerDay']; ?> coins.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $claimed ? 'bg-green-50 text-green-500' : 'bg-gray-100 text-gray-300'; ?>">
                    <i data-lucide="<?php echo $claimed ? 'check-circle-2' : 'clock'; ?>" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="convert">
            <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Convert to Cash</h3>

            <div class="relative">
                <input type="number" name="coins" id="coinInput" max="<?php echo $currentUser['bonusCoins']; ?>" placeholder="Enter amount of coins" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none border-2 border-transparent focus:border-billpay-green">
                <div class="absolute right-5 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 uppercase">Coins</div>
            </div>

            <div class="bg-blue-50 p-6 rounded-3xl border border-blue-100 flex justify-between items-center">
                <div class="text-[10px] font-black text-blue-800 uppercase">You will receive</div>
                <div id="nairaOutput" class="text-lg font-black text-blue-900">₦0.00</div>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase text-xs tracking-widest shadow-xl active:scale-95 transition-all">Redeem to Wallet</button>
        </form>

        <script>
            const rate = <?php echo $settings['conversionRate'] ?: 20; ?>;
            document.getElementById('coinInput').addEventListener('input', (e) => {
                const coins = parseFloat(e.target.value) || 0;
                const naira = coins / rate;
                document.getElementById('nairaOutput').innerText = '₦' + naira.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            });
        </script>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
