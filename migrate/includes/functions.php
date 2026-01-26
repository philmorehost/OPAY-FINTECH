<?php
// Core Functions

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return '₦' . number_format((float)$amount, 2);
}

function generateId($prefix = '') {
    return $prefix . bin2hex(random_bytes(4));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || isset($_SESSION['original_admin_id']);
}

function checkKycRestriction($settings, $currentUser) {
    if (!isset($settings['isKycEnforced']) || !$settings['isKycEnforced']) {
        return; // KYC not enforced
    }

    if ($currentUser['kycStatus'] !== 'verified') {
        if ($currentUser['kycStatus'] === 'rejected') {
            // Redirect to KYC with error or just let KYC page show the rejection
            header('Location: /kyc?error=restricted');
        } else {
            header('Location: /kyc');
        }
        exit;
    }
}

function isKycRejected($user) {
    return ($user['kycStatus'] ?? 'none') === 'rejected';
}

function claimDailyRewardIfEligible($pdo, $userId) {
    $today = date('Y-m-d');

    // Check if already claimed today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND type = 'Daily Reward' AND DATE(date) = ?");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetchColumn() > 0) {
        return; // Already claimed today
    }

    $stmt = $pdo->prepare("SELECT bonusPerDay FROM settings WHERE id = 1");
    $stmt->execute();
    $bonus = $stmt->fetchColumn();

    if ($bonus > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET bonusCoins = bonusCoins + ? WHERE id = ?");
            $stmt->execute([$bonus, $userId]);
            logTransaction($pdo, $userId, 'Daily Reward', $bonus, 'successful', "Daily check-in reward (Auto-claimed)", 'Wallet', 'System');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

function checkMinDepositRestriction($settings, $currentUser) {
    if (!isset($settings['isMinDepositForced']) || !$settings['isMinDepositForced']) {
        return false;
    }
    return !($currentUser['hasCompletedInitialDeposit'] ?? false);
}

function redirect($path) {
    header("Location: $path");
    exit;
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session security
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Database helper
function fetchActiveOffers($pdo) {
    $stmt = $pdo->query("SELECT * FROM offers WHERE expiryDate > NOW() ORDER BY createdAt DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $settings['dataNetworks'] = json_decode($settings['dataNetworks'], true) ?: [];
        $settings['cableProviders'] = json_decode($settings['cableProviders'], true) ?: [];
        $settings['electricProviders'] = json_decode($settings['electricProviders'], true) ?: [];
        $settings['bettingProviders'] = json_decode($settings['bettingProviders'], true) ?: [];
        $settings['airtimeDiscounts'] = json_decode($settings['airtimeDiscounts'], true) ?: [];
        $settings['dataProducts'] = json_decode($settings['dataProducts'] ?? '[]', true) ?: [];
    }
    return $settings;
}

function updateWallet($pdo, $userId, $amount, $type = 'credit') {
    $operator = ($type === 'credit') ? '+' : '-';
    $stmt = $pdo->prepare("UPDATE users SET walletBalance = walletBalance $operator ? WHERE id = ?");
    $res = $stmt->execute([$amount, $userId]);
    if ($res && $type === 'credit') {
        // Only mark as completed if the credit amount meets or exceeds the minimum required deposit
        $sStmt = $pdo->query("SELECT minDepositAmount FROM settings WHERE id = 1");
        $min = $sStmt->fetchColumn() ?: 0;
        if ($amount >= $min) {
            $pdo->prepare("UPDATE users SET hasCompletedInitialDeposit = 1 WHERE id = ?")->execute([$userId]);
        }
    }
    return $res;
}

function checkDailyLimit($pdo, $userId, $recipient, $limit) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE userId = ? AND recipient = ? AND DATE(date) = CURDATE() AND status = 'successful'");
    $stmt->execute([$userId, $recipient]);
    return $stmt->fetchColumn() < $limit;
}

function logTransaction($pdo, $userId, $type, $amount, $status, $details, $recipient, $provider = null, $token = null) {
    $id = 'TX-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $pdo->prepare("INSERT INTO transactions (id, userId, type, amount, status, details, recipient, provider, token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$id, $userId, $type, $amount, $status, $details, $recipient, $provider, $token]);
}

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

    if ($data['count'] >= $limit) {
        return false;
    }

    $data['count']++;
    return true;
}

function sendMail($pdo, $to, $subject, $message) {
    $stmt = $pdo->query("SELECT senderName, fromEmail FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $senderName = $settings['senderName'] ?? 'Billpay Support';
    $fromEmail = $settings['fromEmail'] ?? 'no-reply@' . $_SERVER['HTTP_HOST'];

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $senderName <$fromEmail>" . "\r\n";

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f6f9fc; font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f6f9fc; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <table width='600' border='0' cellspacing='0' cellpadding='0' style='background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.05);'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #00c689 0%, #00a672 100%); padding: 60px 40px; text-align: center;'>
                                <div style='width: 60px; height: 60px; background-color: rgba(255,255,255,0.2); border-radius: 16px; margin: 0 auto 20px; display: inline-block; line-height: 60px; color: #ffffff; font-size: 32px; font-weight: 900; border: 1px solid rgba(255,255,255,0.3);'>B</div>
                                <h1 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;'>$senderName</h1>
                                <p style='margin: 10px 0 0; color: rgba(255,255,255,0.8); font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 2px;'>$subject</p>
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style='padding: 50px 40px; line-height: 1.8; color: #4a5568;'>
                                <div style='font-size: 16px; font-weight: 500;'>
                                    $message
                                </div>
                                <div style='margin-top: 40px; padding-top: 30px; border-top: 1px solid #edf2f7;'>
                                    <p style='margin: 0; font-size: 14px; font-weight: 700; color: #1a202c;'>Need help?</p>
                                    <p style='margin: 5px 0 0; font-size: 13px; color: #718096;'>Contact our 24/7 support team if you have any questions.</p>
                                </div>
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #fcfdfe; padding: 40px; text-align: center; border-top: 1px solid #f1f5f9;'>
                                <div style='margin-bottom: 20px;'>
                                    <a href='#' style='display: inline-block; margin: 0 10px; color: #a0aec0; text-decoration: none;'><span style='font-size: 18px;'>●</span></a>
                                    <a href='#' style='display: inline-block; margin: 0 10px; color: #a0aec0; text-decoration: none;'><span style='font-size: 18px;'>●</span></a>
                                    <a href='#' style='display: inline-block; margin: 0 10px; color: #a0aec0; text-decoration: none;'><span style='font-size: 18px;'>●</span></a>
                                </div>
                                <p style='margin: 0; font-size: 12px; font-weight: 600; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px;'>
                                    &copy; " . date('Y') . " $senderName. All rights reserved.
                                </p>
                                <p style='margin: 10px 0 0; font-size: 11px; color: #cbd5e0;'>
                                    This is an automated notification from our system.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";

    return @mail($to, $subject, $htmlBody, $headers);
}
