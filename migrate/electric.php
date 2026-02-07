<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Electricity';

$electricProviders = $settings['electricProviders'] ?? [];
if (is_string($electricProviders)) $electricProviders = json_decode($electricProviders, true) ?: [];

$error = '';
$success = false;
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $serviceId = $_POST['serviceId'];
    $meterNumber = sanitize($_POST['meterNumber']);
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; // prepaid/postpaid

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } elseif (!checkDailyLimit($pdo, $currentUser['id'], $meterNumber, $settings['maxDailyTxPerId'] ?? 50)) {
        $error = "Daily transaction limit reached for $meterNumber";
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');

            $vtRes = callVtpass($pdo, $serviceId, [
                'billersCode' => $meterNumber,
                'variation_code' => $type,
                'amount' => $amount,
                'phone' => $currentUser['phone']
            ]);

            $isSuccess = isset($vtRes['code']) && $vtRes['code'] === '000';

            if ($isSuccess) {
                $token = $vtRes['mainToken'] ?? $vtRes['token'] ?? '';
                $profit = $amount * 0.01; // Placeholder 1% profit
                $apiAmount = $amount - $profit;
                logTransaction($pdo, $currentUser['id'], 'Electricity', $amount, 'successful', "Electric ($serviceId) for $meterNumber", $meterNumber, $serviceId, $token, $apiAmount, $profit);
                sendMail($pdo, $currentUser['email'], "Electricity Receipt", "Successful recharge for $meterNumber. Token: $token");
                claimDailyRewardIfEligible($pdo, $currentUser['id']);
                $success = true;
            } else {
                updateWallet($pdo, $currentUser['id'], $amount, 'credit');
                logTransaction($pdo, $currentUser['id'], 'Electricity', $amount, 'failed', "Electric failed: " . ($vtRes['response_description'] ?? 'API Error'), $meterNumber, $serviceId);
                $error = 'Transaction failed: ' . ($vtRes['response_description'] ?? 'Provider Error');
            }

            $pdo->commit();
            // Refresh balance
            $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $currentUser['walletBalance'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Internal Error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Electricity</h1>
    </div>
    <div class="p-4 space-y-6">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="p-8 bg-green-50 text-green-800 rounded-[40px] border border-green-100 text-center space-y-4">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto text-green-500 shadow-sm"><i data-lucide="zap" class="w-8 h-8"></i></div>
                <h3 class="text-xl font-black uppercase">Successful!</h3>
                <?php if ($token): ?>
                <div class="p-4 bg-white rounded-2xl font-mono text-lg font-black tracking-widest text-gray-900 shadow-inner"><?php echo $token; ?></div>
                <p class="text-[10px] font-bold text-gray-400 uppercase">Your recharge token</p>
                <?php endif; ?>
                <a href="/dashboard" class="block w-full py-4 bg-green-600 text-white rounded-2xl font-black uppercase text-xs">Return Home</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Distribution Company</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php foreach ($electricProviders as $p): ?>
                    <button type="button" onclick="setService('<?php echo $p['serviceId']; ?>')" id="svc_<?php echo $p['serviceId']; ?>" class="svc-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50">
                        <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($p['name'], 0, 1); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $p['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="serviceId" id="serviceInput" required>
            </div>

            <div class="flex bg-gray-100 p-1.5 rounded-2xl">
                <button type="button" onclick="setType('prepaid')" id="typePrepaid" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green">Prepaid</button>
                <button type="button" onclick="setType('postpaid')" id="typePostpaid" class="flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400">Postpaid</button>
                <input type="hidden" name="type" id="typeInput" value="prepaid">
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Meter Number</label>
                <input type="text" name="meterNumber" placeholder="Enter meter number" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" placeholder="Enter amount" min="500" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Recharge Electricity</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
    function setService(id) {
        document.getElementById('serviceInput').value = id;
        document.querySelectorAll('.svc-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        document.getElementById('svc_' + id).classList.add('border-billpay-green', 'bg-green-50');
    }
    function setType(val) {
        document.getElementById('typeInput').value = val;
        document.getElementById('typePrepaid').className = val === 'prepaid' ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase text-gray-400';
        document.getElementById('typePostpaid').className = val === 'postpaid' ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase text-gray-400';
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
