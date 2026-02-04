<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['temp_google_user'])) {
    redirect('/login');
}

$tempUser = $_SESSION['temp_google_user'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $phone = sanitize($_POST['phone']);

    // Server-side simple validation (browser does the heavy lifting with libphonenumber)
    if (strlen($phone) < 7) {
        $error = "Please enter a valid phone number";
    } else {
        if (isset($tempUser['id'])) {
            // Update existing user
            $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $tempUser['id']]);
            $userId = $tempUser['id'];
            $username = fetchUser($pdo, $userId)['username'];
        } else {
            // Create new user
            $userId = 'u' . bin2hex(random_bytes(4));
            $username = explode('@', $tempUser['email'])[0] . rand(10, 99);

            $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, phone, googleId, role, walletBalance, bonusCoins) VALUES (?, ?, ?, ?, ?, ?, 'user', ?, ?)");
            $stmt->execute([
                $userId,
                $username,
                $tempUser['name'],
                $tempUser['email'],
                $phone,
                $tempUser['googleId'],
                $settings['welcomeBonus'] / $settings['conversionRate'],
                $settings['welcomeBonus']
            ]);
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        unset($_SESSION['temp_google_user']);

        redirect('/dashboard');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Registration - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
        .bg-billpay-green { background-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-gray-900">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100">
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i data-lucide="user-plus" class="w-8 h-8"></i>
            </div>
            <h2 class="text-xl font-black uppercase tracking-tight">Almost There</h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Complete your profile to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Full Name</label>
                <input type="text" value="<?php echo $tempUser['name']; ?>" readonly class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 font-bold text-sm text-gray-400">
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Email Address</label>
                <input type="text" value="<?php echo $tempUser['email']; ?>" readonly class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 font-bold text-sm text-gray-400">
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Phone Number</label>
                <input type="tel" name="phone" id="phone" required placeholder="+234..." class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
                <p id="phone-error" class="text-[8px] font-bold text-red-500 uppercase mt-1 hidden">Invalid international format</p>
            </div>

            <button type="submit" id="submitBtn" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all">Complete Registration</button>
        </form>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/libphonenumber-js@1.10.44/bundle/libphonenumber-js.min.js"></script>
    <script>
        lucide.createIcons();

        const phoneInput = document.getElementById('phone');
        const errorMsg = document.getElementById('phone-error');
        const submitBtn = document.getElementById('submitBtn');

        phoneInput.addEventListener('input', () => {
            const val = phoneInput.value;
            try {
                const phoneNumber = libphonenumber.parsePhoneNumberWithError(val);
                if (phoneNumber.isValid()) {
                    phoneInput.classList.remove('border-red-500');
                    phoneInput.classList.add('border-billpay-green');
                    errorMsg.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50');
                } else {
                    throw new Error('Invalid');
                }
            } catch (e) {
                phoneInput.classList.add('border-red-500');
                phoneInput.classList.remove('border-billpay-green');
                errorMsg.classList.remove('hidden');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50');
            }
        });
    </script>
</body>
</html>
