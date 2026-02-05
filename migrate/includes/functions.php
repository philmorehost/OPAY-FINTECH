<?php
// Core Functions

if (!function_exists('sanitize')) {
function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(strip_tags(trim($data)));
}
}

if (!function_exists('formatCurrency')) {
function formatCurrency($amount) {
    return '₦' . number_format((float)$amount, 2);
}
}

if (!function_exists('generateId')) {
function generateId($prefix = '') {
    return $prefix . bin2hex(random_bytes(4));
}
}

if (!function_exists('isLoggedIn')) {
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
}

if (!function_exists('isAdmin')) {
function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || isset($_SESSION['original_admin_id']);
}
}

if (!function_exists('checkKycRestriction')) {
function checkKycRestriction($settings, $currentUser) {
    if (!isset($settings['isKycEnforced']) || !$settings['isKycEnforced']) return;
    if (($currentUser['kycStatus'] ?? 'none') !== 'verified') {
        header('Location: /kyc' . ($currentUser['kycStatus'] === 'rejected' ? '?error=restricted' : ''));
        exit;
    }
}
}

if (!function_exists('isKycRejected')) {
function isKycRejected($user) {
    return ($user['kycStatus'] ?? 'none') === 'rejected';
}
}

if (!function_exists('checkMinDepositRestriction')) {
function checkMinDepositRestriction($settings, $currentUser) {
    if (!isset($settings['isMinDepositForced']) || !$settings['isMinDepositForced']) return false;
    return !($currentUser['hasCompletedInitialDeposit'] ?? false);
}
}

if (!function_exists('claimDailyRewardIfEligible')) {
function claimDailyRewardIfEligible($pdo, $userId) {
    $today = date('Y-m-d');

    // 1. Check if already rewarded today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND type = 'Daily Reward' AND DATE(date) = ?");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetchColumn() > 0) return;

    // 2. Ensure at least one successful transaction exists for today (excluding daily rewards)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND status = 'successful' AND type != 'Daily Reward' AND DATE(date) = ?");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetchColumn() == 0) return;

    $stmt = $pdo->query("SELECT bonusPerDay FROM settings WHERE id = 1");
    $bonus = $stmt->fetchColumn();

    if ($bonus > 0) {
        $started = false; if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $started = true; }
        try {
            $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins + ? WHERE id = ?")->execute([$bonus, $userId]);
            logTransaction($pdo, $userId, 'Daily Reward', $bonus, 'successful', "Daily check-in reward", 'Wallet', 'System');
            if ($started) $pdo->commit();
        } catch (Exception $e) { if ($started) $pdo->rollBack(); }
    }
}
}

if (!function_exists('redirect')) {
function redirect($path) {
    header("Location: $path");
    exit;
}
}

if (!function_exists('generateCsrfToken')) {
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('verifyCsrfToken')) {
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
}

if (!function_exists('startSecureSession')) {
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}
}

if (!function_exists('fetchActiveOffers')) {
function fetchActiveOffers($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM offers WHERE expiryDate > NOW() ORDER BY createdAt DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}
}

if (!function_exists('fetchSettings')) {
function fetchSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $json_fields = ['dataNetworks', 'cableProviders', 'electricProviders', 'bettingProviders', 'airtimeDiscounts', 'dataProducts', 'airtimeSettings', 'dataSettings', 'utilitySettings', 'financialSettings', 'otherApiSettings', 'loginSecuritySettings'];
        foreach($json_fields as $f) {
            if(isset($settings[$f]) && !empty($settings[$f])) {
                $val = json_decode($settings[$f], true) ?: [];
                $settings[$f] = $val;

                // Compatibility Flattening (e.g. paystackSecretKey)
                if ($f === 'financialSettings') {
                    if (isset($val['paystack']['secretKey'])) $settings['paystackSecretKey'] = $val['paystack']['secretKey'];
                    if (isset($val['paystack']['publicKey'])) $settings['paystackPublicKey'] = $val['paystack']['publicKey'];
                    if (isset($val['virtual_card_fee'])) $settings['vcardIssuanceFee'] = $val['virtual_card_fee'];
                }
            }
        }
    }
    return $settings;
}
}

if (!function_exists('fetchUser')) {
function fetchUser($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && isset($user['configuredSecurityMethods'])) {
        $user['configuredSecurityMethods'] = json_decode($user['configuredSecurityMethods'], true) ?: [];
    }
    return $user;
}
}

if (!function_exists('updateWallet')) {
function updateWallet($pdo, $userId, $amount, $type = 'credit') {
    $op = ($type === 'credit') ? '+' : '-';
    $res = $pdo->prepare("UPDATE users SET walletBalance = walletBalance $op ? WHERE id = ?")->execute([$amount, $userId]);
    if ($res && $type === 'credit') {
        $stmt = $pdo->prepare("SELECT walletBalance FROM users WHERE id = ?"); $stmt->execute([$userId]);
        $bal = (float)$stmt->fetchColumn();
        $min = (float)$pdo->query("SELECT minDepositAmount FROM settings WHERE id = 1")->fetchColumn();
        if ($bal >= $min) $pdo->prepare("UPDATE users SET hasCompletedInitialDeposit = 1 WHERE id = ?")->execute([$userId]);
    }
    return $res;
}
}

if (!function_exists('checkDailyLimit')) {
function checkDailyLimit($pdo, $userId, $recipient, $limit) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND recipient = ? AND DATE(date) = CURDATE() AND status = 'successful'");
    $stmt->execute([$userId, $recipient]);
    return $stmt->fetchColumn() < $limit;
}
}

if (!function_exists('checkRateLimit')) {
function checkRateLimit($key, $limit = 5, $period = 60) {
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    $data = &$_SESSION['rate_limit'][$key];
    if (time() - $data['start'] > $period) {
        $data = ['count' => 1, 'start' => time()];
        return true;
    }
    if ($data['count'] >= $limit) return false;
    $data['count']++;
    return true;
}
}

if (!function_exists('require2fa')) {
function require2fa($currentUser) {
    if ($currentUser['google2faEnabled'] || $currentUser['email2faEnabled']) {
        if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== $currentUser['id']) {
            $_SESSION['2fa_redirect'] = $_SERVER['REQUEST_URI'];
            header('Location: /2fa-verify');
            exit;
        }
    }
}
}

if (!function_exists('verifyFundPassword')) {
function verifyFundPassword($pdo, $userId, $password) {
    $stmt = $pdo->prepare("SELECT fundPassword FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    return $hash && password_verify($password, $hash);
}
}

if (!function_exists('logTransaction')) {
function logTransaction($pdo, $userId, $type, $amount, $status, $details, $recipient, $provider = null, $token = null, $apiAmount = 0, $profit = 0, $providerRef = null, $refunded = 0) {
    $id = 'TX-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $pdo->prepare("INSERT INTO transactions (id, userId, type, amount, status, details, recipient, provider, token, apiAmount, profit, provider_ref, refunded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$id, $userId, $type, $amount, $status, $details, $recipient, $provider, $token, $apiAmount, $profit, $providerRef, $refunded]);
}
}

if (!function_exists('changeTransactionStatus')) {
function changeTransactionStatus($pdo, $txId, $newStatus) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    if (!$tx) return false;

    if ($tx['status'] === $newStatus) return true;

    $pdo->beginTransaction();
    try {
        // failed -> successful: DEBIT user (if not already debited)
        if ($tx['status'] === 'failed' && $newStatus === 'successful') {
             // If 'refunded' flag is 1, we re-debit.
             if ($tx['refunded']) {
                updateWallet($pdo, $tx['userId'], $tx['amount'], 'debit');
                $pdo->prepare("UPDATE transactions SET refunded = 0 WHERE id = ?")->execute([$txId]);
             }
        }
        // any active state -> failed: REFUND user (if not already refunded)
        elseif (($tx['status'] === 'successful' || $tx['status'] === 'pending') && $newStatus === 'failed') {
            if (!$tx['refunded']) {
                updateWallet($pdo, $tx['userId'], $tx['amount'], 'credit');
                $pdo->prepare("UPDATE transactions SET refunded = 1 WHERE id = ?")->execute([$txId]);
            }
        }

        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $txId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
}

if (!function_exists('sendEmail2fa')) {
function sendEmail2fa($pdo, $user) {
    $code = rand(100000, 999999);
    $_SESSION['email_2fa_code'] = $code;
    $_SESSION['email_2fa_expiry'] = time() + 300; // 5 mins
    $subject = "Your 2FA Verification Code";
    $message = "Your verification code is: <b>$code</b>. It expires in 5 minutes.";
    return sendMail($pdo, $user['email'], $subject, $message);
}
}

if (!function_exists('sendMail')) {
function sendMail($pdo, $to, $subject, $message, $status = 'successful') {
    $settings = $pdo->query("SELECT senderName, fromEmail, primaryColor FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $name = $settings['senderName'] ?? 'Billpay Support';
    $from = $settings['fromEmail'] ?? 'no-reply@' . $_SERVER['HTTP_HOST'];
    $color = $settings['primaryColor'] ?? '#00c689';

    $statusColor = ($status === 'failed') ? '#ef4444' : (($status === 'pending') ? '#f59e0b' : $color);
    $statusLabel = strtoupper($status);

    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: $name <$from>";
    $body = "
    <html>
    <body style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;background-color:#f8fafc;margin:0;padding:40px;'>
        <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:32px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.03);'>
            <div style='background-color:$color;padding:40px;text-align:center;'>
                <div style='background-color:rgba(255,255,255,0.2);display:inline-block;padding:12px;border-radius:16px;margin-bottom:20px;'>
                    <span style='color:#ffffff;font-weight:900;font-size:24px;text-transform:uppercase;'>B</span>
                </div>
                <h2 style='color:#ffffff;margin:0;font-size:20px;font-weight:900;text-transform:uppercase;letter-spacing:1px;'>$subject</h2>
            </div>
            <div style='padding:40px;'>
                <div style='margin-bottom:30px;text-align:center;'>
                    <span style='background-color:{$statusColor}15;color:$statusColor;padding:8px 16px;border-radius:100px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:1px;border:1px solid {$statusColor}30;'>$statusLabel</span>
                </div>
                <div style='color:#4b5563;font-size:14px;line-height:1.6;font-weight:500;'>$message</div>
                <hr style='border:none;border-top:1px solid #f1f5f9;margin:40px 0;'>
                <div style='text-align:center;'>
                    <p style='font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>&copy; ".date('Y')." $name. All rights reserved.</p>
                    <p style='font-size:9px;color:#cbd5e1;font-weight:600;margin-top:10px;'>This is an automated security notification. If you did not expect this, please contact support immediately.</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    return @mail($to, $subject, $body, $headers);
}
}
