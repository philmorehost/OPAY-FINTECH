<?php
if (!isLoggedIn()) {
    redirect('/login');
}
// $currentUser is now defined in config.php

$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>;
            --primary-color-rgb: <?php
                $hex = $settings['primaryColor'] ?? '#00c689';
                list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
                echo "$r, $g, $b";
            ?>;
        }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; color: #111827; }
        .billpay-green { color: var(--primary-color); }
        .bg-billpay-green { background-color: var(--primary-color); }
        .border-billpay-green { border-color: var(--primary-color); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes slideDown {
          from { transform: translateY(-100%); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-down {
          animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Desktop Sidebar -->
        <aside class="w-72 bg-white border-r border-gray-100 hidden lg:flex flex-col fixed h-full z-40">
            <div class="p-8 flex items-center gap-3">
                <div class="w-10 h-10 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">B</div>
                <span class="font-black text-lg tracking-tight">Billpay</span>
            </div>

            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto scrollbar-hide">
                <?php
                $menuItems = [
                    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'path' => '/dashboard'],
                    ['label' => 'Services', 'icon' => 'grid', 'path' => '/services'],
                    ['label' => 'Transactions', 'icon' => 'arrow-right-left', 'path' => '/transactions'],
                    ['label' => 'Rewards', 'icon' => 'coins', 'path' => '/rewards'],
                    ['label' => 'Virtual Card', 'icon' => 'credit-card', 'path' => '/vcard'],
                    ['label' => 'Referrals', 'icon' => 'users', 'path' => '/referrals'],
                    ['label' => 'Support', 'icon' => 'message-square', 'path' => '/support'],
                    ['label' => 'Profile', 'icon' => 'user', 'path' => '/profile'],
                ];
                foreach ($menuItems as $item):
                    $active = ($_SERVER['REQUEST_URI'] === $item['path']);
                ?>
                <a href="<?php echo $item['path']; ?>" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $active ? 'bg-billpay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50 hover:text-gray-600'; ?>">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                    <?php echo $item['label']; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <div class="p-6 border-t border-gray-50 space-y-2">
                <?php if (isset($_SESSION['original_admin_id'])): ?>
                    <a href="/admin/users?action=return_admin" class="w-full flex items-center gap-4 px-6 py-4 bg-gray-900 text-white font-black text-[10px] uppercase tracking-widest rounded-2xl shadow-lg transition-all">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                        Back to Admin
                    </a>
                <?php endif; ?>
                <a href="/logout" class="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    Sign Out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-72 flex flex-col min-h-screen pb-24 lg:pb-0">
            <!-- Header -->
            <header class="h-20 bg-white/80 backdrop-blur-md border-b border-gray-50 sticky top-0 z-30 px-6 lg:px-12 flex items-center justify-between">
                <div class="flex items-center gap-4 lg:hidden">
                    <div class="w-10 h-10 bg-billpay-green rounded-xl flex items-center justify-center text-white font-black text-xl">B</div>
                </div>

                <h2 class="text-sm font-black uppercase tracking-widest text-gray-800 hidden lg:block"><?php echo $pageTitle; ?></h2>

                <div class="flex items-center gap-6">
                    <div class="text-right hidden sm:block">
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Main Balance</div>
                        <div class="text-sm font-black text-gray-900"><?php echo formatCurrency($currentUser['walletBalance']); ?></div>
                    </div>
                    <div class="w-10 h-10 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-billpay-green font-black">
                        <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                    </div>
                </div>
            </header>

            <div class="p-6 lg:p-12 max-w-6xl mx-auto w-full <?php echo isAdmin() ? '' : 'lg:max-w-[70%]'; ?>">
