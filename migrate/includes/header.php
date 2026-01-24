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
    <title>VTU-Fintech Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'vtu-green': '#00c689', }, animation: { 'slide-up': 'slideUp 0.3s ease-out', 'fade-in': 'fadeIn 0.4s ease-out', }, keyframes: { slideUp: { '0%': { transform: 'translateY(100%)' }, '100%': { transform: 'translateY(0)' }, }, fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' }, } } } } }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 <?php echo ($settings['darkModeEnabled'] ?? 0) ? 'dark-mode' : ''; ?>">
<style>
.dark-mode { background-color: #111827 !important; color: #f9fafb !important; }
.dark-mode .bg-white { background-color: #1f2937 !important; color: #f9fafb !important; }
.dark-mode .bg-gray-50 { background-color: #111827 !important; }
.dark-mode .text-gray-900 { color: #f9fafb !important; }
.dark-mode .text-gray-800 { color: #f3f4f6 !important; }
.dark-mode .text-gray-400 { color: #9ca3af !important; }
.dark-mode .border-gray-100 { border-color: #374151 !important; }
.dark-mode .bg-gray-200 { background-color: #374151 !important; }
.dark-mode .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.5) !important; }
</style>
<?php
if (is_logged_in()) {
    $u_data = get_current_user_data();
    if ($u_data && $u_data['isSuspended'] && !empty($_SESSION['original_admin_id'])) {
        echo '<div class="bg-red-600 text-white text-[10px] font-black uppercase text-center py-2 sticky top-0 z-[100] tracking-widest">Viewing Suspended Account</div>';
    }
}
?>
