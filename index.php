<?php
if (!file_exists(__DIR__ . '/migrate/includes/db.php')) {
    header('Location: /install');
    exit;
}

// Load the app index without redirecting to the /migrate/ subdirectory
require_once __DIR__ . '/migrate/index.php';
