<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Manage Data EPINs';
$success = '';
$error = '';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_epin') {
    header('Content-Type: application/json');
    $pin = sanitize($_GET['pin']);
    $stmt = $pdo->prepare("SELECT * FROM data_epins WHERE pin = ?");
    $stmt->execute([$pin]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($res ?: ['error' => 'Not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if ($_POST['action'] === 'process_epin') {
        $pin = sanitize($_POST['pin']);
        $phone = sanitize($_POST['processorPhone']);

        $stmt = $pdo->prepare("SELECT * FROM data_epins WHERE pin = ? AND status = 'active'");
        $stmt->execute([$pin]);
        $epin = $stmt->fetch();

        if ($epin) {
            // Trigger API Purchasesite wide
            $res = purchaseData($pdo, $epin['network'], $epin['planId'], $phone);

            if (isset($res['status']) && $res['status'] === 'success') {
                $stmt = $pdo->prepare("UPDATE data_epins SET status = 'used', processorPhone = ?, processedAt = NOW() WHERE id = ?");
                $stmt->execute([$phone, $epin['id']]);
                $success = "EPIN [{$pin}] successfully vended to [{$phone}]. Provider response: " . $res['message'];
            } else {
                $error = "API FAILED: " . ($res['message'] ?? 'Unknown Provider Error');
            }
        } else {
            $error = "Invalid or already processed EPIN.";
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black uppercase tracking-tight">Manage Data EPINs</h2>
    </div>

    <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Process Panel -->
        <div class="lg:col-span-1 space-y-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100" x-data="{ epinInfo: null }">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 flex items-center gap-3"><i data-lucide="scan" class="text-indigo-500"></i> Process EPIN</h3>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="process_epin">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Received PIN</label>
                        <input type="text" name="pin" @input="fetchEpin($event.target.value)" placeholder="XXXX-XXXX-XXXX" required class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none border border-transparent focus:border-billpay-green">
                    </div>

                    <div x-show="epinInfo" class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 animate-fade-in">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[9px] font-black uppercase text-indigo-400">Associated Plan</span>
                            <span class="px-2 py-1 rounded bg-indigo-600 text-white text-[8px] font-black uppercase" x-text="epinInfo.status"></span>
                        </div>
                        <div class="text-xs font-black text-indigo-900 uppercase" x-text="epinInfo.network + ' - ' + epinInfo.planName"></div>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Recipient Phone</label>
                        <input type="tel" name="processorPhone" placeholder="08XXXXXXXXX" required class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none border border-transparent focus:border-billpay-green">
                    </div>
                    <button type="submit" :disabled="!epinInfo || epinInfo.status !== 'active'" class="w-full py-5 bg-gray-900 text-white rounded-2xl font-black uppercase text-xs shadow-xl disabled:opacity-50">Execute API Vend</button>
                </form>
                <script>
                    function fetchEpin(val) {
                        if (val.length < 5) return;
                        fetch('?ajax=get_epin&pin=' + val)
                            .then(r => r.json())
                            .then(res => {
                                this.epinInfo = res.error ? null : res;
                            });
                    }
                </script>
            </div>
        </div>

        <!-- Recent EPINs -->
        <div class="lg:col-span-2">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
                <h3 class="text-sm font-black uppercase tracking-widest mb-8">Active/Recent Batches</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                <th class="pb-4">Network/Plan</th>
                                <th class="pb-4">PIN / Serial</th>
                                <th class="pb-4">User</th>
                                <th class="pb-4">Status</th>
                                <th class="pb-4 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $stmt = $pdo->query("SELECT e.*, u.username FROM data_epins e LEFT JOIN users u ON e.userId = u.id ORDER BY e.createdAt DESC LIMIT 50");
                            while ($e = $stmt->fetch()):
                                $statusColor = ($e['status'] === 'active') ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600';
                            ?>
                            <tr>
                                <td class="py-4">
                                    <div class="font-black text-xs uppercase"><?php echo $e['network']; ?></div>
                                    <div class="text-[10px] text-gray-400"><?php echo $e['planName']; ?></div>
                                </td>
                                <td class="py-4 font-mono text-[10px]">
                                    <div class="font-black text-gray-900"><?php echo $e['pin']; ?></div>
                                    <div class="text-gray-400"><?php echo $e['serial']; ?></div>
                                </td>
                                <td class="py-4 text-[10px] font-bold">@<?php echo $e['username']; ?></td>
                                <td class="py-4">
                                    <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $statusColor; ?>"><?php echo $e['status']; ?></span>
                                    <?php if($e['processorPhone']): ?>
                                    <div class="text-[8px] text-gray-400 mt-1 uppercase">For: <?php echo $e['processorPhone']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-right text-[10px] text-gray-400"><?php echo date('d M, H:i', strtotime($e['createdAt'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
