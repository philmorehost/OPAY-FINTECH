<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Transaction Issue Reports';

if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');
    $stmt = $pdo->prepare("UPDATE transaction_reports SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['id']]);
    $success = "Report status updated.";
}

$reports = $pdo->query("SELECT r.*, u.fullName, u.username FROM transaction_reports r JOIN users u ON r.userId = u.id ORDER BY r.createdAt DESC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
        <h2 class="text-2xl font-black uppercase tracking-tighter mb-8">User Issue Reports</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                   <tr class="bg-gray-50 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                      <th class="p-6">Date</th>
                      <th class="p-6">User</th>
                      <th class="p-6">TX ID</th>
                      <th class="p-6">Subject</th>
                      <th class="p-6">Status</th>
                      <th class="p-6">Action</th>
                   </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($reports as $r): ?>
                       <tr>
                          <td class="p-6 text-[10px] font-bold text-gray-400"><?php echo date('M d, H:i', strtotime($r['createdAt'])); ?></td>
                          <td class="p-6">
                             <div class="flex flex-col">
                                <span class="text-xs font-black text-gray-800"><?php echo $r['fullName']; ?></span>
                                <span class="text-[9px] text-gray-400 font-bold uppercase">@<?php echo $r['username']; ?></span>
                             </div>
                          </td>
                          <td class="p-6 font-mono text-[10px] font-bold text-billpay-green uppercase cursor-pointer" onclick="window.location='/receipt?id=<?php echo $r['txId']; ?>'"><?php echo $r['txId']; ?></td>
                          <td class="p-6">
                              <div class="text-xs font-black text-gray-800"><?php echo $r['subject']; ?></div>
                              <div class="text-[9px] text-gray-400 mt-1"><?php echo $r['message']; ?></div>
                          </td>
                          <td class="p-6">
                             <span class="px-2 py-1 rounded-full text-[8px] font-black uppercase <?php echo $r['status'] === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600'; ?>">
                                <?php echo $r['status']; ?>
                             </span>
                          </td>
                          <td class="p-6">
                             <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <select name="status" onchange="this.form.submit()" class="text-[9px] font-black uppercase bg-gray-50 border border-gray-100 rounded-lg p-1 outline-none">
                                    <option value="pending" <?php echo $r['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="resolved" <?php echo $r['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="dismissed" <?php echo $r['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                </select>
                             </form>
                          </td>
                       </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
