<?php require_once 'includes/header.php'; require_login(); $user = get_current_user_data(); $error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = "Security token mismatch"; }
    else {
        $method = $_POST['method'] ?? ''; $amount = (float)($_POST['amount'] ?? 0);
        if ($amount < 100) { $error = "Min ₦100"; }
        else {
            if ($method === 'manual') {
                $stmt = $pdo->prepare("INSERT INTO deposit_requests (id, userId, amount, method, status, date, senderName, charge) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([generate_id(), $user['id'], $amount, 'manual', 'pending', date('c'), $_POST['senderName'] ?? '', $settings['manualDepositCharge']]);
                $success = "Deposit notification submitted!";
            } else {
                if (empty($settings['paystackSecretKey'])) {
                    $error = "Online payment is currently unavailable (API keys not configured). Please use Manual Transfer.";
                } else {
                    // Real Paystack integration would start here with a CURL request to initialize transaction
                    // For this migration, we will keep it as a placeholder to prevent "free money" exploits.
                    $error = "Paystack integration requires server-side callback verification. Please configure your webhook URL to complete this integration.";
                }
            }
        }
    }
} ?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10 animate-fade-in">
  <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm"><a href="dashboard"><i data-lucide="arrow-left" class="text-gray-900"></i></a><h1 class="text-lg font-black text-gray-900">Add Money</h1></div>
  <div class="p-4 space-y-6 flex-1">
    <?php if ($error): ?><div class="p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-bold"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="p-4 rounded-2xl bg-green-50 text-green-800 border border-green-200 text-sm font-bold"><?php echo h($success); ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <select name="method" class="w-full p-4 bg-gray-50 rounded-2xl font-bold"><option value="paystack">Paystack (Instant)</option><option value="manual">Manual Transfer</option></select>
      <input type="number" name="amount" required placeholder="Amount (Min ₦100)" class="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg" />
      <input type="text" name="senderName" placeholder="Sender Name (for manual)" class="w-full p-4 bg-gray-50 rounded-2xl font-bold" />
      <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl">ADD MONEY</button>
    </form>
  </div>
  <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
