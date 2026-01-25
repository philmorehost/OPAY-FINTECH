<?php
require_once __DIR__ . '/includes/config.php';
checkKycRestriction($settings, $currentUser);
$pageTitle = 'Crypto';
require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Crypto Exchange</h1>
    </div>
    <div class="p-6 space-y-6 flex-1">
        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center py-20">
            <i data-lucide="bitcoin" class="w-16 h-16 text-orange-500 mx-auto mb-4 opacity-20"></i>
            <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">Coming Soon</h2>
            <p class="text-sm text-gray-400 font-bold uppercase mt-2">Crypto wallet integration is in progress.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
