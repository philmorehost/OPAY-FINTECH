<?php
if (!file_exists(__DIR__ . '/migrate/includes/db.php')) {
    header('Location: migrate/install.php');
} else {
    header('Location: migrate/');
}
exit;
