<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$txId = sanitize($_GET['txId'] ?? '');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);

    if (empty($subject) || empty($message)) {
        $error = "Please fill all fields";
    } else {
        $stmt = $pdo->prepare("INSERT INTO transaction_reports (txId, userId, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$txId, $currentUser['id'], $subject, $message]);
        $success = "Your report has been submitted. We will look into it shortly.";
    }
}

$pageTitle = 'Report an Issue';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto space-y-8 animate-fade-in pb-20">
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h2 class="text-xl font-black uppercase tracking-tight mb-2">Report an Issue</h2>
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-8">Transaction ID: <?php echo $txId ?: 'General'; ?></p>

        <?php if ($success): ?>
            <div class="p-6 bg-green-50 text-green-800 rounded-3xl text-xs font-black uppercase text-center border border-green-100 mb-8"><?php echo $success; ?></div>
            <a href="/transactions" class="block w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase text-center">Back to History</a>
        <?php else: ?>
            <?php if ($error): ?><div class="mb-6 p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black text-center uppercase border border-red-100"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Subject</label>
                    <input type="text" name="subject" value="<?php echo ($txId ? 'Issue with transaction '.$txId : ''); ?>" placeholder="What's the problem?" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Detailed Message</label>
                    <textarea name="message" rows="5" placeholder="Please provide as much detail as possible..." class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm leading-relaxed" required></textarea>
                </div>

                <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all">Submit Report</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
