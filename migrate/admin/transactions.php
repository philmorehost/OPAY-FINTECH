<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Global Transaction Log';

$typeFilter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT t.*, u.fullName, u.username FROM transactions t JOIN users u ON t.userId = u.id WHERE 1=1";
$params = [];

if ($typeFilter) {
    $sql .= " AND t.type = ?";
    $params[] = $typeFilter;
}

if ($search) {
    $sql .= " AND (t.recipient LIKE ? OR t.details LIKE ? OR t.id LIKE ? OR u.username LIKE ? OR u.fullName LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($_GET['date'])) {
    $sql .= " AND DATE(t.date) = ?";
    $params[] = $_GET['date'];
}

$sql .= " ORDER BY t.date DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <h2 class="text-2xl font-black uppercase tracking-tighter">Live Audit Log</h2>

            <form method="GET" class="flex flex-wrap gap-4 w-full">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Search ID/User/Recipient</label>
                    <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search ID, Phone, IUC..." class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold outline-none focus:border-billpay-green">
                </div>
                <div class="w-40">
                    <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Service</label>
                    <select name="type" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold outline-none focus:border-billpay-green">
                        <option value="">All Services</option>
                        <?php
                        $types = ['Airtime', 'Data', 'Transfer', 'Cable TV', 'Electricity', 'Betting', 'Bulk SMS', 'Deposit'];
                        foreach ($types as $t):
                        ?>
                            <option value="<?php echo $t; ?>" <?php echo $typeFilter === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-40">
                    <label class="text-[8px] font-black text-gray-400 uppercase ml-1">Filter Date</label>
                    <input type="date" name="date" value="<?php echo sanitize($_GET['date'] ?? ''); ?>" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold outline-none focus:border-billpay-green">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="p-3 bg-gray-900 text-white rounded-xl text-xs font-black uppercase px-8 shadow-lg hover:scale-105 transition-all">Filter</button>
                </div>
                <div class="flex items-end">
                    <button type="button" onclick="triggerRequery(event)" class="p-3 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase px-6 shadow-lg hover:scale-105 transition-all flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Run Auto-Requery
                    </button>
                </div>
            </form>
        </div>

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
                       <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="window.location='/receipt?id=<?php echo $tx['id']; ?>'">
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
<script>
function triggerRequery(e) {
    const btn = e.currentTarget;
    const icon = btn.querySelector('i');
    btn.disabled = true;
    icon.classList.add('animate-spin');

    fetch('/cron-requery.php?ajax=1&force=1')
        .then(r => r.json())
        .then(data => {
            alert('Requery completed. Found ' + data.queried + ' transactions to check.');
            window.location.reload();
        })
        .catch(err => {
            console.error(err);
            alert('Requery failed. Check console for details.');
        })
        .finally(() => {
            btn.disabled = false;
            icon.classList.remove('animate-spin');
        });
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
