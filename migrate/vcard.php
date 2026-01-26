<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);
$pageTitle = 'Virtual Card';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_card') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $type = $_POST['type'] ?? 'Visa';
    $cost = 1500; // Simulated cost

    if ($currentUser['walletBalance'] < $cost) {
        $error = "Insufficient balance. Card request costs " . formatCurrency($cost);
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $cost, 'debit');
            $cardId = 'VC-' . strtoupper(bin2hex(random_bytes(4)));
            $cardNumber = "4" . mt_rand(100, 999) . " " . mt_rand(1000, 9999) . " " . mt_rand(1000, 9999) . " " . mt_rand(1000, 9999);
            $expiry = date('m/y', strtotime('+3 years'));
            $cvv = mt_rand(100, 999);

            $stmt = $pdo->prepare("INSERT INTO virtual_cards (id, userId, cardNumber, expiry, cvv, type, balance) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$cardId, $currentUser['id'], $cardNumber, $expiry, $cvv, $type]);

            logTransaction($pdo, $currentUser['id'], 'Virtual Card', $cost, 'successful', "New Virtual $type Card issued", 'System', 'CardIssuer');

            claimDailyRewardIfEligible($pdo, $currentUser['id']);
            $pdo->commit();
            $success = "Virtual Card issued successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to issue card.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM virtual_cards WHERE userId = ?");
$stmt->execute([$currentUser['id']]);
$cards = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Virtual Cards</h1>
    </div>
    <div class="p-4 space-y-6 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <?php if (empty($cards)): ?>
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-6">
                <div class="w-20 h-20 bg-billpay-green/10 rounded-full flex items-center justify-center mx-auto">
                    <i data-lucide="credit-card" class="w-10 h-10 text-billpay-green"></i>
                </div>
                <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">No Active Cards</h2>
                <p class="text-sm text-gray-400 font-bold uppercase">Generate a virtual Visa or Mastercard for your online payments.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="request_card">
                    <input type="hidden" name="type" value="Visa">
                    <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase tracking-widest shadow-xl">Request New Card (₦1,500)</button>
                </form>
            </div>
        <?php else: ?>
            <?php foreach ($cards as $card): ?>
                <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-8 rounded-[32px] text-white shadow-2xl relative overflow-hidden aspect-[1.6/1] flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="text-sm font-black italic tracking-widest"><?php echo strtoupper($card['type']); ?></div>
                        <i data-lucide="wifi" class="w-6 h-6 rotate-90 opacity-40"></i>
                    </div>
                    <div class="text-2xl font-black tracking-[0.2em] my-4"><?php echo $card['cardNumber']; ?></div>
                    <div class="flex justify-between items-end">
                        <div>
                            <div class="text-[8px] font-black uppercase opacity-50 mb-1">Card Holder</div>
                            <div class="text-xs font-black uppercase tracking-widest"><?php echo $currentUser['fullName']; ?></div>
                        </div>
                        <div>
                            <div class="text-[8px] font-black uppercase opacity-50 mb-1">Expiry</div>
                            <div class="text-xs font-black"><?php echo $card['expiry']; ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
