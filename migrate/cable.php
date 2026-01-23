<?php
// migrate/cable.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();
$error = ''; $success = ''; $customerName = ''; $currentBouquet = ''; $renewalAmount = 0;
$cableProviders = get_json_setting('cableProviders') ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $action = $_POST['action'] ?? '';
        $providerId = $_POST['providerId'] ?? '';
        $smartcardNumber = $_POST['smartcardNumber'] ?? '';
        $selectedProvider = null;
        foreach ($cableProviders as $p) { if ($p['id'] === $providerId) { $selectedProvider = $p; break; } }
        if ($action === 'verify') {
            if ($smartcardNumber && $selectedProvider) { $customerName = "MOCK CUSTOMER NAME"; $currentBouquet = "Active Package"; $renewalAmount = 2500; }
            else { $error = "Select provider and enter number"; }
        } elseif ($action === 'pay') {
            $amount = (float)($_POST['amount'] ?? 0);
            if ($user['walletBalance'] < $amount) { $error = "Insufficient balance"; }
            else {
                update_user_balance($user['id'], -$amount);
                log_transaction($user['id'], 'Cable TV', $amount, 'successful', "{$selectedProvider['name']} Renewal for $smartcardNumber", $smartcardNumber, $selectedProvider['name']);
                check_and_apply_loyalty_bonus($user['id']); $success = "Subscription successful!"; $user = get_current_user_data();
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">Cable TV</h1></div>
  <div class="p-4 flex-1 space-y-6 pb-24 overflow-y-auto scrollbar-hide">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <div class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="verify">
        <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Provider</label>
        <div class="grid grid-cols-4 gap-3"><?php foreach ($cableProviders as $p): ?>
          <button type="button" onclick="document.getElementById('providerId').value='<?php echo h($p['id']); ?>'; this.form.submit();" class="flex flex-col items-center gap-2 p-3 rounded-2xl border-2 transition-all <?php echo ($providerId ?? '') === $p['id'] ? 'border-opay-green bg-green-50' : 'border-transparent bg-gray-50 opacity-60'; ?>">
            <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black"><?php echo h(substr($p['name'], 0, 2)); ?></div>
            <span class="text-[8px] font-black text-gray-800 uppercase"><?php echo h($p['name']); ?></span>
          </button><?php endforeach; ?></div>
        <input type="hidden" name="providerId" id="providerId" value="<?php echo h($providerId ?? ''); ?>">
        <div class="mt-6"><label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">IUC Number</label><div class="flex gap-2"><input type="tel" name="smartcardNumber" value="<?php echo h($smartcardNumber ?? ''); ?>" placeholder="Enter number" class="flex-1 p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg" /><button type="submit" class="p-4 bg-gray-900 text-white rounded-2xl"><i data-lucide="search" size="20"></i></button></div></div>
      </form>
      <?php if ($customerName): ?>
        <form method="POST" class="space-y-6 animate-fade-in"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="pay"><input type="hidden" name="providerId" value="<?php echo h($providerId); ?>"><input type="hidden" name="smartcardNumber" value="<?php echo h($smartcardNumber); ?>"><input type="hidden" name="amount" value="<?php echo h($renewalAmount); ?>">
          <div class="p-4 bg-green-50 rounded-2xl border border-green-100 flex items-center gap-3"><i data-lucide="user" class="text-opay-green"></i><span class="text-xs font-black"><?php echo h($customerName); ?></span></div>
          <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl">PAY <?php echo format_currency($renewalAmount); ?></button>
        </form><?php endif; ?>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
