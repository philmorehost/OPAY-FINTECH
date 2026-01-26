<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/config.php';

$siteName = $settings['senderName'] ?? 'Billpay';
$description = $settings['siteDescription'] ?? "Seamless bill payments and rewards platform.";

$icon = '/uploads/logo.png'; // Default
if (!empty($settings['pwaIcon'])) {
    $icon = '/' . $settings['pwaIcon'];
}

$manifest = [
    "name" => $siteName,
    "short_name" => $siteName,
    "description" => $description,
    "start_url" => "/dashboard",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => $settings['primaryColor'] ?? "#00c689",
    "icons" => [
        [
            "src" => $icon,
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $icon,
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
