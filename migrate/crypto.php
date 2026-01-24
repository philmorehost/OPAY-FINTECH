<?php
// migrate/crypto.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

// Pricing simulation
$prices = [
    'Bitcoin' => ['symbol' => 'BTC', 'price' => 95000000, 'change' => '+2.4%'],
    'Ethereum' => ['symbol' => 'ETH', 'price' => 5200000, 'change' => '-1.1%'],
    'Solana' => ['symbol' => 'SOL', 'price' => 210000, 'change' => '+5.8%'],
    'USDT' => ['symbol' => 'USDT', 'price' => 1500, 'change' => '0%']
];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'transfer') {
        $coin = $_POST['coin'];
        $amount = (float)$_POST['amount'];
        $address = $_POST['address'];

        // Mock transfer logic
        add_notification($user['id'], "Crypto Transfer Initiated", "You sent $amount " . $prices[$coin]['symbol'] . " to $address.", 'info');
        $success = "Transfer initiated successfully!";
    }
}
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Crypto Wallet</h1>
    </div>

    <div class="p-6 space-y-6">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>

        <div class="bg-gradient-to-br from-indigo-900 to-blue-900 p-8 rounded-[40px] text-white shadow-xl">
            <div class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-2">Total Crypto Balance</div>
            <div class="text-3xl font-black mb-6">₦0.00</div>
            <div class="flex gap-3">
                <button onclick="openModal('buy')" class="flex-1 bg-white/10 hover:bg-white/20 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-white/10 transition-all">Buy Crypto</button>
                <button onclick="openModal('transfer')" class="flex-1 bg-vtu-green py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all active:scale-95">Transfer</button>
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Market Overview</h3>
            <div class="grid grid-cols-1 gap-3">
                <?php foreach ($prices as $name => $c): ?>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center font-black text-xs"><?php echo $c['symbol']; ?></div>
                        <div><div class="text-sm font-black text-gray-900"><?php echo h($name); ?></div><div class="text-[9px] font-bold <?php echo strpos($c['change'], '+') !== false ? 'text-green-500' : 'text-red-500'; ?>"><?php echo $c['change']; ?> (24h)</div></div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black text-gray-900"><?php echo format_currency($c['price']); ?></div>
                        <div class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Price in NGN</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>

<div id="transferModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-sm rounded-[40px] p-8">
        <h3 class="text-lg font-black mb-6">Transfer Crypto</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="transfer">
            <div class="space-y-4">
                <select name="coin" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <?php foreach ($prices as $name => $c): ?><option value="<?php echo $name; ?>"><?php echo $name; ?> (<?php echo $c['symbol']; ?>)</option><?php endforeach; ?>
                </select>
                <input type="text" name="address" required placeholder="Recipient Wallet Address" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold">
                <input type="number" step="0.000001" name="amount" required placeholder="Amount" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold">
                <button type="submit" class="w-full bg-vtu-green text-white py-4 rounded-2xl font-black">SEND CRYPTO</button>
                <button type="button" onclick="closeModal('transfer')" class="w-full text-gray-400 font-bold text-xs uppercase mt-4">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(m) { document.getElementById(m + 'Modal').classList.remove('hidden'); }
function closeModal(m) { document.getElementById(m + 'Modal').classList.add('hidden'); }
</script>
<?php require_once 'includes/footer.php'; ?>
