<?php
/**
 * Centralized API Integration Layer for BillPay Fintech
 * Handles communication with external service providers
 */

function callApi($url, $method = 'GET', $data = [], $headers = []) {
    $curl = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
    }

    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) return ['status' => 'error', 'message' => $err];
    return json_decode($response, true) ?: $response;
}

/**
 * Nellobyte (Airtime & Data)
 */
function purchaseAirtimeNellobyte($settings, $network, $amount, $phone) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['status' => 'success', 'msg' => 'Simulation: Airtime successful', 'ref' => 'SIM-' . uniqid()];
    }

    // Logic for Nellobyte API (Real)
    // Example: https://nellobyte.com/api/airtime?userid=xxx&apikey=xxx&network=xxx&amount=xxx&phone=xxx
    $url = "https://nellobyte.com/api/airtime?userid=" . $settings['nellobyteUserId'] . "&apikey=" . $settings['nellobyteApiKey'] . "&network=$network&amount=$amount&phone=$phone";
    return callApi($url);
}

function purchaseDataNellobyte($settings, $network, $plan, $phone) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['status' => 'success', 'msg' => 'Simulation: Data successful', 'ref' => 'SIM-' . uniqid()];
    }

    $url = "https://nellobyte.com/api/data?userid=" . $settings['nellobyteUserId'] . "&apikey=" . $settings['nellobyteApiKey'] . "&network=$network&plan=$plan&phone=$phone";
    return callApi($url);
}

/**
 * VTpass (Cable, Electric, Betting, Exam)
 */
function callVtpass($settings, $serviceId, $data) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['code' => '000', 'content' => ['transactions' => ['status' => 'delivered']], 'response_description' => 'Simulation Success'];
    }

    $url = "https://vtpass.com/api/pay";
    $headers = [
        "api-key: " . $settings['vtPassApiKey'],
        "public-key: " . $settings['vtPassPublicKey'],
        "Content-Type: application/json"
    ];

    // VTpass usually requires request_id (YYYYMMDDHHII + random)
    if (!isset($data['request_id'])) {
        $data['request_id'] = date('YmdHi') . bin2hex(random_bytes(3));
    }
    $data['serviceID'] = $serviceId;

    return callApi($url, 'POST', $data, $headers);
}

/**
 * KudiSMS
 */
function sendKudiSms($settings, $sender, $message, $recipients) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['status' => 'success', 'message' => 'Simulation: SMS Sent'];
    }

    $url = "https://my.kudisms.net/api/sms";
    $data = [
        'token' => $settings['kudiSmsToken'],
        'sender' => $sender,
        'message' => $message,
        'recipients' => $recipients
    ];

    // KudiSMS often uses GET or POST with query params/form-data
    $query = http_build_query($data);
    return callApi($url . "?" . $query);
}

/**
 * JuicyWay (Virtual Cards)
 */
function callJuicyWay($settings, $endpoint, $method = 'POST', $data = []) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['status' => 'success', 'data' => ['id' => 'VC-' . uniqid(), 'card_number' => '4111222233334444', 'cvv' => '123', 'expiry' => '12/26']];
    }

    $url = "https://api.juicyway.com/v1/$endpoint";
    $headers = [
        "Authorization: Bearer " . $settings['juicywayApiKey'],
        "Content-Type: application/json"
    ];
    return callApi($url, $method, $data, $headers);
}

/**
 * Reloadly (Gift Cards)
 */
function getReloadlyToken($settings) {
    if (!empty($settings['apiSimulationMode'])) return 'SIM-TOKEN';

    $url = "https://auth.reloadly.com/oauth/token";
    $data = [
        'client_id' => $settings['reloadlyClientId'],
        'client_secret' => $settings['reloadlyClientSecret'],
        'grant_type' => 'client_credentials',
        'audience' => 'https://giftcards.reloadly.com'
    ];
    $res = callApi($url, 'POST', $data, ["Content-Type: application/json"]);
    return $res['access_token'] ?? null;
}

function getReloadlyGiftCards($settings) {
    if (!empty($settings['apiSimulationMode'])) {
        return [
            'content' => [
                ['productId' => 1, 'productName' => 'Amazon US', 'global' => false, 'senderFee' => 0, 'discountPercentage' => 2, 'denominationType' => 'FIXED', 'fixedDenominations' => [10, 25, 50, 100]],
                ['productId' => 2, 'productName' => 'iTunes US', 'global' => false, 'senderFee' => 0, 'discountPercentage' => 3, 'denominationType' => 'FIXED', 'fixedDenominations' => [5, 10, 15]],
                ['productId' => 3, 'productName' => 'Google Play US', 'global' => false, 'senderFee' => 0, 'discountPercentage' => 1, 'denominationType' => 'RANGE', 'minDenomination' => 10, 'maxDenomination' => 500],
                ['productId' => 4, 'productName' => 'Netflix US', 'global' => false, 'senderFee' => 0, 'discountPercentage' => 1.5, 'denominationType' => 'FIXED', 'fixedDenominations' => [25, 50, 100]],
                ['productId' => 5, 'productName' => 'Steam Wallet', 'global' => true, 'senderFee' => 0, 'discountPercentage' => 2, 'denominationType' => 'FIXED', 'fixedDenominations' => [20, 50, 100]],
                ['productId' => 6, 'productName' => 'PlayStation Store', 'global' => true, 'senderFee' => 0, 'discountPercentage' => 2, 'denominationType' => 'FIXED', 'fixedDenominations' => [10, 20, 50]],
                ['productId' => 7, 'productName' => 'Xbox Live', 'global' => true, 'senderFee' => 0, 'discountPercentage' => 2, 'denominationType' => 'FIXED', 'fixedDenominations' => [10, 25, 50]],
                ['productId' => 8, 'productName' => 'Roblox', 'global' => true, 'senderFee' => 0, 'discountPercentage' => 5, 'denominationType' => 'FIXED', 'fixedDenominations' => [10, 25, 50]]
            ]
        ];
    }

    $token = getReloadlyToken($settings);
    if (!$token) return ['content' => []];

    $url = "https://giftcards.reloadly.com/products";
    return callApi($url, 'GET', [], ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json"]);
}

function purchaseReloadlyGiftCard($settings, $productId, $amount, $recipientEmail) {
    if (!empty($settings['apiSimulationMode'])) {
        return ['status' => 'SUCCESS', 'transactionId' => 'SIM-GC-' . uniqid()];
    }

    $token = getReloadlyToken($settings);
    if (!$token) return ['status' => 'FAILED', 'message' => 'Token failed'];

    $url = "https://giftcards.reloadly.com/orders";
    $data = [
        'productId' => $productId,
        'quantity' => 1,
        'unitPrice' => $amount,
        'recipientEmail' => $recipientEmail,
        'customIdentifier' => 'BILL-' . uniqid()
    ];
    return callApi($url, 'POST', $data, ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json", "Content-Type: application/json"]);
}

/**
 * Paystack (Dedicated Virtual Accounts)
 */
function createPaystackCustomer($settings, $user) {
    if (!empty($settings['apiSimulationMode'])) return ['status' => true, 'data' => ['customer_code' => 'CUS_sim' . uniqid()]];

    $url = "https://api.paystack.co/customer";
    $data = [
        'email' => $user['email'],
        'first_name' => explode(' ', $user['fullName'])[0],
        'last_name' => explode(' ', $user['fullName'])[1] ?? 'User',
        'phone' => $user['phone']
    ];
    return callApi($url, 'POST', $data, ["Authorization: Bearer " . $settings['paystackSecretKey'], "Content-Type: application/json"]);
}

function createPaystackDedicatedAccount($settings, $customerCode) {
    if (!empty($settings['apiSimulationMode'])) {
        return [
            'status' => true,
            'data' => [
                'bank' => ['name' => 'Simulation Bank'],
                'account_number' => mt_rand(1000000000, 9999999999),
                'account_name' => 'SIMULATED ACCOUNT',
                'assignment' => ['integration' => 1]
            ]
        ];
    }

    $url = "https://api.paystack.co/dedicated_account";
    $data = ['customer' => $customerCode, 'preferred_bank' => 'wema-bank'];
    return callApi($url, 'POST', $data, ["Authorization: Bearer " . $settings['paystackSecretKey'], "Content-Type: application/json"]);
}

/**
 * Crypto Prices (CoinGecko)
 */
function getCryptoPrices() {
    $url = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,solana,tether&vs_currencies=ngn,usd&include_24hr_change=true";
    return callApi($url);
}
