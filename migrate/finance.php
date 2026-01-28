<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Elite Finance';
$error = '';
$success = '';

// Handle AJAX Requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'getBanks') {
        $country = sanitize($_GET['country'] ?? 'NG');
        echo json_encode(juicywayGetBanks($pdo, $country));
        exit;
    }
    if ($_GET['ajax'] === 'getQuote') {
        $amount = (float)$_GET['amount'];
        $from = sanitize($_GET['from']);
        $to = sanitize($_GET['to']);
        echo json_encode(juicywayGetQuote($pdo, $amount, $from, $to));
        exit;
    }
    if ($_GET['ajax'] === 'getBeneficiaries') {
        $type = sanitize($_GET['type']);
        $stmt = $pdo->prepare("SELECT * FROM beneficiaries WHERE userId = ? AND type = ? ORDER BY createdAt DESC");
        $stmt->execute([$currentUser['id'], $type]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

// Fetch Wallets/Balances from JuicyWay
$walletsRes = juicywayGetWallets($pdo);
$wallets = $walletsRes['data'] ?? [];

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $action = $_POST['action'];
    $description = sanitize($_POST['description'] ?? '');

    // Security Verification (Mandatory for all financial operations)
    if (!verifyFundPassword($pdo, $currentUser['id'], $_POST['fund_password'] ?? '')) {
        $error = "Incorrect Fund Password. Action denied.";
    } else {
    try {
        if ($action === 'transfer_bank') {
            $currency = sanitize($_POST['currency']);
            $amount = (float)$_POST['amount'];

            // Check Local/JuicyWay balance
            $currentWallet = array_filter($wallets, fn($w) => $w['currency'] === $currency);
            $avlBal = $currentWallet ? (float)reset($currentWallet)['balance'] : 0;
            if ($avlBal < $amount) throw new Exception("Insufficient balance in $currency wallet.");

            $payoutData = [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'payment_method' => 'bank_transfer',
                'account_number' => sanitize($_POST['account_number']),
                'bank_code' => sanitize($_POST['bank_code']),
                'account_name' => sanitize($_POST['account_name'])
            ];

            if ($currency === 'GBP') {
                $payoutData['bank_name'] = sanitize($_POST['bank_name']);
            } elseif ($currency === 'USD') {
                $payoutData['routing_number'] = sanitize($_POST['routing_number']);
                $payoutData['transfer_type'] = sanitize($_POST['usd_type']); // ach, fedwire, swift
            }

            $res = juicywayInitiatePayout($pdo, $payoutData);
            $resData = $res['data'] ?? $res;
            if ((isset($resData['status']) && $resData['status'] === 'failed') || (isset($res['status']) && $res['status'] === 'error')) throw new Exception($res['message'] ?? $resData['message'] ?? 'Transfer failed');

            // Save Beneficiary
            if (isset($_POST['save_beneficiary'])) {
                $details = json_encode(['account_number' => $_POST['account_number'], 'bank_code' => $_POST['bank_code'] ?? '', 'account_name' => $_POST['account_name'], 'bank_name' => $_POST['bank_name'] ?? '']);
                $pdo->prepare("INSERT INTO beneficiaries (userId, type, currency, details, description) VALUES (?, 'bank', ?, ?, ?)")->execute([$currentUser['id'], $currency, $details, $description]);
            }

            logTransaction($pdo, $currentUser['id'], "Bank Transfer ($currency)", $amount, 'successful', $description, $_POST['account_number']);
            $success = "Transfer initiated successfully!";
        }
        elseif ($action === 'transfer_internal') {
            $currency = sanitize($_POST['currency']);
            $amount = (float)$_POST['amount'];
            $recipient = sanitize($_POST['recipient']); // email or tag

            $res = juicywayInitiatePayout($pdo, [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'payment_method' => 'internal',
                'recipient' => $recipient
            ]);
            $resData = $res['data'] ?? $res;
            if ((isset($resData['status']) && $resData['status'] === 'failed') || (isset($res['status']) && $res['status'] === 'error')) throw new Exception($res['message'] ?? $resData['message'] ?? 'Internal transfer failed');

            logTransaction($pdo, $currentUser['id'], "Internal Transfer ($currency)", $amount, 'successful', $description, $recipient);
            $success = "Sent $amount $currency to $recipient";
        }
        elseif ($action === 'transfer_crypto') {
            $currency = sanitize($_POST['currency']); // USDT, USDC
            $amount = (float)$_POST['amount'];
            $address = sanitize($_POST['address']);
            $network = sanitize($_POST['network']);

            $res = juicywayInitiatePayout($pdo, [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'payment_method' => 'crypto',
                'address' => $address,
                'network' => $network
            ]);
            $resData = $res['data'] ?? $res;
            if ((isset($resData['status']) && $resData['status'] === 'failed') || (isset($res['status']) && $res['status'] === 'error')) throw new Exception($res['message'] ?? $resData['message'] ?? 'Crypto transfer failed');

            logTransaction($pdo, $currentUser['id'], "Crypto Transfer ($currency)", $amount, 'successful', $description, $address);
            $success = "Crypto payout of $amount $currency processed.";
        }
        elseif ($action === 'request') {
            $amount = (float)$_POST['amount'];
            $currency = sanitize($_POST['currency']);
            $res = juicywayCreatePaymentLink($pdo, $amount, $currency, $description, $currentUser);
            $resData = $res['data'] ?? $res;
            if ((isset($resData['status']) && $resData['status'] === 'failed') || (isset($res['status']) && $res['status'] === 'error')) throw new Exception($res['message'] ?? $resData['message'] ?? 'Failed to generate request link');

            $linkId = $resData['id'] ?? uniqid();
            $pdo->prepare("INSERT INTO payment_links (id, userId, amount, currency, description) VALUES (?, ?, ?, ?, ?)")->execute([$linkId, $currentUser['id'], $amount, $currency, $description]);
            $success = "Payment link generated: " . ($res['data']['url'] ?? '#');
        }
        elseif ($action === 'convert') {
            $amount = (float)$_POST['amount'];
            $from = sanitize($_POST['from_currency']);
            $to = sanitize($_POST['to_currency']);
            $quoteId = sanitize($_POST['quote_id']);

            $res = juicywaySwap($pdo, $amount, $from, $to, $quoteId);
            $resData = $res['data'] ?? $res;
            if ((isset($resData['status']) && $resData['status'] === 'failed') || (isset($res['status']) && $res['status'] === 'error')) throw new Exception($res['message'] ?? $resData['message'] ?? 'Conversion failed');

            logTransaction($pdo, $currentUser['id'], "Currency Conversion", $amount, 'successful', "Converted $from to $to", 'System');
            $success = "Successfully converted $from to $to!";
        }

        // Refresh Wallets
        $walletsRes = juicywayGetWallets($pdo);
        $wallets = $walletsRes['data'] ?? [];

    } catch (Exception $e) { $error = $e->getMessage(); }
    }
}

// Fetch Local Transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE userId = ? ORDER BY date DESC LIMIT 20");
$stmt->execute([$currentUser['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <!-- Header -->
    <div class="bg-white p-6 flex items-center justify-between sticky top-0 z-40 border-b">
        <div class="flex items-center gap-4">
            <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
            <h1 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Elite Finance</h1>
        </div>
        <div class="w-10 h-10 bg-gray-100 rounded-2xl flex items-center justify-center">
            <i data-lucide="crown" class="w-5 h-5 text-amber-500"></i>
        </div>
    </div>

    <div class="p-4 lg:p-10 space-y-10 max-w-6xl mx-auto w-full">
        <?php if ($error): ?><div class="p-6 bg-red-50 text-red-800 rounded-[32px] text-xs font-black border border-red-100 uppercase text-center shadow-sm"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-6 bg-green-50 text-green-800 rounded-[32px] text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div><?php endif; ?>

        <!-- Main Action Icons -->
        <div class="grid grid-cols-4 gap-4 md:gap-8">
            <button onclick="showSection('deposit')" class="flex flex-col items-center gap-3 group">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-white shadow-lg rounded-[28px] flex items-center justify-center group-hover:bg-billpay-green group-hover:text-white transition-all duration-500 border border-gray-100">
                    <i data-lucide="download" class="w-6 h-6 md:w-8 md:h-8"></i>
                </div>
                <span class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900">Deposit</span>
            </button>
            <button onclick="showSection('transfer')" class="flex flex-col items-center gap-3 group">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-white shadow-lg rounded-[28px] flex items-center justify-center group-hover:bg-billpay-green group-hover:text-white transition-all duration-500 border border-gray-100">
                    <i data-lucide="send" class="w-6 h-6 md:w-8 md:h-8"></i>
                </div>
                <span class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900">Transfer</span>
            </button>
            <button onclick="showSection('request')" class="flex flex-col items-center gap-3 group">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-white shadow-lg rounded-[28px] flex items-center justify-center group-hover:bg-billpay-green group-hover:text-white transition-all duration-500 border border-gray-100">
                    <i data-lucide="link" class="w-6 h-6 md:w-8 md:h-8"></i>
                </div>
                <span class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900">Request</span>
            </button>
            <button onclick="showSection('convert')" class="flex flex-col items-center gap-3 group">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-white shadow-lg rounded-[28px] flex items-center justify-center group-hover:bg-billpay-green group-hover:text-white transition-all duration-500 border border-gray-100">
                    <i data-lucide="repeat" class="w-6 h-6 md:w-8 md:h-8"></i>
                </div>
                <span class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900">Convert</span>
            </button>
        </div>

        <!-- Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <!-- Left Panel: Forms -->
            <div class="lg:col-span-2 space-y-10">

                <!-- Deposit Section -->
                <div id="depositSection" class="bg-white p-8 md:p-12 rounded-[48px] shadow-sm border border-gray-100 hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-10">
                        <h2 class="text-2xl font-black uppercase tracking-tighter">Fund Wallet</h2>
                        <button onclick="hideSections()" class="text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <a href="/add-money" class="p-8 bg-gray-50 rounded-[32px] border border-gray-100 hover:border-billpay-green transition-all group">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-sm flex items-center justify-center mb-4 group-hover:bg-billpay-green group-hover:text-white transition-all"><i data-lucide="credit-card" class="w-6 h-6"></i></div>
                            <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Online Payment</div>
                            <div class="text-sm font-black text-gray-900 uppercase">Pay via Paystack</div>
                        </a>
                        <a href="/add-money?method=manual" class="p-8 bg-gray-50 rounded-[32px] border border-gray-100 hover:border-billpay-green transition-all group">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-sm flex items-center justify-center mb-4 group-hover:bg-billpay-green group-hover:text-white transition-all"><i data-lucide="building-2" class="w-6 h-6"></i></div>
                            <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Manual Funding</div>
                            <div class="text-sm font-black text-gray-900 uppercase">Direct Bank Transfer</div>
                        </a>
                    </div>
                </div>

                <!-- Transfer Section -->
                <div id="transferSection" class="bg-white p-8 md:p-12 rounded-[48px] shadow-sm border border-gray-100 hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-10">
                        <h2 class="text-2xl font-black uppercase tracking-tighter">Transfer Funds</h2>
                        <button onclick="hideSections()" class="text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
                    </div>

                    <!-- Transfer Sub-Tabs -->
                    <div class="flex overflow-x-auto gap-3 pb-6 no-scrollbar mb-8">
                        <button onclick="setTransferType('bank')" id="tabBank" class="px-6 py-3 rounded-full bg-gray-900 text-white text-[10px] font-black uppercase whitespace-nowrap">Bank Payout</button>
                        <button onclick="setTransferType('internal')" id="tabInternal" class="px-6 py-3 rounded-full bg-gray-100 text-gray-500 text-[10px] font-black uppercase whitespace-nowrap">Internal (C2C)</button>
                        <button onclick="setTransferType('crypto')" id="tabCrypto" class="px-6 py-3 rounded-full bg-gray-100 text-gray-500 text-[10px] font-black uppercase whitespace-nowrap">Crypto Payout</button>
                        <button onclick="setTransferType('interac')" id="tabInterac" class="px-6 py-3 rounded-full bg-gray-100 text-gray-500 text-[10px] font-black uppercase whitespace-nowrap">Interac (CAD)</button>
                    </div>

                    <form method="POST" id="transferForm" class="space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="transferAction" value="transfer_bank">

                        <!-- Common Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Select Currency</label>
                                <select name="currency" id="transferCurrency" onchange="onTransferCurrencyChange()" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm border-2 border-transparent focus:border-billpay-green outline-none transition-all">
                                    <option value="NGN">Naira (NGN)</option>
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="GBP">British Pound (GBP)</option>
                                    <option value="CAD">Canadian Dollar (CAD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                    <option value="USDT">Tether (USDT)</option>
                                    <option value="USDC">USD Coin (USDC)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Amount</label>
                                <input type="number" name="amount" step="any" placeholder="0.00" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-lg outline-none border-2 border-transparent focus:border-billpay-green" required>
                            </div>
                        </div>

                        <!-- Bank Specific -->
                        <div id="bankFields" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div id="bankSelectGroup">
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Select Bank</label>
                                    <select name="bank_code" id="bankSelect" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none border-2 border-transparent focus:border-billpay-green"></select>
                                </div>
                                <div id="bankNameGroup" class="hidden">
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Bank Name</label>
                                    <input type="text" name="bank_name" placeholder="Enter bank name" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Account Number</label>
                                    <input type="text" name="account_number" placeholder="0000000000" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                </div>
                            </div>
                            <div id="usdFields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Routing Number</label>
                                    <input type="text" name="routing_number" placeholder="Routing / Sort Code" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">USD Transfer Type</label>
                                    <select name="usd_type" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                        <option value="ach">ACH</option>
                                        <option value="fedwire">FED WIRE</option>
                                        <option value="swift">SWIFT</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Account Name</label>
                                <input type="text" name="account_name" placeholder="Full Account Name" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                            </div>
                        </div>

                        <!-- Internal Specific -->
                        <div id="internalFields" class="hidden space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Recipient Email or User Tag</label>
                                <input type="text" name="recipient" placeholder="user@example.com or @username" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none border-2 border-transparent focus:border-billpay-green">
                            </div>
                        </div>

                        <!-- Crypto Specific -->
                        <div id="cryptoFields" class="hidden space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Network</label>
                                    <select name="network" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none border-2 border-transparent focus:border-billpay-green">
                                        <option value="trc20">TRON (TRC20)</option>
                                        <option value="erc20">ETHEREUM (ERC20)</option>
                                        <option value="bsc">BINANCE SMART CHAIN (BEP20)</option>
                                        <option value="solana">SOLANA</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Wallet Address</label>
                                    <input type="text" name="address" placeholder="Enter crypto address" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Description (Required)</label>
                                <input type="text" name="description" placeholder="What is this for?" class="w-full p-5 bg-gray-50 rounded-[24px] font-bold text-sm outline-none" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Fund Password</label>
                                <input type="password" name="fund_password" placeholder="••••••" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none" required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <input type="checkbox" name="save_beneficiary" id="saveBen" class="w-5 h-5 accent-billpay-green">
                            <label for="saveBen" class="text-[10px] font-black uppercase text-gray-500">Save to Beneficiaries</label>
                        </div>

                        <button type="submit" class="w-full bg-gray-900 text-white font-black py-6 rounded-[32px] shadow-xl hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest">Execute Transfer</button>
                    </form>

                    <!-- Beneficiaries List -->
                    <div id="benList" class="mt-12 pt-12 border-t border-gray-100">
                        <h3 class="text-xs font-black uppercase tracking-widest mb-6">Recent Beneficiaries</h3>
                        <div id="benItems" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                    </div>
                </div>

                <!-- Convert Section -->
                <div id="convertSection" class="bg-white p-8 md:p-12 rounded-[48px] shadow-sm border border-gray-100 hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-10">
                        <h2 class="text-2xl font-black uppercase tracking-tighter">Swap Currencies</h2>
                        <button onclick="hideSections()" class="text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-2xl flex items-center gap-3 mb-8">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                        <p class="text-[10px] font-black uppercase text-blue-600 tracking-tight">Convert instantly: Rates update at 10 seconds interval</p>
                    </div>

                    <form method="POST" id="convertForm" class="space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="convert">
                        <input type="hidden" name="quote_id" id="quoteId">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div class="space-y-4">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">From</label>
                                <div class="relative">
                                    <select name="from_currency" id="fromCurrency" class="w-full p-6 bg-gray-50 rounded-[28px] font-black text-lg outline-none appearance-none">
                                        <option value="NGN">NGN</option>
                                        <option value="USD">USD</option>
                                        <option value="GBP">GBP</option>
                                        <option value="USDT">USDT</option>
                                    </select>
                                    <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none"><i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i></div>
                                </div>
                                <input type="number" name="amount" id="convertAmount" placeholder="0.00" class="w-full p-8 bg-gray-900 text-white rounded-[32px] font-black text-3xl outline-none" required>
                            </div>

                            <div class="flex justify-center -my-4 md:my-0">
                                <div class="w-12 h-12 bg-billpay-green text-white rounded-full flex items-center justify-center shadow-lg"><i data-lucide="repeat" class="w-6 h-6"></i></div>
                            </div>

                            <div class="space-y-4">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">To</label>
                                <div class="relative">
                                    <select name="to_currency" id="toCurrency" class="w-full p-6 bg-gray-50 rounded-[28px] font-black text-lg outline-none appearance-none">
                                        <option value="USDT">USDT</option>
                                        <option value="NGN">NGN</option>
                                        <option value="USD">USD</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                    <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none"><i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i></div>
                                </div>
                                <div class="w-full p-8 bg-gray-100 rounded-[32px] font-black text-3xl text-gray-400" id="toAmountDisplay">0.00</div>
                            </div>
                        </div>

                        <button type="button" onclick="getQuote()" id="btnGetQuote" class="w-full bg-gray-100 text-gray-900 font-black py-6 rounded-[32px] uppercase tracking-widest transition-all">Preview Conversion</button>

                        <div id="rateDisplay" class="hidden text-center py-4">
                            <span class="text-xs font-black text-indigo-500" id="liveRateText">Rate: 1 [FROM] ~ [RATE] [TO]</span>
                        </div>

                        <div id="quoteInfo" class="hidden space-y-6">
                            <div class="p-6 bg-amber-50 rounded-[24px] border border-amber-100 text-center space-y-2">
                                <div class="text-[10px] font-black uppercase text-amber-600 tracking-widest">Quote expires in</div>
                                <div class="text-3xl font-black text-amber-800" id="quoteTimer">30s</div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Fund Password</label>
                                <input type="password" name="fund_password" placeholder="••••••" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                            </div>
                        </div>

                        <button type="submit" id="btnSwapSubmit" class="w-full bg-billpay-green text-white font-black py-6 rounded-[32px] shadow-xl uppercase tracking-widest hidden">Confirm Swap</button>
                    </form>
                </div>

                <!-- Request Section -->
                <div id="requestSection" class="bg-white p-8 md:p-12 rounded-[48px] shadow-sm border border-gray-100 hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-10">
                        <h2 class="text-2xl font-black uppercase tracking-tighter">Generate Payment Link</h2>
                        <button onclick="hideSections()" class="text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
                    </div>
                    <form method="POST" class="space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="request">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Currency</label>
                                <select name="currency" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none">
                                    <option value="NGN">NGN</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Amount</label>
                                <input type="number" name="amount" placeholder="0.00" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-lg outline-none" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Description</label>
                                <input type="text" name="description" placeholder="Internal Memo" class="w-full p-5 bg-gray-50 rounded-[24px] font-bold text-sm outline-none" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Fund Password</label>
                                <input type="password" name="fund_password" placeholder="••••••" class="w-full p-5 bg-gray-50 rounded-[24px] font-black text-sm outline-none" required>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gray-900 text-white font-black py-6 rounded-[32px] shadow-xl uppercase tracking-widest">Generate Link</button>
                    </form>
                </div>

                <!-- Default Balances & Tabs -->
                <div id="mainTabs" class="space-y-10">
                    <div class="flex bg-white p-2 rounded-[32px] shadow-sm border border-gray-100">
                        <button onclick="setMainTab('balances')" id="tabBal" class="flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase bg-gray-900 text-white shadow-lg transition-all">Balances</button>
                        <button onclick="setMainTab('transactions')" id="tabTx" class="flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase text-gray-400 transition-all">Transactions</button>
                    </div>

                    <!-- Balances Tab -->
                    <div id="balancesTab" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <?php
                        $supported = ['NGN', 'USD', 'GBP', 'CAD', 'EUR', 'USDT', 'USDC'];
                        foreach ($supported as $curr):
                            $w = array_filter($wallets, fn($wal) => $wal['currency'] === $curr);
                            $w = $w ? reset($w) : ['balance' => 0.00, 'ledger_balance' => 0.00];
                            $color = ($curr === 'NGN') ? 'bg-billpay-green' : (($curr === 'USD') ? 'bg-blue-600' : 'bg-gray-800');
                        ?>
                        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex items-center justify-between group hover:border-billpay-green transition-all">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full <?php echo $color; ?>"></div>
                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?php echo $curr; ?> Wallet</span>
                                </div>
                                <div class="text-2xl font-black text-gray-900"><?php echo number_format($w['balance'], 2); ?> <span class="text-[10px] opacity-30"><?php echo $curr; ?></span></div>
                                <div class="text-[9px] font-black text-gray-400 uppercase">LDG: <?php echo number_format($w['ledger_balance'] ?? $w['balance'], 2); ?></div>
                            </div>
                            <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300 group-hover:bg-billpay-green group-hover:text-white transition-all">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Transactions Tab -->
                    <div id="transactionsTab" class="bg-white p-8 md:p-12 rounded-[48px] shadow-sm border border-gray-100 hidden animate-fade-in">
                        <div class="space-y-8">
                            <?php foreach ($transactions as $tx): ?>
                            <div class="flex items-center justify-between group">
                                <div class="flex items-center gap-5">
                                    <div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center text-gray-400 group-hover:bg-billpay-green group-hover:text-white transition-all">
                                        <i data-lucide="<?php echo strpos($tx['type'], 'Transfer') !== false ? 'send' : (strpos($tx['type'], 'Conversion') !== false ? 'repeat' : 'arrow-down-left'); ?>" class="w-6 h-6"></i>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-black text-gray-900 uppercase"><?php echo $tx['type']; ?></div>
                                        <div class="text-[9px] font-bold text-gray-400 uppercase"><?php echo date('d M, H:i', strtotime($tx['date'])); ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-black <?php echo $tx['status'] === 'successful' ? 'text-gray-900' : 'text-red-500'; ?>"><?php echo ($tx['amount'] > 0 ? '' : '') . number_format($tx['amount'], 2); ?></div>
                                    <div class="text-[8px] font-black text-gray-300 uppercase"><?php echo $tx['status']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; if(empty($transactions)) echo '<div class="text-center py-10 text-[10px] font-black text-gray-400 uppercase">No transactions yet</div>'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Info -->
            <div class="space-y-10">
                <div class="bg-gray-900 p-10 rounded-[48px] text-white shadow-2xl relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-[10px] font-black uppercase tracking-widest opacity-40 mb-2">Total Estimated Value</div>
                        <div class="text-4xl font-black tracking-tighter">₦<?php echo number_format($currentUser['walletBalance'], 2); ?></div>
                        <div class="mt-10 pt-10 border-t border-white/10 space-y-6">
                            <div class="flex justify-between items-center"><span class="text-[10px] font-black uppercase opacity-30">KYC Status</span><span class="px-3 py-1 bg-green-500/20 text-green-500 text-[9px] font-black rounded-full uppercase"><?php echo $currentUser['kycStatus']; ?></span></div>
                            <div class="flex justify-between items-center"><span class="text-[10px] font-black uppercase opacity-30">Tier Limit</span><span class="text-[10px] font-black">₦10,000,000.00</span></div>
                        </div>
                    </div>
                    <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-billpay-green/20 rounded-full blur-[100px]"></div>
                </div>

                <div class="bg-white p-10 rounded-[48px] border border-gray-100 shadow-sm">
                    <h3 class="text-[11px] font-black uppercase tracking-widest mb-8 text-gray-400">Security Tips</h3>
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center flex-shrink-0"><i data-lucide="shield-check" class="w-4 h-4"></i></div>
                            <p class="text-[10px] font-bold text-gray-500 leading-relaxed uppercase">Always verify the recipient details before confirming any payout. Transfers are final.</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center flex-shrink-0"><i data-lucide="info" class="w-4 h-4"></i></div>
                            <p class="text-[10px] font-bold text-gray-500 leading-relaxed uppercase">Conversion rates are updated live. You have 30 seconds to confirm a swap quote.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSection(id) {
        document.getElementById('mainTabs').classList.add('hidden');
        ['transfer', 'convert', 'request'].forEach(s => document.getElementById(s + 'Section').classList.add('hidden'));
        document.getElementById(id + 'Section').classList.remove('hidden');
        if (id === 'transfer') onTransferCurrencyChange();
    }

    function hideSections() {
        ['transfer', 'convert', 'request'].forEach(s => document.getElementById(s + 'Section').classList.add('hidden'));
        document.getElementById('mainTabs').classList.remove('hidden');
    }

    function setMainTab(tab) {
        document.getElementById('balancesTab').classList.toggle('hidden', tab !== 'balances');
        document.getElementById('transactionsTab').classList.toggle('hidden', tab !== 'transactions');
        document.getElementById('tabBal').className = tab === 'balances' ? 'flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase bg-gray-900 text-white shadow-lg' : 'flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase text-gray-400';
        document.getElementById('tabTx').className = tab === 'transactions' ? 'flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase bg-gray-900 text-white shadow-lg' : 'flex-1 py-4 rounded-[24px] text-[10px] font-black uppercase text-gray-400';
    }

    function setTransferType(type) {
        ['Bank', 'Internal', 'Crypto', 'Interac'].forEach(t => {
            const btn = document.getElementById('tab' + t);
            btn.className = t.toLowerCase() === type ? 'px-6 py-3 rounded-full bg-gray-900 text-white text-[10px] font-black uppercase whitespace-nowrap' : 'px-6 py-3 rounded-full bg-gray-100 text-gray-500 text-[10px] font-black uppercase whitespace-nowrap';
            document.getElementById(t.toLowerCase() + 'Fields').classList.toggle('hidden', t.toLowerCase() !== type);
        });
        document.getElementById('transferAction').value = 'transfer_' + type;
        loadBeneficiaries(type);
    }

    function onTransferCurrencyChange() {
        const curr = document.getElementById('transferCurrency').value;
        const bankFields = document.getElementById('bankFields');
        const internalFields = document.getElementById('internalFields');
        const cryptoFields = document.getElementById('cryptoFields');

        // Default hide
        [bankFields, internalFields, cryptoFields].forEach(f => f.classList.add('hidden'));

        if (['USDT', 'USDC'].includes(curr)) {
            setTransferType('crypto');
        } else if (curr === 'CAD') {
            setTransferType('interac');
            document.getElementById('internalFields').classList.remove('hidden'); // CAD often uses internal-like interac email
        } else {
            setTransferType('bank');
            document.getElementById('bankFields').classList.remove('hidden');
            document.getElementById('bankSelectGroup').classList.toggle('hidden', !['NGN'].includes(curr));
            document.getElementById('bankNameGroup').classList.toggle('hidden', !['GBP', 'EUR', 'USD'].includes(curr));
            document.getElementById('usdFields').classList.toggle('hidden', curr !== 'USD');
            if (curr === 'NGN') fetchBanks('NG');
        }
    }

    function fetchBanks(country) {
        fetch('?ajax=getBanks&country=' + country)
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('bankSelect');
                sel.innerHTML = '<option value="">Select Bank</option>';
                if (res.status === 'success' || res.status === true) {
                    res.data.forEach(b => sel.innerHTML += `<option value="${b.code}">${b.name}</option>`);
                }
            });
    }

    function loadBeneficiaries(type) {
        fetch('?ajax=getBeneficiaries&type=' + type)
            .then(r => r.json())
            .then(items => {
                const cont = document.getElementById('benItems');
                cont.innerHTML = '';
                if (!items.length) { cont.innerHTML = '<div class="col-span-2 text-center text-[8px] font-black text-gray-300 uppercase py-4">No saved beneficiaries</div>'; return; }
                items.forEach(item => {
                    const details = JSON.parse(item.details);
                    cont.innerHTML += `
                        <div class="p-4 bg-gray-50 rounded-[20px] border border-gray-100 cursor-pointer hover:border-billpay-green" onclick="fillBeneficiary('${item.type}', '${item.currency}', ${item.details})">
                            <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">${item.description}</div>
                            <div class="text-[11px] font-black text-gray-900 uppercase">${details.account_number || details.address || details.recipient || details.email || 'Saved Beneficiary'}</div>
                            <div class="text-[8px] font-bold text-gray-400 uppercase">${item.currency}</div>
                        </div>
                    `;
                });
            });
    }

    function fillBeneficiary(type, currency, details) {
        document.getElementById('transferCurrency').value = currency;
        onTransferCurrencyChange();
        setTransferType(type);

        const form = document.getElementById('transferForm');
        if (details.account_number) form.account_number.value = details.account_number;
        if (details.bank_code) form.bank_code.value = details.bank_code;
        if (details.account_name) form.account_name.value = details.account_name;
        if (details.bank_name) form.bank_name.value = details.bank_name;
        if (details.recipient) form.recipient.value = details.recipient;
        if (details.address) form.address.value = details.address;
        if (details.network) form.network.value = details.network;

        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth' });
    }

    let quoteInterval, rateInterval;
    let isCountingDown = false;

    function getQuote() {
        const amount = document.getElementById('convertAmount').value;
        const from = document.getElementById('fromCurrency').value;
        const to = document.getElementById('toCurrency').value;
        if (!amount || amount <= 0) return alert('Please enter an amount to preview conversion.');

        const btn = document.getElementById('btnGetQuote');
        btn.disabled = true;
        btn.innerText = 'Fetching Rate...';

        fetch(`?ajax=getQuote&amount=${amount}&from=${from}&to=${to}`)
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                if (res.status === 'success' || res.status === true || res.id || res.data) {
                    const data = res.data || res;
                    // JuicyWay returns target_amount in minor units.
                    // If target_amount is not present, calculate via rate.
                    let targetVal = 0;
                    if (data.target_amount) {
                        targetVal = data.target_amount / 100;
                    } else if (data.rate) {
                        targetVal = amount * data.rate;
                    }

                    document.getElementById('toAmountDisplay').innerText = targetVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('quoteId').value = data.id;

                    // Display Rate (Normalize to 1 unit)
                    const rate = data.rate || (targetVal / amount);
                    document.getElementById('liveRateText').innerText = `Rate: 1 ${from} ~ ${rate.toFixed(4)} ${to}`;
                    document.getElementById('rateDisplay').classList.remove('hidden');

                    document.getElementById('quoteInfo').classList.remove('hidden');
                    document.getElementById('btnSwapSubmit').classList.remove('hidden');

                    let seconds = 30;
                    isCountingDown = true;
                    clearInterval(quoteInterval);
                    btn.innerText = `Preview Conversion (00:${seconds.toString().padStart(2, '0')})`;

                    quoteInterval = setInterval(() => {
                        seconds--;
                        document.getElementById('quoteTimer').innerText = seconds + 's';
                        btn.innerText = `Preview Conversion (00:${seconds.toString().padStart(2, '0')})`;

                        if (seconds <= 0) {
                            clearInterval(quoteInterval);
                            clearInterval(rateInterval);
                            isCountingDown = false;
                            document.getElementById('quoteInfo').classList.add('hidden');
                            document.getElementById('btnSwapSubmit').classList.add('hidden');
                            document.getElementById('rateDisplay').classList.add('hidden');
                            btn.innerText = 'Preview Conversion';
                        }
                    }, 1000);

                    // 10-second auto-refresh of rate if active
                    clearInterval(rateInterval);
                    rateInterval = setInterval(() => {
                        if (isCountingDown && seconds > 5) {
                             // Silently refresh rate
                             fetch(`?ajax=getQuote&amount=${amount}&from=${from}&to=${to}`)
                                .then(r => r.json())
                                .then(refresh => {
                                    const rData = refresh.data || refresh;
                                    const rRate = rData.rate || ((rData.target_amount || targetAmount) / amount);
                                    document.getElementById('liveRateText').innerText = `Rate: 1 ${from} ~ ${rRate.toFixed(4)} ${to}`;
                                    // Update target display if quote didn't change ID but rate did (though locked usually doesn't)
                                    if (rData.target_amount) document.getElementById('toAmountDisplay').innerText = rData.target_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                });
                        }
                    }, 10000);

                } else {
                    btn.innerText = 'Preview Conversion';
                    alert(res.message || 'Conversion Not Found. Please check currencies.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = 'Preview Conversion';
                alert('Connection Error. Please try again.');
            });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
