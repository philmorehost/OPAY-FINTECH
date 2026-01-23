<?php
// migrate/gift-cards.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = '';
$cards = [['name' => 'Amazon', 'rate' => 1520], ['name' => 'iTunes', 'rate' => 1480], ['name' => 'Steam', 'rate' => 1550]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $action = $_POST['action'] ?? ''; $amt = (float)($_POST['amount'] ?? 0); $name = $_POST['cardName'] ?? '';
        $rate = 0; foreach ($cards as $c) { if ($c['name'] === $name) { $rate = $c['rate']; break; } }
        if ($action === 'buy') {
            $total = $amt * $rate;
            if ($user['walletBalance'] < $total) { $error = "Insufficient balance"; }
            else { update_user_balance($user['id'], -$total); log_transaction($user['id'], 'Gift Card', $total, 'successful', "Bought \$$amt $name Card", $name); $success = "Bought $name!"; $user = get_current_user_data(); }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b shadow-sm"><div class="flex items-center gap-4"><a href="dashboard"><i data-lucide="arrow-left"></i></a><h1 class="text-lg font-black text-gray-900">Gift Cards</h1></div></div>
  <div class="p-4 space-y-6 flex-1">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="buy">
            <select name="cardName" required class="w-full p-4 bg-gray-50 rounded-2xl font-black mb-4 outline-none"><?php foreach ($cards as $c): ?><option value="<?php echo h($c['name']); ?>"><?php echo h($c['name']); ?> - ₦<?php echo $c['rate']; ?>/$</option><?php endforeach; ?></select>
            <input type="number" name="amount" required placeholder="Amount in USD" class="w-full p-5 bg-gray-50 text-gray-900 rounded-2xl font-black text-2xl" />
            <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-3xl mt-6 shadow-xl">BUY GIFT CARD</button>
        </form>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
