<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'billpay_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS billpay_db");
    $pdo->exec("USE billpay_db");
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
