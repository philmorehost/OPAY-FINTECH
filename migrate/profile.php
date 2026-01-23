<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-20"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">Account</h1></div>
  <div class="p-6 space-y-6 pb-24">
    <div class="bg-white p-8 rounded-[40px] shadow-sm flex flex-col items-center border border-gray-100">
      <div class="w-24 h-24 bg-opay-green/10 rounded-full flex items-center justify-center shadow-lg mb-4"><i data-lucide="user" size="48" class="text-opay-green"></i></div>
      <h2 class="text-xl font-black text-gray-900"><?php echo h($user['fullName']); ?></h2>
      <p class="text-xs font-bold text-gray-400 mt-1"><?php echo h($user['phone']); ?></p>
      <div class="mt-4 flex gap-2"><span class="px-3 py-1 bg-opay-green/10 text-opay-green text-[10px] font-black rounded-full">TIER <?php echo h($user['tier']); ?></span><span class="px-3 py-1 bg-gray-100 text-[10px] font-black rounded-full uppercase"><?php echo h($user['kycStatus']); ?></span></div>
    </div>
    <div class="bg-white rounded-[32px] shadow-sm overflow-hidden border border-gray-100">
      <a href="login-settings" class="p-5 flex items-center gap-4 border-b border-gray-50"><i data-lucide="lock" size="20"></i><span class="text-sm font-black text-gray-700">Login Settings</span></a>
      <a href="support" class="p-5 flex items-center gap-4 border-b border-gray-50"><i data-lucide="help-circle" size="20"></i><span class="text-sm font-black text-gray-700">Support</span></a>
      <a href="logout" class="p-5 flex items-center gap-4 text-red-500"><i data-lucide="x" size="20"></i><span class="text-sm font-black">Log out</span></a>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
