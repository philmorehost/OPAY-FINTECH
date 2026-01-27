<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Virtual Cards';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $action = $_POST['action'];

    if ($action === 'request_card') {
        $type = $_POST['type'] ?? 'Visa';
        $cost = (float)($settings['vcardIssuanceFee'] ?? 1500);

        if ($currentUser['walletBalance'] < $cost) {
            $error = "Insufficient balance. Card request costs " . formatCurrency($cost);
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $cost, 'debit');

                // Call JuicyWay API
                $jwRes = callJuicyWay($settings, 'cards', 'POST', [
                    'userId' => $currentUser['id'],
                    'type' => $type,
                    'amount' => 0
                ]);

                if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                    $cardData = $jwRes['data'];
                    $cardId = $cardData['id'];
                    $cardNumber = $cardData['card_number'];
                    $expiry = $cardData['expiry'];
                    $cvv = $cardData['cvv'];

                    $stmt = $pdo->prepare("INSERT INTO virtual_cards (id, userId, cardNumber, expiry, cvv, type, balance) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$cardId, $currentUser['id'], $cardNumber, $expiry, $cvv, $type]);

                    logTransaction($pdo, $currentUser['id'], 'Virtual Card', $cost, 'successful', "New Virtual $type Card issued: $cardNumber", 'System', 'JuicyWay');
                    $success = "Virtual Card issued successfully!";
                } else {
                    throw new Exception($jwRes['message'] ?? 'Failed to issue card from provider');
                }

                claimDailyRewardIfEligible($pdo, $currentUser['id']);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'fund_card') {
        $cardId = $_POST['cardId'];
        $amount = (float)$_POST['amount'];

        if ($currentUser['walletBalance'] < $amount) {
            $error = "Insufficient balance to fund card.";
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $amount, 'debit');

                // Call JuicyWay API
                $jwRes = callJuicyWay($settings, "cards/$cardId/fund", 'POST', ['amount' => $amount]);

                if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                    $stmt = $pdo->prepare("UPDATE virtual_cards SET balance = balance + ? WHERE id = ? AND userId = ?");
                    $stmt->execute([$amount, $cardId, $currentUser['id']]);

                    logTransaction($pdo, $currentUser['id'], 'Card Funding', $amount, 'successful', "Funded Virtual Card ($cardId)", $cardId, 'JuicyWay');
                    $success = "Card funded successfully!";
                } else {
                    throw new Exception($jwRes['message'] ?? 'Failed to fund card');
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
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
            $jwRes = callJuicyWay($settings, $endpoint, 'POST');

            if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                $stmt = $pdo->prepare("UPDATE virtual_cards SET isFrozen = ? WHERE id = ? AND userId = ?");
                $stmt->execute([$isFrozen, $cardId, $currentUser['id']]);
                $success = "Card " . ($isFrozen ? 'frozen' : 'unfrozen') . " successfully!";
            } else {
                throw new Exception($jwRes['message'] ?? 'Failed to update card status');
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
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

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
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
<div id="requestModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-md rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight">New Virtual Card</h3>
            <button onclick="document.getElementById('requestModal').classList.add('hidden')"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="request_card">

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

            <div class="bg-gray-50 p-6 rounded-3xl flex justify-between items-center">
                <span class="text-[10px] font-black uppercase text-gray-400">Issuance Fee</span>
                <span class="text-lg font-black"><?php echo formatCurrency($settings['vcardIssuanceFee'] ?? 1500); ?></span>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest">Confirm & Pay</button>
        </form>
    </div>
</div>

<!-- Fund Modal -->
<div id="fundModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight text-gray-800">Top Up Card</h3>
            <button onclick="document.getElementById('fundModal').classList.add('hidden')"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="fund_card">
            <input type="hidden" name="cardId" id="fundCardId">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" placeholder="0.00" class="w-full p-6 bg-gray-50 rounded-3xl font-black text-2xl outline-none focus:ring-4 focus:ring-billpay-green/5" required>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
