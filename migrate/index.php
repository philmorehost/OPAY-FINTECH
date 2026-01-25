<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('/migrate/admin/index');
    } else {
        redirect('/migrate/dashboard');
    }
} else {
    redirect('/migrate/login');
}
