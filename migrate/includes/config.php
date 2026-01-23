<?php
// migrate/includes/config.php
session_start();
define('DB_PATH', __DIR__ . '/../database.db');
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Check if installed by checking for settings table
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    if (!$check->fetch()) {
        throw new Exception("Not installed");
    }

    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();
} catch (Exception $e) {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}
function get_json_setting($key) {
    global $settings;
    return isset($settings[$key]) ? json_decode($settings[$key], true) : null;
}
date_default_timezone_set('Africa/Lagos');
