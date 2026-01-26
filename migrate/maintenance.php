<?php
require_once __DIR__ . '/includes/config.php';

// If maintenance mode is off, redirect to home
if (!$settings['isMaintenanceMode']) {
    redirect('/');
}

// Ensure logout works even in maintenance mode
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('/login');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance | Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center p-6 text-center">
    <div class="max-w-md w-full space-y-8 animate-fade-in">
        <div class="relative inline-block">
            <div class="w-32 h-32 bg-white rounded-[40px] shadow-2xl flex items-center justify-center mx-auto mb-8 animate-float overflow-hidden">
                <img src="/<?php echo !empty($settings['pwaIcon']) ? $settings['pwaIcon'] : 'uploads/logo.png'; ?>" class="w-full h-full object-contain">
            </div>
            <div class="absolute -top-2 -right-2 w-12 h-12 bg-red-500 rounded-2xl flex items-center justify-center text-white shadow-lg animate-pulse">
                <i data-lucide="shield-alert" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="space-y-4">
            <h1 class="text-4xl font-black text-gray-900 uppercase tracking-tight">We'll be<br><span class="text-billpay-green">back shortly</span></h1>
            <p class="text-gray-500 font-medium leading-relaxed">Our systems are currently undergoing scheduled maintenance to improve your experience. We apologize for any inconvenience.</p>
        </div>

        <div class="p-8 bg-white rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <div class="flex items-center gap-4 text-left">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase">Estimated Return</div>
                    <div class="text-sm font-bold text-gray-800 uppercase">Within 2 Hours</div>
                </div>
            </div>

            <div class="flex items-center gap-4 text-left">
                <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-500">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase">Systems Status</div>
                    <div class="text-sm font-bold text-gray-800 uppercase">All Data Safe</div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <?php if (isLoggedIn()): ?>
                <a href="?logout=1" class="text-[11px] font-black text-gray-400 uppercase tracking-widest hover:text-red-500 transition-colors">Sign Out of Account</a>
            <?php else: ?>
                <a href="/login" class="text-[11px] font-black text-gray-400 uppercase tracking-widest hover:text-billpay-green transition-colors">Staff Login</a>
            <?php endif; ?>
        </div>

        <p class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] pt-10">© <?php echo date('Y'); ?> Billpay Fintech. All Rights Reserved.</p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
