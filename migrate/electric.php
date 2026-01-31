<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Electricity';

$error = '';
$success = false;
$token = '';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'verify') {
        $serviceId = sanitize($_GET['serviceId']);
        $meter = sanitize($_GET['meter']);
        $type = sanitize($_GET['type']);
        echo json_encode(vtpassVerifyMerchant($pdo, $serviceId, $meter, $type));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $serviceId = $_POST['serviceId'];
    $meterNumber = sanitize($_POST['meterNumber']);
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; // prepaid/postpaid

    // Fetch dynamic discount/profit from utility_packages if available
    $stmt = $pdo->prepare("SELECT * FROM utility_packages WHERE category = 'electric' AND service_id = ? AND (package_id = ? OR package_id = 'electricity') LIMIT 1");
    $stmt->execute([$serviceId, $type]);
    $pkg = $stmt->fetch();

    $apiDisc = (float)($pkg['api_discount'] ?? 0);
    $userDisc = (float)($pkg['user_discount'] ?? 0);

    $ls = $settings['loginSecuritySettings'] ?? [];
    $isPinForced = !empty($ls['pin']['forced']);
    $userPinEnabled = !empty($currentUser['fundPasswordVtuEnabled']);

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif (($isPinForced || $userPinEnabled) && !verifyFundPassword($pdo, $currentUser['id'], $_POST['fund_password'] ?? '')) {
        $error = 'Invalid Security PIN';
    } elseif ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } elseif (!checkDailyLimit($pdo, $currentUser['id'], $meterNumber, $settings['maxDailyTxPerId'] ?? 50)) {
        $error = "Daily transaction limit reached for $meterNumber";
    } else {
            $globalChargePct = getApiCharge($pdo, 'electric');
            $chargedAmount = ($amount * (1 - ($userDisc / 100))) * (1 + ($globalChargePct / 100));

        $pdo->beginTransaction();
        try {
                updateWallet($pdo, $currentUser['id'], $chargedAmount, 'debit');

            $gateway = $pkg['provider'] ?: 'vtpass';
            if ($gateway === 'vtpass') {
                $res = callVtpass($pdo, $serviceId, [
                    'billersCode' => $meterNumber,
                    'variation_code' => $type,
                    'amount' => $amount,
                    'phone' => $currentUser['phone']
                ]);
                $isSuccess = isset($res['code']) && $res['code'] === '000';
                $errMsg = $res['response_description'] ?? 'API Error';
                $token = $res['mainToken'] ?? $res['token'] ?? ($res['purchased_code'] ?? '');
            } else {
                $isSuccess = false;
                $errMsg = "Gateway $gateway not implemented for Electric";
            }

            if ($isSuccess) {
                $apiCost = $amount * (1 - ($apiDisc / 100));
                $profitVal = $chargedAmount - $apiCost;

                logTransaction($pdo, $currentUser['id'], 'Electricity', $chargedAmount, 'successful', "Electric ($serviceId $type) for $meterNumber", $meterNumber, $serviceId, $token, $apiCost, $profitVal);
                sendMail($pdo, $currentUser['email'], "Electricity Receipt", "Successful recharge for $meterNumber. Token: $token");
                claimDailyRewardIfEligible($pdo, $currentUser['id']);
                $success = true;
            } else {
                updateWallet($pdo, $currentUser['id'], $chargedAmount, 'credit');
                logTransaction($pdo, $currentUser['id'], 'Electricity', $chargedAmount, 'failed', "Electric failed: $errMsg", $meterNumber, $serviceId);
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
        <form method="POST" id="purchaseForm" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Distribution Company</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php
                    $discos = [
                        'ikeja-electric' => 'IKEDC', 'eko-electric' => 'EKEDC', 'kano-electric' => 'KEDCO',
                        'portharcourt-electric' => 'PHED', 'jos-electric' => 'JED', 'ibadan-electric' => 'IBEDC',
                        'kaduna-electric' => 'KAEDCO', 'abuja-electric' => 'AEDC', 'enugu-electric' => 'EEDC',
                        'benin-electric' => 'BEDC', 'yola-electric' => 'YEDC', 'aba-electric' => 'APEDC'
                    ];
                    foreach ($discos as $id => $name):
                    ?>
                    <button type="button" onclick="setService('<?php echo $id; ?>')" id="svc_<?php echo $id; ?>" class="svc-btn flex flex-col items-center gap-2 p-3 rounded-2xl border-2 transition-all border-transparent bg-gray-50 hover:bg-gray-100">
                        <div class="w-12 h-12 rounded-2xl bg-orange-500 flex items-center justify-center text-white text-xs font-black shadow-sm group-hover:scale-110 transition-transform"><?php echo substr($name, 0, 2); ?></div>
                        <span class="text-[9px] font-black uppercase text-gray-800 tracking-tighter"><?php echo $name; ?></span>
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
                <input type="text" name="meterNumber" id="meterNumber" inputmode="numeric" pattern="[0-9]*" placeholder="Enter meter number" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
                <div id="meterInfo" class="mt-2 ml-1"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                    <input type="number" name="amount" inputmode="numeric" pattern="[0-9]*" placeholder="Enter amount" min="500" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Security PIN</label>
                    <input type="password" name="fund_password" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="••••••" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" <?php echo ($isPinForced || $userPinEnabled) ? 'required' : ''; ?>>
                </div>
            </div>

            <button type="submit" id="submitBtn" disabled class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase flex items-center justify-center gap-3 disabled:opacity-40 disabled:cursor-not-allowed">
                <span id="btnText">Recharge Electricity</span>
                <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
    function setService(id) {
        document.getElementById('serviceInput').value = id;
        document.querySelectorAll('.svc-btn').forEach(btn => btn.classList.remove('border-gray-900', 'bg-billpay-green/10', 'ring-2', 'ring-billpay-green/20'));
        const active = document.getElementById('svc_' + id);
        if (active) active.classList.add('border-gray-900', 'bg-billpay-green/10', 'ring-2', 'ring-billpay-green/20');
        verifyMeter();
    }
    function setType(val) {
        document.getElementById('typeInput').value = val;
        document.getElementById('typePrepaid').className = val === 'prepaid' ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase text-gray-400';
        document.getElementById('typePostpaid').className = val === 'postpaid' ? 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase bg-white shadow-md text-billpay-green' : 'flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase text-gray-400';
        verifyMeter();
    }

    let verifyTimeout;
    async function verifyMeter() {
        const meter = document.getElementById('meterNumber').value;
        const provider = document.getElementById('serviceInput').value;
        const type = document.getElementById('typeInput').value;
        const infoDiv = document.getElementById('meterInfo');
        const submitBtn = document.getElementById('submitBtn');

        if (meter.length >= 8 && provider) {
            submitBtn.disabled = true;
            infoDiv.innerHTML = '<div class="text-xs font-black text-amber-500 uppercase animate-pulse flex items-center gap-2"><div class="w-2 h-2 bg-amber-500 rounded-full animate-bounce"></div> Verifying Meter Security...</div>';

            clearTimeout(verifyTimeout);
            verifyTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`?ajax=verify&serviceId=${provider}&meter=${meter}&type=${type}`);
                    const data = await res.json();
                    if (data.code === '000' && data.content && data.content.Customer_Name) {
                        infoDiv.innerHTML = `
                            <div class="p-3 bg-green-50 rounded-xl border border-green-100 animate-fade-in">
                                <div class="text-[11px] font-black text-green-600 uppercase">Customer Verified</div>
                                <div class="text-sm font-black text-gray-900 mt-1">${data.content.Customer_Name}</div>
                                <div class="text-[9px] font-bold text-gray-400 uppercase mt-0.5">${data.content.Address || 'Address Confirmed'}</div>
                            </div>
                        `;
                        submitBtn.disabled = false;
                    } else {
                        infoDiv.innerHTML = `<div class="p-3 bg-red-50 rounded-xl border border-red-100 text-[11px] font-black text-red-500 uppercase animate-shake">Verification Failed: ${data.response_description || 'Invalid Meter Number'}</div>`;
                        submitBtn.disabled = true;
                    }
                } catch (e) {
                    infoDiv.innerHTML = '<div class="text-[10px] font-black text-red-400 uppercase">Connection failed. Retrying...</div>';
                    submitBtn.disabled = true;
                }
            }, 500); // Debounce to prevent API spam
        } else {
            infoDiv.innerHTML = '';
            submitBtn.disabled = true;
        }
    }

    document.getElementById('meterNumber').addEventListener('input', verifyMeter);

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
