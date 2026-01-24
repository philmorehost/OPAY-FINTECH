<?php
// migrate/profile.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }
    $stmt = $pdo->prepare("UPDATE users SET fullName = ?, email = ?, dob = ?, address = ?, gender = ?, occupation = ? WHERE id = ?");
    $stmt->execute([$_POST['fullName'], $_POST['email'], $_POST['dob'], $_POST['address'], $_POST['gender'], $_POST['occupation'], $user['id']]);
    $success = "Profile updated successfully";
    $user = get_current_user_data();
}
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">My Profile</h1>
    </div>

    <div class="p-6 space-y-6">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>

        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 text-center">
            <div class="w-24 h-24 bg-vtu-green/10 rounded-full flex items-center justify-center text-vtu-green font-black text-3xl mx-auto mb-4"><?php echo substr($user['fullName'], 0, 1); ?></div>
            <h2 class="text-xl font-black"><?php echo h($user['fullName']); ?></h2>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">@<?php echo h($user['username']); ?></p>
            <div class="mt-4 flex justify-center gap-2">
                <span class="px-3 py-1 bg-gray-100 text-[9px] font-black uppercase tracking-widest rounded-full"><?php echo get_user_tier($user); ?></span>
                <a href="kyc" class="px-3 py-1 bg-vtu-green/10 text-vtu-green text-[9px] font-black uppercase tracking-widest rounded-full"><?php echo $user['kycStatus'] === 'verified' ? 'Verified' : 'Verify Identity'; ?></a>
            </div>
        </div>

        <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Full Name</label><input type="text" name="fullName" value="<?php echo h($user['fullName']); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
            <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Email Address</label><input type="email" name="email" value="<?php echo h($user['email']); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
            <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Date of Birth</label><input type="date" name="dob" value="<?php echo h($user['dob'] ?? ''); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
            <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Address</label><input type="text" name="address" value="<?php echo h($user['address'] ?? ''); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Gender</label>
                    <select name="gender" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                        <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Occupation</label><input type="text" name="occupation" value="<?php echo h($user['occupation'] ?? ''); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
            </div>
            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl">UPDATE PROFILE</button>
        </form>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
