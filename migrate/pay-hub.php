<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Pay Hub';
require_once __DIR__ . '/includes/header.php';

$disabledServices = $settings['disabledServices'] ?? [];
if (is_string($disabledServices)) $disabledServices = json_decode($disabledServices, true) ?: [];

$vtuServices = [
    ['id' => 'airtime', 'icon' => 'phone', 'label' => 'Airtime', 'path' => '/airtime', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'],
    ['id' => 'data', 'icon' => 'wifi', 'label' => 'Data', 'path' => '/data', 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    ['id' => 'sms', 'icon' => 'message-circle', 'label' => 'Bulk SMS', 'path' => '/sms', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'],
    ['id' => 'cable', 'icon' => 'tv', 'label' => 'Cable TV', 'path' => '/cable', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
    ['id' => 'electric', 'icon' => 'zap', 'label' => 'Electricity', 'path' => '/electric', 'color' => 'text-yellow-500', 'bg' => 'bg-yellow-50'],
    ['id' => 'exam', 'icon' => 'shield-check', 'label' => 'Exam PIN', 'path' => '/exam', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    ['id' => 'betting', 'icon' => 'trending-up', 'label' => 'Betting', 'path' => '/betting', 'color' => 'text-green-500', 'bg' => 'bg-green-50'],
];

$vtuServices = array_filter($vtuServices, function($s) use ($disabledServices) {
    return !in_array($s['id'], $disabledServices);
});
?>

<div class="mx-auto bg-gray-50 min-h-screen pb-24 relative">
    <!-- Header -->
    <div class="bg-white p-6 border-b border-gray-100 flex items-center gap-4 sticky top-0 z-20">
        <a href="/dashboard" class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-black text-gray-900 uppercase tracking-tight">Pay Hub</h1>
    </div>

    <div class="p-6 space-y-8 max-w-6xl mx-auto">
        <!-- Info Banner -->
        <div class="bg-gray-900 rounded-[32px] p-8 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-billpay-green/20 rounded-full blur-2xl"></div>
            <div class="relative z-10">
                <h2 class="text-2xl font-black tracking-tighter mb-2 uppercase">Bills & Utilities</h2>
                <p class="text-xs text-white/60 font-medium uppercase tracking-widest">Fast, secure and reliable VTU services</p>
            </div>
        </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($vtuServices as $s): ?>
                <a href="<?php echo $s['path']; ?>" class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm hover:shadow-md transition-all group flex flex-col items-center gap-4 text-center">
                    <div class="w-16 h-16 <?php echo $s['bg']; ?> <?php echo $s['color']; ?> rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110">
                        <i data-lucide="<?php echo $s['icon']; ?>" class="w-8 h-8"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-gray-900 uppercase tracking-tight"><?php echo $s['label']; ?></div>
                        <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mt-1">Instant Delivery</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Security Message -->
        <div class="p-6 bg-white rounded-[32px] border border-gray-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center shrink-0">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-500 uppercase leading-relaxed">Your transactions are secured with military-grade encryption. Always double-check recipient numbers before proceeding.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
