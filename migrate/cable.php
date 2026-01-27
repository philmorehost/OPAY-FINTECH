<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Cable TV';

$cableProviders = $settings['cableProviders'] ?? [];
if (is_string($cableProviders)) $cableProviders = json_decode($cableProviders, true) ?: [];

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF token validation failed'); }

    $providerId = $_POST['providerId'];
    $variationCode = $_POST['variationCode'];
    $iucNumber = sanitize($_POST['iucNumber']);
    $amount = (float)$_POST['amount'];

    if (isKycRejected($currentUser)) {
        $error = 'Account restricted. Please update your KYC.';
    } elseif ($currentUser['walletBalance'] < $amount) {
        $error = 'Insufficient balance';
    } elseif (!checkDailyLimit($pdo, $currentUser['id'], $iucNumber, $settings['maxDailyTxPerId'])) {
        $error = "Daily transaction limit reached for $iucNumber";
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');

            $vtRes = callVtpass($settings, $providerId, [
                'billersCode' => $iucNumber,
                'variation_code' => $variationCode,
                'amount' => $amount,
                'phone' => $currentUser['phone']
            ]);

            $isSuccess = isset($vtRes['code']) && $vtRes['code'] === '000';

            logTransaction($pdo, $currentUser['id'], 'Cable TV', $amount, $isSuccess ? 'successful' : 'failed', "Cable Subscription ($providerId) for $iucNumber", $iucNumber, $providerId);

            if (!$isSuccess) {
                throw new Exception($vtRes['response_description'] ?? 'API Error');
            }

            // Receipt Email
            $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your cable subscription was successful.<br><br>Provider: $providerId<br>IUC: $iucNumber<br>Amount: " . formatCurrency($amount);
            sendMail($pdo, $currentUser['email'], "Cable TV Receipt", $receiptMsg);

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
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
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
                    <button type="button" onclick="setProvider('<?php echo $p['id']; ?>')" id="prov_<?php echo $p['id']; ?>" class="prov-btn flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all border-transparent bg-gray-50 <?php echo !$p['enabled'] ? 'opacity-30 pointer-events-none' : ''; ?>">
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
    const providers = <?php echo json_encode($cableProviders); ?>;
    function setProvider(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => btn.classList.remove('border-billpay-green', 'bg-green-50'));
        document.getElementById('prov_' + id).classList.add('border-billpay-green', 'bg-green-50');

        const prov = providers.find(p => p.id === id);
        const select = document.getElementById('variationSelect');
        select.innerHTML = '<option value="">Select Package</option>';
        if (prov && prov.variations) {
            prov.variations.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.variation_code;
                opt.text = v.name + ' - ₦' + parseFloat(v.variation_amount).toLocaleString();
                opt.dataset.amount = v.variation_amount;
                select.appendChild(opt);
            });
        }
    }
    document.getElementById('variationSelect').addEventListener('change', function(e) {
        const opt = e.target.options[e.target.selectedIndex];
        document.getElementById('amountInput').value = opt.dataset.amount || 0;
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
