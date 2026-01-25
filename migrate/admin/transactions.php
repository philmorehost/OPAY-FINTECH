<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'Global Transaction Log';

$stmt = $pdo->query("SELECT t.*, u.fullName, u.username FROM transactions t JOIN users u ON t.userId = u.id ORDER BY t.date DESC LIMIT 100");
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
        <h2 class="text-2xl font-black uppercase tracking-tighter mb-6">Live Audit Log</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                   <tr class="bg-gray-50 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                      <th class="p-6">Transaction ID</th>
                      <th class="p-6">User</th>
                      <th class="p-6">Service</th>
                      <th class="p-6">Amount</th>
                      <th class="p-6">Status</th>
                      <th class="p-6">Date</th>
                   </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($transactions as $tx): ?>
                       <tr class="hover:bg-gray-50 transition-colors">
                          <td class="p-6 font-mono text-[10px] font-bold text-gray-400 uppercase"><?php echo $tx['id']; ?></td>
                          <td class="p-6">
                             <div class="flex flex-col">
                                <span class="text-xs font-black text-gray-800"><?php echo $tx['fullName']; ?></span>
                                <span class="text-[9px] text-gray-400 font-bold uppercase">@<?php echo $tx['username']; ?></span>
                             </div>
                          </td>
                          <td class="p-6"><span class="px-3 py-1 bg-gray-100 rounded-full text-[9px] font-black uppercase text-gray-500"><?php echo $tx['type']; ?></span></td>
                          <td class="p-6 font-black text-gray-800 text-xs"><?php echo formatCurrency($tx['amount']); ?></td>
                          <td class="p-6">
                             <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $tx['status'] === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>">
                                <?php echo $tx['status']; ?>
                             </span>
                          </td>
                          <td class="p-6 text-[10px] font-bold text-gray-400"><?php echo date('Y-m-d H:i', strtotime($tx['date'])); ?></td>
                       </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
