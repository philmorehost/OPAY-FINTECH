<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Data PIN History';

$stmt = $pdo->prepare("SELECT batchId, network, planName, COUNT(*) as qty, MIN(createdAt) as date, status FROM data_epins WHERE userId = ? GROUP BY batchId ORDER BY date DESC");
$stmt->execute([$currentUser['id']]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="lg:mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/datacard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">EPIN History</h1>
    </div>

    <div class="p-4 space-y-4">
        <?php if (empty($batches)): ?>
            <div class="py-20 text-center text-gray-300 font-black uppercase text-xs">No PIN batches generated yet</div>
        <?php else: ?>
            <?php foreach ($batches as $b): ?>
                <div class="bg-white p-5 rounded-[32px] border border-gray-100 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center font-black text-[10px]">PIN</div>
                            <div>
                                <div class="text-xs font-black text-gray-800"><?php echo $b['network']; ?> • <?php echo $b['planName']; ?></div>
                                <div class="text-[9px] text-gray-400 font-bold uppercase"><?php echo date('M d, Y • H:i', strtotime($b['date'])); ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-black text-billpay-green"><?php echo $b['qty']; ?> PINS</div>
                            <div class="text-[8px] font-black uppercase opacity-40"><?php echo $b['status']; ?></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="/datacard?print_batch=<?php echo $b['batchId']; ?>" target="_blank" class="flex-1 py-3 bg-gray-900 text-white rounded-2xl font-black text-[9px] uppercase tracking-widest text-center shadow-lg hover:scale-[1.02] transition-all">
                            Reprint Cards
                        </a>
                        <div class="px-4 py-3 bg-gray-50 rounded-2xl text-[8px] font-mono text-gray-400 flex items-center justify-center">
                            <?php echo $b['batchId']; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
