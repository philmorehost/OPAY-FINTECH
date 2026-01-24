<?php
// migrate/kyc.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }

    $idType = $_POST['idType'] ?? '';
    $idNumber = $_POST['idNumber'] ?? '';

    if (isset($_FILES['idCard']) && $_FILES['idCard']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['idCard']['name'], PATHINFO_EXTENSION);
        $idCardPath = 'uploads/kyc_id_' . $user['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['idCard']['tmp_name'], $idCardPath);

        $stmt = $pdo->prepare("INSERT INTO kyc_submissions (id, userId, fullName, idType, idNumber, idImageUrl, status, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([generate_id(), $user['id'], $user['fullName'], $idType, $idNumber, $idCardPath, 'pending', date('c')]);

        $pdo->prepare("UPDATE users SET kycStatus = 'pending' WHERE id = ?")->execute([$user['id']]);
        $success = "KYC documents submitted for review.";
    } else {
        $error = "Please upload a valid ID document.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM kyc_submissions WHERE userId = ? ORDER BY date DESC LIMIT 1");
$stmt->execute([$user['id']]);
$kyc = $stmt->fetch();
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="profile" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">KYC Verification</h1>
    </div>

    <div class="p-6 space-y-6">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>

        <?php if ($user['kycStatus'] === 'verified'): ?>
            <div class="bg-green-500 p-8 rounded-[40px] text-white text-center shadow-xl">
                <i data-lucide="shield-check" size="48" class="mx-auto mb-4"></i>
                <h2 class="text-xl font-black mb-2">Verified Account</h2>
                <p class="text-sm opacity-80">You have full access to all services and higher transaction limits.</p>
            </div>
        <?php elseif ($user['kycStatus'] === 'pending'): ?>
            <div class="bg-amber-500 p-8 rounded-[40px] text-white text-center shadow-xl">
                <i data-lucide="clock" size="48" class="mx-auto mb-4"></i>
                <h2 class="text-xl font-black mb-2">Verification Pending</h2>
                <p class="text-sm opacity-80">Our team is currently reviewing your documents. Please wait.</p>
            </div>
        <?php else: ?>
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-lg font-black mb-6">Submit KYC Documents</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">ID Type</label>
                        <select name="idType" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                            <option value="national_id">National ID</option>
                            <option value="passport">International Passport</option>
                            <option value="drivers_license">Driver's License</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">ID Number</label>
                        <input type="text" name="idNumber" required placeholder="Enter ID number" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Upload ID Card</label>
                        <input type="file" name="idCard" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    </div>
                    <button type="submit" class="w-full bg-vtu-green text-white font-black py-5 rounded-2xl shadow-xl">SUBMIT DOCUMENTS</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
