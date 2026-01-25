<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Rewards';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    // Simple checkin logic: only once per day
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND type = 'Daily Reward' AND DATE(date) = ?");
    $stmt->execute([$currentUser['id'], $today]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins + ? WHERE id = ?");
            $stmt->execute([$settings['bonusPerDay'], $currentUser['id']]);
            logTransaction($pdo, $currentUser['id'], 'Daily Reward', $settings['bonusPerDay'], 'successful', "Daily check-in reward", 'Wallet', 'System');
            $pdo->commit();
            $success = "Reward claimed!";
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $currentUser = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to claim reward.";
        }
    } else {
        $error = "Already claimed today.";
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

        <form method="POST" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="checkin">
            <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Daily Streak</h3>
            <div class="flex justify-between items-center bg-gray-50 p-6 rounded-3xl border border-gray-100">
                <div>
                    <div class="text-sm font-black text-gray-800">Check-in Reward</div>
                    <div class="text-[10px] text-gray-400 font-bold uppercase">Earn <?php echo $settings['bonusPerDay']; ?> coins today</div>
                </div>
                <button type="submit" class="bg-billpay-green text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-green-100 active:scale-95 transition-all">Claim</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
