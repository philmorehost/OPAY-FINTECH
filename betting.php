<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Betting';

$bettingProviders = $settings['bettingProviders'] ?? [];
if (is_string($bettingProviders)) $bettingProviders = json_decode($bettingProviders, true) ?: [];

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $providerId = $_POST['providerId'];
    $customerId = sanitize($_POST['customerId']);
    $amount = (float)$_POST['amount'];

    if ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');
            logTransaction($pdo, $currentUser['id'], 'Betting', $amount, 'successful', "Betting Wallet Fund ($providerId) for ID: $customerId", $customerId, $providerId);

            // Receipt Email
            $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your betting account funding was successful.<br><br>Provider: $providerId<br>Customer ID: $customerId<br>Amount: " . formatCurrency($amount);
            sendMail($pdo, $currentUser['email'], "Betting Funding Receipt", $receiptMsg);

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
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Betting</h1>
    </div>
    <div class="p-4 space-y-6">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Funding Successful!</div><?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Provider</label>
                <div class="grid grid-cols-4 gap-3">
                    <?php foreach ($bettingProviders as $p): ?>
                    <button type="button" onclick="setProvider('<?php echo $p['id']; ?>')" id="prov_<?php echo $p['id']; ?>" class="prov-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 <?php echo !$p['enabled'] ? 'opacity-30 pointer-events-none' : ''; ?>">
                        <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black"><?php echo substr($p['name'], 0, 2); ?></div>
                        <span class="text-[8px] font-black uppercase text-gray-800"><?php echo $p['name']; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="providerId" id="providerInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Customer ID</label>
                <input type="text" name="customerId" placeholder="Enter betting ID" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
                <input type="number" name="amount" placeholder="Min ₦100" min="100" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Fund Account</button>
        </form>
    </div>
</div>
<script>
    function setProvider(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        document.getElementById('prov_' + id).classList.add('border-billpay-green', 'bg-green-50');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
