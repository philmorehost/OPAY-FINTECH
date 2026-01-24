<?php
// migrate/vcard.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$stmt = $pdo->prepare("SELECT * FROM virtual_cards WHERE userId = ?");
$stmt->execute([$user['id']]);
$cards = $stmt->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_card') {
        $amount = (float)$_POST['amount'];
        $fee = 500.00;
        if ($user['walletBalance'] < ($amount + $fee)) {
            $error = "Insufficient balance. Card creation fee is ₦500.";
        } else {
            update_user_balance($user['id'], -($amount + $fee));
            $cardNumber = "4512 " . rand(1000, 9999) . " " . rand(1000, 9999) . " " . rand(1000, 9999);
            $cvv = rand(100, 999);
            $expiry = date('m/y', strtotime('+3 years'));

            $stmt = $pdo->prepare("INSERT INTO virtual_cards (id, userId, cardNumber, expiry, cvv, balance, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([generate_id(), $user['id'], $cardNumber, $expiry, $cvv, $amount, 'VISA']);

            add_notification($user['id'], "Virtual Card Created", "Your new virtual VISA card is ready for use.", 'success');
            redirect('vcard?success=Card created successfully');
        }
    }
}
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Virtual Cards</h1>
    </div>

    <div class="p-6 space-y-8">
        <?php if ($success || isset($_GET['success'])): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success ?: $_GET['success']); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>

        <?php if (empty($cards)): ?>
            <div class="bg-white p-10 rounded-[40px] text-center shadow-sm border border-gray-100">
                <div class="w-20 h-20 bg-vtu-green/10 rounded-full flex items-center justify-center text-vtu-green mx-auto mb-6"><i data-lucide="credit-card" size="32"></i></div>
                <h2 class="text-xl font-black mb-3">No Virtual Cards</h2>
                <p class="text-sm text-gray-400 font-medium mb-8">Generate a virtual card for your global online payments instantly.</p>
                <button onclick="document.getElementById('createCardModal').classList.remove('hidden')" class="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl">GET NEW CARD</button>
            </div>
        <?php else: ?>
            <?php foreach ($cards as $c): ?>
            <div class="bg-gradient-to-br from-gray-800 to-black p-8 rounded-[32px] text-white shadow-2xl relative overflow-hidden h-56 flex flex-col justify-between">
                <div class="flex justify-between items-start relative z-10">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60"><?php echo h($c['type']); ?> PREPAID</div>
                    <i data-lucide="wifi" class="rotate-90 opacity-40"></i>
                </div>
                <div class="text-xl font-bold tracking-[0.2em] relative z-10"><?php echo h($c['cardNumber']); ?></div>
                <div class="flex justify-between items-end relative z-10">
                    <div>
                        <div class="text-[8px] font-black uppercase opacity-40 mb-1">Card Holder</div>
                        <div class="text-xs font-bold uppercase"><?php echo h($user['fullName']); ?></div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black uppercase opacity-40 mb-1">Expires</div>
                        <div class="text-xs font-bold"><?php echo h($c['expiry']); ?></div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black uppercase opacity-40 mb-1">CVV</div>
                        <div class="text-xs font-bold"><?php echo h($c['cvv']); ?></div>
                    </div>
                </div>
                <div class="absolute -right-16 -bottom-16 w-48 h-48 bg-vtu-green/20 rounded-full blur-3xl"></div>
            </div>
            <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex justify-between items-center">
                <div><div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Card Balance</div><div class="text-xl font-black"><?php echo format_currency($c['balance']); ?></div></div>
                <button class="bg-gray-100 p-4 rounded-2xl text-gray-900 font-black text-[10px] uppercase tracking-widest">Freeze Card</button>
            </div>
            <?php endforeach; ?>
            <button onclick="document.getElementById('createCardModal').classList.remove('hidden')" class="w-full border-2 border-dashed border-gray-200 p-5 rounded-3xl text-gray-300 font-black text-xs uppercase tracking-widest hover:border-vtu-green hover:text-vtu-green transition-all">+ ADD ANOTHER CARD</button>
        <?php endif; ?>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>

<div id="createCardModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-sm rounded-[40px] p-8">
        <h3 class="text-lg font-black mb-6">Create Virtual Card</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="create_card">
            <div class="space-y-4">
                <p class="text-[10px] font-bold text-gray-400">Card Creation Fee: ₦500.00</p>
                <input type="number" name="amount" required min="100" placeholder="Initial Funding Amount" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold">
                <button type="submit" class="w-full bg-vtu-green text-white py-4 rounded-2xl font-black">PAY & GENERATE CARD</button>
                <button type="button" onclick="document.getElementById('createCardModal').classList.add('hidden')" class="w-full text-gray-400 font-bold text-xs uppercase mt-4">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
