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
        $headerLines = $headers;
        $contextOpts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'timeout' => 30,
                'ignore_errors' => true,
                'user_agent' => 'Mozilla/5.0 (BillPay Fintech; Fallback Hub v4)'
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];

        if ($method !== 'GET' && !empty($payload)) {
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
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    return $response;
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
        'datagifting' => ['MTN' => 'mtn', 'AIRTEL' => 'airtel', 'GLO' => 'glo', '9MOBILE' => '9mobile'],
        'datastation' => ['MTN' => '1', 'GLO' => '2', 'AIRTEL' => '3', '9MOBILE' => '4'],
        'vtpass' => ['MTN' => 'mtn', 'AIRTEL' => 'airtel', 'GLO' => 'glo', '9MOBILE' => 'etisalat']
    ];
    return $map[$provider][$network] ?? strtolower($network);
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
            $apiKey = $creds['apiKey'] ?? '';
            $res = callApi("https://v6.datagifting.com.ng/api/airtime.php?api_key=$apiKey", 'POST', http_build_query([
                'api_key' => $apiKey,
                'network' => $netCode,
                'amount' => $amount,
                'phone_number' => $phone
            ]), ['Content-Type: application/x-www-form-urlencoded']);
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['desc'] ?? $res['response_desc'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['desc'] ?? $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $requestId = date('YmdHi') . bin2hex(random_bytes(4));
            $callbackUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/webhook-nellobyte.php";
            $url = "https://www.nellobytesystems.com/APIAirtimeV1.asp?UserID=" . ($creds['userId'] ?? '') . "&APIKey=" . ($creds['apiKey'] ?? '') . "&MobileNetwork=$netCode&Amount=$amount&MobileNumber=$phone&RequestID=$requestId&CallBackURL=" . urlencode($callbackUrl);
            $res = callApi($url);
            if (is_array($res)) {
                if (isset($res['status']) && ($res['status'] === 'ORDER_RECEIVED' || $res['status'] === 'ORDER_COMPLETED')) return ['status' => 'success', 'message' => 'Successful', 'ref' => $res['orderid'] ?? $requestId];
                return ['status' => 'failed', 'message' => $res['status'] ?? $res['msg'] ?? 'Provider Error'];
            }
            if (is_string($res) && (strpos($res, 'ORDER_RECEIVED') !== false || strpos($res, 'ORDER_COMPLETED') !== false)) return ['status' => 'success', 'message' => 'Successful', 'ref' => $requestId];
            return ['status' => 'failed', 'message' => is_string($res) ? $res : 'Provider Error'];
        case 'datastation':
            $res = callApi("https://datastationapi.com/api/topup/", 'POST', [
                'network' => $netCode,
                'amount' => $amount,
                'mobile_number' => $phone,
                'Ported_number' => true,
                'airtime_type' => 'VTU'
            ], ["Authorization: Token " . ($creds['token'] ?? '')]);
            // Documentation says: "This request doesn't return any response body"
            // But usually there is something or at least HTTP 201/200.
            // I'll check for any response or just assume success if no error.
            if (empty($res) || (isset($res['Status']) && $res['Status'] === 'successful')) return ['status' => 'success', 'message' => 'Successful', 'ref' => uniqid()];
            return ['status' => 'failed', 'message' => 'Provider Error'];
        case 'vtpass':
            $requestId = date('YmdHi') . bin2hex(random_bytes(4));
            $payload = ['request_id' => $requestId, 'serviceID' => $netCode, 'amount' => $amount, 'phone' => $phone];
            $res = callVtpass($pdo, $netCode, $payload, $creds);
            if (isset($res['code']) && $res['code'] === '000') {
                $status = $res['content']['transactions']['status'] ?? 'pending';
                if ($status === 'delivered') return ['status' => 'success', 'message' => 'Successful', 'ref' => $res['requestId'] ?? $requestId];
                return ['status' => 'pending', 'message' => 'Processing', 'ref' => $res['requestId'] ?? $requestId];
            }
            return ['status' => 'failed', 'message' => $res['response_description'] ?? 'Provider Error'];
        case 'hdkdata':
            $res = callApi("https://hdkdata.com/api/topup/", 'POST', [
                'network' => getNetworkCode('datastation', $network),
                'amount' => $amount,
                'mobile_number' => $phone,
                'Ported_number' => true,
                'airtime_type' => 'VTU'
            ], ["Authorization: Token " . ($creds['token'] ?? '')]);
            if (empty($res) || (isset($res['Status']) && $res['Status'] === 'successful')) return ['status' => 'success', 'message' => 'Successful', 'ref' => uniqid()];
            return ['status' => 'failed', 'message' => 'Provider Error'];
    }
    return ['status' => 'failed', 'message' => 'No provider'];
}
}

if (!function_exists('testAirtimeConnection')) {
function testAirtimeConnection($pdo, $provider) {
    $settings = fetchSettings($pdo);
    $as = $settings['airtimeSettings'] ?? [];
    $creds = $as['providers'][$provider] ?? [];
    return testGenericConnection($pdo, $provider, $creds);
}
}

if (!function_exists('testDataConnection')) {
function testDataConnection($pdo, $provider) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];
    $creds = $ds['providers'][$provider] ?? [];
    return testGenericConnection($pdo, $provider, $creds);
}
}

if (!function_exists('testUtilityConnection')) {
function testUtilityConnection($pdo, $provider) {
    $settings = fetchSettings($pdo);
    $us = $settings['utilitySettings'] ?? [];
    $creds = $us[$provider] ?? [];
    return testGenericConnection($pdo, $provider, $creds);
}
}

if (!function_exists('testOtherConnection')) {
function testOtherConnection($pdo, $provider) {
    $settings = fetchSettings($pdo);
    $os = $settings['otherApiSettings'] ?? [];
    $creds = $os[$provider] ?? [];
    return testGenericConnection($pdo, $provider, $creds);
}
}

if (!function_exists('testGenericConnection')) {
function testGenericConnection($pdo, $provider, $creds) {
    if (empty($creds)) return ['status' => 'error', 'message' => 'Credentials not set'];

    switch ($provider) {
        case 'datagifting':
            // Try user.php, fallback to profile.php or index.php if 404
            $url = "https://v6.datagifting.com.ng/api/user.php?api_key=" . ($creds['apiKey'] ?? '');
            $res = callApi($url);
            if (is_string($res) && strpos($res, '404') !== false) {
                 $url = "https://v6.datagifting.com.ng/api/index.php?api_key=" . ($creds['apiKey'] ?? '');
                 $res = callApi($url);
            }

            if (is_array($res)) {
                if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format((float)($res['wallet'] ?? $res['balance'] ?? 0), 2)];
                if (isset($res['status']) && $res['status'] === 'fail') return ['status' => 'error', 'message' => $res['msg'] ?? 'Authentication Failed'];
                if (isset($res['status']) && $res['status'] === 'error') return ['status' => 'error', 'message' => $res['message'] ?? $res['msg'] ?? 'Provider Error'];
            }
            return ['status' => 'error', 'message' => 'Connection Failed: ' . (is_string($res) ? $res : json_encode($res))];
        case 'nellobyte':
            $res = callApi("https://www.nellobytesystems.com/APIWalletV1.asp?UserID=" . ($creds['userId'] ?? '') . "&APIKey=" . ($creds['apiKey'] ?? ''));
            if (is_array($res)) {
                if (isset($res['status']) && strpos(strtoupper($res['status']), 'SUCCESS') !== false) return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format((float)($res['balance'] ?? $res['walletbalance'] ?? 0), 2)];
                if (isset($res['walletbalance'])) return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format((float)$res['walletbalance'], 2)];
                if (isset($res['status'])) return ['status' => 'error', 'message' => $res['status']];
            }
            if (is_string($res)) {
                if (strpos($res, 'Balance:') !== false) return ['status' => 'success', 'message' => 'Connected! ' . $res];
                if (strpos($res, 'INVALID_USER') !== false) return ['status' => 'error', 'message' => 'Invalid User ID or API Key'];
                return ['status' => 'error', 'message' => 'Response: ' . substr($res, 0, 100)];
            }
            return ['status' => 'error', 'message' => 'Connection Failed: ' . json_encode($res)];
        case 'datastation':
            $res = callApi("https://datastationapi.com/api/user/", 'GET', [], ["Authorization: Token " . ($creds['token'] ?? '')]);
            if (is_array($res)) {
                if (isset($res['user']['wallet_balance'])) return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format((float)$res['user']['wallet_balance'], 2)];
                if (isset($res['wallet_balance'])) return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format((float)$res['wallet_balance'], 2)];
                if (isset($res['detail'])) return ['status' => 'error', 'message' => $res['detail']];
            }
            return ['status' => 'error', 'message' => 'Connection Failed: ' . (is_string($res) ? $res : json_encode($res))];
        case 'vtpass':
            $headers = ["Content-Type: application/json"];
            if (!empty($creds['username']) && !empty($creds['password'])) {
                $headers[] = "Authorization: Basic " . base64_encode($creds['username'] . ":" . $creds['password']);
            } elseif (!empty($creds['apiKey'])) {
                $headers[] = "api-key: " . $creds['apiKey'];
                $headers[] = "public-key: " . ($creds['publicKey'] ?? '');
            }
            $url = (!empty($creds['sandbox']) || (!empty($settings['liveMode']) && !$settings['liveMode'])) ? "https://sandbox.vtpass.com/api/balance" : "https://vtpass.com/api/balance";
            $res = callApi($url, 'GET', [], $headers);
            if (is_array($res)) {
                if (isset($res['code']) && $res['code'] === '000') {
                    $bal = $res['content']['balance'] ?? $res['contents']['balance'] ?? 'N/A';
                    return ['status' => 'success', 'message' => "Connected! Balance: ₦" . (is_numeric($bal) ? number_format($bal, 2) : $bal)];
                }
                return ['status' => 'error', 'message' => $res['response_description'] ?? 'Auth Failed: ' . json_encode($res)];
            }
            return ['status' => 'error', 'message' => 'Connection Failed: ' . (is_string($res) ? $res : json_encode($res))];
        case 'flutterwave':
            $res = callApi("https://api.flutterwave.com/v3/balances/NGN", 'GET', [], ["Authorization: Bearer " . ($creds['secretKey'] ?? '')]);
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => 'Connected! Balance: ₦' . number_format($res['data']['available_balance'], 2)];
            return ['status' => 'error', 'message' => $res['message'] ?? 'Connection Failed'];
        case 'paypal':
            $auth = base64_encode(($creds['clientId'] ?? '') . ":" . ($creds['clientSecret'] ?? ''));
            $mode = !empty($creds['liveMode']) ? 'api' : 'api-m.sandbox';
            $res = callApi("https://$mode.paypal.com/v1/oauth2/token", 'POST', "grant_type=client_credentials", [
                "Authorization: Basic $auth",
                "Content-Type: application/x-www-form-urlencoded"
            ]);
            if (isset($res['access_token'])) return ['status' => 'success', 'message' => 'Connected! Access Token Generated.'];
            return ['status' => 'error', 'message' => $res['error_description'] ?? 'PayPal Auth Failed'];
        case 'naijaresultpins':
            // Hypothetical test for NaijaResultPins
            return ['status' => 'success', 'message' => 'NaijaResultPins (Simulated Connection)'];
    }
    return ['status' => 'error', 'message' => 'Provider not supported for test'];
}
}

if (!function_exists('purchaseData')) {
function purchaseData($pdo, $network, $planId, $phone) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];

    // Fetch plan to get its specific gateway (independent routing)
    $stmt = $pdo->prepare("SELECT gateway, plan_id, type, data_size FROM data_plans WHERE id = ? OR plan_id = ? LIMIT 1");
    $stmt->execute([$planId, $planId]);
    $p = $stmt->fetch();

    // Priority: Plan Specific Gateway > Global Network Routing > Default
    $provider = $p['gateway'] ?? ($ds['routing'][$network] ?? 'datagifting');

    $creds = $ds['providers'][$provider] ?? [];
    $netCode = getNetworkCode($provider, $network);
    switch ($provider) {
        case 'datagifting':
            // Fetch plan details
            $stmt = $pdo->prepare("SELECT * FROM data_plans WHERE plan_id = ? OR id = ? LIMIT 1");
            $stmt->execute([$planId, $planId]);
            $p = $stmt->fetch();

            // Normalize Type: sme -> sme-data, gifting -> gifting-data
            $type = $p ? strtolower($p['type']) : 'sme';
            if ($type === 'databundle') $type = 'gifting'; // fallback
            if (!empty($type) && strpos($type, '-data') === false) {
                $type .= '-data';
            }

            // Normalize Quantity: e.g. 1gb, 500mb
            $qty = $p ? strtolower(str_replace(' ', '', $p['plan_id'])) : '1gb';
            // If plan_id is just a numeric ID, try extracting from data_size
            if (is_numeric($qty) && $p) {
                $cleanSize = strtolower(str_replace(' ', '', $p['data_size']));
                if (preg_match('/(\d+(gb|mb|tb))/', $cleanSize, $matches)) {
                    $qty = $matches[1];
                }
            }

            $apiKey = $creds['apiKey'] ?? '';
            $res = callApi("https://v6.datagifting.com.ng/api/data.php?api_key=$apiKey", 'POST', http_build_query([
                'api_key' => $apiKey,
                'network' => $netCode,
                'phone_number' => $phone,
                'type' => $type,
                'quantity' => $qty
            ]), ['Content-Type: application/x-www-form-urlencoded']);
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['desc'] ?? $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['desc'] ?? $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $res = callApi("https://nellobytesystems.com/api/data?userid=" . ($creds['userId'] ?? '') . "&apikey=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'datastation':
            $res = callApi("https://datastationapi.com/api/data/", 'POST', [
                'network' => $netCode,
                'plan' => $planId,
                'mobile_number' => $phone,
                'Ported_number' => true
            ], ["Authorization: Token " . ($creds['token'] ?? '')]);
            if (empty($res) || (isset($res['Status']) && $res['Status'] === 'successful')) return ['status' => 'success', 'message' => 'Successful', 'ref' => uniqid()];
            return ['status' => 'failed', 'message' => 'Provider Error'];
        case 'hdkdata':
            $res = callApi("https://hdkdata.com/api/data/", 'POST', [
                'network' => $netCode,
                'plan' => $planId,
                'mobile_number' => $phone,
                'Ported_number' => true
            ], ["Authorization: Token " . ($creds['token'] ?? '')]);
            if (empty($res) || (isset($res['Status']) && $res['Status'] === 'successful')) return ['status' => 'success', 'message' => 'Successful', 'ref' => uniqid()];
            return ['status' => 'failed', 'message' => 'Provider Error'];
    }
    return ['status' => 'failed', 'message' => 'No provider'];
}
}

/**
 * UTILITIES
 */
if (!function_exists('vtpassGetVariations')) {
function vtpassGetVariations($pdo, $serviceId) {
    $settings = fetchSettings($pdo);
    $creds = $settings['utilitySettings']['vtpass'] ?? [];
    $url = "https://vtpass.com/api/service-variations?serviceID=" . $serviceId;
    if (!empty($creds['sandbox'])) $url = "https://sandbox.vtpass.com/api/service-variations?serviceID=" . $serviceId;
    return callApi($url, 'GET');
}
}

if (!function_exists('vtpassVerifyMerchant')) {
function vtpassVerifyMerchant($pdo, $serviceId, $billersCode, $type = null) {
    $data = ['serviceID' => $serviceId, 'billersCode' => $billersCode];
    if ($type) $data['variation_code'] = $type;
    $url = "https://vtpass.com/api/merchant-verify";
    $settings = fetchSettings($pdo);
    $creds = $settings['utilitySettings']['vtpass'] ?? [];
    if (!empty($creds['sandbox'])) $url = "https://sandbox.vtpass.com/api/merchant-verify";

    $headers = ["Content-Type: application/json"];
    if (!empty($creds['username']) && !empty($creds['password'])) {
        $headers[] = "Authorization: Basic " . base64_encode($creds['username'] . ":" . $creds['password']);
    } elseif (!empty($creds['apiKey'])) {
        $headers[] = "api-key: " . $creds['apiKey'];
        $headers[] = "public-key: " . ($creds['publicKey'] ?? '');
    }
    return callApi($url, 'POST', $data, $headers);
}
}

if (!function_exists('callVtpass')) {
function callVtpass($pdo, $serviceId, $data, $customCreds = null) {
    $settings = fetchSettings($pdo);
    $creds = $customCreds ?: ($settings['utilitySettings']['vtpass'] ?? []);
    if (!isset($data['request_id'])) $data['request_id'] = date('YmdHi') . bin2hex(random_bytes(3));
    $data['serviceID'] = $serviceId;
    $headers = ["Content-Type: application/json"];

    if (!empty($creds['username']) && !empty($creds['password'])) {
        $headers[] = "Authorization: Basic " . base64_encode($creds['username'] . ":" . $creds['password']);
    } elseif (!empty($creds['apiKey'])) {
        $headers[] = "api-key: " . $creds['apiKey'];
        $headers[] = "secret-key: " . ($creds['secretKey'] ?? '');
    }

    $url = (!empty($creds['sandbox'])) ? "https://sandbox.vtpass.com/api/pay" : "https://vtpass.com/api/pay";
    return callApi($url, 'POST', $data, $headers);
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

/**
 * MEXC API (V3)
 */
if (!function_exists('callMexc')) {
function callMexc($pdo, $endpoint, $method = 'GET', $params = []) {
    $settings = fetchSettings($pdo);
    $creds = $settings['financialSettings']['mexc'] ?? [];
    $apiKey = $creds['apiKey'] ?? '';
    $secret = $creds['apiSecret'] ?? '';
    $baseUrl = "https://api.mexc.com";

    $timestamp = round(microtime(true) * 1000);
    $params['timestamp'] = $timestamp;
    $params['recvWindow'] = 5000;

    $queryString = http_build_query($params);
    $signature = hash_hmac('sha256', $queryString, $secret);
    $fullUrl = $baseUrl . $endpoint . "?" . $queryString . "&signature=" . $signature;

    $headers = [
        "X-MEXC-APIKEY: $apiKey",
        "Content-Type: application/json"
    ];

    return callApi($fullUrl, $method, [], $headers);
}
}

if (!function_exists('testMexcConnection')) {
function testMexcConnection($pdo) {
    $res = callMexc($pdo, '/api/v3/account', 'GET');
    if (isset($res['accountType'])) return ['status' => 'success', 'message' => 'MEXC Connection Successful!'];
    return ['status' => 'error', 'message' => 'MEXC Failed: ' . ($res['msg'] ?? 'Unknown Error')];
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

if (!function_exists('bybitGetDepositAddress')) {
function bybitGetDepositAddress($pdo, $coin, $chainType = 'TRC20') {
    return callBybit($pdo, '/v5/asset/deposit/query-address', 'GET', ['coin' => strtoupper($coin), 'chainType' => $chainType]);
}
}

if (!function_exists('bybitGetDepositRecords')) {
function bybitGetDepositRecords($pdo, $coin = '') {
    $params = [];
    if ($coin) $params['coin'] = strtoupper($coin);
    return callBybit($pdo, '/v5/asset/deposit/query-record', 'GET', $params);
}
}

/**
 * Unified Crypto Hub Helpers
 * These respect the 'primaryCrypto' setting
 */
if (!function_exists('cryptoGetBalances')) {
function cryptoGetBalances($pdo) {
    $settings = fetchSettings($pdo);
    $provider = $settings['financialSettings']['primaryCrypto'] ?? 'bybit';
    if ($provider === 'juicyway') return juicywayGetWallets($pdo);
    if ($provider === 'mexc') return mexcGetBalances($pdo);
    return bybitGetBalances($pdo);
}
}

if (!function_exists('cryptoWithdraw')) {
function cryptoWithdraw($pdo, $coin, $amount, $address, $tag = '') {
    $settings = fetchSettings($pdo);
    $provider = $settings['financialSettings']['primaryCrypto'] ?? 'bybit';
    if ($provider === 'juicyway') {
        return juicywayInitiatePayout($pdo, [
            'amount' => $amount,
            'currency' => strtoupper($coin),
            'method' => 'crypto',
            'destination' => [
                'address' => $address,
                'network' => 'TRC20', // Default or derived from coin
                'memo' => $tag
            ]
        ]);
    }
    if ($provider === 'mexc') return mexcWithdraw($pdo, $coin, $amount, $address, $tag);
    return bybitWithdraw($pdo, $coin, $amount, $address, $tag);
}
}

if (!function_exists('cryptoGetPrice')) {
function cryptoGetPrice($pdo, $coin) {
    // Currently using Bybit for price even if MEXC is primary,
    // but can be extended if needed. CoinGecko is also used for display.
    return bybitGetMarketPrice($pdo, $coin . 'USDT');
}
}

if (!function_exists('getApiCharge')) {
function getApiCharge($pdo, $key) {
    $settings = fetchSettings($pdo);
    $fs = $settings['financialSettings'] ?? [];
    if (is_string($fs)) $fs = json_decode($fs, true) ?: [];
    return (float)($fs['globalCharges'][$key] ?? 0);
}
}

if (!function_exists('mexcGetBalances')) {
function mexcGetBalances($pdo) {
    $res = callMexc($pdo, '/api/v3/account', 'GET');
    if (isset($res['balances'])) {
        // Normalize to match bybitGetBalances format if possible,
        // but since they are different APIs, the caller should handle it or we normalize here.
        // For now, returning raw.
    }
    return $res;
}
}

if (!function_exists('mexcWithdraw')) {
function mexcWithdraw($pdo, $coin, $amount, $address, $tag = '', $network = '') {
    $params = [
        'coin' => strtoupper($coin),
        'address' => $address,
        'amount' => (string)$amount
    ];
    if ($tag) $params['memo'] = $tag;
    if ($network) $params['network'] = $network;
    return callMexc($pdo, '/api/v3/capital/withdraw/apply', 'POST', $params);
}
}

if (!function_exists('mexcGetDepositAddress')) {
function mexcGetDepositAddress($pdo, $coin, $network = 'TRX') {
    return callMexc($pdo, '/api/v3/capital/deposit/address', 'GET', ['coin' => strtoupper($coin), 'network' => $network]);
}
}

if (!function_exists('mexcGetDepositRecords')) {
function mexcGetDepositRecords($pdo, $coin = '') {
    $params = [];
    if ($coin) $params['coin'] = strtoupper($coin);
    return callMexc($pdo, '/api/v3/capital/deposit/hisrec', 'GET', $params);
}
}

if (!function_exists('juicywayGetCryptoAddress')) {
function juicywayGetCryptoAddress($pdo, $coin, $network = 'TRC20') {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return ['status' => 'error', 'message' => 'User not in session'];

    // 1. Fetch all wallets
    $wallets = juicywayGetWallets($pdo);
    $targetWalletId = null;
    if (isset($wallets['data']) && is_array($wallets['data'])) {
        foreach ($wallets['data'] as $w) {
            if (strtoupper($w['currency'] ?? '') === strtoupper($coin)) {
                if (!empty($w['address'])) return ['status' => 'success', 'address' => $w['address']];
                if (!empty($w['payment_methods'])) {
                    foreach ($w['payment_methods'] as $pm) {
                        if ($pm['type'] === 'crypto_address' && !empty($pm['address'])) {
                            return ['status' => 'success', 'address' => $pm['address']];
                        }
                    }
                }
                $targetWalletId = $w['id'];
                break;
            }
        }
    }

    // 2. If no wallet, find or create customer
    if (!$targetWalletId) {
        $customers = callJuicyWay($pdo, 'customers', 'GET');
        $customerId = null;
        if (isset($customers['data']) && is_array($customers['data'])) {
            foreach ($customers['data'] as $c) {
                if (strtolower($c['email'] ?? '') === strtolower($user['email'])) {
                    $customerId = $c['id'];
                    break;
                }
            }
        }
        if (!$customerId) {
            $cRes = callJuicyWay($pdo, 'customers', 'POST', [
                'first_name' => explode(' ', $user['name'])[0],
                'last_name' => explode(' ', $user['name'])[1] ?? 'User',
                'email' => $user['email'],
                'phone' => $user['phone'] ?? ''
            ]);
            $customerId = $cRes['data']['id'] ?? null;
        }
        if ($customerId) {
            $wRes = callJuicyWay($pdo, 'wallets', 'POST', ['customer_id' => $customerId, 'currency' => strtoupper($coin)]);
            $targetWalletId = $wRes['data']['id'] ?? null;
        }
    }

    // 3. Set payment method and retrieve
    if ($targetWalletId) {
        callJuicyWay($pdo, "wallets/$targetWalletId/payment-method", 'POST', ['type' => 'crypto_address']);
        $finalRes = callJuicyWay($pdo, "wallets/$targetWalletId", 'GET');
        if (isset($finalRes['data']['payment_methods'])) {
             foreach ($finalRes['data']['payment_methods'] as $pm) {
                if ($pm['type'] === 'crypto_address' && !empty($pm['address'])) {
                    return ['status' => 'success', 'address' => $pm['address']];
                }
            }
        }
        if (!empty($finalRes['data']['address'])) return ['status' => 'success', 'address' => $finalRes['data']['address']];
    }

    return ['status' => 'error', 'message' => 'Failed to generate crypto address through full JuicyWay flow.'];
}
}

if (!function_exists('cryptoGetDepositAddress')) {
function cryptoGetDepositAddress($pdo, $coin, $network = 'TRC20') {
    $settings = fetchSettings($pdo);
    $provider = $settings['financialSettings']['primaryCrypto'] ?? 'bybit';
    if ($provider === 'juicyway') return juicywayGetCryptoAddress($pdo, $coin, $network);
    if ($provider === 'mexc') {
        $mNet = ($network === 'TRC20') ? 'TRX' : (($network === 'ERC20') ? 'ETH' : $network);
        return mexcGetDepositAddress($pdo, $coin, $mNet);
    }
    return bybitGetDepositAddress($pdo, $coin, $network);
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
    $businessId = $creds['businessId'] ?? '';
    $baseUrl = !empty($creds['liveMode']) ? "https://api.spendjuice.com/v1" : "https://api-sandbox.spendjuice.com/v1";

    $authHeader = (strpos($apiKey, 'Bearer') === 0 || empty($apiKey)) ? $apiKey : "Bearer " . $apiKey;
    $headers = [
        "Authorization: " . $authHeader,
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    if ($businessId) $headers[] = "X-Business-ID: $businessId";

    $url = $baseUrl . "/" . ltrim($endpoint, '/');
    if ($method === 'GET' && !empty($data)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        return callApi($url, 'GET', [], $headers);
    }
    return callApi($url, $method, $data, $headers);
}
}

if (!function_exists('juicywayGetWallets')) {
function juicywayGetWallets($pdo) {
    return callJuicyWay($pdo, 'wallets/all', 'GET');
}
}

if (!function_exists('juicywayGetBanks')) {
function juicywayGetBanks($pdo, $country = 'NG') {
    return callJuicyWay($pdo, "payment-methods/banks?country=$country", 'GET');
}
}

if (!function_exists('juicywayGetQuote')) {
function juicywayGetQuote($pdo, $amount, $from, $to) {
    $from = strtoupper($from);
    $to = strtoupper($to);
    // JuicyWay quote endpoint often fails if 'amount' or 'source_amount' is sent.
    // We fetch the base rate and calculate locally if needed, or use the response rate.
    $url = "exchange/quote?source_currency=$from&target_currency=$to&lock=true";
    return callJuicyWay($pdo, $url, 'GET');
}
}

if (!function_exists('juicywaySwap')) {
function juicywaySwap($pdo, $amount, $from, $to, $quoteId = null) {
    // JuicyWay exchange/swap endpoint primarily expects a quote_id.
    // If quote_id is present, we MUST NOT send amount or currency fields.
    if (!empty($quoteId) && $quoteId !== 'null' && $quoteId !== 'undefined') {
        $data = ['quote_id' => $quoteId];
    } else {
        // Direct swaps without a quote_id use amount, source_currency, and target_currency
        // Note: JuicyWay may require a quote_id for most swap operations.
        $minorAmount = (int)($amount * 100);
        $data = [
            'amount' => $minorAmount,
            'source_currency' => strtoupper($from),
            'target_currency' => strtoupper($to)
        ];
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

/**
 * REQUERY SERVICE
 */
if (!function_exists('requeryTransaction')) {
function requeryTransaction($pdo, $txId) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    if (!$tx) return ['status' => 'error', 'message' => 'Transaction not found'];

    $settings = fetchSettings($pdo);
    $provider = $tx['provider'] ?: 'manual';
    $ref = $tx['provider_ref'] ?: $tx['token'];

    if (!$ref || $provider === 'manual' || $provider === 'Internal' || $provider === 'System') {
        return ['status' => 'error', 'message' => 'Transaction not requeryable'];
    }

    $newStatus = null;
    $apiMsg = '';

    switch ($provider) {
        case 'vtpass':
            $us = $settings['utilitySettings']['vtpass'] ?? [];
            $headers = ["Content-Type: application/json"];
            if (!empty($us['username']) && !empty($us['password'])) {
                $headers[] = "Authorization: Basic " . base64_encode($us['username'] . ":" . $us['password']);
            } elseif (!empty($us['apiKey'])) {
                $headers[] = "api-key: " . $us['apiKey'];
                $headers[] = "public-key: " . ($us['publicKey'] ?? '');
            }
            $url = (!empty($us['sandbox'])) ? "https://sandbox.vtpass.com/api/requery" : "https://vtpass.com/api/requery";
            $res = callApi($url, 'POST', ['request_id' => $ref], $headers);
            if (isset($res['code']) && $res['code'] === '000') {
                $apiStatus = $res['content']['transactions']['status'] ?? '';
                if ($apiStatus === 'delivered' || $apiStatus === 'successful') $newStatus = 'successful';
                elseif ($apiStatus === 'failed') $newStatus = 'failed';
                $apiMsg = $res['response_description'] ?? $apiStatus;
            }
            break;

        case 'nellobyte':
            // Try to find if it was Airtime/Data to get correct creds
            $creds = [];
            if ($tx['type'] === 'Airtime') $creds = $settings['airtimeSettings']['providers']['nellobyte'] ?? [];
            else $creds = $settings['dataSettings']['providers']['nellobyte'] ?? [];

            $url = "https://www.nellobytesystems.com/APIQueryV1.asp?UserID=" . ($creds['userId'] ?? '') . "&APIKey=" . ($creds['apiKey'] ?? '') . "&OrderID=" . $ref;
            $res = callApi($url);
            if (isset($res['status'])) {
                if (strpos($res['status'], 'COMPLETED') !== false || strpos($res['status'], 'SUCCESSFUL') !== false) $newStatus = 'successful';
                elseif (strpos($res['status'], 'FAILED') !== false) $newStatus = 'failed';
                $apiMsg = $res['status'];
            }
            break;

        case 'datagifting':
            $creds = [];
            if ($tx['type'] === 'Airtime') $creds = $settings['airtimeSettings']['providers']['datagifting'] ?? [];
            else $creds = $settings['dataSettings']['providers']['datagifting'] ?? [];
            $apiKey = $creds['apiKey'] ?? '';

            $res = callApi("https://v6.datagifting.com.ng/api/requery.php?api_key=$apiKey&reference=$ref");
            if (isset($res['status'])) {
                if ($res['status'] === 'success') $newStatus = 'successful';
                elseif ($res['status'] === 'fail' || $res['status'] === 'failed') $newStatus = 'failed';
                $apiMsg = $res['desc'] ?? $res['msg'] ?? $res['status'];
            }
            break;

        case 'datastation':
        case 'hdkdata':
            $creds = [];
            $baseUrl = ($provider === 'datastation') ? 'https://datastationapi.com' : 'https://hdkdata.com';
            if ($tx['type'] === 'Airtime') $creds = $settings['airtimeSettings']['providers'][$provider] ?? [];
            else $creds = $settings['dataSettings']['providers'][$provider] ?? [];

            $res = callApi("$baseUrl/api/status/$ref", 'GET', [], ["Authorization: Token " . ($creds['token'] ?? '')]);
            if (isset($res['Status'])) {
                if ($res['Status'] === 'successful') $newStatus = 'successful';
                elseif ($res['Status'] === 'failed') $newStatus = 'failed';
                $apiMsg = $res['Status'];
            }
            break;

        case 'juicyway':
            $res = callJuicyWay($pdo, "transactions/$ref", 'GET');
            if (isset($res['data']['status'])) {
                if ($res['data']['status'] === 'success' || $res['data']['status'] === 'successful') $newStatus = 'successful';
                elseif ($res['data']['status'] === 'failed' || $res['data']['status'] === 'fail') $newStatus = 'failed';
                $apiMsg = $res['data']['status'];
            }
            break;
    }

    if ($newStatus && $newStatus !== $tx['status']) {
        if (changeTransactionStatus($pdo, $txId, $newStatus)) {
            $pdo->prepare("UPDATE transactions SET details = CONCAT(details, ' | Requery: ', ?), last_queried_at = NOW(), query_count = query_count + 1 WHERE id = ?")
                ->execute([$apiMsg, $txId]);
            return ['status' => 'success', 'new_status' => $newStatus, 'message' => "Updated to $newStatus"];
        }
    }

    $pdo->prepare("UPDATE transactions SET last_queried_at = NOW(), query_count = query_count + 1 WHERE id = ?")->execute([$txId]);
    return ['status' => 'no_change', 'current_status' => $tx['status'], 'message' => $apiMsg ?: 'No change detected'];
}
}

if (!function_exists('nellobyteGetBettingCompanies')) {
function nellobyteGetBettingCompanies($pdo) {
    $settings = fetchSettings($pdo);
    $us = $settings['utilitySettings'] ?? [];
    $creds = $us['nellobyte'] ?? [];
    $userId = $creds['userId'] ?? '';
    $url = "https://www.nellobytesystems.com/APIBettingCompaniesV2.asp?UserID=$userId";
    return callApi($url);
}
}

if (!function_exists('getReloadlyAccessToken')) {
function getReloadlyAccessToken($pdo) {
    $settings = fetchSettings($pdo);
    $os = $settings['otherApiSettings']['reloadly'] ?? [];
    $clientId = $os['clientId'] ?? '';
    $clientSecret = $os['clientSecret'] ?? '';
    if (empty($clientId) || empty($clientSecret)) return null;

    $cacheKey = 'reloadly_token';
    if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey . '_expiry'] > time()) {
        return $_SESSION[$cacheKey];
    }

    $res = callApi("https://auth.reloadly.com/oauth/token", 'POST', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials',
        'audience' => 'https://giftcards.reloadly.com'
    ], ['Content-Type: application/json']);

    if (isset($res['access_token'])) {
        $_SESSION[$cacheKey] = $res['access_token'];
        $_SESSION[$cacheKey . '_expiry'] = time() + ($res['expires_in'] ?? 3600) - 60;
        return $res['access_token'];
    }
    return null;
}
}

if (!function_exists('getReloadlyGiftCards')) {
function getReloadlyGiftCards($pdo) {
    $token = getReloadlyAccessToken($pdo);
    if (!$token) return ['status' => 'error', 'message' => 'Reloadly not configured'];
    return callApi("https://giftcards.reloadly.com/products", 'GET', [], ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json"]);
}
}

if (!function_exists('purchaseReloadlyGiftCard')) {
function purchaseReloadlyGiftCard($pdo, $productId, $amount, $recipientEmail, $quantity = 1) {
    $token = getReloadlyAccessToken($pdo);
    if (!$token) return ['status' => 'error', 'message' => 'Reloadly not configured'];
    $payload = [
        'productId' => $productId,
        'quantity' => $quantity,
        'unitPrice' => $amount,
        'senderName' => 'BillPay User',
        'recipientEmail' => $recipientEmail,
        'customIdentifier' => 'JW-' . uniqid()
    ];
    return callApi("https://giftcards.reloadly.com/orders", 'POST', $payload, ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json", "Content-Type: application/json"]);
}
}

if (!function_exists('nellobyteGetDataEPINPlans')) {
function nellobyteGetDataEPINPlans($pdo) {
    $settings = fetchSettings($pdo);
    $us = $settings['utilitySettings']['nellobyte'] ?? [];
    $userId = $us['userId'] ?? '';
    // Documentation suggests APIDatabundlePlansV2 for all data plans including EPIN
    $url = "https://www.nellobytesystems.com/APIDatabundlePlansV2.asp?UserID=$userId";
    return callApi($url);
}
}

if (!function_exists('purchaseDataEPIN')) {
function purchaseDataEPIN($pdo, $networkCode, $planId, $quantity = 1) {
    $settings = fetchSettings($pdo);
    $us = $settings['utilitySettings']['nellobyte'] ?? [];
    $userId = $us['userId'] ?? '';
    $apiKey = $us['apiKey'] ?? '';
    $requestId = date('YmdHi') . bin2hex(random_bytes(4));
    $url = "https://www.nellobytesystems.com/APIDatabundleEPINV1.asp?UserID=$userId&APIKey=$apiKey&MobileNetwork=$networkCode&DataPlan=$planId&Quantity=$quantity&RequestID=$requestId";
    return callApi($url);
}
}

if (!function_exists('datagiftingGetDataPlans')) {
function datagiftingGetDataPlans($pdo) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];
    $creds = $ds['providers']['datagifting'] ?? [];
    $apiKey = $creds['apiKey'] ?? '';

    $url = "https://v6.datagifting.com.ng/api/data-plans.php?api_key=$apiKey";
    return callApi($url);
}
}

if (!function_exists('nellobyteGetDataPlans')) {
function nellobyteGetDataPlans($pdo) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];
    $creds = $ds['providers']['nellobyte'] ?? [];
    $userId = $creds['userId'] ?? '';

    $url = "https://www.nellobytesystems.com/APIDatabundlePlansV2.asp?UserID=$userId";
    return callApi($url);
}
}

if (!function_exists('getCryptoPrices')) {
function getCryptoPrices() {
    return callApi("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,solana,tether&vs_currencies=ngn,usd&include_24hr_change=true");
}
}

/**
 * BETTING (Nellobyte)
 */
if (!function_exists('naijaresultpinsExams')) {
function naijaresultpinsExams($pdo, $action, $params = []) {
    $settings = fetchSettings($pdo);
    $os = $settings['otherApiSettings'] ?? [];
    $creds = $os['naijaresultpins'] ?? [];
    $apiKey = $creds['apiKey'] ?? '';

    $url = "https://naijaresultpins.com/api/$action?api_key=$apiKey";
    foreach ($params as $k => $v) { $url .= "&$k=" . urlencode($v); }
    return callApi($url);
}
}

if (!function_exists('nellobyteBetting')) {
function nellobyteBetting($pdo, $action, $params = []) {
    $settings = fetchSettings($pdo);
    $us = $settings['utilitySettings'] ?? [];
    $creds = $us['nellobyte'] ?? [];
    $userId = $creds['userId'] ?? '';
    $apiKey = $creds['apiKey'] ?? '';

    $url = "https://www.nellobytesystems.com/APIBettingV1.asp?UserID=$userId&APIKey=$apiKey&Action=$action";
    foreach ($params as $k => $v) {
        $url .= "&$k=" . urlencode($v);
    }
    return callApi($url);
}
}
