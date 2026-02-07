<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Referrals';

require_once __DIR__ . '/includes/header.php';
$refLink = "http://".$_SERVER['HTTP_HOST']."/register?ref=" . $currentUser['username'];
?>
<div class="lg:max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Refer & Earn</h1>
    </div>

    <div class="p-6 space-y-6 flex-1">
        <div class="bg-gray-900 p-8 rounded-[40px] text-white shadow-xl relative overflow-hidden">
            <i data-lucide="users" class="w-24 h-24 text-white/5 absolute -right-6 -bottom-6"></i>
            <h2 class="text-2xl font-black tracking-tight mb-2">Invite Friends</h2>
            <p class="text-xs text-gray-400 font-bold uppercase leading-relaxed max-w-[200px]">Get <?php echo $settings['referralBonus']; ?> coins for every friend who joins.</p>

            <div class="mt-8 bg-white/10 p-4 rounded-2xl border border-white/10 flex items-center justify-between">
                <div class="truncate text-xs font-bold text-gray-300 mr-4"><?php echo $refLink; ?></div>
                <button onclick="copyToClipboard('<?php echo $refLink; ?>')" class="bg-billpay-green text-white p-2 rounded-xl"><i data-lucide="copy" class="w-4 h-4"></i></button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Invites</div>
                <div class="text-2xl font-black text-gray-800"><?php echo $currentUser['referralCount']; ?></div>
            </div>
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Earned</div>
                <div class="text-2xl font-black text-billpay-green"><?php echo formatCurrency($currentUser['referralEarnings']); ?></div>
            </div>
        </div>
    </div>
</div>
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Referral link copied!');
        });
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
