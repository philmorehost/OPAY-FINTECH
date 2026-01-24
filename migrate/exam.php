<?php
// migrate/exam.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = ''; $statusDetails = null;
$examProviders = get_json_setting('examProviders') ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $examId = $_POST['examType'] ?? ''; $qty = (int)($_POST['quantity'] ?? 1); $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $sel = null; foreach ($examProviders as $p) { if ($p['id'] === $examId) { $sel = $p; break; } }
        if (!$sel) { $error = "Choose exam"; } elseif (strlen($phone) < 10) { $error = "Invalid phone"; }
        else {
            $total = (float)$sel['userPrice'] * $qty;
            if ($user['walletBalance'] < $total) { $error = "Insufficient balance"; }
            else {
                update_user_balance($user['id'], -$total); $pins = []; for($i=0;$i<$qty;$i++) { $pins[] = ['pin' => rand(1000, 9999)."-".rand(1000, 9999), 'serial' => 'SN'.rand(100,999)]; }
                log_transaction($user['id'], 'Exam PIN', $total, 'successful', "{$sel['name']} ($qty unit) for $phone", $phone);
                check_and_apply_loyalty_bonus($user['id']); $user = get_current_user_data();
                $statusDetails = ['status' => 'success', 'amount' => $total, 'recipient' => $phone, 'provider' => $sel['name'], 'pins' => $pins];
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20 animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">Education</h1></div>
  <div class="p-4 flex-1 space-y-6">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <div class="bg-white p-8 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
      <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <select name="examType" required class="w-full p-4 bg-gray-50 rounded-2xl font-black text-sm appearance-none outline-none"><option value="">Choose Exam Type</option><?php foreach ($examProviders as $e): ?><option value="<?php echo h($e['id']); ?>"><?php echo h($e['name']); ?> - <?php echo format_currency($e['userPrice']); ?></option><?php endforeach; ?></select>
        <div class="grid grid-cols-2 gap-4 mt-6"><input type="number" name="quantity" min="1" value="1" class="p-4 bg-gray-50 rounded-2xl font-black text-center" /><div class="p-4 bg-gray-900 rounded-2xl text-vtu-green font-black text-xs flex items-center justify-center">SECURE HUB</div></div>
        <div class="mt-6"><input type="tel" name="phone" required placeholder="Phone Number" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-sm" /></div>
        <button type="submit" class="w-full mt-6 bg-vtu-green text-white font-black py-5 rounded-3xl shadow-xl">CONFIRM & PAY</button>
      </form>
    </div>
  </div>
  <?php if ($statusDetails): ?><div class="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6"><div class="bg-white w-full max-w-sm rounded-[40px] p-8 overflow-y-auto max-h-[80vh] text-center"><i data-lucide="check-circle-2" class="mx-auto text-vtu-green mb-4" size="48"></i><h3 class="text-xl font-black uppercase"><?php echo h($statusDetails['provider']); ?> Successful</h3><div class="space-y-2 mt-6"><?php foreach ($statusDetails['pins'] as $p): ?><div class="p-3 bg-gray-50 rounded-xl font-mono text-sm"><?php echo h($p['pin']); ?></div><?php endforeach; ?></div><button onclick="window.location.href='dashboard'" class="w-full mt-8 py-5 rounded-3xl bg-gray-900 text-white font-black">DONE</button></div></div><?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
