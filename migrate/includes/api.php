<?php
/**
 * Centralized API Integration Layer v4 (Live Only)
 * Handles communication with external service providers
 */

if (!function_exists('callApi')) {
function callApi($url, $method = 'GET', $data = [], $headers = []) {
    $method = strtoupper($method);
    $payload = is_array($data) ? json_encode($data) : $data;
    $cacert = __DIR__ . '/cacert.pem';

    $curl = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => file_exists($cacert),
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (BillPay Fintech; Live Hub v4)',
        CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1',
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_FRESH_CONNECT => true
    ];

    if (file_exists($cacert)) {
        $opts[CURLOPT_CAINFO] = $cacert;
        $opts[CURLOPT_CAPATH] = __DIR__;
    } else {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'])) {
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }
    if (!empty($headers)) $opts[CURLOPT_HTTPHEADER] = $headers;

    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err || $info['http_code'] == 0 || $info['http_code'] >= 400) {
        $headerStr = implode("\r\n", $headers);
        $contextOpts = [
            'http' => [
                'method' => $method,
                'header' => $headerStr,
                'timeout' => 30,
                'ignore_errors' => true,
                'user_agent' => 'Mozilla/5.0 (BillPay Fintech; Fallback Hub v4)'
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];

        if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE']) && !empty($payload)) {
            $contextOpts['http']['header'] .= "\r\nContent-Length: " . strlen($payload);
            $contextOpts['http']['content'] = $payload;
        }

        $res = @file_get_contents($url, false, stream_context_create($contextOpts));
        if ($res !== false) {
            $decoded = json_decode($res, true);
            if ($decoded) return $decoded;
            if ($err) return $res;
        }
    }

    if ($err) return ['status' => 'error', 'message' => $err, 'debug' => $info];
    return json_decode($response, true) ?: $response;
}
}

if (!function_exists('testJuicywayConnection')) {
function testJuicywayConnection($pdo) {
    $res = juicywayGetWallets($pdo);
    if (isset($res['data'])) return ['status' => 'success', 'message' => 'Connection successful! Found ' . count($res['data']) . ' wallets.'];
    return ['status' => 'error', 'message' => $res['message'] ?? 'Connection failed. Check API key and mode.'];
}
}

/**
 * Helper: Network Code Mapping
 */
if (!function_exists('getNetworkCode')) {
function getNetworkCode($provider, $network) {
    $network = strtoupper($network);
    $map = [
        'nellobyte' => ['MTN' => '01', 'GLO' => '02', '9MOBILE' => '03', 'AIRTEL' => '04'],
        'datagifting' => ['MTN' => '1', 'AIRTEL' => '2', 'GLO' => '3', '9MOBILE' => '4'],
        'hdkdata' => ['MTN' => '1', 'AIRTEL' => '2', 'GLO' => '3', '9MOBILE' => '4']
    ];
    return $map[$provider][$network] ?? $network;
}
}

/**
 * AIRTIME & DATA
 */
if (!function_exists('purchaseAirtime')) {
function purchaseAirtime($pdo, $network, $amount, $phone) {
    $settings = fetchSettings($pdo);
    $as = $settings['airtimeSettings'] ?? [];
    $provider = $as['routing'][$network] ?? 'datagifting';
    $creds = $as['providers'][$provider] ?? [];
    $netCode = getNetworkCode($provider, $network);
    switch ($provider) {
        case 'datagifting':
            $res = callApi("https://v6.datagifting.com.ng/web/api/airtime.php?api_key=" . ($creds['apiKey'] ?? '') . "&network=$netCode&amount=$amount&phone_number=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $res = callApi("https://nellobytesystems.com/api/airtime?userid=" . ($creds['userId'] ?? '') . "&apikey=" . ($creds['apiKey'] ?? '') . "&network=$netCode&amount=$amount&phone=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
    }
    return ['status' => 'failed', 'message' => 'No provider'];
}
}

if (!function_exists('purchaseData')) {
function purchaseData($pdo, $network, $planId, $phone) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];
    $provider = $ds['routing'][$network] ?? 'datagifting';
    $creds = $ds['providers'][$provider] ?? [];
    $netCode = getNetworkCode($provider, $network);
    switch ($provider) {
        case 'datagifting':
            $res = callApi("https://v6.datagifting.com.ng/web/api/data.php?api_key=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone_number=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $res = callApi("https://nellobytesystems.com/api/data?userid=" . ($creds['userId'] ?? '') . "&apikey=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'hdkdata':
            $res = callApi("https://hdkdata.com/api/data?api_key=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone=$phone");
            if (isset($res['status']) && ($res['status'] === 'success' || $res['status'] === true)) return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
    }
    return ['status' => 'failed', 'message' => 'No provider'];
}
}

/**
 * UTILITIES
 */
if (!function_exists('callVtpass')) {
function callVtpass($pdo, $serviceId, $data) {
    $settings = fetchSettings($pdo);
    $creds = $settings['utilitySettings']['vtpass'] ?? [];
    if (!isset($data['request_id'])) $data['request_id'] = date('YmdHi') . bin2hex(random_bytes(3));
    $data['serviceID'] = $serviceId;
    $headers = ["Content-Type: application/json"];
    if (!empty($creds['apiKey'])) {
        $headers[] = "api-key: " . $creds['apiKey'];
        $headers[] = "secret-key: " . ($creds['secretKey'] ?? '');
    } elseif (!empty($creds['username']) && !empty($creds['password'])) {
        $headers[] = "Authorization: Basic " . base64_encode($creds['username'] . ":" . $creds['password']);
    }
    return callApi("https://vtpass.com/api/pay", 'POST', $data, $headers);
}
}

/**
 * BYBIT API (V5)
 */
if (!function_exists('callBybit')) {
function callBybit($pdo, $endpoint, $method = 'GET', $params = []) {
    $settings = fetchSettings($pdo);
    $creds = $settings['financialSettings']['bybit'] ?? [];
    $apiKey = $creds['apiKey'] ?? '';
    $secret = $creds['apiSecret'] ?? '';
    $baseUrl = !empty($creds['testnet']) ? "https://api-testnet.bybit.com" : "https://api.bybit.com";

    $timestamp = round(microtime(true) * 1000);
    $recvWindow = 5000;
    $method = strtoupper($method);

    if ($method === 'GET') {
        $queryString = !empty($params) ? http_build_query($params) : "";
        $fullUrl = $baseUrl . $endpoint . ($queryString ? "?" . $queryString : "");
        $signData = $timestamp . $apiKey . $recvWindow . $queryString;
        $payload = "";
    } else {
        $fullUrl = $baseUrl . $endpoint;
        $payload = !empty($params) ? json_encode($params) : "";
        $signData = $timestamp . $apiKey . $recvWindow . $payload;
    }

    $signature = hash_hmac('sha256', $signData, $secret);

    $headers = [
        "X-BAPI-API-KEY: $apiKey",
        "X-BAPI-SIGN: $signature",
        "X-BAPI-TIMESTAMP: $timestamp",
        "X-BAPI-RECV-WINDOW: $recvWindow",
        "Content-Type: application/json"
    ];

    return callApi($fullUrl, $method, $payload, $headers);
}
}

if (!function_exists('testAirtimeProvider')) {
function testAirtimeProvider($pdo, $provider) {
    $settings = fetchSettings($pdo);
    $as = $settings['airtimeSettings'] ?? [];
    $creds = $as['providers'][$provider] ?? [];

    switch ($provider) {
        case 'datagifting':
            $apiKey = $creds['apiKey'] ?? '';
            $res = callApi("https://v6.datagifting.com.ng/web/api/profile.php?api_key=$apiKey");
            if (isset($res['status']) && $res['status'] === 'success') {
                return ['status' => 'success', 'message' => 'Connection Successful! Wallet Balance: ' . ($res['wallet_balance'] ?? 'N/A')];
            }
            return ['status' => 'error', 'message' => $res['msg'] ?? 'Connection Failed or Invalid API Key.', 'debug' => $res];

        case 'nellobyte':
            $userId = $creds['userId'] ?? '';
            $apiKey = $creds['apiKey'] ?? '';
            $res = callApi("https://nellobytesystems.com/api/profile?userid=$userId&apikey=$apiKey");
            if (isset($res['status']) && $res['status'] === 'success') {
                return ['status' => 'success', 'message' => 'Connection Successful! Wallet Balance: ' . ($res['wallet_balance'] ?? 'N/A')];
            }
            return ['status' => 'error', 'message' => $res['msg'] ?? 'Connection Failed or Invalid Credentials.', 'debug' => $res];

        case 'hdkdata':
            $token = $creds['token'] ?? '';
            $res = callApi("https://hdkdata.com/api/user/", 'GET', [], ["Authorization: Token $token"]);
            if (isset($res['user']['wallet_balance'])) {
                return ['status' => 'success', 'message' => 'Connection Successful! Wallet Balance: ' . ($res['user']['wallet_balance'] ?? 'N/A')];
            }
            return ['status' => 'error', 'message' => $res['msg'] ?? 'Connection Failed. Check Token.', 'debug' => $res];
    }
    return ['status' => 'error', 'message' => 'Unknown Provider'];
}
}

if (!function_exists('testBybitConnection')) {
function testBybitConnection($pdo) {
    $res = callBybit($pdo, '/v5/user/query-api', 'GET');
    if (is_array($res) && isset($res['retCode']) && $res['retCode'] == 0) return ['status' => 'success', 'message' => 'Bybit Connection Successful!'];

    $msg = 'Unknown Error';
    if (is_array($res)) {
        $msg = $res['retMsg'] ?? ($res['message'] ?? 'Unknown Error');
    } elseif (is_string($res)) {
        $msg = (strlen($res) > 100) ? substr(strip_tags($res), 0, 100) . '...' : strip_tags($res);
    }

    return ['status' => 'error', 'message' => 'Bybit Failed: ' . trim($msg), 'debug' => $res];
}
}

if (!function_exists('bybitGetMarketPrice')) {
function bybitGetMarketPrice($pdo, $symbol = 'BTCUSDT') {
    $res = callBybit($pdo, '/v5/market/tickers', 'GET', ['category' => 'spot', 'symbol' => $symbol]);
    if (isset($res['result']['list'][0]['lastPrice'])) {
        return (float)$res['result']['list'][0]['lastPrice'];
    }
    return 0;
}
}

if (!function_exists('bybitGetBalances')) {
function bybitGetBalances($pdo, $accountType = 'UNIFIED') {
    return callBybit($pdo, '/v5/account/wallet-balance', 'GET', ['accountType' => strtoupper($accountType)]);
}
}

if (!function_exists('bybitWithdraw')) {
function bybitWithdraw($pdo, $coin, $amount, $address, $chain = 'TRC20', $tag = '') {
    // Normalize chain name for Bybit (they often use TRX for TRC20)
    $network = strtoupper($chain);
    if ($network === 'TRC20') $network = 'TRX';
    if ($network === 'ERC20') $network = 'ETH';

    $params = [
        'coin' => strtoupper($coin),
        'chain' => $network,
        'address' => $address,
        'amount' => (string)$amount,
        'timestamp' => round(microtime(true) * 1000)
    ];
    if ($tag) $params['tag'] = $tag;
    return callBybit($pdo, '/v5/asset/withdraw/create', 'POST', $params);
}
}

/**
 * Trade: Create Order (SPOT, Linear, Inverse, Option)
 */
if (!function_exists('bybitCreateOrder')) {
function bybitCreateOrder($pdo, $category, $symbol, $side, $orderType, $qty, $price = null, $extra = []) {
    $params = array_merge([
        'category' => strtolower($category),
        'symbol' => strtoupper($symbol),
        'side' => ucfirst(strtolower($side)),
        'orderType' => ucfirst(strtolower($orderType)),
        'qty' => (string)$qty,
        'timeInForce' => 'GTC'
    ], $extra);
    if ($price) $params['price'] = (string)$price;
    return callBybit($pdo, '/v5/order/create', 'POST', $params);
}
}

/**
 * Trade: Get Positions (Contracts)
 */
if (!function_exists('bybitGetPositions')) {
function bybitGetPositions($pdo, $category, $symbol = '') {
    $params = ['category' => strtolower($category)];
    if ($symbol) $params['symbol'] = strtoupper($symbol);
    return callBybit($pdo, '/v5/position/list', 'GET', $params);
}
}

/**
 * Trade: Get Open Orders
 */
if (!function_exists('bybitGetOrders')) {
function bybitGetOrders($pdo, $category, $symbol = '') {
    $params = ['category' => strtolower($category)];
    if ($symbol) $params['symbol'] = strtoupper($symbol);
    return callBybit($pdo, '/v5/order/realtime', 'GET', $params);
}
}

/**
 * Wallet: Internal/Account Transfer
 */
if (!function_exists('bybitTransfer')) {
function bybitTransfer($pdo, $coin, $amount, $fromAccountType, $toAccountType) {
    $params = [
        'transferId' => uniqid('bp_'),
        'coin' => strtoupper($coin),
        'amount' => (string)$amount,
        'fromAccountType' => strtoupper($fromAccountType),
        'toAccountType' => strtoupper($toAccountType),
    ];
    return callBybit($pdo, '/v5/asset/transfer/inter-transfer', 'POST', $params);
}
}

if (!function_exists('bybitSubAccountTransfer')) {
function bybitSubAccountTransfer($pdo, $coin, $amount, $subMemberId, $type = 'OUT') {
    $params = [
        'transferId' => uniqid('bp_sub_'),
        'coin' => strtoupper($coin),
        'amount' => (string)$amount,
        'subMemberId' => $subMemberId,
        'type' => strtoupper($type) // IN (from sub to main), OUT (from main to sub)
    ];
    return callBybit($pdo, '/v5/asset/transfer/save-transfer', 'POST', $params);
}

}

/**
 * Exchange: Convert (Quote + Execution)
 */
if (!function_exists('bybitConvert')) {
function bybitConvert($pdo, $fromCoin, $toCoin, $amount) {
    // 1. Get Quote
    $quoteRes = callBybit($pdo, '/v5/asset/exchange/quote-apply', 'POST', [
        'fromCoin' => strtoupper($fromCoin),
        'toCoin' => strtoupper($toCoin),
        'fromAmount' => (string)$amount
    ]);

    if (isset($quoteRes['result']['quoteId'])) {
        // 2. Execute Convert
        return callBybit($pdo, '/v5/asset/exchange/convert', 'POST', [
            'quoteId' => $quoteRes['result']['quoteId']
        ]);
    }
    return $quoteRes;
}
}

if (!function_exists('bybitGetConvertHistory')) {
function bybitGetConvertHistory($pdo) {
    return callBybit($pdo, '/v5/asset/exchange/order-record', 'GET');
}
}

/**
 * Earn: Flexible Savings Products & Purchase
 */
if (!function_exists('bybitGetEarnProducts')) {
function bybitGetEarnProducts($pdo, $coin = '') {
    $params = [];
    if ($coin) $params['coin'] = strtoupper($coin);
    return callBybit($pdo, '/v5/earn/v1/flexible-savings/product/list', 'GET', $params);
}
}

if (!function_exists('bybitEarnPurchase')) {
function bybitEarnPurchase($pdo, $productId, $amount) {
    return callBybit($pdo, '/v5/earn/v1/flexible-savings/order/purchase', 'POST', [
        'productId' => $productId,
        'amount' => (string)$amount
    ]);
}
}

/**
 * JUICYWAY (Elite Finance)
 */
if (!function_exists('callJuicyWay')) {
function callJuicyWay($pdo, $endpoint, $method = 'POST', $data = []) {
    $settings = fetchSettings($pdo);
    $creds = $settings['financialSettings']['juicyway'] ?? [];
    $apiKey = $creds['apiKey'] ?? '';
    $baseUrl = !empty($creds['liveMode']) ? "https://api.spendjuice.com" : "https://api-sandbox.spendjuice.com";
    return callApi("$baseUrl/$endpoint", $method, $data, [
        "Authorization: " . $apiKey,
        "Content-Type: application/json"
    ]);
}
}

if (!function_exists('juicywayGetWallets')) {
function juicywayGetWallets($pdo) {
    return callJuicyWay($pdo, 'wallets/all', 'GET');
}
}

if (!function_exists('juicywayGetBanks')) {
function juicywayGetBanks($pdo, $country = 'NG') {
    return callJuicyWay($pdo, "payment-methods/banks", 'GET');
}
}

if (!function_exists('juicywayGetQuote')) {
function juicywayGetQuote($pdo, $amount, $from, $to) {
    $from = strtoupper($from);
    $to = strtoupper($to);
    $minorAmount = (int)($amount * 100);
    // JuicyWay GET quote uses minor units for 'amount'
    return callJuicyWay($pdo, "exchange/quote?source_currency=$from&target_currency=$to&amount=$minorAmount&lock=true", 'GET');
}
}

if (!function_exists('juicywaySwap')) {
function juicywaySwap($pdo, $amount, $from, $to, $quoteId = null) {
    $from = strtoupper($from);
    $to = strtoupper($to);
    $minorAmount = (int)($amount * 100);

    // Exact payload as per documentation to avoid 422 Unprocessable Entity
    $data = [
        'amount' => $minorAmount,
        'source_currency' => $from,
        'target_currency' => $to,
        'reference' => 'SW-' . time() . '-' . rand(1000, 9999)
    ];

    if (!empty($quoteId)) {
        $data['quote_id'] = $quoteId;
    }

    return callJuicyWay($pdo, "exchange/swap", 'POST', $data);
}
}

if (!function_exists('juicywayInitiatePayout')) {
function juicywayInitiatePayout($pdo, $data) {
    // Ensure amount is in minor units (kobo/cents)
    if (isset($data['amount'])) $data['amount'] = (int)($data['amount'] * 100);
    // Unified endpoint for Bank, Crypto, Internal, Interac
    return callJuicyWay($pdo, "payouts", 'POST', $data);
}
}

if (!function_exists('juicywayCreatePaymentLink')) {
function juicywayCreatePaymentLink($pdo, $amount, $currency, $description, $user = null) {
    $minorAmount = (int)($amount * 100);
    $payload = [
        'amount' => $minorAmount,
        'currency' => strtoupper($currency),
        'description' => $description,
        'redirect_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/finance'
    ];

    // Include customer data to avoid "Unprocessable entity"
    if ($user) {
        $names = explode(' ', $user['fullName'] ?? 'Customer User');
        $payload['customer'] = [
            'email' => $user['email'] ?? 'customer@example.com',
            'first_name' => $names[0] ?: 'Customer',
            'last_name' => $names[1] ?? 'User',
            'phone_number' => $user['phone'] ?? '+2348000000000'
        ];
    }

    return callJuicyWay($pdo, "payment-links", 'POST', $payload);
}
}

/**
 * PAYSTACK
 */
if (!function_exists('createPaystackCustomer')) {
function createPaystackCustomer($pdo, $user) {
    $settings = fetchSettings($pdo);
    $creds = $settings['financialSettings']['paystack'] ?? [];
    return callApi("https://api.paystack.co/customer", 'POST', ['email' => $user['email'], 'first_name' => explode(' ', $user['fullName'])[0], 'last_name' => explode(' ', $user['fullName'])[1] ?? 'User', 'phone' => $user['phone']], ["Authorization: Bearer " . ($creds['secretKey'] ?? ''), "Content-Type: application/json"]);
}
}

if (!function_exists('createPaystackDedicatedAccount')) {
function createPaystackDedicatedAccount($pdo, $customerCode) {
    $settings = fetchSettings($pdo);
    $creds = $settings['financialSettings']['paystack'] ?? [];
    return callApi("https://api.paystack.co/dedicated_account", 'POST', ['customer' => $customerCode, 'preferred_bank' => 'wema-bank'], ["Authorization: Bearer " . ($creds['secretKey'] ?? ''), "Content-Type: application/json"]);
}
}

if (!function_exists('sendKudiSms')) {
function sendKudiSms($pdo, $senderId, $message, $to) {
    $settings = fetchSettings($pdo);
    $creds = $settings['otherApiSettings']['kudisms'] ?? [];
    $token = $creds['token'] ?? '';
    if (empty($senderId)) $senderId = $creds['senderId'] ?? 'BillPay';
    $url = "https://kudisms.net/api/?token=$token&senderID=$senderId&recipients=$to&message=" . urlencode($message);
    return callApi($url);
}
}

if (!function_exists('getCryptoPrices')) {
function getCryptoPrices() {
    return callApi("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,solana,tether&vs_currencies=ngn,usd&include_24hr_change=true");
}
}

/**
 * RELOADLY GIFT CARDS
 */
if (!function_exists('getReloadlyToken')) {
function getReloadlyToken($pdo) {
    $settings = fetchSettings($pdo);
    $creds = $settings['otherApiSettings']['reloadly'] ?? [];
    $clientId = $creds['clientId'] ?? '';
    $clientSecret = $creds['clientSecret'] ?? '';

    $res = callApi("https://auth.reloadly.com/oauth/token", 'POST', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials',
        'audience' => 'https://giftcards.reloadly.com'
    ], ["Content-Type: application/json"]);

    return $res['access_token'] ?? null;
}
}

if (!function_exists('getReloadlyGiftCards')) {
function getReloadlyGiftCards($pdo) {
    $token = getReloadlyToken($pdo);
    if (!$token) return ['status' => 'error', 'message' => 'Failed to authenticate with Reloadly'];

    return callApi("https://giftcards.reloadly.com/products", 'GET', [], [
        "Authorization: Bearer $token",
        "Accept: application/com.reloadly.giftcards-v1+json"
    ]);
}
}

if (!function_exists('purchaseReloadlyGiftCard')) {
function purchaseReloadlyGiftCard($pdo, $productId, $amount, $recipientEmail) {
    $token = getReloadlyToken($pdo);
    if (!$token) return ['status' => 'error', 'message' => 'Failed to authenticate with Reloadly'];

    return callApi("https://giftcards.reloadly.com/orders", 'POST', [
        'productId' => $productId,
        'amount' => $amount,
        'quantity' => 1,
        'recipientEmail' => $recipientEmail,
        'customIdentifier' => uniqid('GC-')
    ], [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "Accept: application/com.reloadly.giftcards-v1+json"
    ]);
}
}
