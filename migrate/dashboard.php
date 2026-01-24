<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); $offers = get_json_setting('offers') ?? []; ?>
<div class="flex flex-col min-h-screen bg-gray-50 max-w-md mx-auto relative shadow-2xl animate-fade-in">
  <div class="bg-vtu-green p-6 text-white rounded-b-[40px] shadow-lg">
    <div class="flex justify-between items-center mb-6"><a href="profile" class="flex items-center gap-3"><div class="w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/20"><i data-lucide="user" size="20" class="text-white"></i></div><div class="flex flex-col"><span class="font-bold text-sm">Hi, <?php echo h(explode(' ', $user['fullName'])[0]); ?></span><span class="text-[10px] font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full w-fit">Tier <?php echo h($user['tier']); ?></span></div></a><div class="flex gap-3"><a href="notifications" class="relative"><i data-lucide="bell" size="20" class="text-white/80"></i><?php $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND isRead = 0"); $stmt->execute([$user['id']]); $unreadCount = $stmt->fetchColumn(); if ($unreadCount > 0): ?><span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-vtu-green"></span><?php endif; ?></a><a href="support"><i data-lucide="message-square" size="20" class="text-white/80"></i></a><a href="login-settings"><i data-lucide="settings" size="20" class="text-white/80"></i></a></div></div>
    <div class="bg-black/10 p-6 rounded-3xl backdrop-blur-md mb-2 border border-white/10 shadow-inner"><div class="flex justify-between items-start mb-2"><div class="text-[10px] font-black text-white/70 uppercase tracking-widest">Available Balance</div><i data-lucide="arrow-right-left" size="16" class="text-white/40"></i></div><div class="text-3xl font-black flex items-center gap-1 text-white"><?php echo format_currency($user['walletBalance']); ?><div class="text-[10px] font-black bg-white/20 text-white px-2 py-1 rounded-full ml-2 border border-white/10">DETAILS</div></div><div class="mt-6 flex gap-3"><a href="add-money" class="flex-1 bg-white text-gray-900 py-3.5 rounded-2xl font-black text-xs shadow-xl active:scale-95 transition-all text-center">ADD MONEY</a><a href="transfer" class="flex-1 bg-black/20 text-white py-3.5 rounded-2xl font-black text-xs border border-white/10 active:scale-95 transition-all text-center">TRANSFER</a></div></div>
  </div>
  <div class="px-4 -mt-6 mb-6"><a href="rewards" class="bg-white p-5 rounded-3xl shadow-xl flex justify-between items-center active:bg-gray-50 border border-gray-100/50 block"><div class="flex items-center gap-4"><div class="w-12 h-12 bg-yellow-400 rounded-2xl flex items-center justify-center shadow-lg"><i data-lucide="coins" class="text-white" size="24"></i></div><div><div class="text-xs font-black text-gray-900 uppercase tracking-tight">Daily Cashback</div><div class="text-[10px] text-gray-400 font-bold">Earn coins for today's check-in</div></div></div><div class="text-right"><div class="text-sm font-black text-vtu-green"><?php echo h($user['bonusCoins']); ?></div><div class="text-[9px] text-gray-400 font-black uppercase">COINS</div></div></a></div>
  <div class="px-4 py-2 flex-1 pb-24">
    <div class="bg-white p-6 rounded-[32px] shadow-sm grid grid-cols-4 gap-y-10 border border-gray-100">
      <?php $services = [
        ['icon' => 'phone', 'color' => 'text-blue-500', 'label' => 'Airtime', 'path' => 'airtime'],
        ['icon' => 'wifi', 'color' => 'text-orange-500', 'label' => 'Data', 'path' => 'data'],
        ['icon' => 'message-circle', 'color' => 'text-emerald-500', 'label' => 'Bulk SMS', 'path' => 'sms'],
        ['icon' => 'tv', 'color' => 'text-red-500', 'label' => 'Cable TV', 'path' => 'cable'],
        ['icon' => 'zap', 'color' => 'text-yellow-500', 'label' => 'Electricity', 'path' => 'electric'],
        ['icon' => 'trending-up', 'color' => 'text-green-500', 'label' => 'Betting', 'path' => 'betting'],
        ['icon' => 'bitcoin', 'color' => 'text-orange-600', 'label' => 'Crypto', 'path' => 'crypto'],
        ['icon' => 'arrow-right-left', 'color' => 'text-indigo-500', 'label' => 'Transfer', 'path' => 'transfer'],
        ['icon' => 'credit-card', 'color' => 'text-pink-500', 'label' => 'Card', 'path' => 'vcard'],
        ['icon' => 'shield-check', 'color' => 'text-purple-500', 'label' => 'Exam PIN', 'path' => 'exam'],
        ['icon' => 'bar-chart-3', 'color' => 'text-cyan-600', 'label' => 'Referrals', 'path' => 'referrals'],
        ['icon' => 'gift', 'color' => 'text-pink-600', 'label' => 'Gift Cards', 'path' => 'gift-cards']
      ];
      foreach ($services as $s): ?>
      <a href="<?php echo h($s['path']); ?>" class="flex flex-col items-center gap-2.5 cursor-pointer group">
        <div class="w-14 h-14 bg-gray-50 group-active:scale-90 rounded-2xl flex items-center justify-center shadow-sm border border-gray-100 transition-all">
          <i data-lucide="<?php echo $s['icon']; ?>" size="24" class="<?php echo $s['color']; ?>"></i>
        </div>
        <span class="text-[10px] font-black text-gray-600 uppercase tracking-tight text-center"><?php echo h($s['label']); ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Promotions Carousel -->
    <div class="mt-10">
      <div class="flex justify-between items-center px-2 mb-4">
        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Offers for you</h3>
        <span class="text-[10px] font-black text-vtu-green uppercase cursor-pointer">See all</span>
      </div>
      <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
        <?php if (empty($offers)): ?>
          <div class="min-w-[280px] h-36 bg-white rounded-[32px] flex flex-col items-center justify-center text-gray-400 border border-dashed border-gray-200 shadow-sm">
              <i data-lucide="sparkles" class="mb-2 opacity-20"></i>
              <span class="text-[9px] font-black uppercase tracking-widest">No active offers yet</span>
          </div>
        <?php else: ?>
          <?php foreach ($offers as $offer): ?>
            <div
              style="background: linear-gradient(to bottom right, <?php echo h($offer['gradientFrom'] ?? '#00c689'); ?>, <?php echo h($offer['gradientTo'] ?? '#00a370'); ?>); color: <?php echo h($offer['textColor'] ?? '#ffffff'); ?>"
              class="min-w-[280px] h-36 rounded-[32px] p-6 relative overflow-hidden flex flex-col justify-center shadow-xl shadow-gray-200"
            >
              <div class="text-lg font-black leading-tight max-w-[180px]"><?php echo h($offer['title'] ?? 'Special Offer'); ?></div>
              <div class="text-[10px] font-medium opacity-80 mt-2"><?php echo h($offer['description'] ?? 'Enjoy our best rates today.'); ?></div>
              <div class="text-[10px] font-black uppercase opacity-60 mt-2 tracking-widest"><?php echo h($offer['label'] ?? 'Promo'); ?></div>
              <div class="absolute -right-8 -bottom-8 w-28 h-28 bg-white/10 rounded-full"></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
