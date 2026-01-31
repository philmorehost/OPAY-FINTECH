<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Services';
require_once __DIR__ . '/includes/header.php';

$disabledServices = $settings['disabledServices'] ?? [];
if (is_string($disabledServices)) $disabledServices = json_decode($disabledServices, true) ?: [];

$services = [
    ['id' => 'airtime', 'icon' => 'phone', 'label' => 'Airtime', 'path' => '/airtime', 'color' => 'text-blue-500'],
    ['id' => 'data', 'icon' => 'wifi', 'label' => 'Data', 'path' => '/data', 'color' => 'text-orange-500'],
    ['id' => 'datacard', 'icon' => 'printer', 'label' => 'Data Card', 'path' => '/datacard', 'color' => 'text-indigo-600'],
    ['id' => 'sms', 'icon' => 'message-circle', 'label' => 'Bulk SMS', 'path' => '/sms', 'color' => 'text-emerald-500'],
    ['id' => 'cable', 'icon' => 'tv', 'label' => 'Cable TV', 'path' => '/cable', 'color' => 'text-red-500'],
    ['id' => 'electric', 'icon' => 'zap', 'label' => 'Electricity', 'path' => '/electric', 'color' => 'text-yellow-500'],
    ['id' => 'betting', 'icon' => 'trending-up', 'label' => 'Betting', 'path' => '/betting', 'color' => 'text-green-500'],
    ['id' => 'finance', 'icon' => 'crown', 'label' => 'Finance', 'path' => '/finance', 'color' => 'text-amber-500'],
    ['id' => 'crypto', 'icon' => 'bar-chart-3', 'label' => 'Crypto', 'path' => '/crypto', 'color' => 'text-orange-500'],
    ['id' => 'transfer', 'icon' => 'arrow-right-left', 'label' => 'Transfer', 'path' => '/transfer', 'color' => 'text-indigo-500'],
    ['id' => 'vcard', 'icon' => 'credit-card', 'label' => 'Card', 'path' => '/vcard', 'color' => 'text-pink-500'],
    ['id' => 'exam', 'icon' => 'shield-check', 'label' => 'Exam PIN', 'path' => '/exam', 'color' => 'text-purple-500'],
    ['id' => 'referrals', 'icon' => 'bar-chart-3', 'label' => 'Referrals', 'path' => '/referrals', 'color' => 'text-cyan-600'],
    ['id' => 'giftcards', 'icon' => 'gift', 'label' => 'Gift Cards', 'path' => '/giftcards', 'color' => 'text-pink-600'],
];

$services = array_filter($services, function($s) use ($disabledServices) {
    return !in_array($s['id'], $disabledServices);
});
?>

<div class="mx-auto bg-white min-h-screen pb-24 relative">
    <div class="p-6 border-b border-gray-100 flex items-center gap-4 sticky top-0 bg-white z-10">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-xl font-black text-gray-900 uppercase tracking-tight">All Services</h1>
    </div>

    <div class="p-6 grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-10">
        <?php foreach ($services as $service): ?>
            <a href="<?php echo $service['path']; ?>" class="flex flex-col items-center gap-2.5 group">
                <div class="w-14 h-14 bg-gray-50 group-active:scale-90 rounded-2xl flex items-center justify-center shadow-sm border border-gray-100 transition-all <?php echo $service['color']; ?>">
                    <i data-lucide="<?php echo $service['icon']; ?>" class="w-6 h-6"></i>
                </div>
                <span class="text-[10px] font-black text-gray-600 uppercase tracking-tight text-center"><?php echo $service['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
