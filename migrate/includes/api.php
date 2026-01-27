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
 * Crypto Prices (CoinGecko)
 */
function getCryptoPrices() {
    $url = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,solana,tether&vs_currencies=ngn,usd&include_24hr_change=true";
    return callApi($url);
}
