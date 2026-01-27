<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');
checkKycRestriction($settings, $currentUser);

$pageTitle = 'Gift Cards';
$error = '';
$success = '';

// Fetch all available gift cards from Reloadly
$gcRes = getReloadlyGiftCards($settings);
$allCards = $gcRes['content'] ?? [];

// Color presets for cards
$cardColors = [
    ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-100'],
    ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
    ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-100'],
    ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100'],
    ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-100'],
    ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-100'],
    ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
    ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100'],
    ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'border' => 'border-cyan-100'],
    ['bg' => 'bg-lime-50', 'text' => 'text-lime-600', 'border' => 'border-lime-100']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $productId = (int)$_POST['productId'];
    $amount = (float)$_POST['amount'];
    $productName = sanitize($_POST['productName']);

    if ($currentUser['walletBalance'] < $amount) {
        $error = "Insufficient balance.";
    } else {
        $pdo->beginTransaction();
        try {
            updateWallet($pdo, $currentUser['id'], $amount, 'debit');

            $res = purchaseReloadlyGiftCard($settings, $productId, $amount, $currentUser['email']);

            if (isset($res['status']) && ($res['status'] === 'SUCCESS' || $res['status'] === 'PENDING')) {
                logTransaction($pdo, $currentUser['id'], 'Gift Card', $amount, 'successful', "Purchased $productName Gift Card", $currentUser['email'], 'Reloadly');
                $success = "Gift card purchased! Details will be sent to {$currentUser['email']} shortly.";
            } else {
                throw new Exception($res['message'] ?? "Provider error.");
            }
            $pdo->commit();

            // Refresh balance
            $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $currentUser['walletBalance'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-40 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Gift Card Store</h1>
    </div>

    <div class="p-6 space-y-8 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm">
            <div class="relative">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="text" id="gcSearch" oninput="filterCards()" placeholder="Search gift cards..." class="w-full pl-12 pr-4 py-4 bg-gray-50 rounded-2xl font-bold text-sm outline-none border-2 border-transparent focus:border-billpay-green transition-all">
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6" id="gcGrid">
            <?php
            foreach ($allCards as $index => $card):
                $color = $cardColors[$index % count($cardColors)];
            ?>
            <button onclick='selectCard(<?php echo json_encode($card); ?>)' class="gc-card bg-white p-6 rounded-[40px] shadow-sm border <?php echo $color['border']; ?> flex flex-col items-center gap-5 transition-all hover:shadow-xl hover:-translate-y-1 active:scale-95 group">
                <div class="w-16 h-16 <?php echo $color['bg']; ?> rounded-[24px] flex items-center justify-center font-black text-2xl <?php echo $color['text']; ?> shadow-inner group-hover:scale-110 transition-transform">
                    <?php echo substr($card['productName'], 0, 1); ?>
                </div>
                <div class="text-center">
                    <div class="text-[11px] font-black uppercase tracking-tight truncate w-28 card-name text-gray-800"><?php echo $card['productName']; ?></div>
                    <div class="flex items-center justify-center gap-1.5 mt-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-billpay-green animate-pulse"></span>
                        <div class="text-[8px] font-black text-gray-400 uppercase tracking-widest"><?php echo $card['denominationType']; ?></div>
                    </div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Purchase Modal Overlay -->
        <div id="purchaseModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
            <div class="bg-white w-full max-w-md rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
                <div class="p-8 border-b border-gray-50 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-black uppercase tracking-tight" id="modalTitle">Gift Card</h3>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Select denomination</p>
                    </div>
                    <button onclick="closeModal()"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
                </div>
                <form method="POST" class="p-8 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="purchase">
                    <input type="hidden" name="productId" id="modalProductId">
                    <input type="hidden" name="productName" id="modalProductName">

                    <div id="denomGrid" class="grid grid-cols-3 gap-4">
                        <!-- Filled by JS -->
                    </div>

                    <div id="rangeInput" class="hidden space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase px-1">Enter Amount</label>
                        <input type="number" name="amount" id="customAmount" class="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none" placeholder="0.00">
                        <p class="text-[8px] font-bold text-gray-400 uppercase px-1" id="rangeText"></p>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-3xl flex justify-between items-center">
                        <span class="text-[10px] font-black uppercase text-gray-400">Recipient</span>
                        <span class="text-[10px] font-black"><?php echo $currentUser['email']; ?></span>
                    </div>

                    <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[24px] shadow-xl hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest">Confirm Purchase</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function filterCards() {
        const query = document.getElementById('gcSearch').value.toLowerCase();
        document.querySelectorAll('.gc-card').forEach(card => {
            const name = card.querySelector('.card-name').innerText.toLowerCase();
            card.classList.toggle('hidden', !name.includes(query));
        });
    }

    function selectCard(card) {
        document.getElementById('modalTitle').innerText = card.productName;
        document.getElementById('modalProductId').value = card.productId;
        document.getElementById('modalProductName').value = card.productName;

        const denomGrid = document.getElementById('denomGrid');
        const rangeInput = document.getElementById('rangeInput');
        const customAmount = document.getElementById('customAmount');

        denomGrid.innerHTML = '';
        rangeInput.classList.add('hidden');
        customAmount.removeAttribute('required');

        if (card.denominationType === 'FIXED') {
            card.fixedDenominations.forEach(amt => {
                const label = document.createElement('label');
                label.className = 'cursor-pointer';
                label.innerHTML = `
                    <input type="radio" name="amount" value="${amt}" class="sr-only peer" required>
                    <div class="p-4 border-2 border-gray-50 rounded-2xl text-center peer-checked:border-billpay-green peer-checked:bg-green-50 transition-all font-black text-sm">
                        $${amt}
                    </div>
                `;
                denomGrid.appendChild(label);
            });
        } else {
            rangeInput.classList.remove('hidden');
            customAmount.setAttribute('required', 'true');
            customAmount.min = card.minDenomination;
            customAmount.max = card.maxDenomination;
            document.getElementById('rangeText').innerText = `Min: $${card.minDenomination} - Max: $${card.maxDenomination}`;
        }

        document.getElementById('purchaseModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('purchaseModal').classList.add('hidden');
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
