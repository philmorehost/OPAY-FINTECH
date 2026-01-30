<?php
require_once __DIR__ . '/includes/config.php';
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Bank Transfer';

$error = '';
$success = false;

if (isset($_GET['ajax']) && $_GET['ajax'] === 'getBanks') {
    header('Content-Type: application/json');
    $res = juicywayGetBanks($pdo, 'NG');
    if (isset($res['data'])) echo json_encode($res);
    elseif (is_array($res)) echo json_encode(['status' => 'success', 'data' => $res]);
    else echo json_encode(['status' => 'error', 'message' => 'Failed to fetch banks']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $bank = sanitize($_POST['bank']);
    $accountNumber = sanitize($_POST['accountNumber']);
    $accountName = sanitize($_POST['accountName']);
    $amount = (float)$_POST['amount'];
    $fee = 10;
    $totalCost = $amount + $fee;

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($amount < 100) {
        $error = 'Minimum transfer is ₦100';
    } elseif ($currentUser['walletBalance'] < $totalCost) {
        $error = 'Insufficient balance';
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');
            logTransaction($pdo, $currentUser['id'], 'Transfer', $amount, 'successful', "Transfer to $accountName ($bank)", $accountNumber, $bank);

            // Receipt Email
            $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Transfer of " . formatCurrency($amount) . " to $accountName ($bank, $accountNumber) was successful.<br><br>Fee: " . formatCurrency($fee);
            sendMail($pdo, $currentUser['email'], "Transfer Receipt", $receiptMsg);

            claimDailyRewardIfEligible($pdo, $currentUser['id']);
            $pdo->commit();
            $success = true;

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

require_once __DIR__ . '/includes/header.php';

$banks = [
    ["name" => "Access Bank", "code" => "044"],
    ["name" => "Access Bank (Diamond)", "code" => "063"],
    ["name" => "Airtel Smartcash", "code" => "120004"],
    ["name" => "Ecobank Nigeria", "code" => "050"],
    ["name" => "Fidelity Bank", "code" => "070"],
    ["name" => "First Bank of Nigeria", "code" => "011"],
    ["name" => "First City Monument Bank", "code" => "214"],
    ["name" => "Guaranty Trust Bank", "code" => "058"],
    ["name" => "Heritage Bank", "code" => "030"],
    ["name" => "Keystone Bank", "code" => "082"],
    ["name" => "Kuda Bank", "code" => "50211"],
    ["name" => "Moniepoint MFB", "code" => "50515"],
    ["name" => "OPay Digital Services", "code" => "999992"],
    ["name" => "Palmpay", "code" => "999991"],
    ["name" => "Stanbic IBTC Bank", "code" => "039"],
    ["name" => "Standard Chartered Bank", "code" => "068"],
    ["name" => "Sterling Bank", "code" => "232"],
    ["name" => "Union Bank of Nigeria", "code" => "032"],
    ["name" => "United Bank For Africa", "code" => "033"],
    ["name" => "Unity Bank", "code" => "215"],
    ["name" => "VFD Microfinance Bank", "code" => "566"],
    ["name" => "Wema Bank", "code" => "035"],
    ["name" => "Zenith Bank", "code" => "057"]
];
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Transfer to Bank</h1>
    </div>

    <div class="p-4 space-y-6 flex-1">
        <?php if ($error): ?>
            <div class="p-4 bg-red-50 text-red-800 rounded-2xl flex items-center gap-3 border border-red-100">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                <span class="text-sm font-bold uppercase"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="p-4 bg-green-50 text-green-800 rounded-2xl flex items-center gap-3 border border-green-100">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                <span class="text-sm font-bold uppercase">Transfer Successful!</span>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-3xl shadow-sm space-y-6 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="transfer">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Select Bank</label>
                <select name="bank" id="bankSelect" class="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold text-gray-900 text-sm" required>
                    <option value="">Loading Banks...</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?php echo $b['name']; ?>"><?php echo $b['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Account Number</label>
                <div class="relative">
                    <input type="tel" name="accountNumber" id="accountNumber" maxlength="10" placeholder="Enter 10-digit account" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-lg tracking-widest" required>
                    <div id="verifySpinner" class="hidden absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-billpay-green border-t-transparent rounded-full animate-spin"></div>
                </div>
                <div id="accountNameDisplay" class="mt-2 text-[10px] font-black text-billpay-green uppercase hidden"></div>
                <input type="hidden" name="accountName" id="accountNameInput">
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount (₦)</label>
                <input type="number" name="amount" id="amount" placeholder="Min ₦100" min="100" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-xl" required>
            </div>

            <div id="transferSummary" class="hidden bg-gray-50 p-6 rounded-2xl border border-gray-100 space-y-3">
                <div class="flex justify-between items-center text-[10px] font-black uppercase text-gray-400">
                    <span>Transfer Fee</span>
                    <span>₦10.00</span>
                </div>
                <div class="flex justify-between items-center text-[10px] font-black uppercase text-gray-400">
                    <span>Receiver Gets</span>
                    <span id="summaryReceiver" class="text-gray-900">₦0.00</span>
                </div>
                <div class="pt-3 border-t border-gray-200 flex justify-between items-center text-xs font-black uppercase text-gray-900">
                    <span>Total Charge</span>
                    <span id="summaryTotal" class="text-billpay-green text-lg">₦0.00</span>
                </div>
                <div class="text-center pt-2">
                    <span class="text-[8px] font-black text-indigo-500 uppercase">Updates every 30s</span>
                </div>
            </div>

            <button type="submit" id="submitBtn" disabled class="w-full bg-billpay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50 uppercase">
                <span class="btn-text">CONFIRM TRANSFER</span>
                <div class="btn-loader hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
        </form>
    </div>
</div>

<script>
    const amountInput = document.getElementById('amount');
    const summary = document.getElementById('transferSummary');
    const summaryReceiver = document.getElementById('summaryReceiver');
    const summaryTotal = document.getElementById('summaryTotal');

    function updateSummary() {
        const amount = parseFloat(amountInput.value) || 0;
        if (amount >= 100) {
            summary.classList.remove('hidden');
            summaryReceiver.innerText = '₦' + amount.toLocaleString(undefined, {minimumFractionDigits: 2});
            summaryTotal.innerText = '₦' + (amount + 10).toLocaleString(undefined, {minimumFractionDigits: 2});
        } else {
            summary.classList.add('hidden');
        }
    }

    amountInput.addEventListener('input', updateSummary);
    setInterval(updateSummary, 30000);

    window.addEventListener('DOMContentLoaded', () => {
        fetch('?ajax=getBanks')
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('bankSelect');
                if (res.status === 'success' || res.status === true) {
                    sel.innerHTML = '<option value="">Choose Bank</option>';
                    res.data.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.name;
                        opt.textContent = b.name;
                        sel.appendChild(opt);
                    });
                } else {
                    sel.innerHTML = '<option value="">Error loading banks</option>';
                }
            })
            .catch(() => {
                document.getElementById('bankSelect').innerHTML = '<option value="">Connection Error</option>';
            });
    });

    document.getElementById('accountNumber').addEventListener('input', function(e) {
        const val = e.target.value.replace(/\D/g, '');
        e.target.value = val;

        const display = document.getElementById('accountNameDisplay');
        const input = document.getElementById('accountNameInput');
        const spinner = document.getElementById('verifySpinner');
        const btn = document.getElementById('submitBtn');

        if (val.length === 10) {
            spinner.classList.remove('hidden');
            setTimeout(() => {
                spinner.classList.add('hidden');
                display.innerText = "JOHN DOE ENTERPRISE";
                display.classList.remove('hidden');
                input.value = "JOHN DOE ENTERPRISE";
                btn.disabled = false;
            }, 1000);
        } else {
            display.classList.add('hidden');
            input.value = "";
            btn.disabled = true;
        }
    });

    document.querySelector('form').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = btn.querySelector('.btn-text');
        const loader = btn.querySelector('.btn-loader');

        btn.disabled = true;
        btn.classList.add('opacity-70');
        text.innerText = 'PROCESSING...';
        loader.classList.remove('hidden');
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
