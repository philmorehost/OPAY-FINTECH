<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Identity Verification';

$stmt = $pdo->prepare("SELECT * FROM kyc_submissions WHERE userId = ? ORDER BY date DESC LIMIT 1");
$stmt->execute([$currentUser['id']]);
$lastSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if ($currentUser['kycStatus'] === 'verified' || $currentUser['kycStatus'] === 'pending') {
        $error = "Verification already in progress or completed.";
    } else {
        $fullName = sanitize($_POST['fullName']);
        $dob = sanitize($_POST['dob']);
        $address = sanitize($_POST['address']);
        $idType = sanitize($_POST['idType']);
        $idNumber = sanitize($_POST['idNumber']);

        // Handle file uploads
        $uploadDir = __DIR__ . '/uploads/kyc/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $idImageUrl = '';
        if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] === 0) {
            $ext = pathinfo($_FILES['idImage']['name'], PATHINFO_EXTENSION);
            $idImageUrl = 'uploads/kyc/id_' . $currentUser['id'] . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['idImage']['tmp_name'], __DIR__ . '/' . $idImageUrl);
        }

        $addressImageUrl = '';
        if (isset($_FILES['addressImage']) && $_FILES['addressImage']['error'] === 0) {
            $ext = pathinfo($_FILES['addressImage']['name'], PATHINFO_EXTENSION);
            $addressImageUrl = 'uploads/kyc/addr_' . $currentUser['id'] . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['addressImage']['tmp_name'], __DIR__ . '/' . $addressImageUrl);
        }

        $id = 'KYC-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare("INSERT INTO kyc_submissions (id, userId, fullName, dob, address, idType, idNumber, idImageUrl, addressImageUrl, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$id, $currentUser['id'], $fullName, $dob, $address, $idType, $idNumber, $idImageUrl, $addressImageUrl]);

        $pdo->prepare("UPDATE users SET kycStatus = 'pending' WHERE id = ?")->execute([$currentUser['id']]);

        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = "KYC documents submitted successfully for review.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="space-y-10 animate-fade-in pb-20">
    <?php if (isset($_GET['error']) && $_GET['error'] === 'restricted'): ?>
        <div class="p-6 bg-amber-50 border border-amber-100 rounded-[32px] flex items-center gap-4 animate-bounce">
            <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-amber-600">
                <i data-lucide="shield-alert" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-sm font-black text-amber-800 uppercase">Verification Required</div>
                <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Please verify your identity to access restricted features.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-gray-900 uppercase tracking-tight">Identity Verification</h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Submit your documents to increase your account limits</p>
        </div>
        <div class="w-14 h-14 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center text-billpay-green">
            <i data-lucide="shield-check" class="w-7 h-7"></i>
        </div>
    </div>

    <?php if ($currentUser['kycStatus'] === 'verified'): ?>
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-4">
            <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check" class="w-10 h-10"></i>
            </div>
            <h2 class="text-xl font-black text-gray-900 uppercase">Verified Account</h2>
            <p class="text-gray-500 text-sm font-medium">Your identity has been verified. You now have full access to all platform features.</p>
        </div>
    <?php elseif ($currentUser['kycStatus'] === 'pending'): ?>
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 text-center space-y-4">
            <div class="w-20 h-20 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="clock" class="w-10 h-10"></i>
            </div>
            <h2 class="text-xl font-black text-gray-900 uppercase">Verification Pending</h2>
            <p class="text-gray-500 text-sm font-medium">Our team is reviewing your documents. This usually takes 1-2 business days.</p>
        </div>
    <?php else: ?>
        <?php if ($currentUser['kycStatus'] === 'rejected'): ?>
            <div class="p-6 bg-red-50 border border-red-100 rounded-3xl space-y-2">
                <div class="flex items-center gap-2 text-red-600 font-black text-xs uppercase">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i> Verification Rejected
                </div>
                <p class="text-[10px] font-bold text-red-500 uppercase leading-relaxed">
                    Reason: <?php echo $lastSubmission['rejectionReason'] ?? 'Documents provided were not clear or invalid.'; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Full Name (as on ID)</label>
                            <input type="text" name="fullName" required class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Date of Birth</label>
                            <input type="date" name="dob" required class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Residential Address</label>
                        <textarea name="address" required rows="3" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green"></textarea>
                    </div>
                </div>

                <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-900 border-l-4 border-billpay-green pl-4">Document Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">ID Document Type</label>
                            <select name="idType" required class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                                <option value="National ID">National ID</option>
                                <option value="Voters Card">Voters Card</option>
                                <option value="Drivers License">Drivers License</option>
                                <option value="International Passport">International Passport</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">ID Number</label>
                            <input type="text" name="idNumber" required class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Upload ID Image</label>
                            <input type="file" name="idImage" required accept="image/*" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Proof of Address (Utility Bill)</label>
                            <input type="file" name="addressImage" required accept="image/*" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Submit Verification Documents</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
