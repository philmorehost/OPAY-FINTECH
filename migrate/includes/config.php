<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

startSecureSession();
$settings = isset($pdo) ? fetchSettings($pdo) : [];
$csrf_token = generateCsrfToken();
