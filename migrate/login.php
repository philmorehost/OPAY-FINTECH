<?php require_once 'includes/header.php'; if (is_logged_in()) { $user = get_current_user_data(); redirect($user['role'] === 'admin' ? 'admin' : 'dashboard'); }
$error = ''; if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Security token mismatch'; }
    else {
        $username = trim($_POST['username'] ?? ''); $password = $_POST['password'] ?? '';
        if ($username && $password) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)"); $stmt->execute([$username]); $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) { $_SESSION['user_id'] = $user['id']; redirect($user['role'] === 'admin' ? 'admin' : 'dashboard'); }
            else { $error = 'Invalid username or password'; }
        } else { $error = 'Please fill in all fields'; }
    }
} ?>
<div class="flex flex-col items-center justify-center min-h-screen px-6 bg-white animate-fade-in">
  <div class="w-full max-w-md">
    <div class="flex flex-col items-center mb-10"><div class="w-20 h-20 bg-opay-green rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-lg">O</div><h1 class="text-2xl font-bold text-gray-800">Welcome to OPay</h1></div>
    <form method="POST" action="login" class="space-y-6">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <?php if ($error): ?><div class="p-3 bg-red-50 text-red-500 text-sm rounded-lg border border-red-100"><?php echo h($error); ?></div><?php endif; ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Username</label><input type="text" name="username" class="w-full p-4 bg-gray-50 text-gray-900 border border-transparent focus:border-opay-green rounded-xl outline-none" required /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label><input type="password" name="password" class="w-full p-4 bg-gray-50 text-gray-900 border border-transparent focus:border-opay-green rounded-xl outline-none" required /></div>
      <button type="submit" class="w-full bg-opay-green text-white font-bold py-4 rounded-xl shadow-lg">Sign In</button>
    </form>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
