<?php
require_once __DIR__ . '/includes/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die();

// Retrieve the request's body
$body = @file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if (!$signature) die();

// Verify signature
if ($signature !== hash_hmac('sha512', $body, $settings['paystackSecretKey'])) {
    die();
}

http_response_code(200);
$event = json_decode($body, true);

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $amount = $data['amount'] / 100;
    $customerEmail = $data['customer']['email'];
    $reference = $data['reference'];

    // For Dedicated Virtual Accounts, Paystack sends charge.success
    // We need to find the user by email or customer code
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$customerEmail]);
    $userId = $stmt->fetchColumn();

    if ($userId) {
        $pdo->beginTransaction();
        try {
            // Check if already processed
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE id = ?");
            $stmt->execute([$reference]);
            if ($stmt->fetchColumn() == 0) {
                updateWallet($pdo, $userId, $amount, 'credit');
                logTransaction($pdo, $userId, 'Wallet Funding', $amount, 'successful', "Bank Deposit - Ref: $reference", 'Wallet', 'System');
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}
