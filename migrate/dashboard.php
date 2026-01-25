<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$services = [
    ['icon' => 'phone', 'label' => 'Airtime', 'path' => '/migrate/airtime', 'color' => 'text-blue-500'],
    ['icon' => 'wifi', 'label' => 'Data', 'path' => '/migrate/data', 'color' => 'text-orange-500'],
    ['icon' => 'message-circle', 'label' => 'Bulk SMS', 'path' => '/migrate/sms', 'color' => 'text-emerald-500'],
    ['icon' => 'tv', 'label' => 'Cable TV', 'path' => '/migrate/cable', 'color' => 'text-red-500'],
    ['icon' => 'zap', 'label' => 'Electricity', 'path' => '/migrate/electric', 'color' => 'text-yellow-500'],
    ['icon' => 'trending-up', 'label' => 'Betting', 'path' => '/migrate/betting', 'color' => 'text-green-500'],
    ['icon' => 'bitcoin', 'label' => 'Crypto', 'path' => '/migrate/crypto', 'color' => 'text-orange-600'],
    ['icon' => 'arrow-right-left', 'label' => 'Transfer', 'path' => '/migrate/transfer', 'color' => 'text-indigo-500'],
    ['icon' => 'credit-card', 'label' => 'Card', 'path' => '/migrate/vcard', 'color' => 'text-pink-500'],
    ['icon' => 'shield-check', 'label' => 'Exam PIN', 'path' => '/migrate/exam', 'color' => 'text-purple-500'],
    ['icon' => 'bar-chart-3', 'label' => 'Referrals', 'path' => '/migrate/referrals', 'color' => 'text-cyan-600'],
    ['icon' => 'gift', 'label' => 'Gift Cards', 'path' => '/migrate/giftcards', 'color' => 'text-pink-600'],
];

$offers = $settings['offers'] ?? [];
if (is_string($offers)) $offers = json_decode($offers, true) ?: [];

?>

<div class="max-w-md mx-auto bg-gray-50 min-h-screen pb-24 relative">
    <!-- Balance Card Section -->
    <div class="bg-billpay-green p-6 text-white rounded-b-[40px] shadow-lg mb-6">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/20">
                    <i data-lucide="user" class="text-white w-5 h-5"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-sm">Hi, <?php echo explode(' ', $currentUser['fullName'])[0]; ?></span>
                    <span class="text-[10px] font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full w-fit">Tier <?php echo $currentUser['tier']; ?></span>
                </div>
            </div>
            <div class="flex gap-3 text-white/80">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <a href="/migrate/support"><i data-lucide="message-square" class="w-5 h-5"></i></a>
                <a href="/migrate/profile"><i data-lucide="settings" class="w-5 h-5"></i></a>
            </div>
        </div>

        <div class="bg-black/10 p-6 rounded-3xl backdrop-blur-md mb-2 border border-white/10 shadow-inner">
            <div class="flex justify-between items-start mb-2">
                <div class="text-[10px] font-black text-white/70 uppercase tracking-widest">Available Balance</div>
                <i data-lucide="arrow-right-left" class="w-4 h-4 text-white/40"></i>
            </div>
            <div class="text-3xl font-black flex items-center gap-1 text-white">
                <?php echo formatCurrency($currentUser['walletBalance']); ?>
                <div class="text-[10px] font-black bg-white/20 text-white px-2 py-1 rounded-full ml-2 border border-white/10 tracking-widest uppercase">Details</div>
            </div>
            <div class="mt-6 flex gap-3">
                <a href="/migrate/add-money" class="flex-1 bg-white text-gray-900 py-3.5 rounded-2xl font-black text-xs shadow-xl text-center active:scale-95 transition-all uppercase">Add Money</a>
                <a href="/migrate/transfer" class="flex-1 bg-black/20 text-white py-3.5 rounded-2xl font-black text-xs border border-white/10 text-center active:scale-95 transition-all uppercase">Transfer</a>
            </div>
        </div>
    </div>

    <!-- Daily Streak -->
    <div class="px-4 mb-6">
        <a href="/migrate/rewards" class="bg-white p-5 rounded-3xl shadow-xl flex justify-between items-center border border-gray-100/50">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-yellow-400 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-100 text-white">
                    <i data-lucide="coins" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="text-xs font-black text-gray-900 uppercase tracking-tight">Daily Cashback</div>
                    <div class="text-[10px] text-gray-400 font-bold">Earn <?php echo $settings['bonusPerDay']; ?> coins for today's check-in</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-black text-billpay-green"><?php echo $currentUser['bonusCoins']; ?></div>
                <div class="text-[9px] text-gray-400 font-black uppercase">Coins</div>
            </div>
        </a>
    </div>

    <!-- Services Grid -->
    <div class="px-4 py-2">
        <div class="bg-white p-6 rounded-[32px] shadow-sm grid grid-cols-4 gap-y-10 border border-gray-100">
            <?php foreach ($services as $service): ?>
                <a href="<?php echo $service['path']; ?>" class="flex flex-col items-center gap-2.5 group">
                    <div class="w-14 h-14 bg-gray-50 group-active:scale-90 rounded-2xl flex items-center justify-center shadow-sm border border-gray-100 transition-all <?php echo $service['color']; ?>">
                        <i data-lucide="<?php echo $service['icon']; ?>" class="w-6 h-6"></i>
                    </div>
                    <span class="text-[10px] font-black text-gray-600 uppercase tracking-tight text-center"><?php echo $service['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Promotions -->
    <div class="mt-10 px-4">
        <div class="flex justify-between items-center px-2 mb-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Offers for you</h3>
            <span class="text-[10px] font-black text-billpay-green uppercase">See all</span>
        </div>
        <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
            <?php if (empty($offers)): ?>
               <div class="min-w-[280px] h-36 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 text-[10px] font-black uppercase tracking-widest">
                  No active offers
               </div>
            <?php else: ?>
                <?php foreach ($offers as $offer): ?>
                    <div class="min-w-[280px] h-36 rounded-[32px] p-6 relative overflow-hidden flex flex-col justify-center shadow-xl shadow-gray-200" style="background: linear-gradient(to bottom right, <?php echo $offer['gradientFrom']; ?>, <?php echo $offer['gradientTo']; ?>); color: <?php echo $offer['textColor']; ?>;">
                        <div class="text-lg font-black leading-tight max-w-[180px]"><?php echo $offer['title']; ?></div>
                        <div class="text-[10px] font-medium opacity-80 mt-2"><?php echo $offer['description']; ?></div>
                        <div class="text-[10px] font-black uppercase opacity-60 mt-2 tracking-widest"><?php echo $offer['label']; ?></div>
                        <div class="absolute -right-8 -bottom-8 w-28 h-28 bg-white/10 rounded-full"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
