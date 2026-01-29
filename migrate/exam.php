<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
$pageTitle = 'Exam PIN';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $packageId = $_POST['providerId']; // This is package_id in utility_packages
    $qty = (int)$_POST['quantity'];

    $stmt = $pdo->prepare("SELECT * FROM utility_packages WHERE category = 'exam' AND package_id = ?");
    $stmt->execute([$packageId]);
    $pkg = $stmt->fetch();

    if (!$pkg) {
        $error = "Invalid exam product";
    } else {
        $userPrice = (float)$pkg['user_price'] * (1 - ($pkg['user_discount'] / 100));
        $apiCost = (float)$pkg['api_price'] * (1 - ($pkg['api_discount'] / 100));
        $totalCost = $userPrice * $qty;

        if ($currentUser['walletBalance'] < $totalCost) {
            $error = 'Insufficient balance. Need ' . formatCurrency($totalCost);
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

                $res = null;
                $isSuccess = false;
                if ($pkg['provider'] === 'vtpass') {
                    $res = callVtpass($pdo, strtolower(explode(' - ', $pkg['name'])[0]), [
                        'variation_code' => $pkg['package_id'],
                        'amount' => $pkg['api_price'] * $qty,
                        'phone' => $currentUser['phone']
                    ]);
                    $isSuccess = isset($res['code']) && $res['code'] === '000';
                } else {
                    $res = naijaresultpinsExams($pdo, 'buy', ['package' => $pkg['package_id'], 'quantity' => $qty]);
                    $isSuccess = isset($res['status']) && ($res['status'] === 'success' || $res['status'] === true);
                }
                $tokenStr = '';
                if ($isSuccess) {
                    if (isset($res['cards'])) {
                        $tokens = [];
                        foreach ($res['cards'] as $card) { $tokens[] = ($card['pin'] ?? $card['pin_code'] ?? ''); }
                        $tokenStr = implode(', ', $tokens);
                    } elseif (isset($res['pins'])) {
                        $tokenStr = implode(', ', $res['pins']);
                    } elseif (isset($res['pin'])) {
                        $tokenStr = $res['pin'];
                    }

                    $profitVal = $totalCost - ($apiCost * $qty);
                    logTransaction($pdo, $currentUser['id'], 'Exam PIN', $totalCost, 'successful', "Purchase of $qty " . $pkg['name'] . " PIN(s)", 'Self', $pkg['provider'], $tokenStr, ($apiCost * $qty), $profitVal);
                    sendMail($pdo, $currentUser['email'], "Exam PIN Receipt", "Successful purchase of $qty PIN(s). <br>Product: {$pkg['name']} <br>PIN: $tokenStr");
                    claimDailyRewardIfEligible($pdo, $currentUser['id']);
                    $success = true;
                } else {
                    updateWallet($pdo, $currentUser['id'], $totalCost, 'credit');
                    $errMsg = is_array($res) ? ($res['response_description'] ?? $res['msg'] ?? $res['message'] ?? 'API Error') : 'Provider Error';
                    logTransaction($pdo, $currentUser['id'], 'Exam PIN', $totalCost, 'failed', "Exam PIN failed: $errMsg", 'Self', $pkg['provider']);
                    $error = 'Transaction failed: ' . $errMsg;
                }

                $pdo->commit();
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM utility_packages WHERE category = 'exam' AND enabled = 1 ORDER BY name ASC");
                    while ($p = $stmt->fetch()):
                        $price = (float)$p['user_price'] * (1 - ($p['user_discount'] / 100));
                    ?>
                    <button type="button" onclick="setProv('<?php echo $p['package_id']; ?>')" id="prov_<?php echo $p['package_id']; ?>" class="prov-btn p-5 rounded-[24px] border-2 transition-all border-transparent bg-gray-50 flex flex-col items-center gap-2 text-center">
                        <span class="text-xs font-black text-gray-800"><?php echo $p['name']; ?></span>
                        <span class="text-[9px] font-bold text-billpay-green uppercase"><?php echo formatCurrency($price); ?></span>
                    </button>
                    <?php endwhile; ?>
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
    function setProv(id) {
        document.getElementById('providerInput').value = id;
        document.querySelectorAll('.prov-btn').forEach(btn => {
            btn.classList.remove('border-billpay-green', 'bg-green-50', 'shadow-sm');
            btn.classList.add('border-transparent', 'bg-gray-50');
        });
        const active = document.getElementById('prov_' + id);
        active.classList.add('border-billpay-green', 'bg-green-50', 'shadow-sm');
        active.classList.remove('border-transparent', 'bg-gray-50');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
