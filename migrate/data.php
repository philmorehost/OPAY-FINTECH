<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); $error = ''; $success = ''; $dataProducts = get_json_setting('dataProducts') ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $planId = $_POST['planId'] ?? ''; $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $selectedPlan = null; foreach ($dataProducts as $p) { if ($p['id'] === $planId) { $selectedPlan = $p; break; } }
        if (!$selectedPlan) { $error = "Select a plan"; }
        elseif (strlen($phone) < 10) { $error = "Enter valid phone"; }
        else {
            $cost = (float)$selectedPlan['userPrice'];
            if ($user['walletBalance'] < $cost) { $error = "Insufficient balance"; }
            else {
                update_user_balance($user['id'], -$cost); log_transaction($user['id'], 'Data', $cost, 'successful', "{$selectedPlan['size']} Data for $phone", $phone);
                check_and_apply_loyalty_bonus($user['id']); $success = "Data purchase successful!"; $user = get_current_user_data();
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-20 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Data Services</h1></div>
  <div class="p-4 flex-1">
    <?php if ($error): ?><div class="mb-4 p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-4 p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <div><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Recipient Number</label><input type="tel" name="phone" required placeholder="08123456789" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-black text-xl" /></div>
      <div><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Select Plan</label><select name="planId" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"><?php foreach ($dataProducts as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo h($p['size']); ?> - <?php echo format_currency($p['userPrice']); ?></option><?php endforeach; ?></select></div>
      <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl">PROCEED TO PAY</button>
    </form>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
