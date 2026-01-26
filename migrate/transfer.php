<?php
require_once __DIR__ . '/includes/config.php';
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Bank Transfer';

$error = '';
$success = false;

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
    "Access Bank", "First Bank", "GTBank", "Kuda Bank", "Moniepoint", "Billpay Digital Bank", "United Bank for Africa", "Zenith Bank"
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
                <select name="bank" class="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold text-gray-900 text-sm" required>
                    <option value="">Choose Bank</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?php echo $b; ?>"><?php echo $b; ?></option>
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
                <input type="number" name="amount" placeholder="Min ₦100" min="100" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-xl" required>
            </div>

            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                <div class="flex justify-between items-center text-[10px] font-black uppercase text-gray-400">
                    <span>Transfer Fee</span>
                    <span>₦10.00</span>
                </div>
            </div>

            <button type="submit" id="submitBtn" disabled class="w-full bg-billpay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50 uppercase">CONFIRM TRANSFER</button>
        </form>
    </div>
</div>

<script>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
