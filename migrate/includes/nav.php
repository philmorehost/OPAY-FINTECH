<?php $current_page = basename($_SERVER['PHP_SELF'], '.php'); ?>
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-t border-gray-100 flex justify-around items-center py-4 px-6 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] max-w-md mx-auto z-50 rounded-t-[32px]">
    <a href="services" class="flex flex-col items-center gap-1.5 <?php echo $current_page === 'services' ? 'text-opay-green' : 'text-gray-400'; ?>"><i data-lucide="layout-grid" size="22"></i><span class="text-[9px] font-black uppercase">Services</span></a>
    <a href="dashboard" class="flex flex-col items-center gap-1.5 <?php echo $current_page === 'dashboard' ? 'text-opay-green' : 'text-gray-400'; ?>"><i data-lucide="history" size="22" class="rotate-90"></i><span class="text-[9px] font-black uppercase">Home</span></a>
    <a href="rewards" class="flex flex-col items-center gap-1.5 <?php echo $current_page === 'rewards' ? 'text-opay-green' : 'text-gray-400'; ?>"><i data-lucide="gift" size="22"></i><span class="text-[9px] font-bold uppercase">Reward</span></a>
    <a href="transactions" class="flex flex-col items-center gap-1.5 <?php echo $current_page === 'transactions' ? 'text-opay-green' : 'text-gray-400'; ?>"><i data-lucide="history" size="22"></i><span class="text-[9px] font-bold uppercase">History</span></a>
    <a href="profile" class="flex flex-col items-center gap-1.5 <?php echo $current_page === 'profile' ? 'text-opay-green' : 'text-gray-400'; ?>"><i data-lucide="user" size="22"></i><span class="text-[9px] font-bold uppercase">Me</span></a>
    <?php if (isset($_SESSION['original_admin_id'])): ?>
    <a href="admin?action=return_to_admin" class="flex flex-col items-center gap-1.5 text-red-500"><i data-lucide="shield-alert" size="22"></i><span class="text-[9px] font-black uppercase">Admin</span></a>
    <?php endif; ?>
</div>
