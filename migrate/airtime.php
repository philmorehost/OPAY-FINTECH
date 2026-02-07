<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Airtime';

$error = '';
$statusDetails = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $isBulk = isset($_POST['isBulk']) && $_POST['isBulk'] === 'true';
    $network = sanitize($_POST['network']);
    $amount = (float)$_POST['amount'];

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

    // Calculate Costs & Profit
    $userDiscount = (float)($settings['airtimeDiscounts'][$network] ?? 0);
    $globalChargePct = getApiCharge($pdo, 'airtime');

    $unitCost = ($amount * (1 - $userDiscount / 100)) * (1 + ($globalChargePct / 100));
    $totalCost = count($recipients) * $unitCost;

    // API Info for Profit
    $as = $settings['airtimeSettings'] ?? [];
    $provider = $as['routing'][$network] ?? 'datagifting';
    $apiDiscount = (float)($as['networkDiscounts'][$network] ?? 0);
    $unitApiCost = $amount * (1 - $apiDiscount / 100);
    $unitProfit = $unitCost - $unitApiCost;

    $ls = $settings['loginSecuritySettings'] ?? [];
    $isPinForced = !empty($ls['pin']['forced']);
    $userPinEnabled = !empty($currentUser['fundPasswordVtuEnabled']);

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif (($isPinForced || $userPinEnabled) && !verifyFundPassword($pdo, $currentUser['id'], $_POST['fund_password'] ?? '')) {
        $error = 'Invalid Security PIN';
    } elseif ($amount < ($settings['minAirtimePurchase'] ?? 50)) {
        $error = 'Minimum airtime is ' . formatCurrency($settings['minAirtimePurchase'] ?? 50);
    } elseif (empty($recipients)) {
        $error = 'Enter valid phone numbers';
    } elseif ($currentUser['walletBalance'] < $totalCost) {
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
                    $response = purchaseAirtime($pdo, $network, $amount, $num);
                    $isSuccess = (isset($response['status']) && $response['status'] === 'success');

                    if ($isSuccess) {
                        $successCount++;
                        $unitProfitVal = $unitCost - $unitApiCost;
                        logTransaction($pdo, $currentUser['id'], 'Airtime', $unitCost, 'successful', "$network Airtime for $num", $num, $network, null, $unitApiCost, $unitProfitVal);
                    } else {
                        updateWallet($pdo, $currentUser['id'], $unitCost, 'credit');
                        logTransaction($pdo, $currentUser['id'], 'Airtime', $unitCost, 'failed', "$network Airtime failed for $num: " . ($response['message'] ?? 'Error'), $num, $network);
                    }
                }

                $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
                $currentUser['walletBalance'] = $stmt->fetchColumn();

                $status = ($successCount > 0) ? 'successful' : 'failed';
                $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Airtime purchase processed.<br>Network: $network<br>Total: " . formatCurrency($totalCost) . "<br>Status: " . strtoupper($status);
                sendMail($pdo, $currentUser['email'], "Airtime Receipt", $receiptMsg, $status);
                if ($successCount > 0) claimDailyRewardIfEligible($pdo, $currentUser['id']);
                $pdo->commit();

                $statusDetails = ['status' => $successCount > 0 ? 'success' : 'failed', 'amount' => $totalCost, 'count' => $successCount, 'total' => count($recipients), 'msg' => $successCount > 0 ? "Processed $successCount/" . count($recipients) . " successfully." : "Purchase failed."];
            } catch (Exception $e) { $pdo->rollBack(); $error = 'Internal Error: ' . $e->getMessage(); }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Airtime Service</h1>
    </div>

    <div class="p-4 flex-1">
        <?php if ($error): ?><div class="mb-6 p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" id="airtimeForm" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">
            <input type="hidden" name="isBulk" id="isBulkInput" value="false">

            <div class="flex bg-gray-100 p-1.5 rounded-2xl">
                <button type="button" onclick="setBulk(false)" id="tabSingle" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green">Single</button>
                <button type="button" onclick="setBulk(true)" id="tabBulk" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400">Batch</button>
            </div>

            <div id="singlePhoneGroup">
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Phone Number</label>
                <input type="tel" name="phoneNumber" placeholder="08012345678" maxlength="11" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none focus:ring-2 focus:ring-billpay-green/10">
            </div>

            <div id="bulkPhoneGroup" class="hidden space-y-4">
                <div class="flex justify-between items-center px-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients</label>
                    <button type="button" onclick="document.getElementById('bulkNumbers').value = ''" class="text-[9px] font-black text-red-500 uppercase flex items-center gap-1">Clear</button>
                </div>
                <textarea id="bulkNumbers" name="bulkNumbers" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-bold min-h-[140px] text-sm leading-relaxed" placeholder="08012345678, 09012345678..."></textarea>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 px-1 uppercase tracking-widest">Network Provider</label>
                <div class="grid grid-cols-4 gap-4">
                    <?php
                    $networks = ['MTN', 'Airtel', 'Glo', '9mobile'];
                    $networkColors = [
                        'MTN' => ['bg' => 'bg-yellow-400', 'text' => 'text-black', 'hex' => '#FFCC00'],
                        'Airtel' => ['bg' => 'bg-red-600', 'text' => 'text-white', 'hex' => '#ED1C24'],
                        'Glo' => ['bg' => 'bg-green-600', 'text' => 'text-white', 'hex' => '#339933'],
                        '9mobile' => ['bg' => 'bg-emerald-800', 'text' => 'text-white', 'hex' => '#006600']
                    ];
                    foreach ($networks as $n):
                        $conf = $networkColors[$n] ?? ['bg' => 'bg-billpay-green', 'text' => 'text-white', 'hex' => $settings['primaryColor']];
                    ?>
                    <button type="button" onclick="setNetwork('<?php echo $n; ?>')" id="net_<?php echo $n; ?>" data-color="<?php echo $conf['bg']; ?>" data-text="<?php echo $conf['text']; ?>" class="network-btn p-3 rounded-2xl border-2 font-black text-[10px] transition-all border-transparent bg-gray-50 text-gray-400 flex flex-col items-center gap-2">
                        <div class="w-8 h-8 rounded-full shadow-sm flex items-center justify-center text-[10px] text-white font-black" style="background-color: <?php echo $conf['hex']; ?>;">
                            <?php echo substr($n, 0, 1); ?>
                        </div>
                        <?php echo $n; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="network" id="networkInput" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                    <input type="number" name="amount" id="amountInput" placeholder="Enter amount" min="50" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Security PIN</label>
                    <input type="password" name="fund_password" maxlength="6" placeholder="••••••" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" <?php echo ($isPinForced || $userPinEnabled) ? 'required' : ''; ?>>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase flex items-center justify-center gap-3">
                <span id="btnText">Confirm Purchase</span>
                <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
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
    function setBulk(isBulk) {
        document.getElementById('isBulkInput').value = isBulk;
        document.getElementById('singlePhoneGroup').classList.toggle('hidden', isBulk);
        document.getElementById('bulkPhoneGroup').classList.toggle('hidden', !isBulk);
        document.getElementById('tabSingle').className = !isBulk ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400';
        document.getElementById('tabBulk').className = isBulk ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400';
    }
    function setNetwork(name) {
        document.getElementById('networkInput').value = name;
        document.querySelectorAll('.network-btn').forEach(btn => {
            btn.className = 'network-btn p-3 rounded-2xl border-2 font-black text-[10px] transition-all border-transparent bg-gray-50 text-gray-400 flex flex-col items-center gap-2';
        });
        const activeBtn = document.getElementById('net_' + name);
        activeBtn.className = 'network-btn p-3 rounded-2xl border-2 font-black text-[10px] transition-all border-billpay-green shadow-md ' + activeBtn.dataset.color + ' ' + activeBtn.dataset.text + ' flex flex-col items-center gap-2';
    }

    document.getElementById('airtimeForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('btnText');
        const loader = document.getElementById('btnLoader');

        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-not-allowed');
        text.innerText = 'Processing...';
        loader.classList.remove('hidden');
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
