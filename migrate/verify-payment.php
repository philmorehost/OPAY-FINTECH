<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    redirect('/add-money?error=Missing reference');
}

$method = $_GET['method'] ?? 'paystack';
$type = $_GET['type'] ?? 'deposit';
$isSuccess = false;
$amount = 0;

if ($method === 'paystack') {
    $secretKey = $settings['paystackSecretKey'];
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $secretKey", "Cache-Control: no-cache"]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    if (isset($result['status']) && $result['status'] === true && $result['data']['status'] === 'success') {
        $isSuccess = true;
        $amount = $result['data']['amount'] / 100;
    }
} elseif ($method === 'flutterwave') {
    $creds = $settings['otherApiSettings']['flutterwave'] ?? [];
    $secret = $creds['secretKey'] ?? '';
    $id = $_GET['transaction_id'] ?? '';
    $res = callApi("https://api.flutterwave.com/v3/transactions/$id/verify", 'GET', [], ["Authorization: Bearer $secret"]);
    if (isset($res['status']) && $res['status'] === 'success') {
        $isSuccess = true;
        $amount = $res['data']['amount'];
        $reference = $res['data']['tx_ref'];
    }
} elseif ($method === 'paypal') {
    $creds = $settings['otherApiSettings']['paypal'] ?? [];
    $clientId = $creds['clientId'] ?? '';
    $secret = $creds['clientSecret'] ?? '';
    $mode = !empty($creds['liveMode']) ? 'api' : 'api-m.sandbox';
    $orderId = $_GET['token'] ?? '';

    $auth = base64_encode("$clientId:$secret");
    $tokenRes = callApi("https://$mode.paypal.com/v1/oauth2/token", 'POST', "grant_type=client_credentials", ["Authorization: Basic $auth", "Content-Type: application/x-www-form-urlencoded"]);
    $token = $tokenRes['access_token'] ?? '';
    if ($token) {
        $capture = callApi("https://$mode.paypal.com/v2/checkout/orders/$orderId/capture", 'POST', [], ["Authorization: Bearer $token", "Content-Type: application/json"]);
        if (isset($capture['status']) && $capture['status'] === 'COMPLETED') {
            $isSuccess = true;
            $amount = (float)$capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] * 1600; // Mock USD to NGN
            $reference = $capture['id'];
        }
    }
}

if ($isSuccess) {
    $pdo->beginTransaction();
    try {
        if ($type === 'vcard') {
            $req = $_SESSION['pending_vcard_request'] ?? null;
            if (!$req || $req['ref'] !== $reference) throw new Exception("VCard request session expired or mismatch.");

            // Issue Card
            $jwRes = callJuicyWay($pdo, 'cards', 'POST', ['userId' => $currentUser['id'], 'type' => $req['type'], 'amount' => 0]);
            if (isset($jwRes['status']) && $jwRes['status'] === 'success') {
                $cd = $jwRes['data'];
                $stmt = $pdo->prepare("INSERT INTO virtual_cards (id, userId, cardNumber, expiry, cvv, type, balance) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$cd['id'], $currentUser['id'], $cd['card_number'], $cd['expiry'], $cd['cvv'], $req['type']]);
                logTransaction($pdo, $currentUser['id'], 'Virtual Card', $amount, 'successful', "Card issued via $method: {$cd['card_number']}", 'System', 'CardIssuer');
                unset($_SESSION['pending_vcard_request']);
                $pdo->commit();
                redirect('/vcard?success=Card issued successfully!');
            } else { throw new Exception("Card issuance failed from provider."); }
        } else {
            // Standard Deposit
            $stmt = $pdo->prepare("SELECT * FROM deposit_requests WHERE id = ?");
            $stmt->execute([$reference]);
            $dep = $stmt->fetch();
            if ($dep && $dep['status'] === 'pending') {
                $stmt = $pdo->prepare("UPDATE deposit_requests SET status = 'successful' WHERE id = ?");
                $stmt->execute([$reference]);
                updateWallet($pdo, $dep['userId'], $amount, 'credit');
                logTransaction($pdo, $dep['userId'], 'Wallet Funding', $amount, 'successful', "Deposit via $method - Ref: $reference", 'Wallet', 'System');
                $pdo->commit();
                redirect('/add-money?success=Wallet funded successfully');
            } else { throw new Exception("Transaction not found or already processed."); }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('/add-money?error=' . urlencode($e->getMessage()));
    }
} else {
    redirect('/add-money?error=Payment verification failed');
}
