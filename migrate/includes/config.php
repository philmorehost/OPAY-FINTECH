<?php
// migrate/includes/config.php
session_start();
define('DB_CONFIG', __DIR__ . '/db.php');
try {
    if (!file_exists(DB_CONFIG)) { throw new Exception("Not installed"); }
    require_once DB_CONFIG;
    if (DB_TYPE === 'sqlite') {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../' . DB_NAME);
    } else {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Quick check if fully installed
    $check = $pdo->query("SELECT id FROM settings LIMIT 1");
    if (!$check) { throw new Exception("Incomplete installation"); }

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
