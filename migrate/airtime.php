<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$isServiceRestricted = checkMinDepositRestriction($settings, $currentUser);

$pageTitle = 'Airtime';

$error = '';
$success = false;
$statusDetails = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

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

    $totalCost = count($recipients) * $amount;

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($amount < $settings['minAirtimePurchase']) {
        $error = 'Minimum airtime is ' . formatCurrency($settings['minAirtimePurchase']);
    } elseif (empty($recipients)) {
        $error = 'Enter valid phone numbers';
    } elseif ($currentUser['walletBalance'] < $totalCost) {
        $error = 'Insufficient balance';
    } else {
        // Daily Limit Check
        foreach ($recipients as $num) {
            if (!checkDailyLimit($pdo, $currentUser['id'], $num, $settings['maxDailyTxPerId'])) {
                $error = "Daily transaction limit reached for $num";
                break;
            }
        }

        if ($error) {
            // Error already set
        } else {
        // Process purchase
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

            $successCount = 0;
            foreach ($recipients as $num) {
                // Simulation: 98% success
                $isSuccess = (mt_rand(1, 100) > 2);
                if ($isSuccess) $successCount++;

                logTransaction($pdo, $currentUser['id'], 'Airtime', $amount, $isSuccess ? 'successful' : 'failed', "$network Airtime recharge for $num", $num, $network);
            }

            // Receipt Email
            $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your airtime purchase was processed.<br><br>";
            $receiptMsg .= "Network: $network<br>Amount: " . formatCurrency($totalCost) . "<br>Status: " . ($successCount > 0 ? 'Successful' : 'Failed');
            sendMail($pdo, $currentUser['email'], "Airtime Receipt", $receiptMsg);

            $pdo->commit();
            $success = true;
            $statusDetails = [
                'status' => 'success',
                'amount' => $totalCost,
                'count' => $successCount,
                'total' => count($recipients),
                'msg' => "Recharge of $successCount/" . count($recipients) . " was successful."
            ];

            // Refresh currentUser balance
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
?>

<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Airtime Service</h1>
    </div>

    <div class="p-4 flex-1">
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>

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
                    $networks = [
                        ['name' => 'MTN', 'code' => '01'],
                        ['name' => 'Airtel', 'code' => '04'],
                        ['name' => 'Glo', 'code' => '02'],
                        ['name' => '9mobile', 'code' => '03']
                    ];
                    foreach ($networks as $n):
                    ?>
                    <button type="button" onclick="setNetwork('<?php echo $n['name']; ?>')" id="net_<?php echo $n['name']; ?>" class="network-btn p-3 rounded-2xl border-2 font-black text-[10px] transition-all border-transparent bg-gray-50 text-gray-400">
                        <?php echo $n['name']; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="network" id="networkInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" id="amountInput" placeholder="Enter amount" min="50" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all">CONFIRM & BUY</button>
        </form>
    </div>
</div>

<?php if ($statusDetails): ?>
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 text-white flex flex-col items-center text-center bg-billpay-green">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg text-billpay-green">
                <i data-lucide="check-circle-2" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-black uppercase tracking-tight text-white">Request Processed</h3>
            <div class="text-3xl font-black mt-2 text-white"><?php echo formatCurrency($statusDetails['amount']); ?></div>
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

        document.getElementById('tabSingle').classList.toggle('bg-white', !isBulk);
        document.getElementById('tabSingle').classList.toggle('shadow-md', !isBulk);
        document.getElementById('tabSingle').classList.toggle('text-billpay-green', !isBulk);
        document.getElementById('tabSingle').classList.toggle('text-gray-400', isBulk);

        document.getElementById('tabBulk').classList.toggle('bg-white', isBulk);
        document.getElementById('tabBulk').classList.toggle('shadow-md', isBulk);
        document.getElementById('tabBulk').classList.toggle('text-billpay-green', isBulk);
        document.getElementById('tabBulk').classList.toggle('text-gray-400', !isBulk);
    }

    function setNetwork(name) {
        document.getElementById('networkInput').value = name;
        document.querySelectorAll('.network-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-white', 'shadow-md', 'text-gray-900');
            btn.classList.add('border-transparent', 'bg-gray-50', 'text-gray-400');
        });
        const activeBtn = document.getElementById('net_' + name);
        activeBtn.classList.add('border-billpay-green', 'bg-white', 'shadow-md', 'text-gray-900');
        activeBtn.classList.remove('border-transparent', 'bg-gray-50', 'text-gray-400');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
