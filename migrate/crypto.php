<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Crypto Hub';

$prices = getCryptoPrices();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $action = $_POST['action'];
    $coin = sanitize($_POST['coin']);
    $amount = (float)$_POST['amount'];

    $pdo->beginTransaction();
    try {
        if ($action === 'buy') {
            if ($currentUser['walletBalance'] < $amount) throw new Exception("Insufficient wallet balance.");
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');
            logTransaction($pdo, $currentUser['id'], 'Crypto Buy', $amount, 'successful', "Purchased $coin worth " . formatCurrency($amount), 'Crypto Wallet', $coin);
            $success = "Successfully purchased $coin!";
        } elseif ($action === 'sell') {
            logTransaction($pdo, $currentUser['id'], 'Crypto Sell', $amount, 'pending', "Sale request for $coin (Expected: " . formatCurrency($amount) . ")", 'System', $coin);
            $success = "Your sale request for $coin has been submitted for review!";
        } elseif ($action === 'swap' || $action === 'convert') {
            $fee = $amount * 0.01;
            if ($currentUser['walletBalance'] < $fee) throw new Exception("Insufficient balance to cover swap fee.");
            updateWallet($pdo, $currentUser['id'], $fee, 'debit');
            $targetCoin = sanitize($_POST['targetCoin'] ?? 'tether');
            logTransaction($pdo, $currentUser['id'], 'Crypto Swap', $amount, 'successful', "Swapped $coin for $targetCoin (Amount: " . formatCurrency($amount) . ")", $targetCoin, $coin);
            $success = "Successfully swapped $coin to $targetCoin!";
        } elseif ($action === 'withdraw') {
            if ($currentUser['walletBalance'] < $amount) throw new Exception("Insufficient balance to withdraw.");
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');
            logTransaction($pdo, $currentUser['id'], 'Crypto Withdrawal', $amount, 'successful', "Withdrew " . formatCurrency($amount) . " to external $coin wallet", $_POST['address'], $coin);
            $success = "Withdrawal request submitted!";
        }
        claimDailyRewardIfEligible($pdo, $currentUser['id']);
        $pdo->commit();
        $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
        $currentUser['walletBalance'] = $stmt->fetchColumn();
    } catch (Exception $e) { $pdo->rollBack(); $error = $e->getMessage(); }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center justify-between sticky top-0 z-40 border-b">
        <div class="flex items-center gap-4">
            <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
            <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Crypto Hub</h1>
        </div>
        <div class="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-[9px] font-black uppercase text-gray-500">Live Market</span>
        </div>
    </div>

    <div class="p-4 lg:p-8 space-y-8 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <?php
            $coins = ['bitcoin' => ['name' => 'BTC', 'color' => 'bg-orange-500', 'icon' => 'bitcoin'], 'ethereum' => ['name' => 'ETH', 'color' => 'bg-blue-600', 'icon' => 'gem'], 'binancecoin' => ['name' => 'BNB', 'color' => 'bg-yellow-500', 'icon' => 'coins'], 'solana' => ['name' => 'SOL', 'color' => 'bg-purple-500', 'icon' => 'zap'], 'tether' => ['name' => 'USDT', 'color' => 'bg-emerald-500', 'icon' => 'anchor']];
            foreach ($coins as $id => $info):
                $data = $prices[$id] ?? ['ngn' => 0, 'ngn_24h_change' => 0];
                $change = $data['ngn_24h_change'] ?? 0;
            ?>
            <div class="bg-white p-5 rounded-[32px] shadow-sm border border-gray-100 space-y-3 text-gray-900">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 <?php echo $info['color']; ?> rounded-xl flex items-center justify-center text-white shadow-lg"><i data-lucide="<?php echo $info['icon']; ?>" class="w-4 h-4"></i></div>
                    <span class="text-[10px] font-black <?php echo $change >= 0 ? 'text-green-500' : 'text-red-500'; ?>"><?php echo ($change >= 0 ? '+' : '') . number_format($change, 2); ?>%</span>
                </div>
                <div>
                    <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest"><?php echo $info['name']; ?></div>
                    <div class="text-sm font-black text-gray-900">₦<?php echo number_format($data['ngn'] ?? 0, 2); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                    <div class="flex bg-gray-100 p-1.5 rounded-2xl mb-8">
                        <button onclick="setMode('buy')" id="btnBuy" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green">Buy</button>
                        <button onclick="setMode('sell')" id="btnSell" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-500">Sell</button>
                        <button onclick="setMode('swap')" id="btnSwap" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-500">Swap</button>
                        <button onclick="setMode('withdraw')" id="btnWithdraw" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-500">Withdraw</button>
                    </div>

                    <form method="POST" id="cryptoForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="actionInput" value="buy">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div id="fromGroup">
                                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Select Asset</label>
                                <select name="coin" id="coinSelect" onchange="updateExchange()" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg border-2 border-transparent focus:border-billpay-green outline-none transition-all"><?php foreach ($coins as $id => $info): ?><option value="<?php echo $id; ?>"><?php echo $info['name']; ?> - <?php echo ucfirst($id); ?></option><?php endforeach; ?></select>
                            </div>
                            <div id="toGroup" class="hidden">
                                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Swap To</label>
                                <select name="targetCoin" id="targetCoinSelect" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg border-2 border-transparent focus:border-billpay-green outline-none transition-all"><?php foreach ($coins as $id => $info): ?><option value="<?php echo $id; ?>" <?php echo $id === 'tether' ? 'selected' : ''; ?>><?php echo $info['name']; ?> - <?php echo ucfirst($id); ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                        <div id="addressGroup" class="hidden">
                            <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Wallet Address</label>
                            <input type="text" name="address" placeholder="Enter recipient address" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-sm outline-none border-2 border-transparent focus:border-billpay-green">
                        </div>
                        <div class="relative">
                            <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (NGN)</label>
                            <input type="number" name="amount" id="amountInput" oninput="updateExchange()" placeholder="0.00" class="w-full p-6 bg-gray-50 rounded-3xl font-black text-2xl outline-none focus:ring-4 focus:ring-billpay-green/5" required>
                            <div class="absolute right-6 bottom-6 text-[10px] font-black text-gray-400">≈ <span id="coinEquivalent">0.000000</span> <span id="coinLabel">BTC</span></div>
                        </div>
                        <button type="submit" id="submitBtn" class="w-full bg-gray-900 text-white font-black py-6 rounded-[32px] shadow-xl hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest">Confirm Purchase</button>
                    </form>
                </div>
            </div>
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-gray-900 to-black p-8 rounded-[40px] text-white shadow-2xl relative overflow-hidden">
                    <div class="relative z-10"><div class="text-[10px] font-black uppercase tracking-widest opacity-50 mb-2">Estimated Balance</div><div class="text-3xl font-black tracking-tighter">₦<?php echo number_format($currentUser['walletBalance'], 2); ?></div><div class="mt-8 pt-8 border-t border-white/10 space-y-4"><div class="flex justify-between items-center"><span class="text-[10px] font-black uppercase opacity-40">Portfolio Value</span><span class="text-xs font-black text-billpay-green">+₦12,450.00</span></div><div class="flex justify-between items-center"><span class="text-[10px] font-black uppercase opacity-40">Active Assets</span><span class="text-xs font-black">5 Coins</span></div></div></div>
                    <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-billpay-green/10 rounded-full blur-3xl"></div>
                </div>
                <div class="bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm"><h3 class="text-xs font-black uppercase tracking-widest mb-6">Recent Activity</h3><div class="space-y-6"><div class="flex items-center gap-4"><div class="w-10 h-10 bg-green-50 text-green-500 rounded-xl flex items-center justify-center"><i data-lucide="arrow-down-left" class="w-5 h-5"></i></div><div><div class="text-[10px] font-black text-gray-900 uppercase">Received BTC</div><div class="text-[9px] font-bold text-gray-400 uppercase">2 hours ago</div></div></div><div class="flex items-center gap-4"><div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center"><i data-lucide="repeat" class="w-5 h-5"></i></div><div><div class="text-[10px] font-black text-gray-900 uppercase">Swapped ETH/USDT</div><div class="text-[9px] font-bold text-gray-400 uppercase">Yesterday</div></div></div></div></div>
            </div>
        </div>
    </div>
</div>

<script>
    const coinData = <?php echo json_encode($coins); ?>;
    const priceData = <?php echo json_encode($prices); ?>;
    function setMode(mode) {
        document.getElementById('actionInput').value = mode;
        ['Buy', 'Sell', 'Swap', 'Withdraw'].forEach(b => {
            const btn = document.getElementById('btn' + b);
            btn.classList.remove('bg-white', 'shadow-md', 'text-billpay-green');
            btn.classList.add('text-gray-500');
        });
        document.getElementById('btn' + mode.charAt(0).toUpperCase() + mode.slice(1)).classList.add('bg-white', 'shadow-md', 'text-billpay-green');
        document.getElementById('btn' + mode.charAt(0).toUpperCase() + mode.slice(1)).classList.remove('text-gray-500');
        const submitBtn = document.getElementById('submitBtn');
        const addressGroup = document.getElementById('addressGroup');
        const toGroup = document.getElementById('toGroup');
        if (mode === 'buy') { submitBtn.innerText = 'Confirm Purchase'; addressGroup.classList.add('hidden'); toGroup.classList.add('hidden'); }
        else if (mode === 'sell') { submitBtn.innerText = 'Sell to Wallet'; addressGroup.classList.add('hidden'); toGroup.classList.add('hidden'); }
        else if (mode === 'swap') { submitBtn.innerText = 'Swap Assets'; addressGroup.classList.add('hidden'); toGroup.classList.remove('hidden'); }
        else if (mode === 'withdraw') { submitBtn.innerText = 'Withdraw to External Wallet'; addressGroup.classList.remove('hidden'); toGroup.classList.add('hidden'); }
        updateExchange();
    }
    function updateExchange() {
        const coin = document.getElementById('coinSelect').value;
        const amount = parseFloat(document.getElementById('amountInput').value) || 0;
        const price = priceData[coin]?.ngn || 1;
        const equivalent = amount / price;
        document.getElementById('coinEquivalent').innerText = equivalent.toFixed(8);
        document.getElementById('coinLabel').innerText = coinData[coin]?.name || 'BTC';
    }
    document.addEventListener('DOMContentLoaded', updateExchange);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
