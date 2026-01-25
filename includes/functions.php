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
    return $stmt->execute([$amount, $userId]);
}

function logTransaction($pdo, $userId, $type, $amount, $status, $details, $recipient, $provider = null) {
    $id = 'TX-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $pdo->prepare("INSERT INTO transactions (id, userId, type, amount, status, details, recipient, provider) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$id, $userId, $type, $amount, $status, $details, $recipient, $provider]);
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
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 10px;'>
        <div style='text-align: center; margin-bottom: 20px;'>
            <h2 style='color: #00c689;'>$senderName</h2>
        </div>
        <div style='line-height: 1.6; color: #333;'>
            $message
        </div>
        <div style='margin-top: 30px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px;'>
            &copy; " . date('Y') . " $senderName. All rights reserved.
        </div>
    </div>";

    return @mail($to, $subject, $htmlBody, $headers);
}
