<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$txId = sanitize($_GET['txId'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND userId = ?");
$stmt->execute([$txId, $currentUser['id']]);
$tx = $stmt->fetch();

if (!$tx) redirect('/transactions');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $issueType = sanitize($_POST['issueType']);
    $message = sanitize($_POST['message']);

    if (empty($message)) {
        $error = "Please provide details about the issue.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO transaction_reports (txId, userId, issueType, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$txId, $currentUser['id'], $issueType, $message])) {
            $success = "Issue reported successfully. Our team will review it.";
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

$pageTitle = 'Report Issue';
require_once __DIR__ . '/includes/header.php';
?>

<div class="lg:mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 text-gray-900">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/receipt?id=<?php echo $txId; ?>"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Report Issue</h1>
    </div>

    <div class="p-6 space-y-8">
        <?php if ($success): ?>
            <div class="bg-white p-10 rounded-[40px] text-center space-y-6 shadow-sm border border-gray-100 animate-fade-in">
                <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto shadow-inner"><i data-lucide="check-circle" class="w-10 h-10"></i></div>
                <h3 class="text-xl font-black uppercase"><?php echo $success; ?></h3>
                <a href="/transactions" class="block w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase text-xs tracking-widest shadow-xl">Back to Activity</a>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm space-y-4">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                    <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 font-black text-[10px] uppercase">TX</div>
                    <div>
                        <div class="text-xs font-black text-gray-800"><?php echo $tx['type']; ?> • <?php echo formatCurrency($tx['amount']); ?></div>
                        <div class="text-[8px] font-mono text-gray-400 uppercase"><?php echo $tx['id']; ?></div>
                    </div>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <?php if ($error): ?><div class="p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-black uppercase text-center border border-red-100"><?php echo $error; ?></div><?php endif; ?>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Issue Category</label>
                        <select name="issueType" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
                            <option value="Service not received">Service not received</option>
                            <option value="Incorrect amount charged">Incorrect amount charged</option>
                            <option value="System Error">System Error</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Details</label>
                        <textarea name="message" rows="5" placeholder="Explain what happened..." class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required></textarea>
                    </div>

                    <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 active:scale-[0.98] transition-all">Submit Report</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
