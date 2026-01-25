<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'KYC Review';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $id = $_POST['submissionId'];
    $status = $_POST['action'] === 'approve' ? 'verified' : 'rejected';

    $stmt = $pdo->prepare("SELECT userId FROM kyc_submissions WHERE id = ?");
    $stmt->execute([$id]);
    $userId = $stmt->fetchColumn();

    if ($userId) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE kyc_submissions SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            $stmt = $pdo->prepare("UPDATE users SET kycStatus = ?, tier = ? WHERE id = ?");
            $tier = ($status === 'verified') ? 3 : 1;
            $stmt->execute([$status, $tier, $userId]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$tab = $_GET['tab'] ?? 'pending';
$sql = "SELECT * FROM kyc_submissions";
if ($tab === 'pending') $sql .= " WHERE status = 'pending'";
else $sql .= " WHERE status != 'pending'";
$sql .= " ORDER BY date DESC";
$stmt = $pdo->query($sql);
$submissions = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div class="flex flex-col">
            <h2 class="text-2xl font-black uppercase tracking-tighter">KYC Desk</h2>
            <span class="text-[10px] font-bold text-gray-400 uppercase">Verification Protocol</span>
        </div>
        <div class="flex bg-gray-100 p-1 rounded-2xl">
            <a href="?tab=pending" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo $tab === 'pending' ? 'bg-white shadow-sm text-billpay-green' : 'text-gray-400'; ?>">Pending</a>
            <a href="?tab=processed" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo $tab === 'processed' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400'; ?>">Reviewed</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($submissions as $sub): ?>
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $sub['status'] === 'pending' ? 'bg-blue-50 text-blue-500' : ($sub['status'] === 'verified' ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-500'); ?>">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-gray-800"><?php echo $sub['fullName']; ?></div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight"><?php echo $sub['idType']; ?> • <?php echo $sub['idNumber']; ?></div>
                    </div>
                </div>
                <?php if ($sub['status'] === 'pending'): ?>
                <div class="flex gap-2">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="submissionId" value="<?php echo $sub['id']; ?>">
                        <button type="submit" class="p-2.5 bg-green-50 text-green-600 rounded-xl hover:bg-green-600 hover:text-white transition-all"><i data-lucide="check" class="w-5 h-5"></i></button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="submissionId" value="<?php echo $sub['id']; ?>">
                        <button type="submit" class="p-2.5 bg-red-50 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </form>
                </div>
                <?php else: ?>
                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $sub['status'] === 'verified' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>"><?php echo $sub['status']; ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
