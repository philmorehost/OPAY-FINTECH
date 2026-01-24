<?php
// migrate/transfer.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$success = '';
$error = '';
$fee = 50.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }

    $bank = $_POST['bank'] ?? '';
    $accountNumber = $_POST['acc'] ?? '';
    $amount = (float)($_POST['amt'] ?? 0);
    $totalAmount = $amount + $fee;

    if ($amount < 100) { $error = "Minimum transfer is ₦100"; }
    elseif ($user['walletBalance'] < $totalAmount) { $error = "Insufficient balance (incl. fee)"; }
    else {
        // Mock transfer process
        update_user_balance($user['id'], -$totalAmount);
        log_transaction($user['id'], 'Bank Transfer', $amount, 'successful', "Transfer to $bank ($accountNumber)", $accountNumber, $bank);
        add_notification($user['id'], "Transfer Successful", "You transferred ₦" . number_format($amount, 2) . " to $bank.", 'success');
        $success = "Transfer successful!";
        $user = get_current_user_data();
    }
}

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE userId = ? AND type = 'Bank Transfer' ORDER BY date DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recentTransfers = $stmt->fetchAll();
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Bank Transfer</h1>
    </div>

    <div class="p-6 space-y-6">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>

        <div class="bg-gray-900 p-8 rounded-[40px] text-white shadow-xl relative overflow-hidden">
            <div class="relative z-10">
                <div class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-2">Wallet Balance</div>
                <div class="text-4xl font-black"><?php echo format_currency($user['walletBalance']); ?></div>
            </div>
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full"></div>
        </div>

        <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Select Bank</label>
                <select name="bank" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <option>Access Bank</option>
                    <option>GTBank</option>
                    <option>First Bank</option>
                    <option>UBA</option>
                    <option>Zenith Bank</option>
                    <option>Kuda Bank</option>
                    <option>OPay Digital Bank</option>
                    <option>Palmpay</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Account Number</label>
                <input type="tel" name="acc" maxlength="10" required placeholder="0123456789" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold text-lg tracking-widest">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Amount</label>
                <input type="number" name="amt" required placeholder="Min 100" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold text-xl">
            </div>
            <div class="flex justify-between items-center px-1"><span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Transfer Fee</span><span class="text-xs font-black"><?php echo format_currency($fee); ?></span></div>
            <button type="submit" class="w-full bg-vtu-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-95">SEND MONEY</button>
        </form>

        <div class="space-y-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Recent Transfers</h3>
            <div class="space-y-3">
                <?php if (empty($recentTransfers)): ?>
                    <div class="py-12 text-center text-gray-300 font-black text-[10px] uppercase tracking-widest bg-white rounded-[32px] border border-gray-100 border-dashed">No recent transfers</div>
                <?php else: ?>
                    <?php foreach ($recentTransfers as $t): ?>
                        <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex items-center justify-between shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600"><i data-lucide="arrow-up-right" size="20"></i></div>
                                <div><div class="text-xs font-black text-gray-800"><?php echo h($t['recipient']); ?></div><div class="text-[9px] text-gray-400 font-bold"><?php echo h($t['provider']); ?> • <?php echo date('M d', strtotime($t['date'])); ?></div></div>
                            </div>
                            <div class="text-xs font-black text-red-500">-<?php echo format_currency($t['amount']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
