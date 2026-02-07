<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Add Money';

$error = sanitize($_GET['error'] ?? '');
$successMsg = sanitize($_GET['success'] ?? '');
$success = !empty($successMsg);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_bank') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $pdo->beginTransaction();
    try {
        // 1. Create/Get Customer
        $cusRes = createPaystackCustomer($pdo, $currentUser);
        if (!$cusRes['status']) throw new Exception($cusRes['message'] ?? 'Failed to create customer');
        $customerCode = $cusRes['data']['customer_code'];

        // 2. Create Dedicated Account
        $accRes = createPaystackDedicatedAccount($pdo, $customerCode);
        if (!$accRes['status']) throw new Exception($accRes['message'] ?? 'Failed to create account');
        $accData = $accRes['data'];

        $stmt = $pdo->prepare("INSERT INTO virtual_accounts (userId, bankName, accountNumber, accountName, customerCode) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$currentUser['id'], $accData['bank']['name'], $accData['account_number'], $accData['account_name'], $customerCode]);

        $pdo->commit();
        redirect('/add-money?success=Virtual account generated');
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $amount = (float)$_POST['amount'];
    $method = $_POST['method'];

    if ($amount < $settings['minDepositAmount']) {
        $error = 'Minimum deposit is ' . formatCurrency($settings['minDepositAmount']);
    } else {
        $id = 'DEP-' . bin2hex(random_bytes(4));
        $charge = ($method === 'manual') ? $settings['manualDepositCharge'] : ($amount * $settings['paystackChargePercent'] / 100);

        $stmt = $pdo->prepare("INSERT INTO deposit_requests (id, userId, amount, method, status, charge) VALUES (?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([$id, $currentUser['id'], $amount, $method, $charge]);

        // Notify user
        sendMail($pdo, $currentUser['email'], "Deposit Request Received", "Hi {$currentUser['fullName']},<br><br>We have received your deposit request of " . formatCurrency($amount) . " via " . strtoupper($method) . ".<br><br>Reference: $id<br>Status: Pending Approval.");

        $success = true;
        if ($method === 'paystack') {
            $ref = $id;
            $paystackKey = $settings['paystackPublicKey'];
            $userEmail = $currentUser['email'];
            $payAmount = $amount * 100; // In kobo
            $script = "
                <script src='https://js.paystack.co/v1/inline.js'></script>
                <script>
                    window.onload = function() {
                        const handler = PaystackPop.setup({
                            key: '$paystackKey',
                            email: '$userEmail',
                            amount: $payAmount,
                            ref: '$ref',
                            onClose: function() {
                                window.location.href = '/add-money?error=Payment cancelled';
                            },
                            callback: function(response) {
                                window.location.href = '/verify-payment?reference=' + response.reference;
                            }
                        });
                        handler.openIframe();
                    };
                </script>
            ";
        }
    }
}

$vAccStmt = $pdo->prepare("SELECT * FROM virtual_accounts WHERE userId = ?");
$vAccStmt->execute([$currentUser['id']]);
$vAcc = $vAccStmt->fetch();

require_once __DIR__ . '/includes/header.php';
if (isset($script)) echo $script;
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Fund Wallet</h1>
    </div>

    <div class="p-4 space-y-6 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($successMsg): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $successMsg; ?></div><?php endif; ?>
        <?php if ($success && !$successMsg): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Deposit Request Submitted!</div><?php endif; ?>

        <div class="bg-billpay-green p-8 rounded-[40px] text-white shadow-lg text-center">
            <div class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-2">Current Balance</div>
            <div class="text-4xl font-black"><?php echo formatCurrency($currentUser['walletBalance']); ?></div>
        </div>

        <form method="POST" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="deposit">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount to Add (₦)</label>
                <input type="number" name="amount" placeholder="Min ₦<?php echo $settings['minDepositAmount']; ?>" min="<?php echo $settings['minDepositAmount']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Funding Method</label>
                <div class="grid grid-cols-2 gap-4">
                    <button type="button" onclick="setMethod('manual')" id="methManual" class="meth-btn p-4 rounded-2xl border-2 transition-all border-billpay-green bg-green-50 flex flex-col items-center gap-2">
                        <i data-lucide="landmark" class="w-6 h-6 text-billpay-green"></i>
                        <span class="text-[9px] font-black uppercase text-gray-800">Transfer</span>
                    </button>
                    <button type="button" onclick="setMethod('paystack')" id="methPaystack" class="meth-btn p-4 rounded-2xl border-2 transition-all border-transparent bg-gray-50 flex flex-col items-center gap-2 opacity-50">
                        <i data-lucide="credit-card" class="w-6 h-6 text-gray-400"></i>
                        <span class="text-[9px] font-black uppercase text-gray-400">Card</span>
                    </button>
                </div>
                <input type="hidden" name="method" id="methodInput" value="manual">
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Initiate Deposit</button>
        </form>

        <div class="bg-indigo-50 p-6 rounded-[32px] border border-indigo-100 space-y-4">
            <h4 class="text-[10px] font-black text-indigo-800 uppercase tracking-widest flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4"></i> Your Reserved Bank Account</h4>
            <?php if ($vAcc): ?>
                <div class="space-y-3">
                    <div class="p-4 bg-white rounded-2xl border border-indigo-100">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[8px] font-black text-gray-400 uppercase">Bank Name</span>
                            <span class="text-xs font-black text-indigo-900"><?php echo $vAcc['bankName']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[8px] font-black text-gray-400 uppercase">Account Number</span>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-black text-indigo-900"><?php echo $vAcc['accountNumber']; ?></span>
                                <button onclick="navigator.clipboard.writeText('<?php echo $vAcc['accountNumber']; ?>')" class="text-indigo-400 hover:text-indigo-600"><i data-lucide="copy" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-[8px] font-black text-gray-400 uppercase">Account Name</span>
                            <span class="text-[10px] font-black text-indigo-900 truncate"><?php echo $vAcc['accountName']; ?></span>
                        </div>
                    </div>
                    <p class="text-[8px] font-bold text-indigo-400 uppercase text-center">Funds sent to this account are credited instantly to your wallet.</p>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-[10px] font-bold text-gray-500 uppercase mb-4">Generate a personalized bank account for instant funding.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="generate_bank">
                        <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase shadow-lg active:scale-95 transition-all">Generate My Static Account</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-blue-50 p-6 rounded-[32px] border border-blue-100 space-y-4">
            <h4 class="text-[10px] font-black text-blue-800 uppercase tracking-widest flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i> Official Bank Details</h4>
            <div class="space-y-2">
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Bank</span><span class="text-blue-900"><?php echo $settings['bankName']; ?></span></div>
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Acc Num</span><span class="text-blue-900"><?php echo $settings['bankAccount']; ?></span></div>
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Name</span><span class="text-blue-900"><?php echo $settings['accountName']; ?></span></div>
            </div>
            <p class="text-[8px] font-bold text-blue-400 uppercase text-center">Manual transfers require confirmation from admin.</p>
        </div>
    </div>
</div>
<script>
    function setMethod(method) {
        document.getElementById('methodInput').value = method;
        document.querySelectorAll('.meth-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-green-50', 'opacity-100');
            btn.classList.add('border-transparent', 'bg-gray-50', 'opacity-50');
            const icon = btn.querySelector('.lucide') || btn.querySelector('i');
            if (icon) {
                icon.classList.remove('text-billpay-green');
                icon.classList.add('text-gray-400');
            }
            const span = btn.querySelector('span');
            if (span) {
                span.classList.remove('text-gray-800');
                span.classList.add('text-gray-400');
            }
        });
        const activeId = 'meth' + method.charAt(0).toUpperCase() + method.slice(1);
        const active = document.getElementById(activeId);
        if (active) {
            active.classList.add('border-billpay-green', 'bg-green-50', 'opacity-100');
            active.classList.remove('border-transparent', 'bg-gray-50', 'opacity-50');
            const icon = active.querySelector('.lucide') || active.querySelector('i');
            if (icon) {
                icon.classList.remove('text-gray-400');
                icon.classList.add('text-billpay-green');
            }
            const span = active.querySelector('span');
            if (span) {
                span.classList.remove('text-gray-400');
                span.classList.add('text-gray-800');
            }
        }
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
