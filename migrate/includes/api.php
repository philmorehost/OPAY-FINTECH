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

    if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') $opts[CURLOPT_POSTFIELDS] = $payload;
    if (!empty($headers)) $opts[CURLOPT_HTTPHEADER] = $headers;

    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err || $info['http_code'] == 0 || $info['http_code'] >= 400) {
        $contextOpts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
                'user_agent' => 'Mozilla/5.0 (BillPay Fintech; Fallback Hub v4)'
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
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

    $queryString = "";
    if ($method === 'GET') {
        $queryString = http_build_query($params);
        $fullUrl = $baseUrl . $endpoint . "?" . $queryString;
        $signData = $timestamp . $apiKey . $recvWindow . $queryString;
    } else {
        $fullUrl = $baseUrl . $endpoint;
        $signData = $timestamp . $apiKey . $recvWindow . json_encode($params);
    }

    $signature = hash_hmac('sha256', $signData, $secret);

    $headers = [
        "X-BAPI-API-KEY: $apiKey",
        "X-BAPI-SIGN: $signature",
        "X-BAPI-TIMESTAMP: $timestamp",
        "X-BAPI-RECV-WINDOW: $recvWindow",
        "Content-Type: application/json"
    ];

    return callApi($fullUrl, $method, $method === 'GET' ? [] : $params, $headers);
}
}

if (!function_exists('testBybitConnection')) {
function testBybitConnection($pdo) {
    $res = callBybit($pdo, '/v5/user/query-api', 'GET');
    if (isset($res['retCode']) && $res['retCode'] == 0) return ['status' => 'success', 'message' => 'Bybit Connection Successful!'];
    return ['status' => 'error', 'message' => 'Bybit Failed: ' . ($res['retMsg'] ?? 'Unknown Error')];
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
function bybitGetBalances($pdo) {
    return callBybit($pdo, '/v5/account/wallet-balance', 'GET', ['accountType' => 'UNIFIED']);
}
}

if (!function_exists('bybitWithdraw')) {
function bybitWithdraw($pdo, $coin, $amount, $address, $tag = '') {
    $params = [
        'coin' => strtoupper($coin),
        'chain' => 'TRX', // Default to TRC20 for USDT/USDC if not specified
        'address' => $address,
        'amount' => (string)$amount,
        'timestamp' => round(microtime(true) * 1000)
    ];
    if ($tag) $params['tag'] = $tag;
    return callBybit($pdo, '/v5/asset/withdraw/create', 'POST', $params);
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
    // GET request to singular endpoint with query params
    return callJuicyWay($pdo, "exchange/quote?source_currency=$from&target_currency=$to&lock=true", 'GET');
}
}

if (!function_exists('juicywaySwap')) {
function juicywaySwap($pdo, $amount, $from, $to, $quoteId = null) {
    $minorAmount = (int)($amount * 100);
    $data = [
        'amount' => $minorAmount,
        'source_currency' => strtoupper($from),
        'target_currency' => strtoupper($to)
    ];
    if ($quoteId) $data['quote_id'] = $quoteId;
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
