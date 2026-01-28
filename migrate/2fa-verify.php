<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Verify 2FA';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $code = $_POST['code'];
    $success = false;

    if ($currentUser['google2faEnabled']) {
        if (TOTP::verifyCode($currentUser['google2faSecret'], $code)) $success = true;
    } elseif ($currentUser['email2faEnabled']) {
        if (isset($_SESSION['email_2fa_code']) && $_SESSION['email_2fa_code'] == $code && time() < $_SESSION['email_2fa_expiry']) {
            $success = true;
            unset($_SESSION['email_2fa_code'], $_SESSION['email_2fa_expiry']);
        }
    } else {
        $success = true; // No 2FA enabled
    }

    if ($success) {
        $_SESSION['2fa_verified'] = $currentUser['id'];
        $redirect = $_SESSION['2fa_redirect'] ?? '/dashboard';
        unset($_SESSION['2fa_redirect']);
        redirect($redirect);
    } else {
        $error = "Invalid 2FA code. Please try again.";
    }
}

if ($currentUser['email2faEnabled'] && !isset($_POST['code']) && !isset($_SESSION['email_2fa_code'])) {
    sendEmail2fa($pdo, $currentUser);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto bg-gray-900 min-h-screen flex items-center justify-center p-6 text-white">
    <div class="w-full max-w-md bg-white/5 border border-white/10 rounded-[48px] p-10 md:p-12 shadow-2xl animate-slide-up">
        <div class="text-center space-y-4 mb-10">
            <div class="w-20 h-20 bg-amber-500/10 rounded-3xl flex items-center justify-center mx-auto mb-6">
                <i data-lucide="shield-check" class="w-10 h-10 text-amber-500"></i>
            </div>
            <h1 class="text-2xl font-black uppercase tracking-tighter">Two-Factor Authentication</h1>
            <p class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em]">Enter your 6-digit code to continue</p>
        </div>

        <?php if ($error): ?><div class="p-6 bg-red-500/10 text-red-400 rounded-3xl text-[10px] font-black border border-red-500/20 uppercase text-center mb-8"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="text" name="code" autofocus placeholder="000 000" class="w-full p-8 bg-white/5 rounded-3xl font-black text-4xl text-center tracking-[0.5em] outline-none border-2 border-transparent focus:border-amber-500 transition-all">
            <button type="submit" class="w-full bg-amber-500 text-white py-6 rounded-[32px] font-black uppercase tracking-widest shadow-xl shadow-amber-500/20 hover:scale-[1.02] active:scale-95 transition-all">Verify & Unlock</button>
        </form>

        <div class="mt-10 text-center">
            <a href="/logout" class="text-[10px] font-black text-white/20 hover:text-white uppercase tracking-widest transition-all">Switch Account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
