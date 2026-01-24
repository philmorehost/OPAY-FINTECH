<?php
// migrate/electric.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = ''; $customerName = '';
$discos = [['id' => 'ikedc', 'label' => 'Ikeja Electric', 'logo' => 'IK'], ['id' => 'ekedc', 'label' => 'Eko Electric', 'logo' => 'EK'], ['id' => 'aedc', 'label' => 'Abuja Electric', 'logo' => 'AE']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $action = $_POST['action'] ?? ''; $providerId = $_POST['provider'] ?? ''; $meterNumber = $_POST['meterNumber'] ?? ''; $amount = (float)($_POST['amount'] ?? 0);
        if ($action === 'verify') { if ($meterNumber && $providerId) { $customerName = "SULEIMAN PETER GOMWALK"; } else { $error = "Select provider and enter number"; } }
        elseif ($action === 'pay') {
            if ($user['walletBalance'] < $amount) { $error = "Insufficient balance"; }
            elseif ($amount < 500) { $error = "Min ₦500"; }
            else {
                update_user_balance($user['id'], -$amount); $token = rand(1000, 9999)."-".rand(1000, 9999)."-".rand(1000, 9999);
                log_transaction($user['id'], 'Electricity', $amount, 'successful', "Token for $meterNumber: $token", $meterNumber);
                check_and_apply_loyalty_bonus($user['id']); $success = "Success! Token: $token"; $user = get_current_user_data();
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left"></i></a><h1 class="text-lg font-black text-gray-900">Electricity</h1></div>
  <div class="p-4 flex-1 space-y-6 pb-20">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <div class="bg-white p-6 rounded-3xl shadow-sm space-y-6 border border-gray-100">
      <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="verify">
        <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest">Select Provider</label>
        <div class="flex overflow-x-auto gap-3 pb-2 scrollbar-hide"><?php foreach ($discos as $d): ?>
          <button type="button" onclick="document.getElementById('provider').value='<?php echo h($d['id']); ?>'; this.form.submit();" class="flex flex-col items-center gap-2 p-3 min-w-[90px] rounded-2xl border-2 transition-all <?php echo ($providerId ?? '') === $d['id'] ? 'border-vtu-green bg-green-50' : 'border-transparent bg-gray-50 opacity-60'; ?>"><div class="w-10 h-10 rounded-full bg-yellow-400 flex items-center justify-center text-white font-black"><?php echo h($d['logo']); ?></div><span class="text-[8px] font-black text-gray-800 uppercase"><?php echo h($d['label']); ?></span></button>
        <?php endforeach; ?></div><input type="hidden" name="provider" id="provider" value="<?php echo h($providerId ?? ''); ?>">
        <div class="mt-6"><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Meter Number</label><input type="tel" name="meterNumber" value="<?php echo h($meterNumber ?? ''); ?>" placeholder="Enter number" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-vtu-green outline-none rounded-2xl font-black text-lg" /></div>
        <?php if (!$customerName): ?><button type="submit" class="w-full mt-4 bg-gray-900 text-white font-black py-4 rounded-2xl">VERIFY METER</button><?php endif; ?>
      </form>
      <?php if ($customerName): ?><form method="POST" class="space-y-6 animate-fade-in"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="pay"><input type="hidden" name="provider" value="<?php echo h($providerId); ?>"><input type="hidden" name="meterNumber" value="<?php echo h($meterNumber); ?>">
        <div class="p-2 flex items-center gap-2 px-2 animate-fade-in"><i data-lucide="user" class="text-vtu-green"></i><span class="text-xs font-black uppercase text-vtu-green"><?php echo h($customerName); ?></span></div>
        <input type="number" name="amount" required placeholder="Min 500" class="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl font-black text-xl" />
        <button type="submit" class="w-full bg-vtu-green text-white font-black py-5 rounded-2xl shadow-xl">PROCEED TO PAY</button>
      </form><?php endif; ?>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
