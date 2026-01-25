<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Virtual Card';

$stmt = $pdo->prepare("SELECT * FROM virtual_cards WHERE userId = ?");
$stmt->execute([$currentUser['id']]);
$cards = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/migrate/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Virtual Cards</h1>
    </div>
    <div class="p-4 space-y-6 flex-1">
        <?php if (empty($cards)): ?>
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-6">
                <div class="w-20 h-20 bg-billpay-green/10 rounded-full flex items-center justify-center mx-auto">
                    <i data-lucide="credit-card" class="w-10 h-10 text-billpay-green"></i>
                </div>
                <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">No Active Cards</h2>
                <p class="text-sm text-gray-400 font-bold uppercase">Generate a virtual Visa or Mastercard for your online payments.</p>
                <button class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase tracking-widest shadow-xl">Request New Card</button>
            </div>
        <?php else: ?>
            <?php foreach ($cards as $card): ?>
                <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-8 rounded-[32px] text-white shadow-2xl relative overflow-hidden aspect-[1.6/1] flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="text-sm font-black italic tracking-widest"><?php echo strtoupper($card['type']); ?></div>
                        <i data-lucide="wifi" class="w-6 h-6 rotate-90 opacity-40"></i>
                    </div>
                    <div class="text-2xl font-black tracking-[0.2em] my-4"><?php echo $card['cardNumber']; ?></div>
                    <div class="flex justify-between items-end">
                        <div>
                            <div class="text-[8px] font-black uppercase opacity-50 mb-1">Card Holder</div>
                            <div class="text-xs font-black uppercase tracking-widest"><?php echo $currentUser['fullName']; ?></div>
                        </div>
                        <div>
                            <div class="text-[8px] font-black uppercase opacity-50 mb-1">Expiry</div>
                            <div class="text-xs font-black"><?php echo $card['expiry']; ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
