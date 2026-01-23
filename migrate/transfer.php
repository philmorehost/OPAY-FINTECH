<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); $error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $bank = $_POST['bank'] ?? ''; $acc = $_POST['acc'] ?? ''; $amt = (float)($_POST['amt'] ?? 0); $fee = 10; $total = $amt + $fee;
        if (strlen($acc) !== 10) { $error = "Invalid account number"; }
        elseif ($amt < 100) { $error = "Min ₦100"; }
        elseif ($user['walletBalance'] < $total) { $error = "Insufficient balance"; }
        else {
            update_user_balance($user['id'], -$total); log_transaction($user['id'], 'Transfer', $amt, 'successful', "Transfer to $acc ($bank)", $acc, $bank);
            $success = "Transfer successful!"; $user = get_current_user_data();
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">Transfer</h1></div>
  <div class="p-4 space-y-6 flex-1">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-6 rounded-3xl shadow-sm space-y-6 border border-gray-100">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <div><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Select Bank</label><select name="bank" class="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold"><option>Access Bank</option><option>First Bank</option><option>GTBank</option><option>Kuda Bank</option><option>Moniepoint</option><option>O-Pay Digital Bank</option></select></div>
      <div><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Account Number</label><input type="tel" name="acc" maxlength="10" placeholder="10-digit account" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg tracking-widest" /></div>
      <div><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount</label><input type="number" name="amt" placeholder="Min 100" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl" /></div>
      <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl">CONFIRM TRANSFER</button>
    </form>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
