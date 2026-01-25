<?php
if (!file_exists(__DIR__ . '/migrate/includes/db.php')) {
    header('Location: /migrate/install.php');
    exit;
}

// If installed, redirect to the main app
header('Location: /migrate/');
exit;
