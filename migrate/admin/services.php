<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Service Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $disabled = $_POST['disabled_services'] ?? [];
    $stmt = $pdo->prepare("UPDATE settings SET disabledServices = ? WHERE id = 1");
    $stmt->execute([json_encode($disabled)]);

    $settings = fetchSettings($pdo);
    $success = "Service visibility updated!";
}

$disabledServices = $settings['disabledServices'] ?? [];
if (is_string($disabledServices)) $disabledServices = json_decode($disabledServices, true) ?: [];

$allServices = [
    'Dashboard' => [
        ['id' => 'pay_hub', 'label' => 'Pay Hub'],
        ['id' => 'finance', 'label' => 'Finance'],
        ['id' => 'crypto', 'label' => 'Crypto Hub'],
        ['id' => 'vcard', 'label' => 'Virtual Cards'],
        ['id' => 'datacard', 'label' => 'Data Card (EPIN)'],
        ['id' => 'transfer', 'label' => 'Bank Transfer'],
        ['id' => 'referrals', 'label' => 'Referral System'],
        ['id' => 'giftcards', 'label' => 'Gift Cards'],
    ],
    'Pay Hub' => [
        ['id' => 'airtime', 'label' => 'Airtime Topup'],
        ['id' => 'data', 'label' => 'Data Bundle'],
        ['id' => 'sms', 'label' => 'Bulk SMS'],
        ['id' => 'cable', 'label' => 'Cable TV'],
        ['id' => 'electric', 'label' => 'Electricity'],
        ['id' => 'exam', 'label' => 'Exam PIN'],
        ['id' => 'betting', 'label' => 'Betting Funding'],
    ]
];

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tight">Service Visibility</h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Enable or disable features site-wide</p>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <?php foreach ($allServices as $category => $services): ?>
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8 flex items-center gap-3">
                <i data-lucide="layers" class="text-indigo-500"></i> <?php echo $category; ?> Services
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($services as $s): ?>
                <div class="flex items-center justify-between p-6 bg-gray-50 rounded-3xl border border-gray-100 group hover:border-billpay-green transition-all">
                    <div>
                        <div class="text-sm font-black text-gray-800 uppercase"><?php echo $s['label']; ?></div>
                        <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Toggle visibility on user app</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="enabled_services[]" value="<?php echo $s['id']; ?>" class="sr-only peer" <?php echo !in_array($s['id'], $disabledServices) ? 'checked' : ''; ?> onchange="updateHiddenInput(this)">
                        <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-billpay-green"></div>
                    </label>
                    <input type="hidden" name="disabled_services[]" value="<?php echo $s['id']; ?>" <?php echo !in_array($s['id'], $disabledServices) ? 'disabled' : ''; ?>>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Update Service Visibility</button>
    </form>
</div>

<script>
function updateHiddenInput(checkbox) {
    const hiddenInput = checkbox.closest('.group').querySelector('input[type="hidden"]');
    if (checkbox.checked) {
        hiddenInput.disabled = true;
    } else {
        hiddenInput.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
