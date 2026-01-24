<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); $error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $rate = (float)$settings['conversionRate']; if ($user['bonusCoins'] < $rate) { $error = "Min coins: $rate"; }
        else { $val = floor($user['bonusCoins'] / $rate); $deduct = $val * $rate; $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins - ?, walletBalance = walletBalance + ? WHERE id = ?"); $stmt->execute([$deduct, $val, $user['id']]); log_transaction($user['id'], 'Reward', $val, 'successful', "Converted $deduct coins", 'Wallet'); $success = "Converted!"; $user = get_current_user_data(); }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col animate-fade-in">
  <div class="bg-vtu-green p-8 text-white rounded-b-[40px] shadow-lg"><a href="dashboard"><i data-lucide="arrow-left"></i></a><div class="flex flex-col items-center py-8"><div class="w-28 h-28 bg-white/20 rounded-[35px] flex items-center justify-center relative mb-6 backdrop-blur-sm"><i data-lucide="coins" size="56" class="text-yellow-300"></i></div><div class="text-5xl font-black mb-2 tracking-tighter"><?php echo h($user['bonusCoins']); ?></div><div class="text-xs opacity-90 font-black uppercase">Reward Coins</div></div></div>
  <div class="p-4 flex-1 -mt-10">
    <div class="bg-white p-8 rounded-[40px] shadow-2xl space-y-8">
      <div class="flex justify-between items-center p-6 bg-gray-50 rounded-3xl"><div><div class="text-[10px] font-black text-gray-400">Cash Value</div><div class="text-2xl font-black"><?php echo format_currency($user['bonusCoins'] / $settings['conversionRate']); ?></div></div><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><button type="submit" class="bg-vtu-green text-white px-8 py-4 rounded-2xl font-black text-xs">CASH OUT</button></form></div>
    </div>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
