<?php
/**
 * Centralized API Integration Layer for BillPay Fintech
 * Handles communication with external service providers
 */

if (!function_exists('callApi')) {
function callApi($url, $method = 'GET', $data = [], $headers = []) {
    $curl = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($method === 'POST') $opts[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
    if (!empty($headers)) $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) return ['status' => 'error', 'message' => $err];
    return json_decode($response, true) ?: $response;
}
}

/**
 * Helper: Network Code Mapping
 */
if (!function_exists('getNetworkCode')) {
function getNetworkCode($provider, $network) {
    $network = strtoupper($network);
    $map = [
        'nellobyte' => ['MTN' => '01', 'AIRTEL' => '02', 'GLO' => '03', '9MOBILE' => '04'],
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
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => 'success', 'message' => 'Sim Success', 'ref' => 'SIM-' . uniqid()];
    $provider = $as['routing'][$network] ?? 'datagifting';
    $creds = $as['providers'][$provider] ?? [];
    $netCode = getNetworkCode($provider, $network);
    switch ($provider) {
        case 'datagifting':
            $res = callApi("https://v6.datagifting.com.ng/web/api/airtime.php?api_key=" . ($creds['apiKey'] ?? '') . "&network=$netCode&amount=$amount&phone_number=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $res = callApi("https://nellobyte.com/api/airtime?userid=" . ($creds['userId'] ?? '') . "&apikey=" . ($creds['apiKey'] ?? '') . "&network=$netCode&amount=$amount&phone=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'hdkdata':
            $res = callApi("https://hdkdata.com/api/airtime/", 'POST', ["network" => $network, "amount" => $amount, "mobile_number" => $phone, "Ported_number" => true, "airtime_type" => "VTU"], ["Authorization: Token " . ($creds['token'] ?? ''), "Content-Type: application/json"]);
            if (isset($res['Status']) && strtolower($res['Status']) === 'successful') return ['status' => 'success', 'message' => 'Successful', 'ref' => $res['id'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['error'][0] ?? 'Provider Error'];
    }
    return ['status' => 'failed', 'message' => 'No provider'];
}
}

if (!function_exists('purchaseData')) {
function purchaseData($pdo, $network, $planId, $phone) {
    $settings = fetchSettings($pdo);
    $ds = $settings['dataSettings'] ?? [];
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => 'success', 'message' => 'Sim Success', 'ref' => 'SIM-' . uniqid()];
    $provider = $ds['routing'][$network] ?? 'datagifting';
    $creds = $ds['providers'][$provider] ?? [];
    $netCode = getNetworkCode($provider, $network);
    switch ($provider) {
        case 'datagifting':
            $res = callApi("https://v6.datagifting.com.ng/web/api/data.php?api_key=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone_number=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'nellobyte':
            $res = callApi("https://nellobyte.com/api/data?userid=" . ($creds['userId'] ?? '') . "&apikey=" . ($creds['apiKey'] ?? '') . "&network=$netCode&plan=$planId&phone=$phone");
            if (isset($res['status']) && $res['status'] === 'success') return ['status' => 'success', 'message' => $res['msg'] ?? 'Successful', 'ref' => $res['ref'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['msg'] ?? 'Provider Error'];
        case 'hdkdata':
            $res = callApi("https://hdkdata.com/api/data/", 'POST', ["network" => $network, "plan" => $planId, "mobile_number" => $phone, "Ported_number" => true], ["Authorization: Token " . ($creds['token'] ?? ''), "Content-Type: application/json"]);
            if (isset($res['Status']) && strtolower($res['Status']) === 'successful') return ['status' => 'success', 'message' => 'Successful', 'ref' => $res['id'] ?? uniqid()];
            return ['status' => 'failed', 'message' => $res['error'][0] ?? 'Provider Error'];
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
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['code' => '000', 'content' => ['transactions' => ['status' => 'delivered']], 'response_description' => 'Sim Success'];
    $creds = $settings['utilitySettings']['vtpass'] ?? [];
    if (!isset($data['request_id'])) $data['request_id'] = date('YmdHi') . bin2hex(random_bytes(3));
    $data['serviceID'] = $serviceId;

    $headers = ["Content-Type: application/json"];
    if (!empty($creds['apiKey'])) {
        $headers[] = "api-key: " . $creds['apiKey'];
        $headers[] = "secret-key: " . ($creds['secretKey'] ?? '');
        $headers[] = "public-key: " . ($creds['publicKey'] ?? '');
    } elseif (!empty($creds['username']) && !empty($creds['password'])) {
        $headers[] = "Authorization: Basic " . base64_encode($creds['username'] . ":" . $creds['password']);
    }

    return callApi("https://vtpass.com/api/pay", 'POST', $data, $headers);
}
}

/**
 * SMS
 */
if (!function_exists('sendKudiSms')) {
function sendKudiSms($pdo, $sender, $message, $recipients) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => 'success'];
    $creds = $settings['otherApiSettings']['kudisms'] ?? [];
    $query = http_build_query(['token' => $creds['token'] ?? '', 'sender' => $sender ?: ($creds['sender'] ?? 'BillPay'), 'message' => $message, 'recipients' => $recipients]);
    return callApi("https://my.kudisms.net/api/sms?" . $query);
}
}

/**
 * VIRTUAL CARDS
 */
if (!function_exists('callJuicyWay')) {
function callJuicyWay($pdo, $endpoint, $method = 'POST', $data = []) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => 'success', 'data' => ['id' => 'VC-' . uniqid(), 'card_number' => '4111222233334444', 'cvv' => '123', 'expiry' => '12/26']];
    $creds = $settings['financialSettings']['juicyway'] ?? [];
    return callApi("https://api.juicyway.com/v1/$endpoint", $method, $data, ["Authorization: Bearer " . ($creds['apiKey'] ?? ''), "Content-Type: application/json"]);
}
}

/**
 * GIFT CARDS
 */
if (!function_exists('getReloadlyToken')) {
function getReloadlyToken($pdo) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return 'SIM-TOKEN';
    $creds = $settings['otherApiSettings']['reloadly'] ?? [];
    $res = callApi("https://auth.reloadly.com/oauth/token", 'POST', ['client_id' => $creds['clientId'] ?? '', 'client_secret' => $creds['clientSecret'] ?? '', 'grant_type' => 'client_credentials', 'audience' => 'https://giftcards.reloadly.com'], ["Content-Type: application/json"]);
    return $res['access_token'] ?? null;
}
}

if (!function_exists('getReloadlyGiftCards')) {
function getReloadlyGiftCards($pdo) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['content' => [['productId' => 1, 'productName' => 'Amazon US', 'denominationType' => 'FIXED', 'fixedDenominations' => [10, 25, 50]]]];
    $token = getReloadlyToken($pdo);
    if (!$token) return ['content' => []];
    return callApi("https://giftcards.reloadly.com/products", 'GET', [], ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json"]);
}
}

if (!function_exists('purchaseReloadlyGiftCard')) {
function purchaseReloadlyGiftCard($pdo, $productId, $amount, $recipientEmail) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => 'SUCCESS', 'transactionId' => 'SIM-' . uniqid()];
    $token = getReloadlyToken($pdo);
    if (!$token) return ['status' => 'FAILED', 'message' => 'Token failed'];
    return callApi("https://giftcards.reloadly.com/orders", 'POST', ['productId' => $productId, 'quantity' => 1, 'unitPrice' => $amount, 'recipientEmail' => $recipientEmail, 'customIdentifier' => 'BILL-' . uniqid()], ["Authorization: Bearer $token", "Accept: application/com.reloadly.giftcards-v1+json", "Content-Type: application/json"]);
}
}

/**
 * PAYSTACK
 */
if (!function_exists('createPaystackCustomer')) {
function createPaystackCustomer($pdo, $user) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => true, 'data' => ['customer_code' => 'CUS_sim' . uniqid()]];
    $creds = $settings['financialSettings']['paystack'] ?? [];
    return callApi("https://api.paystack.co/customer", 'POST', ['email' => $user['email'], 'first_name' => explode(' ', $user['fullName'])[0], 'last_name' => explode(' ', $user['fullName'])[1] ?? 'User', 'phone' => $user['phone']], ["Authorization: Bearer " . ($creds['secretKey'] ?? ''), "Content-Type: application/json"]);
}
}

if (!function_exists('createPaystackDedicatedAccount')) {
function createPaystackDedicatedAccount($pdo, $customerCode) {
    $settings = fetchSettings($pdo);
    if (!empty($settings['otherApiSettings']['simulationMode'])) return ['status' => true, 'data' => ['bank' => ['name' => 'Sim Bank'], 'account_number' => mt_rand(1000000000, 9999999999), 'account_name' => 'SIM ACCOUNT']];
    $creds = $settings['financialSettings']['paystack'] ?? [];
    return callApi("https://api.paystack.co/dedicated_account", 'POST', ['customer' => $customerCode, 'preferred_bank' => 'wema-bank'], ["Authorization: Bearer " . ($creds['secretKey'] ?? ''), "Content-Type: application/json"]);
}
}

if (!function_exists('getCryptoPrices')) {
function getCryptoPrices() {
    return callApi("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,solana,tether&vs_currencies=ngn,usd&include_24hr_change=true");
}
}
