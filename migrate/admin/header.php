<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo $settings['primaryColor'] ?? '#00c689'; ?>">
    <link rel="manifest" href="/manifest.json.php">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php if (!empty($settings['pwaEnabled'])): ?>
    <script>
        window.addEventListener('load', () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>', { scope: '/' });
            }
        });
    </script>
    <?php endif; ?>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (!sidebar || !backdrop) return;
            const isOpen = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
            } else {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                setTimeout(() => {
                    backdrop.classList.remove('opacity-0');
                    backdrop.classList.add('opacity-100');
                }, 10);
            }
        }
    </script>
    <style>
        :root { --primary-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: var(--primary-color); }
        .bg-billpay-green { background-color: var(--primary-color); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 overflow-x-hidden w-full">
    <header class="lg:hidden h-16 bg-white border-b border-gray-100 flex items-center justify-between px-6 sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <?php if (!empty($settings['pwaIcon'])): ?><img src="/<?php echo $settings['pwaIcon']; ?>" class="w-8 h-8 rounded-lg object-contain shadow-md"><?php else: ?><div class="w-8 h-8 bg-billpay-green rounded-lg flex items-center justify-center text-white font-black text-lg">B</div><?php endif; ?>
            <span class="font-black text-sm uppercase tracking-tight">Admin Hub</span>
        </div>
        <button onclick="toggleSidebar()" class="p-2 text-gray-400 hover:text-gray-900"><i data-lucide="menu" class="w-6 h-6"></i></button>
    </header>

    <div class="flex min-h-screen relative">
        <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[35] hidden transition-opacity duration-300 opacity-0"></div>
        <aside id="sidebar" class="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100 overflow-y-auto scrollbar-hide -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
            <div class="p-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <?php if (!empty($settings['pwaIcon'])): ?><img src="/<?php echo $settings['pwaIcon']; ?>" class="w-10 h-10 rounded-xl object-contain shadow-md"><?php else: ?><div class="w-10 h-10 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-xl">B</div><?php endif; ?>
                    <span class="font-black text-lg">Admin Hub</span>
                </div>
                <button onclick="toggleSidebar()" class="lg:hidden p-2 text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
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
                [
                    'label' => 'API Manager',
                    'icon' => 'database',
                    'path' => '#',
                    'submenu' => [
                        ['label' => 'Airtime API', 'path' => '/admin/api-airtime'],
                        ['label' => 'Data API', 'path' => '/admin/api-data'],
                        ['label' => 'Manage Data Cards', 'path' => '/admin/manage-epins'],
                        ['label' => 'Utilities API', 'path' => '/admin/api-utilities'],
                        ['label' => 'Financial API', 'path' => '/admin/api-financial'],
                        ['label' => 'Others API', 'path' => '/admin/api-others'],
                    ]
                ],
                ['label' => 'Sales Reports', 'icon' => 'bar-chart-3', 'path' => '/admin/reports'],
                ['label' => 'Offers', 'icon' => 'gift', 'path' => '/admin/offers'],
                ['label' => 'Login Security', 'icon' => 'shield-lock', 'path' => '/admin/login-security'],
                ['label' => 'Brute Force', 'icon' => 'shield-alert', 'path' => '/admin/brute-force'],
                ['label' => 'Settings', 'icon' => 'settings', 'path' => '/admin/settings'],
            ];
            foreach ($adminMenu as $item):
                $isSub = isset($item['submenu']);
                $currentPath = str_replace('.php', '', $_SERVER['PHP_SELF']);
                $active = ($currentPath === $item['path']);
                $anyActive = false;
                if ($isSub) {
                    foreach($item['submenu'] as $sub) if ($currentPath === $sub['path']) $anyActive = true;
                }
            ?>
            <?php if (!$isSub): ?>
                <a href="<?php echo $item['path']; ?>" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $active ? 'bg-billpay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'; ?>">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i> <?php echo $item['label']; ?>
                </a>
            <?php else: ?>
                <div x-data="{ open: <?php echo $anyActive ? 'true' : 'false'; ?> }" class="space-y-1">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $anyActive ? 'text-gray-900 bg-gray-50' : 'text-gray-400 hover:bg-gray-50'; ?>">
                        <div class="flex items-center gap-4"><i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i> <?php echo $item['label']; ?></div>
                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak class="pl-12 space-y-1">
                        <?php foreach ($item['submenu'] as $sub):
                            $subActive = ($currentPath === $sub['path']);
                        ?>
                            <a href="<?php echo $sub['path']; ?>" class="block py-3 text-[9px] font-black uppercase tracking-widest transition-all <?php echo $subActive ? 'text-billpay-green' : 'text-gray-400 hover:text-gray-600'; ?>">
                                <?php echo $sub['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="p-6 border-t border-gray-100">
            <a href="/logout" class="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><i data-lucide="log-out" class="w-5 h-5"></i> Sign Out</a>
        </div>
    </aside>
    <main class="flex-1 lg:ml-72 p-6 lg:p-12 overflow-y-auto w-full">
        <div class="max-w-6xl mx-auto">
