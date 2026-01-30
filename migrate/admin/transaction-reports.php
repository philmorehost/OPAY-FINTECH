<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Transaction Issue Reports';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');
    $reportId = (int)$_POST['reportId'];
    $comment = sanitize($_POST['adminComment']);
    $stmt = $pdo->prepare("UPDATE transaction_reports SET status = 'resolved', adminComment = ? WHERE id = ?");
    $stmt->execute([$comment, $reportId]);
    $success = "Report marked as resolved.";
}

$stmt = $pdo->query("SELECT r.*, u.username, u.fullName, t.type as txType, t.amount as txAmount, t.status as txStatus FROM transaction_reports r JOIN users u ON r.userId = u.id JOIN transactions t ON r.txId = t.id ORDER BY r.createdAt DESC");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Issue Reports</h2>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-[10px] font-black uppercase text-center border border-green-100 shadow-sm"><?php echo $success; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 gap-8">
        <?php if (empty($reports)): ?>
            <div class="bg-white p-20 text-center rounded-[40px] border border-gray-100">
                <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-gray-200"><i data-lucide="inbox" class="w-8 h-8"></i></div>
                <p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">No reports filed yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r): ?>
                <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black">
                                <i data-lucide="alert-circle" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-tight"><?php echo $r['issueType']; ?></h3>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">By <?php echo $r['fullName']; ?> (@<?php echo $r['username']; ?>) • <?php echo date('M d, H:i', strtotime($r['createdAt'])); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $r['status'] === 'open' ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600'; ?>">
                                <?php echo $r['status']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-6 bg-gray-50 rounded-3xl border border-gray-100 space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <span class="text-[9px] font-black text-gray-400 uppercase">Transaction Link</span>
                            <a href="transactions.php?search=<?php echo $r['txId']; ?>" class="text-[9px] font-black text-billpay-green uppercase hover:underline">
                                <?php echo $r['txType']; ?> (<?php echo formatCurrency($r['txAmount']); ?>) - <?php echo $r['txId']; ?>
                            </a>
                        </div>
                        <p class="text-xs font-bold text-gray-600 leading-relaxed uppercase"><?php echo $r['message']; ?></p>
                    </div>

                    <?php if ($r['status'] === 'open'): ?>
                        <form method="POST" class="space-y-4 pt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="reportId" value="<?php echo $r['id']; ?>">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Admin Response / Correction Done</label>
                            <textarea name="adminComment" placeholder="e.g. Transaction verified and successful, or manual refund processed..." class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required></textarea>
                            <button type="submit" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg hover:bg-black transition-all">Resolve Report</button>
                        </form>
                    <?php else: ?>
                        <div class="p-6 bg-green-50 rounded-3xl border border-green-100">
                            <h4 class="text-[9px] font-black text-green-800 uppercase mb-1">Admin Resolution</h4>
                            <p class="text-xs font-bold text-green-700 uppercase"><?php echo $r['adminComment']; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
