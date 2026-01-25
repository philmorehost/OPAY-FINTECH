<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: #00c689; }
        .bg-billpay-green { background-color: #00c689; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="flex min-h-screen bg-gray-50 text-gray-900">
    <aside class="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100 overflow-y-auto scrollbar-hide">
        <div class="p-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">B</div>
            <span class="font-black text-lg">Admin Hub</span>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1">
            <?php
            $adminMenu = [
                ['label' => 'Overview', 'icon' => 'layout-dashboard', 'path' => '/admin/index'],
                ['label' => 'Users', 'icon' => 'users', 'path' => '/admin/users'],
                ['label' => 'KYC Review', 'icon' => 'shield-check', 'path' => '/admin/kyc'],
                ['label' => 'Deposits', 'icon' => 'wallet', 'path' => '/admin/deposits'],
                ['label' => 'All Tx History', 'icon' => 'file-text', 'path' => '/admin/transactions'],
                ['label' => 'Support', 'icon' => 'message-square', 'path' => '/admin/support'],
                ['label' => 'Email Hub', 'icon' => 'mail', 'path' => '/admin/email-hub'],
                ['label' => 'API Hub', 'icon' => 'database', 'path' => '/admin/api-manager'],
                ['label' => 'Settings', 'icon' => 'settings', 'path' => '/admin/settings'],
            ];
            foreach ($adminMenu as $item):
                $active = (str_replace('.php', '', $_SERVER['PHP_SELF']) === $item['path']);
            ?>
            <a href="<?php echo $item['path']; ?>" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $active ? 'bg-billpay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'; ?>">
                <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i> <?php echo $item['label']; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-6 border-t border-gray-100">
            <a href="/logout" class="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all">
                <i data-lucide="log-out" class="w-5 h-5"></i> Sign Out
            </a>
        </div>
    </aside>
    <main class="flex-1 ml-72 p-12 overflow-y-auto">
        <div class="max-w-6xl mx-auto">
