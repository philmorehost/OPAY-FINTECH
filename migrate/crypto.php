<?php
// migrate/crypto.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = '';
$assets = [['name' => 'BTC', 'icon' => '₿', 'color' => 'bg-orange-500', 'price' => 65420.50], ['name' => 'ETH', 'icon' => 'Ξ', 'color' => 'bg-blue-600', 'price' => 3450.20], ['name' => 'USDT', 'icon' => '₮', 'color' => 'bg-green-600', 'price' => 1.00]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $action = $_POST['action'] ?? ''; $amt = (float)($_POST['amount'] ?? 0); $asset = $_POST['asset'] ?? 'BTC';
        if ($action === 'buy') {
            if ($user['walletBalance'] < $amt) { $error = "Insufficient balance"; }
            else { update_user_balance($user['id'], -$amt); log_transaction($user['id'], 'Crypto Buy', $amt, 'successful', "Bought $asset", $asset); $success = "Bought $asset!"; $user = get_current_user_data(); }
        } elseif ($action === 'sell') {
            update_user_balance($user['id'], $amt); log_transaction($user['id'], 'Crypto Sell', $amt, 'successful', "Sold $asset", $asset); $success = "Sold $asset!"; $user = get_current_user_data();
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b shadow-sm"><div class="flex items-center gap-4"><a href="dashboard"><i data-lucide="arrow-left"></i></a><h1 class="text-lg font-black text-gray-900">Crypto</h1></div></div>
  <div class="p-4 space-y-6 flex-1">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <div class="bg-white p-6 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="buy">
            <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Select Asset</label>
            <select name="asset" class="w-full p-4 bg-gray-50 rounded-2xl font-black mb-4 outline-none"><?php foreach ($assets as $a): ?><option value="<?php echo h($a['name']); ?>"><?php echo h($a['name']); ?> - $<?php echo number_format($a['price'], 2); ?></option><?php endforeach; ?></select>
            <input type="number" name="amount" required placeholder="Amount in ₦" class="w-full p-5 bg-gray-50 text-gray-900 border-none outline-none rounded-2xl font-black text-2xl" />
            <button type="submit" class="w-full bg-vtu-green text-white font-black py-5 rounded-[24px] mt-4 shadow-xl">CONFIRM TRADE</button>
        </form>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
