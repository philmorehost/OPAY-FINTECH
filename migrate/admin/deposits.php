<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'Deposit Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $id = $_POST['depositId'];
    $status = $_POST['action'] === 'approve' ? 'successful' : 'rejected';

    $stmt = $pdo->prepare("SELECT * FROM deposit_requests WHERE id = ?");
    $stmt->execute([$id]);
    $req = $stmt->fetch();

    if ($req && $req['status'] === 'pending') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE deposit_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            if ($status === 'successful') {
                $creditAmount = $req['amount'] - $req['charge'];
                updateWallet($pdo, $req['userId'], $creditAmount, 'credit');
                logTransaction($pdo, $req['userId'], 'Wallet Funding', $creditAmount, 'successful', "Manual Deposit Approved", 'Wallet', 'Manual');
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$tab = $_GET['tab'] ?? 'pending';
$sql = "SELECT d.*, u.fullName, u.username FROM deposit_requests d JOIN users u ON d.userId = u.id";
if ($tab === 'pending') {
    $sql .= " WHERE d.status = 'pending'";
} else {
    $sql .= " WHERE d.status != 'pending'";
}
$sql .= " ORDER BY d.date DESC";
$stmt = $pdo->query($sql);
$deposits = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div class="flex flex-col">
            <h2 class="text-2xl font-black uppercase tracking-tighter">Deposits Hub</h2>
            <span class="text-[10px] font-bold text-gray-400 uppercase">Managing Financial Inflow</span>
        </div>
        <div class="flex bg-gray-100 p-1 rounded-2xl">
            <a href="?tab=pending" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo $tab === 'pending' ? 'bg-white shadow-sm text-billpay-green' : 'text-gray-400'; ?>">Pending</a>
            <a href="?tab=processed" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo $tab === 'processed' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400'; ?>">Processed</a>
        </div>
    </div>

    <div class="space-y-4">
        <?php foreach ($deposits as $req): ?>
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $req['method'] === 'manual' ? 'bg-indigo-50 text-indigo-500' : 'bg-emerald-50 text-emerald-500'; ?>">
                        <i data-lucide="<?php echo $req['method'] === 'manual' ? 'landmark' : 'credit-card'; ?>" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-gray-800"><?php echo $req['fullName']; ?> (@<?php echo $req['username']; ?>)</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight"><?php echo $req['method']; ?> • <?php echo date('Y-m-d H:i', strtotime($req['date'])); ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-right">
                        <div class="text-sm font-black text-gray-900"><?php echo formatCurrency($req['amount']); ?></div>
                        <div class="text-[9px] text-gray-400 font-bold uppercase">Fee: <?php echo formatCurrency($req['charge']); ?></div>
                    </div>
                    <?php if ($req['status'] === 'pending'): ?>
                        <div class="flex gap-2">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="depositId" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="p-3 bg-green-50 text-green-600 rounded-xl hover:bg-green-100 transition-colors"><i data-lucide="check" class="w-5 h-5"></i></button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="depositId" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors"><i data-lucide="ban" class="w-5 h-5"></i></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase <?php echo $req['status'] === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>"><?php echo $req['status']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($deposits)): ?>
            <div class="py-24 bg-white rounded-[40px] text-center text-gray-300 font-black uppercase text-xs">No records found</div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
