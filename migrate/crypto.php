<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Crypto Hub';
$error = '';
$success = '';

// Security Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Token Failed');

    // Check Fund Password
    if (!verifyFundPassword($pdo, $currentUser['id'], $_POST['fund_password'] ?? '')) {
        $error = "Incorrect Fund Password. Action denied.";
    }
}

// Handle Buy (Simulated via Bybit Price)
if (!$error && isset($_POST['buy_amount'])) {
    $coin = $_POST['coin'];
    $amountNgn = (float)$_POST['buy_amount'];
    $rate = bybitGetMarketPrice($pdo, $coin . 'USDT') ?: 95000; // Simplified fallback rate
    // Simplified conversion NGN -> USDT -> Coin
    $usdtRate = 1600; // Mock NGN/USDT
    $coinAmount = ($amountNgn / $usdtRate) / $rate;

    if ($currentUser['walletBalance'] >= $amountNgn) {
        if (updateWallet($pdo, $currentUser['id'], $amountNgn, 'debit')) {
            logTransaction($pdo, $currentUser['id'], "Crypto Buy", $amountNgn, 'successful', "Bought " . number_format($coinAmount, 8) . " $coin", "Bybit Hub");
            $success = "Purchase successful! " . number_format($coinAmount, 8) . " $coin added to your portfolio.";
            $currentUser = fetchUser($pdo, $currentUser['id']); // Refresh
        }
    } else {
        $error = "Insufficient balance.";
    }
}

// Handle Sell
if (!$error && isset($_POST['sell_amount'])) {
    $coin = $_POST['coin'];
    $coinAmount = (float)$_POST['sell_amount'];
    $rate = bybitGetMarketPrice($pdo, $coin . 'USDT') ?: 95000;
    $usdtRate = 1580; // Mock NGN/USDT Sell rate
    $amountNgn = ($coinAmount * $rate) * $usdtRate;

    // In a real system we would check user's crypto balance in Bybit or local table
    if (updateWallet($pdo, $currentUser['id'], $amountNgn, 'credit')) {
        logTransaction($pdo, $currentUser['id'], "Crypto Sell", $amountNgn, 'successful', "Sold " . number_format($coinAmount, 8) . " $coin", "Bybit Hub");
        $success = "Sale successful! " . formatCurrency($amountNgn) . " added to your wallet.";
        $currentUser = fetchUser($pdo, $currentUser['id']);
    }
}

// Handle Convert (Bybit Convert API)
if (!$error && isset($_POST['convert_from'])) {
    $from = $_POST['convert_from'];
    $to = $_POST['convert_to'];
    $amount = (float)$_POST['convert_amount'];

    // In a real integration, we'd check if user has enough of $from in their "Crypto Wallet"
    // For this implementation, we execute via Bybit and log it
    $res = bybitConvert($pdo, $from, $to, $amount);
    if (isset($res['retCode']) && $res['retCode'] == 0) {
        logTransaction($pdo, $currentUser['id'], "Crypto Convert", 0, 'successful', "Converted $amount $from to $to", "Bybit Hub");
        $success = "Conversion successful!";
    } else {
        $error = "Conversion failed: " . ($res['retMsg'] ?? 'Unknown Error');
    }
}

// Handle Transfer
if (!$error && isset($_POST['transfer_coin'])) {
    $coin = $_POST['transfer_coin'];
    $amount = (float)$_POST['transfer_amount'];
    $from = $_POST['transfer_from'];
    $to = $_POST['transfer_to'];

    $res = bybitTransfer($pdo, $coin, $amount, $from, $to);
    if (isset($res['retCode']) && $res['retCode'] == 0) {
        $success = "Transfer successful!";
    } else {
        $error = "Transfer failed: " . ($res['retMsg'] ?? 'Unknown Error');
    }
}

// Handle Earn (Savings)
if (!$error && isset($_POST['earn_product_id'])) {
    $productId = $_POST['earn_product_id'];
    $amount = (float)$_POST['earn_amount'];

    $res = bybitEarnPurchase($pdo, $productId, $amount);
    if (isset($res['retCode']) && $res['retCode'] == 0) {
        logTransaction($pdo, $currentUser['id'], "Crypto Earn", 0, 'successful', "Subscribed $amount to Savings", "Bybit Hub");
        $success = "Subscription successful! Your assets are now earning interest.";
    } else {
        $error = "Subscription failed: " . ($res['retMsg'] ?? 'Unknown Error');
    }
}

// Handle Withdrawal (P2P)
if (!$error && isset($_POST['withdraw_address'])) {
    $address = $_POST['withdraw_address'];
    $amount = (float)$_POST['withdraw_amount'];

    // Check Whitelist & Lock
    $stmt = $pdo->prepare("SELECT isLocked, unlockedAt FROM withdrawal_whitelist WHERE userId = ? AND address = ?");
    $stmt->execute([$currentUser['id'], $address]);
    $wl = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wl) {
        $error = "Address not whitelisted. Please add it in Security settings.";
    } elseif ($wl['isLocked'] && strtotime($wl['unlockedAt']) > time()) {
        $timeLeft = round((strtotime($wl['unlockedAt']) - time()) / 3600, 1);
        $error = "Address is under 24h cooling period. $timeLeft hours remaining.";
    } else {
        // Execute Withdrawal via Bybit
        $res = bybitWithdraw($pdo, $_POST['coin'], $amount, $address);
        if (isset($res['retCode']) && $res['retCode'] == 0) {
            $success = "Withdrawal request submitted successfully.";
        } else {
            $error = "Withdrawal failed: " . ($res['retMsg'] ?? 'Provider error');
        }
    }
}

// Get Market Prices
$prices = [
    'BTC' => bybitGetMarketPrice($pdo, 'BTCUSDT'),
    'ETH' => bybitGetMarketPrice($pdo, 'ETHUSDT'),
    'SOL' => bybitGetMarketPrice($pdo, 'SOLUSDT'),
    'USDT' => 1.00
];

// Fetch Positions & Savings (Optional/Live)
$positions = bybitGetPositions($pdo, 'linear'); // Example: USDT Perpetuals
$savings = bybitGetEarnProducts($pdo);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto bg-gray-900 min-h-screen pb-24 text-white">
    <!-- Bybit Style Header -->
    <div class="p-6 border-b border-white/5 flex justify-between items-center sticky top-0 bg-gray-900/80 backdrop-blur-xl z-20">
        <div class="flex items-center gap-4">
            <a href="/dashboard" class="w-10 h-10 bg-white/5 rounded-xl flex items-center justify-center text-gray-400">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-xl font-black uppercase tracking-tighter">Crypto Hub</h1>
        </div>
        <div class="flex items-center gap-2">
            <div class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                <span class="text-[9px] font-black text-amber-500 uppercase tracking-widest">Live Rates</span>
            </div>
        </div>
    </div>

    <div class="p-6 max-w-6xl mx-auto space-y-10">
        <?php if ($error): ?><div class="p-6 bg-red-500/10 text-red-400 rounded-[32px] text-[10px] font-black border border-red-500/20 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-6 bg-emerald-500/10 text-emerald-400 rounded-[32px] text-[10px] font-black border border-emerald-500/20 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <!-- Market Tickers -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($prices as $coin => $p): ?>
            <div class="bg-white/5 p-6 rounded-[32px] border border-white/10 hover:border-amber-500/50 transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-[10px] font-black text-white/40 uppercase tracking-widest"><?php echo $coin; ?>/USDT</span>
                    <i data-lucide="trending-up" class="w-4 h-4 text-emerald-500 opacity-0 group-hover:opacity-100 transition-all"></i>
                </div>
                <div class="text-xl font-black tracking-tight">$<?php echo number_format($p, 2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Action Tabs -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <!-- Left: Transaction Form -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white/5 p-10 rounded-[40px] border border-white/10 shadow-2xl relative">
                    <div class="flex gap-4 mb-10 overflow-x-auto pb-2 scrollbar-hide">
                        <button onclick="setTab('buy')" id="buyTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase bg-white text-gray-900 active-tab shadow-xl">Buy</button>
                        <button onclick="setTab('sell')" id="sellTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all">Sell</button>
                        <button onclick="setTab('convert')" id="convertTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all">Convert</button>
                        <button onclick="setTab('earn')" id="earnTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all">Earn</button>
                        <button onclick="setTab('transfer')" id="transferTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all">Transfer</button>
                        <button onclick="setTab('p2p')" id="p2pTab" class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all">Withdraw (P2P)</button>
                    </div>

                    <!-- Buy Form -->
                    <form id="buy-section" method="POST" class="crypto-section space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Select Asset</label>
                                <select name="coin" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="BTC" class="bg-gray-900">Bitcoin (BTC)</option>
                                    <option value="ETH" class="bg-gray-900">Ethereum (ETH)</option>
                                    <option value="USDT" class="bg-gray-900">Tether (USDT)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Pay with (NGN)</label>
                                <input type="number" name="buy_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                            <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>

                        <button type="submit" class="w-full bg-amber-500 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl shadow-amber-500/20 hover:scale-[1.02] active:scale-95 transition-all">Execute Buy Order</button>
                    </form>

                    <!-- Sell Form -->
                    <form id="sell-section" method="POST" class="crypto-section hidden space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Sell Asset</label>
                                <select name="coin" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-red-500">
                                    <option value="BTC" class="bg-gray-900">Bitcoin (BTC)</option>
                                    <option value="ETH" class="bg-gray-900">Ethereum (ETH)</option>
                                    <option value="USDT" class="bg-gray-900">Tether (USDT)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Amount to Sell</label>
                                <input type="number" step="any" name="sell_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                            <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-red-500">
                        </div>

                        <button type="submit" class="w-full bg-red-500 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl shadow-red-500/20 hover:scale-[1.02] active:scale-95 transition-all">Execute Sell Order</button>
                    </form>

                    <!-- Convert Section -->
                    <form id="convert-section" method="POST" class="crypto-section hidden space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">From</label>
                                <select name="convert_from" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="USDT" class="bg-gray-900">USDT</option>
                                    <option value="BTC" class="bg-gray-900">BTC</option>
                                    <option value="ETH" class="bg-gray-900">ETH</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">To</label>
                                <select name="convert_to" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="BTC" class="bg-gray-900">BTC</option>
                                    <option value="ETH" class="bg-gray-900">ETH</option>
                                    <option value="USDT" class="bg-gray-900">USDT</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Amount</label>
                            <input type="number" step="any" name="convert_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                            <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>
                        <button type="submit" class="w-full bg-amber-500 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl shadow-amber-500/20 hover:scale-[1.02] active:scale-95 transition-all">Execute Conversion</button>
                    </form>

                    <!-- Earn Section -->
                    <form id="earn-section" method="POST" class="crypto-section hidden space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Select Savings Product</label>
                            <select name="earn_product_id" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                <?php if (!empty($savings['result']['list'])): ?>
                                    <?php foreach ($savings['result']['list'] as $p): ?>
                                        <option value="<?php echo $p['productId']; ?>" class="bg-gray-900"><?php echo $p['coin']; ?> - <?php echo $p['estimateApr']; ?>% APR (<?php echo $p['productName']; ?>)</option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled class="bg-gray-900">No products available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Subscription Amount</label>
                            <input type="number" step="any" name="earn_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                            <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>
                        <button type="submit" class="w-full bg-emerald-500 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:scale-[1.02] active:scale-95 transition-all">Subscribe to Earn</button>
                    </form>

                    <!-- Transfer Section -->
                    <form id="transfer-section" method="POST" class="crypto-section hidden space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">From Account</label>
                                <select name="transfer_from" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="UNIFIED" class="bg-gray-900">Unified Account</option>
                                    <option value="FUND" class="bg-gray-900">Funding Account</option>
                                    <option value="CONTRACT" class="bg-gray-900">Contract Account</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">To Account</label>
                                <select name="transfer_to" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="FUND" class="bg-gray-900">Funding Account</option>
                                    <option value="UNIFIED" class="bg-gray-900">Unified Account</option>
                                    <option value="CONTRACT" class="bg-gray-900">Contract Account</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Coin</label>
                                <select name="transfer_coin" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                    <option value="USDT" class="bg-gray-900">USDT</option>
                                    <option value="BTC" class="bg-gray-900">BTC</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Amount</label>
                                <input type="number" step="any" name="transfer_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                            <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                        </div>
                        <button type="submit" class="w-full bg-white text-gray-900 py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl hover:scale-[1.02] active:scale-95 transition-all">Execute Transfer</button>
                    </form>

                    <!-- Withdraw Section (P2P) -->
                    <form id="p2p-section" method="POST" class="crypto-section hidden space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="p-6 bg-blue-500/10 border border-blue-500/20 rounded-3xl flex items-center gap-4">
                            <i data-lucide="shield-check" class="text-blue-400 w-6 h-6"></i>
                            <span class="text-[9px] font-black uppercase text-blue-400 leading-relaxed">Security Tip: Newly whitelisted addresses are locked for 24 hours.</span>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Withdrawal Address</label>
                                <input type="text" name="withdraw_address" placeholder="Enter Wallet Address" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Asset</label>
                                    <select name="coin" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                        <option value="USDT" class="bg-gray-900">USDT (TRC20)</option>
                                        <option value="BTC" class="bg-gray-900">BTC</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Amount</label>
                                    <input type="number" step="0.00000001" name="withdraw_amount" placeholder="0.00" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                                </div>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-3 block ml-4">Fund Password</label>
                                <input type="password" name="fund_password" placeholder="••••••" class="w-full bg-white/5 border-none rounded-2xl p-5 text-sm font-black focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-white text-gray-900 py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl hover:scale-[1.02] active:scale-95 transition-all">Submit Withdrawal</button>
                    </form>
                </div>
            </div>

            <!-- Right: Account Overview -->
            <div class="space-y-6">
                <!-- Live Positions -->
                <?php if (!empty($positions['result']['list'])): ?>
                <div class="bg-white/5 p-8 rounded-[40px] border border-white/10">
                    <h4 class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-6 flex justify-between items-center">
                        Active Positions
                        <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-500 rounded text-[7px]">Live</span>
                    </h4>
                    <div class="space-y-4">
                        <?php foreach ($positions['result']['list'] as $pos): if ($pos['size'] == 0) continue; ?>
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-black"><?php echo $pos['symbol']; ?></span>
                                <span class="text-[10px] font-black <?php echo $pos['unrealisedPnl'] >= 0 ? 'text-emerald-500' : 'text-red-500'; ?>">
                                    <?php echo $pos['unrealisedPnl'] >= 0 ? '+' : ''; ?><?php echo number_format($pos['unrealisedPnl'], 2); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-[8px] font-bold text-white/40">
                                <span>Size: <?php echo $pos['size']; ?></span>
                                <span>Entry: <?php echo number_format($pos['avgPrice'], 2); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white/5 p-8 rounded-[40px] border border-white/10">
                    <h4 class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-6">Asset Security</h4>
                    <div class="space-y-6">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-bold text-white/60 uppercase">2FA (Google)</span>
                            <span class="px-2 py-1 <?php echo $currentUser['google2faEnabled'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'; ?> text-[8px] font-black rounded-full uppercase"><?php echo $currentUser['google2faEnabled'] ? 'On' : 'Off'; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-bold text-white/60 uppercase">Fund Password</span>
                            <span class="px-2 py-1 <?php echo $currentUser['fundPassword'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'; ?> text-[8px] font-black rounded-full uppercase"><?php echo $currentUser['fundPassword'] ? 'Set' : 'Unset'; ?></span>
                        </div>
                        <a href="/security" class="block w-full text-center py-4 bg-white/5 border border-white/10 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-white/10 transition-all">Manage Security</a>
                    </div>
                </div>

                <div class="p-8 bg-amber-500/10 border border-amber-500/20 rounded-[40px] text-amber-500">
                    <div class="flex gap-4 items-start">
                        <i data-lucide="alert-triangle" class="w-6 h-6 shrink-0"></i>
                        <p class="text-[9px] font-black uppercase leading-relaxed">Warning: Crypto transactions are irreversible. Be security conscious and never share your fund password or 2FA codes with anyone.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function setTab(tab) {
        document.querySelectorAll('.crypto-section').forEach(s => s.classList.add('hidden'));
        document.getElementById(tab + '-section').classList.remove('hidden');

        document.querySelectorAll('[id$="Tab"]').forEach(btn => {
            btn.className = 'px-8 py-3 rounded-full text-[10px] font-black uppercase text-white/40 hover:bg-white/5 transition-all';
        });
        const active = document.getElementById(tab + 'Tab');
        active.className = 'px-8 py-3 rounded-full text-[10px] font-black uppercase bg-white text-gray-900 shadow-xl';
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
