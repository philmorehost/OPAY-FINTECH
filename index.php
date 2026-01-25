<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('/admin/index');
    } else {
        redirect('/dashboard');
    }
} else {
    redirect('/login');
}
