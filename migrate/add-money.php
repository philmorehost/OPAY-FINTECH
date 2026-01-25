<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Add Money';

$error = '';
$success = false;

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
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Fund Wallet</h1>
    </div>

    <div class="p-4 space-y-6 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Deposit Request Submitted!</div><?php endif; ?>

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

        <div class="bg-blue-50 p-6 rounded-[32px] border border-blue-100 space-y-4">
            <h4 class="text-[10px] font-black text-blue-800 uppercase tracking-widest flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i> Bank Details</h4>
            <div class="space-y-2">
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Bank</span><span class="text-blue-900"><?php echo $settings['bankName']; ?></span></div>
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Acc Num</span><span class="text-blue-900"><?php echo $settings['bankAccount']; ?></span></div>
                <div class="flex justify-between text-xs font-bold"><span class="text-blue-400 uppercase">Name</span><span class="text-blue-900"><?php echo $settings['accountName']; ?></span></div>
            </div>
        </div>
    </div>
</div>
<script>
    function setMethod(method) {
        document.getElementById('methodInput').value = method;
        document.querySelectorAll('.meth-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-green-50', 'opacity-100');
            btn.classList.add('border-transparent', 'bg-gray-50', 'opacity-50');
            btn.querySelector('i').classList.replace('text-billpay-green', 'text-gray-400');
            btn.querySelector('span').classList.replace('text-gray-800', 'text-gray-400');
        });
        const active = document.getElementById('meth' + method.charAt(0).toUpperCase() + method.slice(1));
        active.classList.add('border-billpay-green', 'bg-green-50', 'opacity-100');
        active.classList.remove('border-transparent', 'bg-gray-50', 'opacity-50');
        active.querySelector('i').classList.replace('text-gray-400', 'text-billpay-green');
        active.querySelector('span').classList.replace('text-gray-400', 'text-gray-800');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
