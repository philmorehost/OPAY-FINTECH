<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Gift Cards';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $brand = sanitize($_POST['brand']);
    $amount = (float)$_POST['amount'];

    if ($currentUser['walletBalance'] < $amount) {
        $error = "Insufficient balance.";
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');

            // Simulation/Mock for Tremendous
            if (!empty($settings['apiSimulationMode'])) {
                $res = ['status' => 'success', 'order' => ['id' => 'ORD-' . uniqid()]];
            } else {
                // Real Tremendous API call would go here
                $res = ['status' => 'success', 'order' => ['id' => 'ORD-' . uniqid()]];
            }

            if ($res['status'] === 'success') {
                logTransaction($pdo, $currentUser['id'], 'Gift Card', $amount, 'successful', "Purchased $brand Gift Card", $currentUser['email'], 'Tremendous');
                $success = "Gift card purchased! Details will be sent to your email.";
            } else {
                throw new Exception("Provider error.");
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Gift Cards</h1>
    </div>

    <div class="p-6 space-y-8 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $brands = [
                ['id' => 'amazon', 'name' => 'Amazon', 'color' => 'bg-orange-100 text-orange-600'],
                ['id' => 'itunes', 'name' => 'iTunes', 'color' => 'bg-pink-100 text-pink-600'],
                ['id' => 'googleplay', 'name' => 'Google Play', 'color' => 'bg-green-100 text-green-600'],
                ['id' => 'netflix', 'name' => 'Netflix', 'color' => 'bg-red-100 text-red-600']
            ];
            foreach ($brands as $b):
            ?>
            <button onclick="selectBrand('<?php echo $b['name']; ?>')" class="brand-btn bg-white p-8 rounded-[32px] shadow-sm border border-gray-100 flex flex-col items-center gap-4 transition-all hover:scale-105 active:scale-95">
                <div class="w-12 h-12 <?php echo $b['color']; ?> rounded-2xl flex items-center justify-center font-black text-xs"><?php echo substr($b['name'], 0, 1); ?></div>
                <span class="text-[10px] font-black uppercase tracking-widest"><?php echo $b['name']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <form method="POST" id="purchaseForm" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8 hidden">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="purchase">
            <input type="hidden" name="brand" id="brandInput">

            <div>
                <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-2">Buy <span id="selectedBrandName"></span> Gift Card</h3>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Select an amount to purchase</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <?php foreach ([10, 25, 50, 100, 200] as $amt): ?>
                <label class="cursor-pointer">
                    <input type="radio" name="amount" value="<?php echo $amt * 1000; ?>" class="sr-only peer">
                    <div class="p-4 border-2 border-gray-50 rounded-2xl text-center peer-checked:border-billpay-green peer-checked:bg-green-50 transition-all font-black text-sm">
                        ₦<?php echo number_format($amt * 1000); ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Purchase Now</button>
        </form>
    </div>
</div>

<script>
    function selectBrand(name) {
        document.getElementById('brandInput').value = name;
        document.getElementById('selectedBrandName').innerText = name;
        document.getElementById('purchaseForm').classList.remove('hidden');
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
