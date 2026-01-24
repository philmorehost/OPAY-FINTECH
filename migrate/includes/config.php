<?php
// migrate/includes/config.php
session_start();
define('DB_CONFIG', __DIR__ . '/db.php');
try {
    if (!file_exists(DB_CONFIG)) { throw new Exception("Not installed"); }
    require_once DB_CONFIG;

    // Connect via MySQL exclusively
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Quick check if fully installed
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();

    if (!$settings) { throw new Exception("Incomplete installation"); }
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
