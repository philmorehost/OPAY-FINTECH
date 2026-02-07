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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo $settings['primaryColor'] ?? '#00c689'; ?>">
    <link rel="manifest" href="/manifest.json.php">
    <link rel="apple-touch-icon" href="<?php echo !empty($settings['pwaIcon']) ? '/'.$settings['pwaIcon'].'?v='.($settings['siteVersion'] ?? '1.0.0') : '/uploads/logo.png'; ?>">
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
        @keyframes slideUp {
          from { transform: translateY(100%); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up {
          animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes rollRight {
          0% { transform: translateX(-150%) rotate(-360deg); opacity: 0; }
          100% { transform: translateX(0) rotate(0deg); opacity: 1; }
        }
        .animate-roll-right {
          animation: rollRight 1s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }
        #splash-screen {
            position: fixed;
            inset: 0;
            background: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease-out, visibility 0.5s;
        }
    </style>
</head>
<body class="bg-gray-50 w-full">
    <div id="offline-toast" class="fixed top-4 left-1/2 -translate-x-1/2 z-[10000] hidden">
        <div class="bg-red-500 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 animate-bounce">
            <i data-lucide="wifi-off" class="w-5 h-5"></i>
            <span class="text-[10px] font-black uppercase tracking-widest">Connection Lost</span>
        </div>
    </div>
    <div id="online-toast" class="fixed top-4 left-1/2 -translate-x-1/2 z-[10000] hidden">
        <div class="bg-green-500 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 animate-slide-down">
            <i data-lucide="wifi" class="w-5 h-5"></i>
            <span class="text-[10px] font-black uppercase tracking-widest">Back Online</span>
        </div>
    </div>
    <script>
        window.addEventListener('online', () => {
            document.getElementById('offline-toast').classList.add('hidden');
            document.getElementById('online-toast').classList.remove('hidden');
            setTimeout(() => document.getElementById('online-toast').classList.add('hidden'), 3000);
        });
        window.addEventListener('offline', () => {
            document.getElementById('online-toast').classList.add('hidden');
            document.getElementById('offline-toast').classList.remove('hidden');
        });
        if (!navigator.onLine) document.getElementById('offline-toast').classList.remove('hidden');
    </script>

    <?php if (!empty($settings['pwaEnabled'])): ?>
    <div id="splash-screen">
        <div class="text-center animate-roll-right">
            <img src="/<?php echo !empty($settings['pwaSplash']) ? $settings['pwaSplash'] : (!empty($settings['pwaIcon']) ? $settings['pwaIcon'] : 'uploads/logo.png'); ?>?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>" class="w-32 h-32 object-contain mx-auto mb-4 rounded-3xl shadow-2xl">
            <h1 class="text-2xl font-black uppercase tracking-tighter text-gray-900"><?php echo $settings['senderName'] ?? 'Billpay'; ?></h1>
        </div>
    </div>
    <script>
        window.addEventListener('load', () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>', { scope: '/' });
            }

            const isPwa = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            const splash = document.getElementById('splash-screen');
            const hasSeenSplash = sessionStorage.getItem('hasSeenSplash');

            if (isPwa && splash && !hasSeenSplash) {
                setTimeout(() => {
                    splash.style.opacity = '0';
                    splash.style.visibility = 'hidden';
                    sessionStorage.setItem('hasSeenSplash', 'true');
                }, 3000);
            } else if (splash) {
                splash.style.display = 'none';
            }
        });
    </script>
    <?php endif; ?>

    <div class="flex min-h-screen w-full">
        <!-- Desktop Sidebar -->
        <aside class="w-72 bg-white border-r border-gray-100 hidden lg:flex flex-col fixed h-full z-40">
            <div class="p-8 flex items-center gap-3">
                <?php if (!empty($settings['pwaIcon'])): ?>
                    <img src="/<?php echo $settings['pwaIcon']; ?>?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>" class="w-10 h-10 rounded-xl object-contain shadow-md">
                <?php else: ?>
                    <div class="w-10 h-10 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">B</div>
                <?php endif; ?>
                <span class="font-black text-lg tracking-tight"><?php echo $settings['senderName'] ?? 'Billpay'; ?></span>
            </div>

            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto scrollbar-hide">
                <?php
                $menuItems = [
                    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'path' => '/dashboard'],
                    ['label' => 'Services', 'icon' => 'grid', 'path' => '/services'],
                    ['label' => 'Finance Hub', 'icon' => 'crown', 'path' => '/finance'],
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
        <main class="flex-1 lg:ml-72 flex flex-col min-h-screen w-full pb-24 lg:pb-0 overflow-x-hidden">
            <!-- Header -->
            <header class="h-20 bg-white/80 backdrop-blur-md border-b border-gray-50 sticky top-0 z-30 px-6 lg:px-12 flex items-center justify-between">
                <div class="flex items-center gap-4 lg:hidden">
                    <?php if (!empty($settings['pwaIcon'])): ?>
                        <img src="/<?php echo $settings['pwaIcon']; ?>?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>" class="w-10 h-10 rounded-xl object-contain shadow-md">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-billpay-green rounded-xl flex items-center justify-center text-white font-black text-xl">B</div>
                    <?php endif; ?>
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

            <?php
            // Min Deposit Enforcement
            $isRestricted = checkMinDepositRestriction($settings, $currentUser);
            $servicePages = ['airtime.php', 'data.php', 'cable.php', 'electric.php', 'betting.php', 'exam.php', 'giftcards.php', 'finance.php', 'transfer.php', 'sms.php', 'vcard.php', 'services.php'];
            $currentFile = basename($_SERVER['PHP_SELF']);
            $isServicePage = in_array($currentFile, $servicePages);

            if ($isRestricted && $isServicePage):
            ?>
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
                <div class="bg-white w-full max-w-sm rounded-[40px] overflow-hidden shadow-2xl animate-slide-up">
                    <div class="p-10 text-center">
                        <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="shield-alert" class="w-10 h-10 text-amber-500"></i>
                        </div>
                        <h3 class="text-xl font-black uppercase tracking-tight text-gray-900 mb-2">Access Restricted</h3>
                        <p class="text-xs font-bold text-gray-400 uppercase leading-relaxed mb-8">
                            To access our premium services, you must complete an initial wallet deposit of at least <span class="text-gray-900"><?php echo formatCurrency($settings['minDepositAmount']); ?></span>.
                        </p>
                        <div class="space-y-3">
                            <a href="/add-money" class="block w-full py-5 bg-billpay-green text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Fund Wallet Now</a>
                            <a href="/dashboard" class="block w-full py-5 bg-gray-50 text-gray-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-100 transition-all">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (defined('SECURITY_COMPLIANCE_ERROR')): ?>
            <div class="p-6">
                <div class="bg-red-50 border border-red-100 p-6 rounded-[32px] flex items-center gap-6 animate-slide-down">
                    <div class="w-12 h-12 bg-red-500 text-white rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-red-200">
                        <i data-lucide="shield-alert" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-black text-red-800 uppercase leading-relaxed"><?php echo SECURITY_COMPLIANCE_ERROR; ?></p>
                    </div>
                    <a href="/login-settings" class="bg-red-500 text-white px-6 py-3 rounded-xl font-black text-[9px] uppercase tracking-widest shadow-lg shadow-red-200 hover:bg-red-600 transition-all active:scale-95 whitespace-nowrap">Configure Now</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="px-4 py-6 lg:p-12 lg:max-w-6xl mx-auto w-full">
