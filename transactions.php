<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Transactions';

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE userId = ? ORDER BY date DESC");
$stmt->execute([$currentUser['id']]);
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Activity Log</h1>
    </div>
    <div class="p-4 space-y-4">
        <?php if (empty($transactions)): ?>
            <div class="py-20 text-center text-gray-300 font-black uppercase text-xs">No transactions yet</div>
        <?php else: ?>
            <?php foreach ($transactions as $tx): ?>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 shadow-sm flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $tx['status'] === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>">
                            <i data-lucide="<?php echo $tx['status'] === 'successful' ? 'check-circle-2' : 'x-circle'; ?>" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="text-sm font-black text-gray-800"><?php echo $tx['type']; ?></div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase"><?php echo date('M d, H:i', strtotime($tx['date'])); ?> • <?php echo $tx['recipient']; ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black <?php echo $tx['status'] === 'successful' ? 'text-gray-900' : 'text-red-500'; ?>"><?php echo formatCurrency($tx['amount']); ?></div>
                        <div class="text-[9px] font-black uppercase opacity-40"><?php echo $tx['status']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
