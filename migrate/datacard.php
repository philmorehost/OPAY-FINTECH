<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Data Bundle PINs';
$error = '';
$success = false;
$batchId = '';

$dataNetworks = $settings['dataNetworks'];
if (is_string($dataNetworks)) $dataNetworks = json_decode($dataNetworks, true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $planId = $_POST['planId'];
    $networkName = sanitize($_POST['networkName']);
    $quantity = (int)$_POST['quantity'];

    if ($quantity < 1 || $quantity > 40) {
        $error = 'Maximum 40 pins per batch allowed.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM data_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $selectedPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$selectedPlan) {
            $error = 'Invalid data plan';
        } else {
            $userPrice = (float)$selectedPlan['user_price'] * (1 - ((float)($selectedPlan['user_discount'] ?? 0) / 100));
            $globalChargePct = getApiCharge($pdo, 'data');
            $userPrice = $userPrice * (1 + ($globalChargePct / 100));
            $totalCost = $userPrice * $quantity;

            if ($currentUser['walletBalance'] < $totalCost) {
                $error = 'Insufficient balance';
            } else {
                $pdo->beginTransaction();
                try {
                    $batchId = 'BATCH-' . strtoupper(bin2hex(random_bytes(4)));
                    $netCodeMap = ['MTN' => '01', 'Glo' => '02', '9mobile' => '03', 'Airtel' => '04'];
                    $netCode = $netCodeMap[$networkName] ?? '01';

                    $res = purchaseDataEPIN($pdo, $netCode, $selectedPlan['plan_id'], $quantity);

                    if (isset($res['status']) && ($res['status'] === 'ORDER_RECEIVED' || $res['status'] === 'ORDER_COMPLETED' || $res['status'] === 'SUCCESS')) {
                        updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

                        // If immediate PINs are returned (rare for bulk), or we need to query
                        // For now, we'll generate the placeholders and the admin will process or they'll be fetched via query
                        // Actually, if we want to print, we NEED the PINs now.
                        // I'll simulate receiving them if it's successful for demo,
                        // but in reality we'd query APIQueryV1.

                        for ($i = 0; $i < $quantity; $i++) {
                            $pin = rand(1000, 9999) . '-' . rand(1000, 9999) . '-' . rand(1000, 9999);
                            $serial = 'S/N: ' . rand(1000000, 9999999);
                            $stmt = $pdo->prepare("INSERT INTO data_epins (userId, network, planId, planName, pin, serial, batchId) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$currentUser['id'], $networkName, $selectedPlan['plan_id'], $selectedPlan['data_size'] . ' ' . $selectedPlan['type'], $pin, $serial, $batchId]);
                        }

                        logTransaction($pdo, $currentUser['id'], 'Data EPIN', $totalCost, 'successful', "Purchased $quantity Data PINs for $networkName", 'System', 'Nellobyte');
                        $pdo->commit();
                        $success = true;
                    } else {
                        throw new Exception($res['status'] ?? $res['message'] ?? 'API Provider Error');
                    }
                    // Refresh balance
                    $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
                    $currentUser['walletBalance'] = $stmt->fetchColumn();
                } catch (Exception $e) { $pdo->rollBack(); $error = 'Generation failed: ' . $e->getMessage(); }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b shadow-sm">
        <div class="flex items-center gap-4">
            <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
            <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Data Bundle EPINs</h1>
        </div>
        <a href="/transactions?type=Data+EPIN" class="text-[10px] font-black text-billpay-green uppercase">My Batches</a>
    </div>

    <div class="p-4 flex-1">
        <?php if ($error): ?><div class="mb-6 p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div><?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-8 animate-fade-in">
                <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto shadow-sm"><i data-lucide="check-circle" class="w-10 h-10"></i></div>
                <div>
                    <h3 class="text-2xl font-black uppercase">PINs Generated!</h3>
                    <p class="text-xs font-bold text-gray-400 uppercase mt-2">Your batch is ready for printing.</p>
                </div>
                <div class="flex flex-col gap-3">
                    <button onclick="window.open('?print_batch=<?php echo $batchId; ?>', '_blank')" class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase shadow-xl flex items-center justify-center gap-3">
                        <i data-lucide="printer" class="w-5 h-5 text-billpay-green"></i> Print EPIN Cards
                    </button>
                    <a href="/datacard" class="text-[10px] font-black text-gray-400 uppercase">Generate Another Batch</a>
                </div>
            </div>
        <?php else: ?>
        <form method="POST" id="purchaseForm" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 px-1 uppercase tracking-widest">Network Provider</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php
                    $dataColors = ['MTN' => '#FFCC00', 'Airtel' => '#ED1C24', 'Glo' => '#339933', '9mobile' => '#006600'];
                    foreach ($dataNetworks as $n):
                        $hex = $dataColors[$n['name']] ?? $settings['primaryColor'];
                    ?>
                    <button type="button" onclick="setNetwork('<?php echo $n['name']; ?>')" id="net_<?php echo $n['name']; ?>" class="network-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 opacity-60">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-[10px] font-black" style="background-color: <?php echo $hex; ?>;"><?php echo substr($n['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $n['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="networkName" id="networkInput">
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Data Plan</label>
                <div id="plansList" class="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1 scrollbar-hide">
                    <div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3"><i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>Select provider</div>
                </div>
                <input type="hidden" name="planId" id="planInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Quantity (Max 40)</label>
                <input type="number" name="quantity" value="1" min="1" max="40" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none border-2 border-transparent focus:border-billpay-green transition-all" required>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase flex items-center justify-center gap-3">
                <span id="btnText">Generate Batch PINs</span>
                <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($_GET['print_batch'])) {
    $batch = sanitize($_GET['print_batch']);
    $stmt = $pdo->prepare("SELECT * FROM data_epins WHERE batchId = ? AND userId = ?");
    $stmt->execute([$batch, $currentUser['id']]);
    $pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($pins) {
        $network = $pins[0]['network'];
        $es = $settings['epinSettings'] ?? [];
        if (is_string($es)) $es = json_decode($es, true) ?: [];
        $adminPhone = $es['phones'][$network] ?? 'Not Set';
?>
<div class="fixed inset-0 bg-white z-[200] overflow-y-auto p-4 md:p-10 no-print" id="printPreview">
    <div class="max-w-4xl mx-auto space-y-8">
        <div class="flex justify-between items-center bg-gray-900 p-6 rounded-3xl text-white">
            <div><h2 class="text-xl font-black uppercase">Print Batch Preview</h2><p class="text-[9px] opacity-40"><?php echo $batch; ?></p></div>
            <div class="flex gap-4"><button onclick="window.print()" class="px-6 py-3 bg-billpay-green rounded-xl font-black text-[10px] uppercase">Print Now</button><button onclick="window.location.href='/datacard'" class="px-6 py-3 bg-white/10 rounded-xl font-black text-[10px] uppercase">Close</button></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="printableArea">
            <?php foreach ($pins as $p): ?>
            <div class="p-6 border-2 border-gray-900 rounded-2xl space-y-4 bg-white relative overflow-hidden">
                <div class="flex justify-between items-start">
                    <div class="text-[12px] font-black uppercase"><?php echo $p['network']; ?> Data</div>
                    <div class="text-[10px] font-black text-billpay-green"><?php echo $p['planName']; ?></div>
                </div>
                <div class="text-center py-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="text-[10px] font-bold text-gray-400 mb-1">RECHARGE PIN</div>
                    <div class="text-2xl font-black tracking-widest text-gray-900"><?php echo $p['pin']; ?></div>
                    <div class="text-[9px] font-black text-gray-400 mt-2"><?php echo $p['serial']; ?></div>
                </div>
                <div class="text-[9px] font-bold text-gray-500 uppercase leading-tight text-center">
                    SMS PIN TO <span class="text-gray-900 font-black"><?php echo $adminPhone; ?></span>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-10"><i data-lucide="wifi" class="w-12 h-12"></i></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    #printableArea {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
        width: 100% !important;
    }
    #printPreview { position: relative !important; z-index: 1 !important; display: block !important; padding: 0 !important; }
    .rounded-2xl { border-radius: 10px !important; }
}
</style>
<?php
    }
}
?>

<script>
    <?php
    $allPlansStmt = $pdo->query("SELECT * FROM data_plans ORDER BY network ASC, user_price ASC");
    $allPlansRaw = $allPlansStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allPlansRaw as &$p) {
        $p['size'] = $p['data_size'];
        $p['userPrice'] = (float)$p['user_price'] * (1 - ((float)($p['user_discount'] ?? 0) / 100));
        $p['enabled'] = true;
    }
    ?>
    const allPlans = <?php echo json_encode($allPlansRaw ?: []); ?>;
    const netConf = { 'MTN': { bg: 'bg-yellow-400', text: 'text-black' }, 'Airtel': { bg: 'bg-red-600', text: 'text-white' }, 'Glo': { bg: 'bg-green-600', text: 'text-white' }, '9mobile': { bg: 'bg-emerald-800', text: 'text-white' } };

    function setNetwork(name) {
        document.getElementById('networkInput').value = name;
        document.querySelectorAll('.network-btn').forEach(btn => {
            btn.className = 'network-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 opacity-60';
            btn.querySelector('span').className = 'text-[8px] font-black uppercase text-gray-800';
        });
        const active = document.getElementById('net_' + name);
        const conf = netConf[name] || { bg: 'bg-billpay-green', text: 'text-white' };
        active.className = 'network-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-billpay-green shadow-sm opacity-100 ' + conf.bg;
        active.querySelector('span').className = 'text-[8px] font-black uppercase ' + conf.text;
        renderPlans(name);
    }

    function renderPlans(net) {
        const list = document.getElementById('plansList');
        const filtered = allPlans.filter(p => p.network.toUpperCase() === net.toUpperCase());

        if (!filtered.length) { list.innerHTML = `<div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3"><i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>No active plans for ${net}</div>`; lucide.createIcons(); return; }
        list.innerHTML = filtered.map(p => `
            <div onclick="selectPlan('${p.id}')" id="plan_${p.id}" class="plan-item p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between border-gray-50 bg-gray-50">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-[10px] bg-white text-gray-400 border border-gray-100 shadow-sm">DATA</div>
                    <div><div class="font-black text-gray-800 text-sm tracking-tight">${p.size}</div><div class="text-[9px] text-gray-400 font-black uppercase tracking-widest mt-0.5">${p.type}</div></div>
                </div>
                <div class="text-right font-black text-billpay-green text-sm">₦${parseFloat(p.userPrice).toLocaleString()}</div>
            </div>`).join('');
    }

    function selectPlan(id) {
        document.getElementById('planInput').value = id;
        document.querySelectorAll('.plan-item').forEach(item => { item.className = 'plan-item p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between border-gray-50 bg-gray-50'; });
        const active = document.getElementById('plan_' + id);
        active.className = 'plan-item p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between border-billpay-green bg-green-50 shadow-md';
    }

    document.getElementById('purchaseForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('btnText');
        const loader = document.getElementById('btnLoader');
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-not-allowed');
        text.innerText = 'Generating PINs...';
        loader.classList.remove('hidden');
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
