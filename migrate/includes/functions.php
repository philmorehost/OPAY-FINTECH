<?php
// migrate/includes/functions.php
function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }
function format_currency($amount) { return '₦' . number_format($amount, 2); }
function detect_network($phone) {
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if (strpos($cleanPhone, '234') === 0) { $cleanPhone = '0' . substr($cleanPhone, 3); }
    $prefix4 = substr($cleanPhone, 0, 4);
    $mtn = ['0703', '0706', '0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916'];
    $airtel = ['0701', '0708', '0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912', '0911'];
    $glo = ['0705', '0805', '0807', '0811', '0815', '0905', '0915'];
    $nineMobile = ['0809', '0817', '0818', '0908', '0909'];
    if (in_array($prefix4, $mtn)) return 'MTN';
    if (in_array($prefix4, $airtel)) return 'Airtel';
    if (in_array($prefix4, $glo)) return 'Glo';
    if (in_array($prefix4, $nineMobile)) return '9mobile';
    return '';
}
function generate_id($length = 9) { return strtoupper(substr(bin2hex(random_bytes(10)), 0, $length)); }
function redirect($path) { header("Location: $path"); exit; }
function is_logged_in() { return isset($_SESSION['user_id']); }
function get_current_user_data() {
    global $pdo;
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
function require_login() {
    if (!is_logged_in()) { redirect('login'); }
    $user = get_current_user_data();
    if ($user && $user['isSuspended']) {
        // Only block if NOT being impersonated by an admin
        if (empty($_SESSION['original_admin_id'])) {
            session_destroy();
            die("ACCOUNT SUSPENDED. CONTACT SUPPORT.");
        }
    }
}
function require_admin() {
    $user = get_current_user_data();
    if (!$user || $user['role'] !== 'admin') { redirect('dashboard'); }
}
function log_transaction($userId, $type, $amount, $status, $details, $recipient, $provider = null) {
    global $pdo;
    $id = generate_id();
    $stmt = $pdo->prepare("INSERT INTO transactions (id, userId, type, amount, status, date, details, recipient, provider) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $userId, $type, $amount, $status, date('c'), $details, $recipient, $provider]);
    return $id;
}
function check_and_apply_loyalty_bonus($userId) {
    global $pdo, $settings;
    $user = get_current_user_data();
    if (!$user) return false;
    $today = date('Y-m-d');
    if (($user['lastPurchaseDate'] ?? '') !== $today) {
        $bonus = (float)($settings['bonusPerDay'] ?? 20);
        $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins + ?, lastPurchaseDate = ? WHERE id = ?");
        $stmt->execute([$bonus, $today, $userId]);
        return true;
    }
    return false;
}
function update_user_balance($userId, $amount) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET walletBalance = walletBalance + ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);
}
function calculate_airtime_price($rawAmount, $networkName) {
    global $settings;
    $discounts = json_decode($settings['airtimeDiscounts'] ?? '{}', true);
    $netKey = ($networkName === '9mobile') ? 'nineMobile' : strtolower($networkName);
    $discountPercent = $discounts[$netKey] ?? 0;
    return $rawAmount * (1 - $discountPercent / 100);
}

function call_nellobyte_api($phone, $netCode, $amt, $reqId) {
    global $settings;
    $userId = $settings['nellobyteUserId'] ?? '';
    $apiKey = $settings['nellobyteApiKey'] ?? '';
    $url = "https://www.nellobytesystems.com/APIAirtimeV1.asp?UserID=$userId&APIKey=$apiKey&MobileNetwork=$netCode&Amount=$amt&MobileNumber=$phone&RequestID=$reqId";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function send_kudi_sms($recipients, $message, $senderId) {
    global $settings;
    $token = $settings['kudiSmsToken'] ?? '';
    $url = "https://my.kudisms.net/api/sms?token=" . urlencode($token) . "&senderID=" . urlencode($senderId) . "&recipients=" . urlencode($recipients) . "&message=" . urlencode($message) . "&gateway=2";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function submit_kudi_sender_id($senderId, $message) {
    global $settings;
    $token = $settings['kudiSmsToken'] ?? '';
    $url = "https://my.kudisms.net/api/senderID";
    $postData = ['token' => $token, 'senderID' => $senderId, 'message' => $message];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function check_kudi_sender_id_status($senderId) {
    global $settings;
    $token = $settings['kudiSmsToken'] ?? '';
    $url = "https://my.kudisms.net/api/check_senderID?token=" . urlencode($token) . "&senderID=" . urlencode($senderId);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function calculate_sms_cost($message, $recipientCount) {
    global $settings;
    $charLimit = 160;
    $pages = ceil(mb_strlen($message) / $charLimit);
    if ($pages < 1) $pages = 1;
    $rate = (float)($settings['smsRate'] ?? 4.5);
    return ['pages' => (int)$pages, 'totalCost' => $pages * $recipientCount * $rate];
}
