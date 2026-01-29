<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Cable TV';

$cableProviders = [
    ['id' => 'dstv', 'name' => 'DSTV'],
    ['id' => 'gotv', 'name' => 'GOTV'],
    ['id' => 'startimes', 'name' => 'Startimes'],
    ['id' => 'showmax', 'name' => 'Showmax']
];

$error = '';
$success = false;

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'verify') {
        $serviceId = sanitize($_GET['serviceId']);
        $iuc = sanitize($_GET['iuc']);
        echo json_encode(vtpassVerifyMerchant($pdo, $serviceId, $iuc));
        exit;
    }
    if ($_GET['ajax'] === 'get_packages') {
        $provider = sanitize($_GET['provider']);
        $stmt = $pdo->prepare("SELECT package_id, name, user_price, user_discount FROM utility_packages WHERE category = 'cable' AND service_id = ? AND enabled = 1");
        $stmt->execute([$provider]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $providerId = $_POST['providerId'];
    $variationCode = $_POST['variationCode'];
    $iucNumber = sanitize($_POST['iucNumber']);

    // Fetch details from DB to prevent tampering
    $stmt = $pdo->prepare("SELECT * FROM utility_packages WHERE category = 'cable' AND service_id = ? AND package_id = ?");
    $stmt->execute([$providerId, $variationCode]);
    $pkg = $stmt->fetch();

    if (!$pkg) {
        $error = 'Invalid package selected';
    } else {
        $globalChargePct = getApiCharge($pdo, 'cable');
        $amount = ((float)$pkg['user_price'] * (1 - ($pkg['user_discount'] / 100))) * (1 + ($globalChargePct / 100));
        $apiCost = (float)$pkg['api_price'] * (1 - ($pkg['api_discount'] / 100));

        if (isKycRejected($currentUser)) {
            $error = 'Account restricted. Please update your KYC.';
        } elseif ($currentUser['walletBalance'] < $amount) {
            $error = 'Insufficient balance';
        } elseif (!checkDailyLimit($pdo, $currentUser['id'], $iucNumber, $settings['maxDailyTxPerId'] ?? 50)) {
            $error = "Daily transaction limit reached for $iucNumber";
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $amount, 'debit');

                $gateway = $pkg['provider'] ?: 'vtpass';
                if ($gateway === 'vtpass') {
                    $res = callVtpass($pdo, $providerId, [
                        'billersCode' => $iucNumber,
                        'variation_code' => $variationCode,
                        'amount' => $pkg['api_price'],
                        'phone' => $currentUser['phone']
                    ]);
                    $isSuccess = isset($res['code']) && $res['code'] === '000';
                    $errMsg = $res['response_description'] ?? 'API Error';
                } else {
                    // Placeholder for other gateways
                    $isSuccess = false;
                    $errMsg = "Gateway $gateway not implemented for Cable";
                }

                if ($isSuccess) {
                    $profitVal = $amount - $apiCost;
                    logTransaction($pdo, $currentUser['id'], 'Cable TV', $amount, 'successful', "Cable Subscription ($providerId) for $iucNumber", $iucNumber, $providerId, null, $apiCost, $profitVal);
                    // Receipt Email
                    $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your cable subscription request was processed.<br><br>Provider: $providerId<br>IUC: $iucNumber<br>Amount: " . formatCurrency($amount) . "<br>Status: Successful";
                    sendMail($pdo, $currentUser['email'], "Cable TV Receipt", $receiptMsg, 'successful');
                    claimDailyRewardIfEligible($pdo, $currentUser['id']);
                    $success = true;
                } else {
                    // Refund
                    updateWallet($pdo, $currentUser['id'], $amount, 'credit');
                    logTransaction($pdo, $currentUser['id'], 'Cable TV', $amount, 'failed', "Cable failed: $errMsg", $iucNumber, $providerId);
                    $error = 'Transaction failed: ' . $errMsg;
                    sendMail($pdo, $currentUser['email'], "Cable TV Failed", "Your cable subscription for $iucNumber failed and has been refunded.", 'failed');
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
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Cable TV</h1>
    </div>
    <div class="p-4 space-y-6">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Subscription Successful!</div><?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Provider</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php foreach ($cableProviders as $p): ?>
                    <button type="button" onclick="setProvider('<?php echo $p['id']; ?>')" id="prov_<?php echo $p['id']; ?>" class="prov-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50">
                        <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($p['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $p['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="providerId" id="providerInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">IUC / Smartcard Number</label>
                <input type="text" name="iucNumber" placeholder="Enter number" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
                <div id="iucInfo" class="mt-2 ml-1"></div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Package</label>
                <select name="variationCode" id="variationSelect" class="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold text-sm" required>
                    <option value="">Select Package</option>
                </select>
                <input type="hidden" name="amount" id="amountInput">
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Subscribe Now</button>
        </form>
    </div>
</div>
<script>
    async function setProvider(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        const active = document.getElementById('prov_' + id);
        if (active) active.classList.add('border-billpay-green', 'bg-green-50');

        const select = document.getElementById('variationSelect');
        select.innerHTML = '<option value="">Loading Packages...</option>';

        try {
            const res = await fetch('?ajax=get_packages&provider=' + id);
            const pkgs = await res.json();
            select.innerHTML = '<option value="">Select Package</option>';
            pkgs.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.package_id;
                // Apply discount if set
                const price = parseFloat(p.user_price) * (1 - (parseFloat(p.user_discount) / 100));
                opt.text = p.name + ' - ₦' + price.toLocaleString();
                opt.dataset.amount = price;
                select.appendChild(opt);
            });
            if (pkgs.length === 0) select.innerHTML = '<option value="">No packages found. Contact Admin.</option>';
        } catch (e) {
            select.innerHTML = '<option value="">Error loading packages</option>';
        }

        // Trigger verification if IUC is already entered
        verifyIUC();
    }

    async function verifyIUC() {
        const iuc = document.querySelector('input[name="iucNumber"]').value;
        const provider = document.getElementById('providerInput').value;
        const infoDiv = document.getElementById('iucInfo');

        if (iuc.length >= 8 && provider) {
            infoDiv.innerHTML = '<div class="text-[9px] font-black text-amber-500 uppercase animate-pulse">Verifying IUC...</div>';
            try {
                const res = await fetch(`?ajax=verify&serviceId=${provider}&iuc=${iuc}`);
                const data = await res.json();
                if (data.code === '000' && data.content && data.content.Customer_Name) {
                    infoDiv.innerHTML = `<div class="text-[9px] font-black text-green-500 uppercase">Verified: ${data.content.Customer_Name}</div>`;
                } else {
                    infoDiv.innerHTML = `<div class="text-[9px] font-black text-red-500 uppercase">Verification Failed: ${data.response_description || 'Invalid IUC'}</div>`;
                }
            } catch (e) {
                infoDiv.innerHTML = '';
            }
        } else {
            infoDiv.innerHTML = '';
        }
    }

    document.querySelector('input[name="iucNumber"]').addEventListener('input', verifyIUC);

    document.getElementById('variationSelect').addEventListener('change', function(e) {
        const opt = e.target.options[e.target.selectedIndex];
        document.getElementById('amountInput').value = opt.dataset.amount || 0;
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
