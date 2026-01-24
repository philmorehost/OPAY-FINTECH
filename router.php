<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/' || $path === '') { include 'index.php'; return; }
if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) { return false; }
if (file_exists(__DIR__ . $path . '.php')) { include __DIR__ . $path . '.php'; return; }
return false;
