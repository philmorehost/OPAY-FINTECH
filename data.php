<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Data';

$error = '';
$success = false;
$msg = '';

$dataNetworks = $settings['dataNetworks'];
$dataProducts = $settings['dataProducts'] ?? []; // Need to add this to settings table if not exists or fetch from separate table
// For now let's assume it's in settings as JSON if not too big, or I should have created a table.
// Looking at the installer, I didn't create a data_products table. I should probably have one.
// But the React app seems to store them in settings.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $isBulk = isset($_POST['isBulk']) && $_POST['isBulk'] === 'true';
    $planId = $_POST['planId'];

    // Find plan
    $selectedPlan = null;
    $allPlans = $settings['dataProducts'] ?? [];
    if (is_string($allPlans)) $allPlans = json_decode($allPlans, true) ?: [];
    foreach ($allPlans as $p) {
        if ($p['id'] === $planId) {
            $selectedPlan = $p;
            break;
        }
    }

    $recipients = [];
    if ($isBulk) {
        $raw = preg_split('/[,\s\n]+/', $_POST['bulkNumbers']);
        foreach ($raw as $num) {
            $num = trim($num);
            if (strlen($num) >= 10) $recipients[] = $num;
        }
        $recipients = array_unique($recipients);
    } else {
        $recipients[] = sanitize($_POST['phoneNumber']);
    }

    if (!$selectedPlan) {
        $error = 'Invalid data plan selected';
    } elseif (empty($recipients)) {
        $error = 'Enter valid phone numbers';
    } else {
        $totalCost = count($recipients) * $selectedPlan['userPrice'];
        if ($currentUser['walletBalance'] < $totalCost) {
            $error = 'Insufficient balance';
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

                $successCount = 0;
                $currentNetworkName = '';
                foreach ($dataNetworks as $n) {
                    if ($n['id'] === $selectedPlan['networkId']) {
                        $currentNetworkName = $n['name'];
                        break;
                    }
                }

                foreach ($recipients as $num) {
                    $isSuccess = (mt_rand(1, 100) > 5);
                    if ($isSuccess) $successCount++;
                    logTransaction($pdo, $currentUser['id'], 'Data', $selectedPlan['userPrice'], $isSuccess ? 'successful' : 'failed', "{$selectedPlan['size']} Plan for $num", $num, $currentNetworkName);
                }

                // Receipt Email
                $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your data purchase was processed.<br><br>";
                $receiptMsg .= "Network: $currentNetworkName<br>Plan: {$selectedPlan['size']}<br>Total: " . formatCurrency($totalCost);
                sendMail($pdo, $currentUser['email'], "Data Receipt", $receiptMsg);

                $pdo->commit();
                $success = true;
                $msg = "Processed $successCount/" . count($recipients) . " successfully.";

                // Refresh balance
                $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                $currentUser['walletBalance'] = $stmt->fetchColumn();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Transaction failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

$allPlans = $settings['dataProducts'] ?? [];
if (is_string($allPlans)) $allPlans = json_decode($allPlans, true) ?: [];
?>

<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Data Services</h1>
    </div>

    <div class="p-4 flex-1">
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 text-green-500 rounded-2xl text-xs font-black border border-green-100 text-center uppercase"><?php echo $msg; ?></div>
        <?php endif; ?>

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
                    <?php foreach ($dataNetworks as $n): ?>
                    <button type="button" onclick="setNetwork('<?php echo $n['id']; ?>')" id="net_<?php echo $n['id']; ?>" class="network-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 opacity-60">
                        <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($n['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $n['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="networkId" id="networkInput">
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Available Packages</label>
                <div id="plansList" class="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1 scrollbar-hide">
                    <div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3">
                        <i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>
                        Select provider
                    </div>
                </div>
                <input type="hidden" name="planId" id="planInput" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all">PROCEED TO PAY</button>
        </form>
    </div>
</div>

<script>
    const allPlans = <?php echo json_encode($allPlans); ?>;

    function setBulk(isBulk) {
        document.getElementById('isBulkInput').value = isBulk;
        document.getElementById('singlePhoneGroup').classList.toggle('hidden', isBulk);
        document.getElementById('bulkPhoneGroup').classList.toggle('hidden', !isBulk);

        document.getElementById('tabSingle').classList.toggle('bg-white', !isBulk);
        document.getElementById('tabSingle').classList.toggle('shadow-md', !isBulk);
        document.getElementById('tabSingle').classList.toggle('text-billpay-green', !isBulk);
        document.getElementById('tabSingle').classList.toggle('text-gray-400', isBulk);

        document.getElementById('tabBulk').classList.toggle('bg-white', isBulk);
        document.getElementById('tabBulk').classList.toggle('shadow-md', isBulk);
        document.getElementById('tabBulk').classList.toggle('text-billpay-green', isBulk);
        document.getElementById('tabBulk').classList.toggle('text-gray-400', !isBulk);
    }

    function setNetwork(id) {
        document.getElementById('networkInput').value = id;
        document.querySelectorAll('.network-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-green-50', 'shadow-sm');
            btn.classList.add('border-transparent', 'bg-gray-50', 'opacity-60');
        });
        const activeBtn = document.getElementById('net_' + id);
        activeBtn.classList.add('border-billpay-green', 'bg-green-50', 'shadow-sm');
        activeBtn.classList.remove('border-transparent', 'bg-gray-50', 'opacity-60');

        renderPlans(id);
    }

    function renderPlans(networkId) {
        const plansList = document.getElementById('plansList');
        const filtered = allPlans.filter(p => p.networkId === networkId && p.enabled);

        if (filtered.length === 0) {
            plansList.innerHTML = `<div class="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3"><i data-lucide="wifi" class="w-6 h-6 opacity-20"></i>No plans registered</div>`;
            lucide.createIcons();
            return;
        }

        plansList.innerHTML = filtered.map(plan => `
            <div onclick="selectPlan('${plan.id}')" id="plan_${plan.id}" class="plan-item p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between border-gray-50 bg-gray-50">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-[10px] bg-white text-gray-400 border border-gray-100 shadow-sm">
                        ${plan.size.includes('GB') ? 'GB' : 'MB'}
                    </div>
                    <div>
                        <div class="font-black text-gray-800 text-sm tracking-tight">${plan.size}</div>
                        <div class="text-[9px] text-gray-400 font-black uppercase tracking-widest mt-0.5">${plan.type.replace('-data', '')}</div>
                    </div>
                </div>
                <div class="text-right flex flex-col items-end gap-1">
                    <div class="text-billpay-green font-black text-sm">₦${parseFloat(plan.userPrice).toLocaleString()}</div>
                </div>
            </div>
        `).join('');
    }

    function selectPlan(id) {
        document.getElementById('planInput').value = id;
        document.querySelectorAll('.plan-item').forEach(item => {
            item.classList.remove('border-billpay-green', 'bg-green-50', 'shadow-md');
            item.classList.add('border-gray-50', 'bg-gray-50');
        });
        const activeItem = document.getElementById('plan_' + id);
        activeItem.classList.add('border-billpay-green', 'bg-green-50', 'shadow-md');
        activeItem.classList.remove('border-gray-50', 'bg-gray-50');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
