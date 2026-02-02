<?php
require_once __DIR__ . '/includes/config.php';

// This script should be run via CRON every few minutes
$isWeb = (php_sapi_name() !== 'cli');
if ($isWeb && !isset($_GET['force'])) {
    die("This script can only be run from the command line.");
}

if (!$isWeb) echo "Starting Crypto Deposit Check...\n";

try {
    $settings = fetchSettings($pdo);
    $provider = $settings['financialSettings']['primaryCrypto'] ?? 'bybit';

    $records = [];
    $specificTxid = sanitize($_GET['txid'] ?? '');

    if ($provider === 'bybit') {
        $res = bybitGetDepositRecords($pdo);
        if (isset($res['result']['list'])) $records = $res['result']['list'];
    } else {
        $res = mexcGetDepositRecords($pdo);
        if (is_array($res)) $records = $res;
    }

    $found = false;
    foreach ($records as $rec) {
        $txid = $rec['txid'] ?? $rec['txId'] ?? '';
        if (!$txid) continue;
        if ($specificTxid && $txid !== $specificTxid) continue;

        // Check if already processed
        $stmt = $pdo->prepare("SELECT * FROM crypto_deposits WHERE txid = ?");
        $stmt->execute([$txid]);
        $existing = $stmt->fetch();

        if ($existing && $existing['status'] === 'credited') continue;

        $amount = (float)($rec['amount'] ?? $rec['quantity'] ?? 0);
        $coin = strtoupper($rec['coin'] ?? $rec['asset'] ?? '');
        $address = $rec['address'] ?? $rec['toAddress'] ?? '';
        $status = strtolower($rec['status'] ?? '');

        // Only credit if status is successful/completed
        // Bybit: 1=pending, 2=success, 3=failed. V5 uses strings usually or different codes.
        // Actually V5 status: 0=unknown, 1=to be confirmed, 2=processing, 3=success, 4=deposit failed
        if ($status != '3' && $status != 'success' && $status != 'completed') continue;

        if ($existing) {
            // Found a pre-registered deposit
            creditDeposit($pdo, $existing, $amount);
            $found = true;
        } else {
            // Unregistered deposit - try to find matching user by address if we assigned fixed addresses
            // Or just log it for manual review if we use shared addresses
            echo "Unrecognized deposit found: $txid | $amount $coin to $address\n";

            // For now, if we use a shared address, we can't automatically know the user unless we match intents.
        }
    }

    if ($isWeb) {
        header('Content-Type: application/json');
        echo json_encode(['status' => $found ? 'success' : 'pending', 'message' => $found ? 'Deposit credited' : 'Deposit not found or still pending']);
        exit;
    }

} catch (Exception $e) {
    if ($isWeb) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

function creditDeposit($pdo, $deposit, $actualAmount) {
    $userId = $deposit['userId'];
    $coin = $deposit['coin'];

    // Calculate NGN value
    $price = cryptoGetPrice($pdo, $coin);
    if (!$price) $price = 1500; // Fallback rate

    $ngnValue = $actualAmount * $price;

    $pdo->beginTransaction();
    try {
        // Update wallet
        updateWallet($pdo, $userId, $ngnValue, 'credit');

        // Update deposit record
        $stmt = $pdo->prepare("UPDATE crypto_deposits SET status = 'credited', amount = ?, creditedAt = NOW() WHERE id = ?");
        $stmt->execute([$actualAmount, $deposit['id']]);

        // Log transaction
        logTransaction($pdo, $userId, "Crypto Deposit ($coin)", $ngnValue, 'successful', "Deposited $actualAmount $coin via crypto", $deposit['address']);

        $pdo->commit();
        echo "Credited user $userId: $actualAmount $coin (₦" . number_format($ngnValue, 2) . ")\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Failed to credit $userId: " . $e->getMessage() . "\n";
    }
}
