<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Electricity';

$electricProviders = $settings['electricProviders'] ?? [];
if (is_string($electricProviders)) $electricProviders = json_decode($electricProviders, true) ?: [];

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $providerId = $_POST['providerId'];
    $meterNumber = sanitize($_POST['meterNumber']);
    $amount = (float)$_POST['amount'];
    $meterType = $_POST['meterType'];

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } elseif (!checkDailyLimit($pdo, $currentUser['id'], $meterNumber, $settings['maxDailyTxPerId'])) {
        $error = "Daily transaction limit reached for $meterNumber";
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');
            logTransaction($pdo, $currentUser['id'], 'Electricity', $amount, 'successful', "Electricity Payment ($providerId) for Meter: $meterNumber ($meterType)", $meterNumber, $providerId);

            // Receipt Email
            $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your electricity payment was successful.<br><br>Provider: $providerId<br>Meter: $meterNumber ($meterType)<br>Amount: " . formatCurrency($amount);
            sendMail($pdo, $currentUser['email'], "Electricity Receipt", $receiptMsg);

            $pdo->commit();
            $success = true;
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
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Electricity</h1>
    </div>
    <div class="p-4 space-y-6">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Payment Successful!</div><?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Disco Provider</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php foreach ($electricProviders as $p): ?>
                    <button type="button" onclick="setProvider('<?php echo $p['id']; ?>')" id="prov_<?php echo $p['id']; ?>" class="prov-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 <?php echo !$p['enabled'] ? 'opacity-30 pointer-events-none' : ''; ?>">
                        <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($p['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $p['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="providerId" id="providerInput" required>
            </div>

            <div class="flex bg-gray-100 p-1.5 rounded-2xl">
                <button type="button" onclick="setType('prepaid')" id="typePrepaid" class="type-btn flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-md text-billpay-green">Prepaid</button>
                <button type="button" onclick="setType('postpaid')" id="typePostpaid" class="type-btn flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400">Postpaid</button>
                <input type="hidden" name="meterType" id="meterTypeInput" value="prepaid">
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Meter Number</label>
                <input type="text" name="meterNumber" placeholder="Enter meter number" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" placeholder="Min ₦1000" min="1000" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Purchase Power</button>
        </form>
    </div>
</div>
<script>
    function setProvider(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        document.getElementById('prov_' + id).classList.add('border-billpay-green', 'bg-green-50');
    }
    function setType(type) {
        document.getElementById('meterTypeInput').value = type;
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'shadow-md', 'text-billpay-green');
            btn.classList.add('text-gray-400');
        });
        const active = type === 'prepaid' ? document.getElementById('typePrepaid') : document.getElementById('typePostpaid');
        active.classList.add('bg-white', 'shadow-md', 'text-billpay-green');
        active.classList.remove('text-gray-400');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
