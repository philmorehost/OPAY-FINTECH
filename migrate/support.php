<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-20"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">Support</h1></div>
  <div class="p-4 space-y-6 flex-1"><div class="bg-white p-5 rounded-2xl shadow-sm text-center py-20"><i data-lucide="message-square" class="mx-auto text-gray-200 mb-4" size="48"></i><p class="text-gray-400 font-bold">Need help? Contact support@<?= htmlspecialchars($settings['siteName'] ?? 'VTU-Fintech') ?>.com</p></div></div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
