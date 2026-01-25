<?php
// Core Functions

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function generateId($prefix = '') {
    return $prefix . bin2hex(random_bytes(4));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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
