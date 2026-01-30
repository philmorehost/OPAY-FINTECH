<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Betting';

$error = '';
$success = false;

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'verify') {
        $provider = sanitize($_GET['provider']);
        $customerId = sanitize($_GET['customerId']);
        echo json_encode(nellobyteBetting($pdo, 'Verify', ['Provider' => $provider, 'CustomerID' => $customerId]));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $providerId = $_POST['providerId'];
    $customerId = sanitize($_POST['customerId']);
    $amount = (float)$_POST['amount'];

    // Fetch discounts
    $stmt = $pdo->prepare("SELECT * FROM utility_packages WHERE category = 'betting' AND service_id = ?");
    $stmt->execute([$providerId]);
    $pkg = $stmt->fetch();

    $apiDisc = (float)($pkg['api_discount'] ?? 0);
    $userDisc = (float)($pkg['user_discount'] ?? 0);

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } elseif (!checkDailyLimit($pdo, $currentUser['id'], $customerId, $settings['maxDailyTxPerId'] ?? 50)) {
        $error = "Daily transaction limit reached for $customerId";
    } else {
        $pdo->beginTransaction();
        try {
            $globalChargePct = getApiCharge($pdo, 'betting');
            $chargedAmount = ($amount * (1 - ($userDisc / 100))) * (1 + ($globalChargePct / 100));
            $apiCost = $amount * (1 - ($apiDisc / 100));

            if ($currentUser['walletBalance'] < $chargedAmount) {
                throw new Exception("Insufficient balance. Total cost: " . formatCurrency($chargedAmount));
            }

            updateWallet($pdo, $currentUser['id'], $chargedAmount, 'debit');

            $requestId = date('YmdHi') . bin2hex(random_bytes(4));
            $res = nellobyteBetting($pdo, 'Fund', [
                'Provider' => $providerId,
                'CustomerID' => $customerId,
                'Amount' => $amount,
                'RequestID' => $requestId
            ]);

            $isSuccess = is_array($res) && isset($res['status']) && ($res['status'] === 'ORDER_RECEIVED' || $res['status'] === 'ORDER_COMPLETED');
            if (!$isSuccess && is_string($res) && (strpos($res, 'ORDER_RECEIVED') !== false || strpos($res, 'ORDER_COMPLETED') !== false)) $isSuccess = true;

            if ($isSuccess) {
                $profitVal = $chargedAmount - $apiCost;
                logTransaction($pdo, $currentUser['id'], 'Betting', $chargedAmount, 'successful', "Betting Fund ($providerId) for $customerId", $customerId, 'Nellobyte', null, $apiCost, $profitVal);
                sendMail($pdo, $currentUser['email'], "Betting Funding Receipt", "Successful funding for $customerId. Amount: " . formatCurrency($chargedAmount));
                claimDailyRewardIfEligible($pdo, $currentUser['id']);
                $success = true;
            } else {
                updateWallet($pdo, $currentUser['id'], $chargedAmount, 'credit');
                $errMsg = is_array($res) ? ($res['status'] ?? $res['msg'] ?? 'API Error') : $res;
                logTransaction($pdo, $currentUser['id'], 'Betting', $chargedAmount, 'failed', "Betting failed: $errMsg", $customerId, 'Nellobyte');
                $error = 'Transaction failed: ' . $errMsg;
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
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Betting</h1>
    </div>
    <div class="p-4 space-y-6">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Funding Successful!</div><?php endif; ?>

        <form method="POST" id="purchaseForm" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Provider</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM utility_packages WHERE category = 'betting' AND enabled = 1 ORDER BY name ASC");
                    while ($p = $stmt->fetch()):
                    ?>
                    <button type="button" onclick="setProvider('<?php echo $p['package_id']; ?>')" id="prov_<?php echo $p['package_id']; ?>" class="prov-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50">
                        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($p['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $p['name']; ?></span>
                    </button>
                    <?php endwhile; ?>
                </div>
                <input type="hidden" name="providerId" id="providerInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Customer ID</label>
                <input type="text" name="customerId" id="customerId" placeholder="Enter betting ID" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
                <div id="verifyInfo" class="mt-2 ml-1"></div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" placeholder="Min ₦100" min="100" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase flex items-center justify-center gap-3">
                <span id="btnText">Fund Account</span>
                <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
        </form>
    </div>
</div>
<script>
    function setProvider(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        const active = document.getElementById('prov_' + id);
        if (active) active.classList.add('border-billpay-green', 'bg-green-50');
        verifyID();
    }

    async function verifyID() {
        const prov = document.getElementById('providerInput').value;
        const cid = document.getElementById('customerId').value;
        const info = document.getElementById('verifyInfo');
        if (prov && cid.length >= 5) {
            info.innerHTML = '<div class="text-[9px] font-black text-amber-500 uppercase animate-pulse">Verifying ID...</div>';
            try {
                const res = await fetch(`?ajax=verify&provider=${prov}&customerId=${cid}`);
                const data = await res.json();
                if (data.status === 'SUCCESS' || (data.content && data.content.Customer_Name)) {
                    const name = data.content ? data.content.Customer_Name : (data.name || 'Verified Account');
                    info.innerHTML = `<div class="text-[9px] font-black text-green-500 uppercase">Verified: ${name}</div>`;
                } else {
                    info.innerHTML = `<div class="text-[9px] font-black text-red-500 uppercase">Verification Failed: ${data.msg || 'Invalid ID'}</div>`;
                }
            } catch (e) { info.innerHTML = ''; }
        } else { info.innerHTML = ''; }
    }
    document.getElementById('customerId').addEventListener('input', verifyID);

    document.getElementById('purchaseForm').addEventListener('submit', function() {
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
