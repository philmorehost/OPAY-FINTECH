<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$services = [
    ['icon' => 'phone', 'label' => 'Airtime', 'path' => '/airtime', 'color' => 'text-blue-500'],
    ['icon' => 'wifi', 'label' => 'Data', 'path' => '/data', 'color' => 'text-orange-500'],
    ['icon' => 'message-circle', 'label' => 'Bulk SMS', 'path' => '/sms', 'color' => 'text-emerald-500'],
    ['icon' => 'tv', 'label' => 'Cable TV', 'path' => '/cable', 'color' => 'text-red-500'],
    ['icon' => 'zap', 'label' => 'Electricity', 'path' => '/electric', 'color' => 'text-yellow-500'],
    ['icon' => 'trending-up', 'label' => 'Betting', 'path' => '/betting', 'color' => 'text-green-500'],
    ['icon' => 'bitcoin', 'label' => 'Crypto', 'path' => '/crypto', 'color' => 'text-orange-600'],
    ['icon' => 'arrow-right-left', 'label' => 'Transfer', 'path' => '/transfer', 'color' => 'text-indigo-500'],
    ['icon' => 'credit-card', 'label' => 'Card', 'path' => '/vcard', 'color' => 'text-pink-500'],
    ['icon' => 'shield-check', 'label' => 'Exam PIN', 'path' => '/exam', 'color' => 'text-purple-500'],
    ['icon' => 'bar-chart-3', 'label' => 'Referrals', 'path' => '/referrals', 'color' => 'text-cyan-600'],
    ['icon' => 'gift', 'label' => 'Gift Cards', 'path' => '/giftcards', 'color' => 'text-pink-600'],
];

$offers = fetchActiveOffers($pdo);
$templateId = $settings['templateId'] ?? 1;

?>

<div class="lg:max-w-md mx-auto bg-gray-50 min-h-screen pb-24 relative">
    <?php if ($templateId == 1): ?>
    <!-- Balance Card Section - Template 1 -->
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
                <a href="/support"><i data-lucide="message-square" class="w-5 h-5"></i></a>
                <a href="/profile"><i data-lucide="settings" class="w-5 h-5"></i></a>
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
                <a href="/add-money" class="flex-1 bg-white text-gray-900 py-3.5 rounded-2xl font-black text-xs shadow-xl text-center active:scale-95 transition-all uppercase">Add Money</a>
                <a href="/transfer" class="flex-1 bg-black/20 text-white py-3.5 rounded-2xl font-black text-xs border border-white/10 text-center active:scale-95 transition-all uppercase">Transfer</a>
            </div>
        </div>
    </div>
    <?php elseif ($templateId == 2): ?>
    <!-- Balance Card Section - Template 2 -->
    <div class="px-4 pt-6 mb-6">
        <div class="bg-gray-900 p-8 rounded-[40px] text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-billpay-green/20 rounded-full blur-3xl"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <div class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] mb-1">Your Balance</div>
                        <div class="text-4xl font-black tracking-tighter"><?php echo formatCurrency($currentUser['walletBalance']); ?></div>
                    </div>
                    <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="wallet" class="text-billpay-green w-6 h-6"></i>
                    </div>
                </div>
                <div class="flex gap-4">
                    <a href="/add-money" class="flex-1 bg-billpay-green text-white py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-green-500/20 text-center">Fund Account</a>
                    <a href="/transfer" class="flex-1 bg-white/5 border border-white/10 text-white py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest text-center">Transfer</a>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($templateId == 3): ?>
    <!-- Balance Card Section - Template 3 (Fintech Elite) -->
    <div class="p-6 bg-white border-b border-gray-100 mb-6">
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gray-900 rounded-2xl flex items-center justify-center text-white font-black text-xl">
                    <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                </div>
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Good Day,</div>
                    <div class="text-sm font-black text-gray-900"><?php echo explode(' ', $currentUser['fullName'])[0]; ?> 👋</div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="/support" class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 hover:text-billpay-green transition-colors"><i data-lucide="message-circle" class="w-5 h-5"></i></a>
                <a href="/profile" class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 hover:text-billpay-green transition-colors"><i data-lucide="settings" class="w-5 h-5"></i></a>
            </div>
        </div>

        <div class="bg-gray-50 p-8 rounded-[40px] border border-gray-100 relative group">
            <div class="flex flex-col items-center text-center">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em]">Total Assets</span>
                    <button onclick="toggleBalance()" class="text-gray-300 hover:text-billpay-green transition-colors"><i data-lucide="eye" id="eyeIcon" class="w-4 h-4"></i></button>
                </div>
                <div class="text-4xl font-black text-gray-900 tracking-tighter mb-8 transition-all" id="balanceText">
                    <?php echo formatCurrency($currentUser['walletBalance']); ?>
                </div>
                <div class="flex gap-3 w-full">
                    <a href="/add-money" class="flex-1 bg-gray-900 text-white py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl active:scale-95 transition-all">Add Money</a>
                    <a href="/transfer" class="flex-1 bg-white border border-gray-200 text-gray-900 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest active:scale-95 transition-all">Withdraw</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        let balanceHidden = false;
        const originalBalance = "<?php echo formatCurrency($currentUser['walletBalance']); ?>";
        function toggleBalance() {
            balanceHidden = !balanceHidden;
            const text = document.getElementById('balanceText');
            const icon = document.getElementById('eyeIcon');
            if (balanceHidden) {
                text.innerText = "₦ ****.**";
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                text.innerText = originalBalance;
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
    <?php else: ?>
    <!-- Balance Card Section - Template 4 (Crypto Hub) -->
    <div class="p-6">
        <div class="bg-gray-900 rounded-[40px] p-8 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-billpay-green/10 rounded-full blur-[80px]"></div>

            <div class="flex justify-between items-start mb-10 relative z-10">
                <div>
                    <div class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] mb-1">Portfolio Balance</div>
                    <div class="text-4xl font-black tracking-tighter" id="balanceText4"><?php echo formatCurrency($currentUser['walletBalance']); ?></div>
                </div>
                <a href="/add-money" class="bg-billpay-green text-white p-3 rounded-2xl shadow-lg shadow-green-500/20 active:scale-90 transition-all">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="grid grid-cols-4 gap-4 relative z-10">
                <a href="/crypto" class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center hover:bg-billpay-green transition-colors">
                        <i data-lucide="shopping-cart" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="text-[8px] font-black uppercase text-white/40">Buy</span>
                </a>
                <a href="/crypto" class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center hover:bg-red-500 transition-colors">
                        <i data-lucide="trending-down" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="text-[8px] font-black uppercase text-white/40">Sell</span>
                </a>
                <a href="/crypto" class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center hover:bg-blue-500 transition-colors">
                        <i data-lucide="arrow-down-left" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="text-[8px] font-black uppercase text-white/40">Deposit</span>
                </a>
                <a href="/crypto" class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center hover:bg-purple-500 transition-colors">
                        <i data-lucide="arrow-up-right" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="text-[8px] font-black uppercase text-white/40">Withdraw</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($templateId == 4): ?>
    <!-- Live Crypto Marquee - Template 4 -->
    <div class="bg-gray-100/50 py-3 overflow-hidden whitespace-nowrap border-y border-gray-100">
        <div id="crypto-marquee" class="flex animate-marquee gap-8 items-center px-4">
            <!-- Dynamic Content -->
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black text-gray-400 animate-pulse">Loading live prices...</span>
            </div>
        </div>
    </div>
    <script>
        async function fetchCryptoPrices() {
            try {
                const response = await fetch('https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum,binancecoin,tether,solana&order=market_cap_desc&per_page=5&page=1&sparkline=false&price_change_percentage=24h');
                const data = await response.json();

                const marquee = document.getElementById('crypto-marquee');
                let html = '';

                // Double the items for seamless loop
                const items = [...data, ...data];

                items.forEach(coin => {
                    const change = coin.price_change_percentage_24h || 0;
                    const colorClass = change >= 0 ? 'text-green-500' : 'text-red-500';
                    const iconColor = change >= 0 ? 'bg-green-500' : 'bg-red-500';
                    const sign = change >= 0 ? '+' : '';

                    html += `
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 ${iconColor} rounded-full"></span>
                            <span class="text-[10px] font-black text-gray-400">${coin.symbol.toUpperCase()}</span>
                            <span class="text-[10px] font-black text-gray-800">$${coin.current_price.toLocaleString()}</span>
                            <span class="text-[8px] font-bold ${colorClass}">${sign}${change.toFixed(2)}%</span>
                        </div>
                    `;
                });

                marquee.innerHTML = html;
            } catch (err) {
                console.error('Failed to fetch crypto prices:', err);
            }
        }

        fetchCryptoPrices();
        setInterval(fetchCryptoPrices, 60000); // Update every minute
    </script>
    <style>
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee {
            display: inline-flex;
            animation: marquee 20s linear infinite;
            width: max-content;
        }
    </style>
    <?php endif; ?>

    <?php if ($templateId == 2): ?>
    <!-- Promotions - Template 2 (Above Services) -->
    <div class="mb-8 px-4">
        <div class="flex justify-between items-center px-2 mb-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Offers for you</h3>
        </div>
        <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
            <?php if (empty($offers)): ?>
               <div class="min-w-[280px] h-36 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 text-[10px] font-black uppercase tracking-widest">
                  No active offers
               </div>
            <?php else: ?>
                <?php foreach ($offers as $offer): ?>
                    <div class="min-w-[280px] h-36 rounded-[32px] p-6 relative overflow-hidden flex flex-col justify-center shadow-xl shadow-gray-200" style="background: linear-gradient(to bottom right, <?php echo $offer['gradientFrom']; ?>, <?php echo $offer['gradientTo']; ?>); color: <?php echo $offer['textColor']; ?>;">
                        <?php if ($offer['image']): ?><img src="/<?php echo $offer['image']; ?>" class="absolute right-0 top-0 h-full w-1/2 object-cover opacity-20"><?php endif; ?>
                        <div class="relative z-10">
                            <div class="text-lg font-black leading-tight max-w-[180px]"><?php echo $offer['title']; ?></div>
                            <div class="text-[10px] font-medium opacity-80 mt-2"><?php echo $offer['content']; ?></div>
                        </div>
                        <div class="absolute -right-8 -bottom-8 w-28 h-28 bg-white/10 rounded-full"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($templateId != 3): ?>
    <!-- Daily Streak (T1, T2) -->
    <div class="px-4 mb-6">
        <a href="/rewards" class="bg-white p-5 rounded-3xl shadow-xl flex justify-between items-center border border-gray-100/50">
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
    <?php endif; ?>

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

    <?php if ($templateId == 3 || $templateId == 4): ?>
    <!-- Daily Streak (Template 3 & 4 - Follows Services) -->
    <div class="px-4 mt-8 mb-6">
        <a href="/rewards" class="bg-white p-6 rounded-[32px] shadow-sm flex justify-between items-center border border-gray-100">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-500">
                    <i data-lucide="award" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="text-xs font-black text-gray-900 uppercase">Loyalty Reward</div>
                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Claim your daily coins</div>
                </div>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-gray-300"></i>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($templateId == 1 || $templateId == 3 || $templateId == 4): ?>
    <!-- Promotions - Template 1, 3 & 4 (Below) -->
    <div class="mt-10 px-4">
        <div class="flex justify-between items-center px-2 mb-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Offers for you</h3>
        </div>
        <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
            <?php if (empty($offers)): ?>
               <div class="min-w-[280px] h-36 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 text-[10px] font-black uppercase tracking-widest">
                  No active offers
               </div>
            <?php else: ?>
                <?php foreach ($offers as $offer): ?>
                    <div class="min-w-[280px] h-36 rounded-[32px] p-6 relative overflow-hidden flex flex-col justify-center shadow-xl shadow-gray-200" style="background: linear-gradient(to bottom right, <?php echo $offer['gradientFrom']; ?>, <?php echo $offer['gradientTo']; ?>); color: <?php echo $offer['textColor']; ?>;">
                        <?php if ($offer['image']): ?><img src="/<?php echo $offer['image']; ?>" class="absolute right-0 top-0 h-full w-1/2 object-cover opacity-20"><?php endif; ?>
                        <div class="relative z-10">
                            <div class="text-lg font-black leading-tight max-w-[180px]"><?php echo $offer['title']; ?></div>
                            <div class="text-[10px] font-medium opacity-80 mt-2"><?php echo $offer['content']; ?></div>
                        </div>
                        <div class="absolute -right-8 -bottom-8 w-28 h-28 bg-white/10 rounded-full"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
