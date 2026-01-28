<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Data';

$error = '';
$statusDetails = null;

$dataNetworks = $settings['dataNetworks'];
if (is_string($dataNetworks)) $dataNetworks = json_decode($dataNetworks, true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $isBulk = isset($_POST['isBulk']) && $_POST['isBulk'] === 'true';
    $planId = $_POST['planId'];
    $networkName = sanitize($_POST['networkName']);

    $selectedPlan = null;
    $allPlans = $settings['dataProducts'] ?? [];
    if (is_string($allPlans)) $allPlans = json_decode($allPlans, true) ?: [];
    foreach ($allPlans as $p) {
        if ($p['id'] == $planId) { $selectedPlan = $p; break; }
    }

    $recipients = [];
    if ($isBulk) {
        $raw = preg_split('/[,\s\n]+/', $_POST['bulkNumbers']);
        foreach ($raw as $num) { $num = trim($num); if (strlen($num) >= 10) $recipients[] = $num; }
        $recipients = array_unique($recipients);
    } else { $recipients[] = sanitize($_POST['phoneNumber']); }

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif (!$selectedPlan) {
        $error = 'Invalid data plan selected';
    } elseif (empty($recipients)) {
        $error = 'Enter valid phone numbers';
    } else {
        $userPrice = (float)$selectedPlan['userPrice'];
        $apiPrice = (float)($selectedPlan['apiPrice'] ?? $userPrice * 0.9); // Fallback to 10% profit if not set
        $profit = $userPrice - $apiPrice;
        $totalCost = count($recipients) * $userPrice;

        if ($currentUser['walletBalance'] < $totalCost) {
            $error = 'Insufficient balance';
        } else {
            foreach ($recipients as $num) {
                if (!checkDailyLimit($pdo, $currentUser['id'], $num, $settings['maxDailyTxPerId'] ?? 50)) {
                    $error = "Daily limit reached for $num"; break;
                }
            }

            if (!$error) {
                $pdo->beginTransaction();
                try {
                    updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

                    $successCount = 0;
                    foreach ($recipients as $num) {
                        $response = purchaseData($pdo, $networkName, $selectedPlan['apiCode'] ?? $selectedPlan['id'], $num);
                        $isSuccess = (isset($response['status']) && $response['status'] === 'success');

                        if ($isSuccess) {
                            $successCount++;
                            logTransaction($pdo, $currentUser['id'], 'Data', $userPrice, 'successful', "{$selectedPlan['size']} {$selectedPlan['type']} for $num", $num, $networkName, null, $apiPrice, $profit);
                        } else {
                            updateWallet($pdo, $currentUser['id'], $userPrice, 'credit');
                            logTransaction($pdo, $currentUser['id'], 'Data', $userPrice, 'failed', "{$selectedPlan['size']} failed: " . ($response['message'] ?? 'Error'), $num, $networkName);
                        }
                    }

                    $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
                    $currentUser['walletBalance'] = $stmt->fetchColumn();

                    $status = ($successCount > 0) ? 'successful' : 'failed';
                    sendMail($pdo, $currentUser['email'], "Data Receipt", "Data request processed for $networkName.<br>Total: " . formatCurrency($totalCost) . "<br>Status: " . strtoupper($status), $status);
                    if ($successCount > 0) claimDailyRewardIfEligible($pdo, $currentUser['id']);
                    $pdo->commit();

                    $statusDetails = ['status' => $successCount > 0 ? 'success' : 'failed', 'amount' => $totalCost, 'count' => $successCount, 'total' => count($recipients), 'msg' => $successCount > 0 ? "Processed $successCount/" . count($recipients) . " successfully." : "Purchase failed."];
                } catch (Exception $e) { $pdo->rollBack(); $error = 'Internal Error: ' . $e->getMessage(); }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Data Services</h1>
    </div>

    <div class="p-4 flex-1">
        <?php if ($error): ?><div class="mb-6 p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" id="dataForm" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">
            <input type="hidden" name="isBulk" id="isBulkInput" value="false">

            <div class="flex bg-gray-100 p-1.5 rounded-2xl">
                <button type="button" onclick="setBulk(false)" id="tabSingle" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green">Single</button>
                <button type="button" onclick="setBulk(true)" id="tabBulk" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400">Batch</button>
            </div>

            <div id="singlePhoneGroup">
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Recipient Number</label>
                <input type="tel" name="phoneNumber" placeholder="e.g. 08123456789" maxlength="11" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none focus:ring-2 focus:ring-billpay-green/10">
            </div>

            <div id="bulkPhoneGroup" class="hidden space-y-4">
                <div class="flex justify-between items-center px-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients</label>
                    <button type="button" onclick="document.getElementById('bulkNumbers').value = ''" class="text-[9px] font-black text-red-500 uppercase flex items-center gap-1">Clear</button>
                </div>
                <textarea id="bulkNumbers" name="bulkNumbers" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-bold min-h-[140px] text-sm leading-relaxed" placeholder="08012345678, 09012345678..."></textarea>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 px-1 uppercase tracking-widest">Provider</label>
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
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Available Packages</label>
                <div id="plansList" class="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1 scrollbar-hide">
                    <div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3"><i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>Select provider</div>
                </div>
                <input type="hidden" name="planId" id="planInput" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Proceed to Pay</button>
        </form>
    </div>
</div>

<?php if ($statusDetails): ?>
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6 text-gray-900">
    <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 text-white flex flex-col items-center text-center <?php echo $statusDetails['status'] === 'success' ? 'bg-billpay-green' : 'bg-red-500'; ?>">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg <?php echo $statusDetails['status'] === 'success' ? 'text-billpay-green' : 'text-red-500'; ?>"><i data-lucide="<?php echo $statusDetails['status'] === 'success' ? 'check-circle-2' : 'x-circle'; ?>" class="w-10 h-10"></i></div>
            <h3 class="text-xl font-black uppercase tracking-tight text-white"><?php echo $statusDetails['status'] === 'success' ? 'Success' : 'Failed'; ?></h3>
        </div>
        <div class="p-8 space-y-6 text-center">
            <p class="text-sm font-bold text-gray-500 leading-relaxed uppercase"><?php echo $statusDetails['msg']; ?></p>
            <a href="/dashboard" class="block w-full py-5 rounded-[24px] font-black text-sm shadow-xl active:scale-95 transition-all text-white bg-billpay-green uppercase text-center">Done</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    const allPlans = <?php echo json_encode($allPlans); ?>;
    function setBulk(isBulk) {
        document.getElementById('isBulkInput').value = isBulk;
        document.getElementById('singlePhoneGroup').classList.toggle('hidden', isBulk);
        document.getElementById('bulkPhoneGroup').classList.toggle('hidden', !isBulk);
        document.getElementById('tabSingle').className = !isBulk ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400';
        document.getElementById('tabBulk').className = isBulk ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400';
    }
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
        const filtered = allPlans.filter(p => p.network.toUpperCase() === net.toUpperCase() && p.enabled);
        if (!filtered.length) { list.innerHTML = `<div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3"><i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>No plans</div>`; lucide.createIcons(); return; }
        list.innerHTML = filtered.map(p => `
            <div onclick="selectPlan('${p.id}')" id="plan_${p.id}" class="plan-item p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between border-gray-50 bg-gray-50">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-[10px] bg-white text-gray-400 border border-gray-100 shadow-sm">${p.size.includes('GB') ? 'GB' : 'MB'}</div>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
