<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$isServiceRestricted = checkMinDepositRestriction($settings, $currentUser);
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Exam PIN';

$examProviders = $settings['examProviders'] ?? [];
if (is_string($examProviders)) $examProviders = json_decode($examProviders, true) ?: [];

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $providerId = $_POST['providerId'];
    $qty = (int)$_POST['quantity'];

    $selectedProv = null;
    foreach ($examProviders as $p) {
        if ($p['id'] === $providerId) { $selectedProv = $p; break; }
    }

    if (!$selectedProv) {
        $error = "Invalid provider";
    } else {
        $totalCost = $selectedProv['userPrice'] * $qty;
        if ($currentUser['walletBalance'] < $totalCost) {
            $error = 'Insufficient balance';
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');
                logTransaction($pdo, $currentUser['id'], 'Exam PIN', $totalCost, 'successful', "Purchase of $qty " . $selectedProv['name'] . " PIN(s)", 'Self', $selectedProv['name']);

                // Receipt Email
                $receiptMsg = "Hi {$currentUser['fullName']},<br><br>Your purchase of $qty " . $selectedProv['name'] . " Exam PIN(s) was successful.<br><br>Total: " . formatCurrency($totalCost) . "<br><br>PINs will be sent to this email address shortly.";
                sendMail($pdo, $currentUser['email'], "Exam PIN Receipt", $receiptMsg);

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
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Exam Result PINs</h1>
    </div>
    <div class="p-6 space-y-6 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center">Purchase Successful! PINs will be sent to your email.</div><?php endif; ?>

        <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Select Exam</label>
                <div class="grid grid-cols-2 gap-4">
                    <?php foreach ($examProviders as $p): ?>
                    <button type="button" onclick="setProv('<?php echo $p['id']; ?>', '<?php echo $p['userPrice']; ?>')" id="prov_<?php echo $p['id']; ?>" class="prov-btn p-5 rounded-[24px] border-2 transition-all border-transparent bg-gray-50 flex flex-col items-center gap-2">
                        <span class="text-xs font-black text-gray-800"><?php echo $p['name']; ?></span>
                        <span class="text-[9px] font-bold text-billpay-green uppercase"><?php echo formatCurrency($p['userPrice']); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="providerId" id="providerInput" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Quantity</label>
                <input type="number" name="quantity" value="1" min="1" max="5" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-xl outline-none" required>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Buy PIN</button>
        </form>
    </div>
</div>
<script>
    function setProv(id, price) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-white', 'shadow-md');
            btn.classList.add('border-transparent', 'bg-gray-50');
        });
        const active = document.getElementById('prov_' + id);
        active.classList.add('border-billpay-green', 'bg-white', 'shadow-md');
        active.classList.remove('border-transparent', 'bg-gray-50');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
