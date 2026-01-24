<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data();
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE userId = ? ORDER BY date DESC"); $stmt->execute([$user['id']]); $myTxs = $stmt->fetchAll(); ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex flex-col sticky top-0 z-40 border-b shadow-sm"><div class="flex items-center gap-4"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900 cursor-pointer"></i></a><h1 class="text-lg font-black text-gray-900">History</h1></div></div>
  <div class="flex-1 overflow-y-auto pt-4"><?php if (empty($myTxs)): ?><div class="text-center py-40 text-gray-400 font-black uppercase text-[10px]">No transactions</div><?php else: ?><div class="px-4 space-y-3"><?php foreach ($myTxs as $tx): ?>
    <div class="bg-white p-5 rounded-[28px] flex items-center justify-between border border-gray-100/50 shadow-sm active:scale-[0.98] transition-all">
      <div class="flex items-center gap-4"><div class="w-12 h-12 rounded-[20px] flex items-center justify-center bg-gray-50"><i data-lucide="credit-card" size="18" class="text-vtu-green"></i></div><div><div class="text-xs font-black text-gray-800 uppercase"><?php echo h($tx['type']); ?></div><div class="text-[10px] text-gray-400 font-bold"><?php echo h($tx['recipient']); ?> • <?php echo date('M j', strtotime($tx['date'])); ?></div></div></div>
      <div class="text-right shrink-0"><div class="text-sm font-black">-<?php echo format_currency($tx['amount']); ?></div><div class="text-[8px] font-black uppercase tracking-widest mt-1 text-vtu-green"><?php echo h($tx['status']); ?></div></div>
    </div><?php endforeach; ?></div><?php endif; ?></div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
