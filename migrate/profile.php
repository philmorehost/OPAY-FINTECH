<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $fullName = sanitize($_POST['fullName']);
    $phone = sanitize($_POST['phone']);

    if (empty($fullName) || empty($phone)) {
        $error = "Full Name and Phone are required.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET fullName = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$fullName, $phone, $currentUser['id']])) {
            $success = "Profile updated successfully!";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update profile.";
        }
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Account Profile</h1>
    </div>

    <div class="p-6 space-y-8">
        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center">
            <div class="w-24 h-24 bg-billpay-green text-white rounded-[32px] flex items-center justify-center text-4xl font-black shadow-xl mx-auto mb-6">
                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
            </div>
            <h2 class="text-2xl font-black text-gray-800 tracking-tight"><?php echo $currentUser['fullName']; ?></h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">@<?php echo $currentUser['username']; ?> • Tier <?php echo $currentUser['tier']; ?></p>
        </div>

        <?php if ($success): ?>
            <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="space-y-4">
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex flex-col gap-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Full Name</label>
                    <input type="text" name="fullName" value="<?php echo $currentUser['fullName']; ?>" class="w-full bg-gray-50 p-3 rounded-xl font-bold text-sm outline-none border-2 border-transparent focus:border-billpay-green transition-all">
                </div>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex flex-col gap-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo $currentUser['phone']; ?>" class="w-full bg-gray-50 p-3 rounded-xl font-bold text-sm outline-none border-2 border-transparent focus:border-billpay-green transition-all">
                </div>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex justify-between items-center opacity-60">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Email Address</span>
                    <span class="text-xs font-black text-gray-800"><?php echo $currentUser['email']; ?></span>
                </div>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex justify-between items-center">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">KYC Status</span>
                    <span class="px-3 py-1 bg-green-50 text-green-600 text-[9px] font-black rounded-full uppercase"><?php echo $currentUser['kycStatus']; ?></span>
                </div>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-black transition-all">Update Profile</button>
        </form>

        <div class="grid grid-cols-2 gap-4">
            <a href="/login-settings" class="bg-white p-6 rounded-[32px] border border-gray-100 flex flex-col items-center gap-3 shadow-sm hover:border-billpay-green transition-all">
                <i data-lucide="shield" class="w-6 h-6 text-blue-500"></i>
                <span class="text-[10px] font-black uppercase text-gray-600">Security</span>
            </a>
            <a href="/logout" class="bg-red-50 p-6 rounded-[32px] border border-red-100 flex flex-col items-center gap-3 shadow-sm hover:bg-red-100 transition-all">
                <i data-lucide="log-out" class="w-6 h-6 text-red-500"></i>
                <span class="text-[10px] font-black uppercase text-red-500">Logout</span>
            </a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
