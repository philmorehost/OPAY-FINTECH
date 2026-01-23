<?php
// migrate/betting.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = ''; $customerName = '';
$providers = [['id' => 'msport', 'name' => 'MSport'], ['id' => 'bet9ja', 'name' => 'Bet9ja'], ['id' => 'betking', 'name' => 'BetKing']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $action = $_POST['action'] ?? ''; $providerId = $_POST['provider'] ?? ''; $bettingId = $_POST['bettingId'] ?? ''; $amount = (float)($_POST['amount'] ?? 0);
        if ($action === 'verify') { if ($bettingId && $providerId) { $customerName = "KABIRU OLAMIDE USMAN"; } else { $error = "Select provider and ID"; } }
        elseif ($action === 'pay') {
            if ($user['walletBalance'] < $amount) { $error = "Insufficient balance"; }
            elseif ($amount < 100) { $error = "Min ₦100"; }
            else {
                update_user_balance($user['id'], -$amount);
                log_transaction($user['id'], 'Betting', $amount, 'successful', "Wallet Funding for ID: $bettingId", $bettingId);
                check_and_apply_loyalty_bonus($user['id']); $success = "Wallet funded!"; $user = get_current_user_data();
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900"></i></a><h1 class="text-lg font-black text-gray-900">Betting</h1></div>
  <div class="p-4 flex-1 space-y-6 pb-20">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <div class="bg-white p-6 rounded-[32px] shadow-sm space-y-6 border border-gray-100">
      <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="verify">
        <select name="provider" onchange="this.form.submit()" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-sm appearance-none outline-none">
            <option value="">Choose Provider</option><?php foreach ($providers as $p): ?><option value="<?php echo h($p['id']); ?>" <?php echo ($providerId ?? '') === $p['id'] ? 'selected' : ''; ?>><?php echo h($p['name']); ?></option><?php endforeach; ?>
        </select>
        <div class="mt-6"><input type="tel" name="bettingId" value="<?php echo h($bettingId ?? ''); ?>" placeholder="Enter ID" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg" /></div>
        <?php if ($customerName): ?><div class="mt-2 flex items-center gap-2 px-2"><i data-lucide="user" class="text-opay-green"></i><span class="text-xs font-black text-opay-green"><?php echo h($customerName); ?></span></div><?php endif; ?>
        <?php if (!$customerName): ?><button type="submit" class="w-full mt-4 bg-gray-900 text-white font-black py-4 rounded-2xl">VERIFY ID</button><?php endif; ?>
      </form>
      <?php if ($customerName): ?><form method="POST" class="space-y-6 animate-fade-in"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="pay"><input type="hidden" name="provider" value="<?php echo h($providerId); ?>"><input type="hidden" name="bettingId" value="<?php echo h($bettingId); ?>">
        <input type="number" name="amount" required placeholder="Min 100" class="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl font-black text-xl" />
        <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl">PROCEED TO PAY</button>
      </form><?php endif; ?>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
