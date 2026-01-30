<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Virtual Cards';

$success = '';
$error = '';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'getQuote') {
        $amount = (float)$_GET['amount'];
        $rate = 1600; // Mock Market Rate
        $fee = (float)($settings['financialSettings']['vcard_deposit_fee'] ?? 500);
        $target = ($amount - $fee) / $rate;
        echo json_encode(['status' => 'success', 'rate' => $rate, 'fee' => $fee, 'target' => max(0, $target)]);
        exit;
    }
    if ($_GET['ajax'] === 'sendOTP') {
        sendEmail2fa($pdo, $currentUser);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $action = $_POST['action'];

    // 1. Email Auth Verification (Requested)
    if (empty($_POST['otp']) || $_POST['otp'] != $_SESSION['email_2fa_code'] || time() > $_SESSION['email_2fa_expiry']) {
        $error = "Invalid or expired Email verification code.";
    }
    // 2. Fund Password Verification
    elseif (!verifyFundPassword($pdo, $currentUser['id'], $_POST['fund_password'] ?? '')) {
        $error = "Incorrect Fund Password. Action denied.";
    } elseif ($action === 'request_card') {
        $type = $_POST['type'] ?? 'Visa';
        $issuanceFee = (float)($settings['vcardIssuanceFee'] ?? 1500);
        $gateway = $_POST['gateway'] ?? 'paystack';

        // Online Payment Linking (Forcefully online only)
        $ref = 'VC-' . bin2hex(random_bytes(4));
        $_SESSION['pending_vcard_request'] = ['type' => $type, 'fee' => $issuanceFee, 'ref' => $ref];

        if ($gateway === 'paystack') {
            $paystackKey = $settings['paystackPublicKey'];
            $payAmount = $issuanceFee * 100;
            echo "<script src='https://js.paystack.co/v1/inline.js'></script>
            <script>
                window.onload = function() {
                    const handler = PaystackPop.setup({
                        key: '$paystackKey', email: '{$currentUser['email']}', amount: $payAmount, ref: '$ref',
                        callback: function(res) { window.location.href = '/verify-payment?reference=' + res.reference + '&type=vcard'; },
                        onClose: function() { window.location.href = '/vcard?error=Payment cancelled'; }
                    });
                    handler.openIframe();
                };
            </script>";
            exit;
        } elseif ($gateway === 'flutterwave') {
            $res = flutterwaveInitiate($pdo, $issuanceFee, $currentUser['email'], $ref);
            if (isset($res['status']) && $res['status'] === 'success') {
                $link = $res['data']['link'] ?? '';
                header("Location: $link");
                exit;
            } else $error = "Flutterwave Init Failed: " . ($res['message'] ?? '');
        } elseif ($gateway === 'paypal') {
            $res = paypalInitiate($pdo, $issuanceFee / 1600, $ref);
            if (isset($res['id'])) {
                $links = $res['links'] ?? [];
                $approve = array_filter($links, fn($l) => $l['rel'] === 'approve');
                if (!empty($approve)) {
                    header("Location: " . reset($approve)['href']);
                    exit;
                }
            } else $error = "PayPal Init Failed: " . ($res['message'] ?? '');
        }
    } elseif ($action === 'fund_card') {
        $cardId = $_POST['cardId'];
        $amountNgn = (float)$_POST['amount'];
        $rate = 1600; // Mock Rate
        $feeNgn = (float)($settings['financialSettings']['vcard_deposit_fee'] ?? 500);
        $amountToVenc = ($amountNgn - $feeNgn) / $rate;

        if ($currentUser['walletBalance'] < $amountNgn) {
            $error = "Insufficient balance. Total: " . formatCurrency($amountNgn);
        } elseif ($amountNgn <= $feeNgn) {
            $error = "Amount must be greater than service fee.";
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $amountNgn, 'debit');

                // Call JuicyWay API (Amount in USD for the card)
                $jwRes = callJuicyWay($pdo, "cards/$cardId/fund", 'POST', ['amount' => $amountToVenc]);

                if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                    $stmt = $pdo->prepare("UPDATE virtual_cards SET balance = balance + ? WHERE id = ? AND userId = ?");
                    $stmt->execute([$amountToVenc, $cardId, $currentUser['id']]);

                    logTransaction($pdo, $currentUser['id'], 'Card Funding', $amountNgn, 'successful', "Funded Virtual Card: +$" . number_format($amountToVenc, 2), $cardId, 'System', null, $amountToVenc, $feeNgn);
                    $success = "Card funded successfully with $" . number_format($amountToVenc, 2);
                } else {
                    updateWallet($pdo, $currentUser['id'], $amountNgn, 'credit');
                    throw new Exception($jwRes['message'] ?? 'Failed to fund card via provider. Balance refunded.');
                }
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'freeze_card') {
        $cardId = $_POST['cardId'];
        $isFrozen = $_POST['status'] === 'freeze' ? 1 : 0;

        $pdo->beginTransaction();
        try {
            // Call JuicyWay API
            $endpoint = $isFrozen ? "cards/$cardId/freeze" : "cards/$cardId/unfreeze";
            $jwRes = callJuicyWay($pdo, $endpoint, 'POST');

            if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                $stmt = $pdo->prepare("UPDATE virtual_cards SET isFrozen = ? WHERE id = ? AND userId = ?");
                $stmt->execute([$isFrozen, $cardId, $currentUser['id']]);
                $success = "Card " . ($isFrozen ? 'frozen' : 'unfrozen') . " successfully!";
                logTransaction($pdo, $currentUser['id'], 'Card Security', 0, 'successful', "Card " . ($isFrozen ? 'frozen' : 'unfrozen'), $cardId, 'System');
            } else {
                throw new Exception($jwRes['message'] ?? 'Failed to update card status');
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // Refresh user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $currentUser = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM virtual_cards WHERE userId = ?");
$stmt->execute([$currentUser['id']]);
$cards = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center justify-between sticky top-0 z-40 border-b">
        <div class="flex items-center gap-4">
            <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
            <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Virtual Cards</h1>
        </div>
        <button onclick="document.getElementById('requestModal').classList.remove('hidden')" class="bg-gray-900 text-white p-2.5 rounded-xl shadow-lg active:scale-95 transition-all">
            <i data-lucide="plus" class="w-5 h-5"></i>
        </button>
    </div>

    <div class="p-6 space-y-8 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <?php if (empty($cards)): ?>
            <div class="bg-white p-12 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-6">
                <div class="w-24 h-24 bg-billpay-green/10 rounded-full flex items-center justify-center mx-auto">
                    <i data-lucide="credit-card" class="w-12 h-12 text-billpay-green"></i>
                </div>
                <h2 class="text-2xl font-black text-gray-800 uppercase tracking-tight">No Active Cards</h2>
                <p class="text-sm text-gray-400 font-bold uppercase max-w-xs mx-auto">Instant virtual Visa or Mastercard for all your global subscriptions and payments.</p>
                <button onclick="document.getElementById('requestModal').classList.remove('hidden')" class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:scale-[1.02] active:scale-95 transition-all">Request New Card (<?php echo formatCurrency($settings['vcardIssuanceFee'] ?? 1500); ?>)</button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($cards as $card): ?>
                <div class="space-y-6">
                    <!-- Physical-ish Card UI -->
                    <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-8 rounded-[32px] text-white shadow-2xl relative overflow-hidden aspect-[1.6/1] flex flex-col justify-between group transition-all duration-500 hover:shadow-billpay-green/20">
                        <div class="flex justify-between items-start z-10">
                            <div class="text-sm font-black italic tracking-widest"><?php echo strtoupper($card['type']); ?></div>
                            <div class="flex gap-2">
                                <?php if ($card['isFrozen']): ?>
                                    <span class="px-3 py-1 bg-red-500/20 text-red-400 text-[8px] font-black rounded-full uppercase backdrop-blur-md border border-red-500/20">Frozen</span>
                                <?php endif; ?>
                                <i data-lucide="wifi" class="w-6 h-6 rotate-90 opacity-40"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-black tracking-[0.2em] my-4 z-10 select-all"><?php echo $card['cardNumber']; ?></div>
                        <div class="flex justify-between items-end z-10">
                            <div>
                                <div class="text-[8px] font-black uppercase opacity-50 mb-1">Card Holder</div>
                                <div class="text-xs font-black uppercase tracking-widest"><?php echo $currentUser['fullName']; ?></div>
                            </div>
                            <div class="flex gap-8">
                                <div>
                                    <div class="text-[8px] font-black uppercase opacity-50 mb-1">Expiry</div>
                                    <div class="text-xs font-black"><?php echo $card['expiry']; ?></div>
                                </div>
                                <div>
                                    <div class="text-[8px] font-black uppercase opacity-50 mb-1">CVV</div>
                                    <div class="text-xs font-black"><?php echo $card['cvv']; ?></div>
                                </div>
                            </div>
                        </div>
                        <!-- Background patterns -->
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-billpay-green/5 rounded-full blur-3xl transition-all group-hover:bg-billpay-green/10"></div>
                        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
                    </div>

                    <!-- Card Stats & Actions -->
                    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Card Balance</div>
                                <div class="text-2xl font-black text-gray-900"><?php echo formatCurrency($card['balance']); ?></div>
                            </div>
                            <button onclick="openFundModal('<?php echo $card['id']; ?>')" class="px-6 py-3 bg-billpay-green text-white rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-green-100">Top Up</button>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="freeze_card">
                                <input type="hidden" name="cardId" value="<?php echo $card['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $card['isFrozen'] ? 'unfreeze' : 'freeze'; ?>">
                                <button type="submit" class="w-full flex items-center justify-center gap-3 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-100 transition-all">
                                    <i data-lucide="<?php echo $card['isFrozen'] ? 'unlock' : 'lock'; ?>" class="w-4 h-4"></i>
                                    <?php echo $card['isFrozen'] ? 'Unfreeze' : 'Freeze'; ?>
                                </button>
                            </form>
                            <button class="w-full flex items-center justify-center gap-3 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-100 transition-all">
                                <i data-lucide="file-text" class="w-4 h-4"></i> Activity
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Modal -->
<div id="requestModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6 text-gray-900">
    <div class="bg-white w-full max-w-md rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight">New Virtual Card</h3>
            <button onclick="document.getElementById('requestModal').classList.add('hidden')"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="request_card">

            <div class="mb-6 p-4 bg-amber-50 rounded-2xl border border-amber-100">
                <p class="text-[9px] font-black uppercase text-amber-700 leading-relaxed">Online Only: Payments are routed via secure gateways. Wallet balance is not used for card issuance.</p>
            </div>

            <div class="space-y-4">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Choose Card Type</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="type" value="Visa" class="peer sr-only" checked>
                        <div class="p-6 border-2 border-gray-50 rounded-3xl peer-checked:border-billpay-green peer-checked:bg-green-50 transition-all flex flex-col items-center gap-2">
                            <span class="font-black italic text-lg text-blue-800">VISA</span>
                            <span class="text-[8px] font-black uppercase opacity-40">Credit / Debit</span>
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="type" value="Mastercard" class="peer sr-only">
                        <div class="p-6 border-2 border-gray-50 rounded-3xl peer-checked:border-billpay-green peer-checked:bg-green-50 transition-all flex flex-col items-center gap-2">
                            <span class="font-black italic text-lg text-red-600">Mastercard</span>
                            <span class="text-[8px] font-black uppercase opacity-40">Credit / Debit</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase ml-1">Fund Password</label>
                    <input type="password" name="fund_password" placeholder="••••••" class="w-full p-4 bg-gray-50 rounded-2xl font-black outline-none" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase ml-1">Email Code</label>
                    <div class="flex gap-2">
                        <input type="text" name="otp" placeholder="000000" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-center outline-none" required>
                        <button type="button" onclick="sendOTP(this)" class="px-4 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase">Send</button>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 p-6 rounded-3xl space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-black uppercase text-gray-400">Issuance Fee</span>
                    <span class="text-lg font-black"><?php echo formatCurrency($settings['vcardIssuanceFee'] ?? 1500); ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <button type="submit" name="gateway" value="paystack" class="p-3 bg-white rounded-xl border border-gray-100 flex flex-col items-center gap-1 hover:border-billpay-green transition-all group">
                        <i data-lucide="credit-card" class="w-4 h-4 text-blue-500"></i>
                        <span class="text-[7px] font-black uppercase text-gray-400 group-hover:text-gray-900">Paystack</span>
                    </button>
                    <button type="submit" name="gateway" value="flutterwave" class="p-3 bg-white rounded-xl border border-gray-100 flex flex-col items-center gap-1 hover:border-billpay-green transition-all group">
                        <i data-lucide="zap" class="w-4 h-4 text-orange-500"></i>
                        <span class="text-[7px] font-black uppercase text-gray-400 group-hover:text-gray-900">Flutterwave</span>
                    </button>
                    <button type="submit" name="gateway" value="paypal" class="p-3 bg-white rounded-xl border border-gray-100 flex flex-col items-center gap-1 hover:border-billpay-green transition-all group">
                        <i data-lucide="globe" class="w-4 h-4 text-blue-700"></i>
                        <span class="text-[7px] font-black uppercase text-gray-400 group-hover:text-gray-900">PayPal</span>
                    </button>
                </div>
            </div>

            <button type="button" onclick="alert('Select a gateway above to pay')" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl transition-all uppercase tracking-widest">Proceed to Payment</button>
        </form>
    </div>
</div>

<!-- Fund Modal -->
<div id="fundModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6 text-gray-900">
    <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight text-gray-800">Top Up Card</h3>
            <button onclick="document.getElementById('fundModal').classList.add('hidden')"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="fund_card">
            <input type="hidden" name="cardId" id="fundCardId">

            <div class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                    <input type="number" name="amount" id="fundAmount" oninput="updateQuote()" placeholder="0.00" class="w-full p-6 bg-gray-50 rounded-3xl font-black text-2xl outline-none focus:ring-4 focus:ring-billpay-green/5" required>
                </div>

                <div id="fundQuote" class="hidden p-6 bg-indigo-50 rounded-3xl border border-indigo-100 space-y-3">
                    <div class="flex justify-between text-[9px] font-black uppercase text-indigo-400"><span>Exchange Rate</span><span id="quoteRate">₦0 / $1</span></div>
                    <div class="flex justify-between text-[9px] font-black uppercase text-indigo-400"><span>Service Fee</span><span id="quoteFee">₦0.00</span></div>
                    <div class="flex justify-between text-xs font-black uppercase text-indigo-900 pt-2 border-t border-indigo-100"><span>Card Receives</span><span id="quoteTarget">$0.00</span></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase ml-1">Fund Password</label>
                        <input type="password" name="fund_password" placeholder="••••••" class="w-full p-4 bg-gray-50 rounded-2xl font-black outline-none" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase ml-1">Email Code</label>
                        <div class="flex gap-2">
                            <input type="text" name="otp" placeholder="000000" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-center outline-none" required>
                            <button type="button" onclick="sendOTP(this)" class="px-4 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase">Send</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest">Add Funds</button>
        </form>
    </div>
</div>

<script>
    function openFundModal(id) {
        document.getElementById('fundCardId').value = id;
        document.getElementById('fundModal').classList.remove('hidden');
    }

    function sendOTP(btn) {
        btn.disabled = true;
        btn.innerText = 'Sending...';
        fetch('?ajax=sendOTP')
            .then(r => r.json())
            .then(res => {
                btn.innerText = 'Sent!';
                setTimeout(() => { btn.disabled = false; btn.innerText = 'Resend'; }, 60000);
            });
    }

    function updateQuote() {
        const amt = document.getElementById('fundAmount').value;
        if (amt < 1000) { document.getElementById('fundQuote').classList.add('hidden'); return; }
        fetch('?ajax=getQuote&amount=' + amt)
            .then(r => r.json())
            .then(res => {
                document.getElementById('fundQuote').classList.remove('hidden');
                document.getElementById('quoteRate').innerText = '₦' + res.rate.toLocaleString() + ' / $1';
                document.getElementById('quoteFee').innerText = '₦' + res.fee.toLocaleString();
                document.getElementById('quoteTarget').innerText = '$' + res.target.toFixed(2);
            });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
