<?php
// migrate/referrals.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

// Analytics simulation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referredBy = ?");
$stmt->execute([$user['username']]);
$totalInvites = $stmt->fetchColumn();

$conversionRate = $totalInvites > 0 ? round(($totalInvites / ($totalInvites + 5)) * 100, 1) : 0; // Simulated conversion
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Referral Program</h1>
    </div>

    <div class="p-6 space-y-6">
        <div class="bg-indigo-600 p-8 rounded-[40px] text-white shadow-xl relative overflow-hidden">
            <div class="relative z-10">
                <div class="text-[10px] font-black uppercase tracking-widest opacity-70 mb-1">Total Earnings</div>
                <div class="text-4xl font-black mb-6"><?php echo format_currency($user['referralEarnings']); ?></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white/10 p-4 rounded-3xl border border-white/10 text-center"><div class="text-[8px] font-black uppercase opacity-60">Invites</div><div class="text-xl font-black"><?php echo $totalInvites; ?></div></div>
                    <div class="bg-white/10 p-4 rounded-3xl border border-white/10 text-center"><div class="text-[8px] font-black uppercase opacity-60">Conversion</div><div class="text-xl font-black"><?php echo $conversionRate; ?>%</div></div>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/5 rounded-full"></div>
        </div>

        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest">Your Referral Link</h3>
            <div class="flex gap-2">
                <input type="text" readonly value="https://vtu-fintech.com/register?ref=<?php echo h($user['username']); ?>" id="refLink" class="flex-1 p-4 bg-gray-50 rounded-2xl text-[10px] font-bold text-gray-500 truncate">
                <button onclick="copyRef()" class="p-4 bg-gray-900 text-white rounded-2xl"><i data-lucide="copy" size="20"></i></button>
            </div>
            <p class="text-[10px] font-bold text-gray-400 leading-relaxed">Share your referral link with friends and earn <?php echo format_currency($settings['referralBonus'] ?? 100); ?> for every successful registration and first transaction.</p>
        </div>

        <div class="space-y-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">How it works</h3>
            <div class="space-y-3">
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex gap-4">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600"><i data-lucide="share-2" size="20"></i></div>
                    <div><h4 class="text-xs font-black mb-1">Invite Friends</h4><p class="text-[9px] text-gray-400 font-bold">Send your referral link to friends and family.</p></div>
                </div>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex gap-4">
                    <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-600"><i data-lucide="user-plus" size="20"></i></div>
                    <div><h4 class="text-xs font-black mb-1">They Register</h4><p class="text-[9px] text-gray-400 font-bold">Your friends join the platform using your unique link.</p></div>
                </div>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex gap-4">
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600"><i data-lucide="gift" size="20"></i></div>
                    <div><h4 class="text-xs font-black mb-1">You Earn</h4><p class="text-[9px] text-gray-400 font-bold">Receive instant bonuses once they perform their first transaction.</p></div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<script>
function copyRef() {
    var copyText = document.getElementById("refLink");
    copyText.select();
    document.execCommand("copy");
    alert("Referral link copied!");
}
</script>
<?php require_once 'includes/footer.php'; ?>
