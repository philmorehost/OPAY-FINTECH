<?php
require_once __DIR__ . '/includes/config.php';

// JuicyWay Webhook Handler
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    die('Invalid payload');
}

$businessId = $settings['financialSettings']['juicyway']['businessId'] ?? '';
$receivedChecksum = $data['checksum'] ?? '';

if (!$businessId) {
    error_log("JuicyWay Webhook Error: Business ID not set in settings");
    http_response_code(500);
    die('Configuration missing');
}

// Prepare data for verification (Exclude checksum, Alphabetical order)
$verificationData = $data['data'];
ksort($verificationData);
$jsonData = json_encode($verificationData);
$event = $data['event'];

$message = "$event|$jsonData";
$expectedChecksum = strtoupper(hash_hmac('sha256', $message, $businessId));

if (!hash_equals($expectedChecksum, $receivedChecksum)) {
    error_log("JuicyWay Webhook Error: Invalid checksum. Expected $expectedChecksum, got $receivedChecksum");
    http_response_code(401);
    die('Invalid signature');
}

$eventData = $data['data'];
$ref = $eventData['reference'] ?? $eventData['correlation_id'] ?? $eventData['id'] ?? '';

switch ($event) {
    case 'payment.session.succeeded':
        // Handle incoming payment (e.g. crypto deposit or card funding if used)
        $txId = $eventData['transaction_id'] ?? '';
        $amount = (float)($eventData['amount'] ?? 0);
        $currency = $eventData['currency'] ?? 'NGN';

        // Find transaction by reference
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? OR recipient = ?");
        $stmt->execute([$ref, $ref]);
        $tx = $stmt->fetch();

        if ($tx && $tx['status'] === 'pending') {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'successful', token = ? WHERE id = ?");
                $stmt->execute([$txId, $tx['id']]);

                // If it was a deposit, credit user wallet
                if ($tx['type'] === 'Deposit') {
                    updateWallet($pdo, $tx['userId'], $amount, 'credit');
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("JuicyWay Webhook DB Error: " . $e->getMessage());
            }
        }
        break;

    case 'payment.session.failed':
        // Handle failed payment
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE id = ? OR recipient = ?");
        $stmt->execute([$ref, $ref]);
        break;

    case 'card.transaction':
        // Handle virtual card transaction updates if needed
        break;
}

http_response_code(200);
echo "Webhook processed";
