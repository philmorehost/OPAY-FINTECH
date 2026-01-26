<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect('/dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $fullName = sanitize($_POST['fullName']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $referrerUsername = sanitize($_POST['referrer'] ?? '');

    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or Email already exists';
        } else {
            $userId = 'u' . bin2hex(random_bytes(4));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, phone, password, role, walletBalance, bonusCoins) VALUES (?, ?, ?, ?, ?, ?, 'user', ?, ?)");
                $stmt->execute([$userId, $username, $fullName, $email, $phone, $hashedPassword, $settings['welcomeBonus'] / $settings['conversionRate'], $settings['welcomeBonus']]);

                // Handle Referral
                if ($referrerUsername) {
                    $refBonusCoins = $settings['referralBonus'];
                    $refBonusCash = $refBonusCoins / $settings['conversionRate'];

                    $stmt = $pdo->prepare("UPDATE users SET referralCount = referralCount + 1, referralEarnings = referralEarnings + ?, bonusCoins = bonusCoins + ?, walletBalance = walletBalance + ? WHERE username = ?");
                    $stmt->execute([$refBonusCash, $refBonusCoins, $refBonusCash, $referrerUsername]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                die("Registration failed: " . $e->getMessage());
            }

            // Welcome Email
            sendMail($pdo, $email, "Welcome to Billpay", "Hi $fullName,<br><br>Thank you for joining Billpay! Your account has been created successfully.<br><br>Enjoy seamless bill payments and rewards.");

            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';

            redirect('/dashboard');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $settings['primaryColor'] ?? '#00c689'; ?>">
    <?php if (!empty($settings['pwaEnabled'])): ?>
    <link rel="manifest" href="/manifest.json.php?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>">
    <link rel="apple-touch-icon" href="<?php echo !empty($settings['pwaIcon']) ? '/'.$settings['pwaIcon'].'?v='.($settings['siteVersion'] ?? '1.0.0') : '/uploads/logo.png'; ?>">
    <?php endif; ?>
    <title>Register - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (!empty($settings['pwaEnabled'])): ?>
    <script>
        window.addEventListener('load', () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js?v=<?php echo $settings['siteVersion'] ?? '1.0.0'; ?>');
            }
        });
    </script>
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?php echo $settings['primaryColor'] ?? '#00c689'; ?>;
        }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: var(--primary-color); }
        .bg-billpay-green { background-color: var(--primary-color); }
        .focus\:border-billpay-green:focus { border-color: var(--primary-color); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100">
        <div class="text-center mb-10">
            <img src="/<?php echo !empty($settings['pwaIcon']) ? $settings['pwaIcon'] : 'uploads/logo.png'; ?>" class="w-16 h-16 object-contain mx-auto mb-4 rounded-2xl shadow-lg">
            <h1 class="text-2xl font-black text-gray-800"><?php echo $settings['senderName'] ?? 'Billpay'; ?></h1>
            <h1 class="text-lg font-bold text-gray-600 mt-2">Create Account</h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Join the future of bill payments</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase tracking-tight"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="referrer" value="<?php echo sanitize($_GET['ref'] ?? ''); ?>">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Full Name</label>
                <input type="text" name="fullName" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Username</label>
                    <input type="text" name="username" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Phone</label>
                    <input type="text" name="phone" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Email Address</label>
                <input type="email" name="email" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Password</label>
                <input type="password" name="password" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Confirm Password</label>
                <input type="password" name="confirm_password" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>

            <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 mt-4">Sign Up</button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-[10px] font-black text-gray-400 uppercase">Already have an account? <a href="/login" class="text-billpay-green">Login</a></p>
        </div>
    </div>
</body>
</html>
