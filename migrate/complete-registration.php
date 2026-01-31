<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['pending_google_reg'])) {
    redirect('/login');
}

$userId = $_SESSION['pending_google_reg'];
$user = fetchUser($pdo, $userId);

if (!$user) {
    unset($_SESSION['pending_google_reg']);
    redirect('/login');
}

if (!empty($user['phone'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['pending_google_reg']);
    redirect('/dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $phone = sanitize($_POST['phone']);
    if (empty($phone)) {
        $error = "Phone number is required.";
    } else {
        // Update user record
        $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->execute([$phone, $userId]);

        // Complete login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['pending_google_reg']);

        redirect('/dashboard');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/libphonenumber-js/1.10.44/libphonenumber-js.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
        .bg-billpay-green { background-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-gray-900">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100 animate-fade-in">
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-billpay-green text-white rounded-2xl flex items-center justify-center mx-auto mb-4 font-black text-2xl">
                B
            </div>
            <h2 class="text-xl font-black uppercase tracking-tight">Almost There!</h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Complete your profile to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Full Name</label>
                <input type="text" value="<?php echo $user['fullName']; ?>" disabled class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 font-bold text-sm text-gray-500 cursor-not-allowed">
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Email Address</label>
                <input type="text" value="<?php echo $user['email']; ?>" disabled class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 font-bold text-sm text-gray-500 cursor-not-allowed">
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Phone Number</label>
                <input type="text" name="phone" id="phoneInput" inputmode="numeric" pattern="[0-9]*" placeholder="+234..." class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                <p id="phoneError" class="hidden text-[8px] font-bold text-red-500 uppercase mt-1">Please enter a valid international phone number (e.g., +234...)</p>
            </div>

            <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all mt-4">Complete Registration</button>
        </form>

        <div class="mt-8 text-center">
            <a href="/logout" class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Start Over</a>
        </div>
    </div>

    <script>
        document.getElementById('regForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phoneInput').value;
            const errorHint = document.getElementById('phoneError');

            try {
                const phoneNumber = libphonenumber.parsePhoneNumber(phone);
                if (!phoneNumber || !phoneNumber.isValid()) {
                    e.preventDefault();
                    errorHint.classList.remove('hidden');
                } else {
                    errorHint.classList.add('hidden');
                }
            } catch (err) {
                e.preventDefault();
                errorHint.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
