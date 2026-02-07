<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    redirect('/add-money?error=Missing reference');
}

$secretKey = $settings['paystackSecretKey'];
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $secretKey",
    "Cache-Control: no-cache"
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['status']) && $result['status'] === true && $result['data']['status'] === 'success') {
    $amount = $result['data']['amount'] / 100; // to NGN

    $pdo->beginTransaction();
    try {
        // Check if already processed
        $stmt = $pdo->prepare("SELECT * FROM deposit_requests WHERE id = ?");
        $stmt->execute([$reference]);
        $dep = $stmt->fetch();

        if ($dep && $dep['status'] === 'pending') {
            // Update deposit request
            $stmt = $pdo->prepare("UPDATE deposit_requests SET status = 'successful' WHERE id = ?");
            $stmt->execute([$reference]);

            // Update user wallet
            updateWallet($pdo, $dep['userId'], $amount, 'credit');

            // Log Transaction
            logTransaction($pdo, $dep['userId'], 'Wallet Funding', $amount, 'successful', "Card Deposit - Ref: $reference", 'Wallet', 'System');

            $pdo->commit();
            redirect('/add-money?success=Wallet funded successfully');
        } else {
            $pdo->rollBack();
            redirect('/add-money?error=Transaction already processed or not found');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('/add-money?error=Verification failed: ' . $e->getMessage());
    }
} else {
    redirect('/add-money?error=Payment verification failed');
}
