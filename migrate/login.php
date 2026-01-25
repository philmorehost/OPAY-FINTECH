<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect('/migrate/dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    if (!checkRateLimit('login', 5, 300)) {
        $error = 'Too many attempts. Try again later.';
    } else {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['isSuspended']) {
            $error = 'ACCOUNT SUSPENDED. CONTACT SUPPORT.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                redirect('/migrate/admin/');
            } else {
                redirect('/migrate/dashboard');
            }
        }
    } else {
        $error = 'Invalid username or password';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Billpay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: #00c689; }
        .bg-billpay-green { background-color: #00c689; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[40px] shadow-2xl p-10 border border-gray-100">
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-3xl shadow-lg mx-auto mb-4">B</div>
            <h1 class="text-2xl font-black text-gray-800">Welcome Back</h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Secure Login to your wallet</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-black border border-red-100 text-center uppercase tracking-tight"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Username or Email</label>
                <input type="text" name="username" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Password</label>
                <input type="password" name="password" class="w-full p-5 bg-gray-50 rounded-[24px] border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
            </div>

            <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Sign In</button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-[10px] font-black text-gray-400 uppercase">Don't have an account? <a href="/migrate/register" class="text-billpay-green">Create One</a></p>
        </div>
    </div>
</body>
</html>
