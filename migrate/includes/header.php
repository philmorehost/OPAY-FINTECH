<?php
// migrate/includes/header.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OPay Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'opay-green': '#00c689', }, animation: { 'slide-up': 'slideUp 0.3s ease-out', 'fade-in': 'fadeIn 0.4s ease-out', }, keyframes: { slideUp: { '0%': { transform: 'translateY(100%)' }, '100%': { transform: 'translateY(0)' }, }, fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' }, } } } } }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
