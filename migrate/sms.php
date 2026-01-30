<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Bulk SMS';

$error = '';
$success = '';

$activeTab = $_GET['tab'] ?? 'compose';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF Failed'); }

    if (isset($_POST['action']) && $_POST['action'] === 'send') {
        $senderId = sanitize($_POST['senderId']);
        $message = sanitize($_POST['message']);
        $rawNumbers = $_POST['numbers'];

        $raw = preg_split('/[,\s\n]+/', $rawNumbers);
        $recipients = [];
        foreach ($raw as $num) {
            $num = trim($num);
            if (strlen($num) >= 10) $recipients[] = $num;
        }
        $recipients = array_unique($recipients);

        $charLimit = 160;
        $pages = ceil(strlen($message) / $charLimit) ?: 1;
        $totalCost = count($recipients) * $pages * ($settings['smsRate'] ?? 4.50);

        if (isKycRejected($currentUser)) {
            $error = 'Account restricted. Please update your KYC.';
        } elseif (empty($senderId)) {
            $error = 'Select an approved Sender ID';
        } elseif (empty($recipients)) {
            $error = 'Enter valid recipients';
        } elseif ($currentUser['walletBalance'] < $totalCost) {
            $error = 'Insufficient balance.';
        } else {
            $pdo->beginTransaction();
            try {
                updateWallet($pdo, $currentUser['id'], $totalCost, 'debit');

                $smsRes = sendKudiSms($pdo, $senderId, $message, implode(',', $recipients));
                $isSuccess = (isset($smsRes['status']) && ($smsRes['status'] === 'success' || $smsRes['status'] === 'OK')) || (isset($smsRes['code']) && $smsRes['code'] == 200);

                if ($isSuccess) {
                    logTransaction($pdo, $currentUser['id'], 'Bulk SMS', $totalCost, 'successful', "Bulk SMS to " . count($recipients) . " recipients.", count($recipients) . " recipients", 'KudiSMS');
                    claimDailyRewardIfEligible($pdo, $currentUser['id']);
                    $success = 'SMS sent successfully!';
                } else {
                    updateWallet($pdo, $currentUser['id'], $totalCost, 'credit');
                    logTransaction($pdo, $currentUser['id'], 'Bulk SMS', $totalCost, 'failed', "SMS failed: " . ($smsRes['message'] ?? 'API Error'), count($recipients) . " recipients", 'KudiSMS');
                    $error = 'Failed to send SMS: ' . ($smsRes['message'] ?? 'Gateway Error');
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
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-40 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Bulk SMS Hub</h1>
    </div>

    <div class="p-4 space-y-6 flex-1">
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl flex items-center gap-3 border border-red-100 uppercase text-center font-black text-xs"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl flex items-center gap-3 border border-green-100 uppercase text-center font-black text-xs"><?php echo $success; ?></div><?php endif; ?>

        <div class="flex bg-gray-200 p-1.5 rounded-[24px]">
            <a href="?tab=compose" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'compose' ? 'bg-white shadow-xl text-billpay-green' : 'text-gray-500'; ?>">Compose</a>
            <a href="?tab=ids" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'ids' ? 'bg-white shadow-xl text-billpay-green' : 'text-gray-500'; ?>">IDs</a>
            <a href="?tab=contacts" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'contacts' ? 'bg-white shadow-xl text-billpay-green' : 'text-gray-500'; ?>">Contacts</a>
        </div>

        <?php if ($activeTab === 'compose'): ?>
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-billpay-green to-emerald-600 p-6 rounded-[32px] text-white shadow-lg relative overflow-hidden">
                   <div class="flex justify-between items-start mb-4">
                     <div>
                        <div class="text-[10px] font-black uppercase tracking-widest opacity-70">Current Rate</div>
                        <div class="text-2xl font-black"><?php echo formatCurrency($settings['smsRate'] ?? 4.50); ?><span class="text-xs font-bold opacity-60"> / SMS</span></div>
                     </div>
                     <div class="bg-white/20 p-2 rounded-xl backdrop-blur-md"><i data-lucide="info" class="w-4 h-4"></i></div>
                   </div>
                   <div class="grid grid-cols-2 gap-4">
                     <div class="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm"><div class="text-[8px] font-black uppercase opacity-60">Char Limit</div><div class="text-xs font-black">160 Characters</div></div>
                     <div class="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm"><div class="text-[8px] font-black uppercase opacity-60">Gateway</div><div class="text-xs font-black">Premium v2</div></div>
                   </div>
                </div>

                <form method="POST" id="purchaseForm" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="send">

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Approved Sender ID</label>
                        <select name="senderId" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-bold text-sm" required>
                            <option value="BILLPAY">BILLPAY (Default)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Recipients</label>
                        <textarea name="numbers" id="numbers" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-medium min-h-[120px] text-sm" placeholder="Numbers separated by comma..." required></textarea>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2 px-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Message</label>
                            <span id="pageCount" class="text-[9px] font-black px-2 py-0.5 rounded-full bg-gray-100 uppercase">1 Page</span>
                        </div>
                        <textarea name="message" id="message" class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-medium min-h-[150px] text-sm" placeholder="Message content..." required></textarea>
                    </div>

                    <button type="submit" id="submitBtn" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all flex items-center justify-center gap-3 uppercase">
                        <span id="btnText" class="flex items-center gap-3"><i data-lucide="send" class="w-5 h-5"></i> BROADCAST SMS</span>
                        <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-[40px] shadow-sm border border-gray-100 text-center py-20">
                <i data-lucide="clock" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">Feature Coming Soon</h2>
                <p class="text-sm text-gray-400 font-bold uppercase mt-2">Sender ID and Contact Book are being migrated.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('message').addEventListener('input', function(e) {
        const len = e.target.value.length;
        const pages = Math.ceil(len / 160) || 1;
        document.getElementById('pageCount').innerText = pages + (pages > 1 ? " Pages" : " Page");
    });

    document.getElementById('purchaseForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('btnText');
        const loader = document.getElementById('btnLoader');

        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-not-allowed');
        text.innerText = 'Processing...';
        loader.classList.remove('hidden');
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
