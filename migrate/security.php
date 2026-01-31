<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Security Center';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $action = $_POST['action'];

    if ($action === 'set_fund_password') {
        $pass = $_POST['fund_password'];
        $confirm = $_POST['confirm_password'];
        if ($pass !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($pass) < 6) {
            $error = "Fund Password must be at least 6 characters.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET fundPassword = ? WHERE id = ?");
            if ($stmt->execute([$hash, $currentUser['id']])) {
                $success = "Fund Password updated successfully!";
            }
        }
    } elseif ($action === 'enable_2fa') {
        $secret = $_POST['secret'];
        $code = $_POST['code'];
        if (TOTP::verifyCode($secret, $code)) {
            $pdo->prepare("UPDATE users SET google2faSecret = ?, google2faEnabled = 1 WHERE id = ?")->execute([$secret, $currentUser['id']]);
            $success = "Google Authenticator enabled!";
        } else {
            $error = "Invalid verification code.";
        }
    } elseif ($action === 'enable_email_2fa') {
        $pdo->prepare("UPDATE users SET email2faEnabled = 1 WHERE id = ?")->execute([$currentUser['id']]);
        $success = "Email 2FA enabled!";
    } elseif ($action === 'disable_2fa') {
        $pdo->prepare("UPDATE users SET google2faEnabled = 0, email2faEnabled = 0 WHERE id = ?")->execute([$currentUser['id']]);
        $success = "Two-Factor Authentication disabled.";
    } elseif ($action === 'add_whitelist') {
        $address = sanitize($_POST['address']);
        $label = sanitize($_POST['label']);
        $type = $_POST['type'];

        $unlockedAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $pdo->prepare("INSERT INTO withdrawal_whitelist (userId, address, label, type, unlockedAt) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$currentUser['id'], $address, $label, $type, $unlockedAt])) {
            $success = "Address added! It will be available for withdrawal in 24 hours.";
        }
    } elseif ($action === 'delete_whitelist') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM withdrawal_whitelist WHERE id = ? AND userId = ?")->execute([$id, $currentUser['id']]);
        $success = "Address removed.";
    }

    // Refresh user
    $currentUser = fetchUser($pdo, $currentUser['id']);
}

$secret = $currentUser['google2faSecret'] ?: TOTP::generateSecret();
$qrCodeUrl = TOTP::getQrCodeUrl($currentUser['email'], $settings['siteName'] ?? 'BillPay', $secret);

$stmt = $pdo->prepare("SELECT * FROM withdrawal_whitelist WHERE userId = ? ORDER BY createdAt DESC");
$stmt->execute([$currentUser['id']]);
$whitelist = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto bg-gray-50 min-h-screen pb-24 text-gray-900">
    <div class="bg-white p-6 border-b border-gray-100 flex items-center gap-4 sticky top-0 z-20">
        <a href="/dashboard" class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Security Center</h1>
    </div>

    <div class="p-6 max-w-4xl mx-auto space-y-10">
        <?php if ($success): ?><div class="p-6 bg-green-50 text-green-800 rounded-[32px] text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="p-6 bg-red-50 text-red-800 rounded-[32px] text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

        <!-- Fund Password -->
        <div class="bg-white p-8 md:p-12 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center"><i data-lucide="lock" class="w-6 h-6"></i></div>
                <div>
                    <h2 class="text-lg font-black uppercase tracking-tight">Fund Password</h2>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Required for all transactions</p>
                </div>
            </div>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="set_fund_password">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="password" name="fund_password" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="New Fund Password" class="w-full p-5 bg-gray-50 rounded-2xl font-black outline-none border-2 border-transparent focus:border-billpay-green">
                    <input type="password" name="confirm_password" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="Confirm Password" class="w-full p-5 bg-gray-50 rounded-2xl font-black outline-none border-2 border-transparent focus:border-billpay-green">
                </div>
                <button type="submit" class="w-full bg-gray-900 text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl">Update Password</button>
            </form>
        </div>

        <!-- 2FA -->
        <div class="bg-white p-8 md:p-12 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center"><i data-lucide="shield-check" class="w-6 h-6"></i></div>
                    <div>
                        <h2 class="text-lg font-black uppercase tracking-tight">Google Authenticator</h2>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Multi-layer account security</p>
                    </div>
                </div>
                <span class="px-4 py-2 <?php echo $currentUser['google2faEnabled'] ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'; ?> text-[10px] font-black rounded-full uppercase"><?php echo $currentUser['google2faEnabled'] ? 'Enabled' : 'Disabled'; ?></span>
            </div>

            <?php if (!$currentUser['google2faEnabled']): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center mt-10">
                <div class="bg-gray-50 p-6 rounded-3xl flex flex-col items-center gap-4">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($qrCodeUrl); ?>" class="rounded-xl border-4 border-white shadow-sm">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?php echo $secret; ?></div>
                </div>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="enable_2fa">
                    <input type="hidden" name="secret" value="<?php echo $secret; ?>">
                    <p class="text-[10px] font-bold text-gray-500 uppercase leading-relaxed">Scan the QR code with Google Authenticator or Authy, then enter the 6-digit code below to confirm.</p>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000 000" class="w-full p-6 bg-gray-50 rounded-2xl font-black text-2xl text-center tracking-[0.5em] outline-none">
                    <button type="submit" class="w-full bg-billpay-green text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-green-100">Enable 2FA</button>
                </form>
            </div>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="disable_2fa">
                <button type="submit" class="w-full py-5 border-2 border-red-50 text-red-500 rounded-2xl font-black uppercase tracking-widest hover:bg-red-50 transition-all">Disable Two-Factor Authentication</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Email 2FA -->
        <div class="bg-white p-8 md:p-12 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center"><i data-lucide="mail" class="w-6 h-6"></i></div>
                    <div>
                        <h2 class="text-lg font-black uppercase tracking-tight">Email Authentication</h2>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Verify via <?php echo $currentUser['email']; ?></p>
                    </div>
                </div>
                <span class="px-4 py-2 <?php echo $currentUser['email2faEnabled'] ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'; ?> text-[10px] font-black rounded-full uppercase"><?php echo $currentUser['email2faEnabled'] ? 'Enabled' : 'Disabled'; ?></span>
            </div>

            <?php if (!$currentUser['email2faEnabled']): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="enable_email_2fa">
                <button type="submit" class="w-full bg-emerald-500 text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-100">Enable Email 2FA</button>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="disable_2fa">
                <button type="submit" class="w-full py-5 border-2 border-red-50 text-red-500 rounded-2xl font-black uppercase tracking-widest hover:bg-red-50 transition-all">Disable Email 2FA</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Whitelist -->
        <div class="bg-white p-8 md:p-12 rounded-[40px] shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center"><i data-lucide="list-check" class="w-6 h-6"></i></div>
                    <div>
                        <h2 class="text-lg font-black uppercase tracking-tight">Withdrawal Whitelist</h2>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Secure destination addresses</p>
                    </div>
                </div>
                <button onclick="document.getElementById('wlModal').classList.remove('hidden')" class="px-4 py-2 bg-gray-900 text-white text-[10px] font-black rounded-full uppercase">Add New</button>
            </div>

            <div class="space-y-4">
                <?php foreach ($whitelist as $item):
                    $locked = strtotime($item['unlockedAt']) > time();
                ?>
                <div class="p-6 bg-gray-50 rounded-3xl border border-gray-100 flex items-center justify-between group">
                    <div>
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?php echo $item['label']; ?> (<?php echo strtoupper($item['type']); ?>)</div>
                        <div class="text-sm font-black text-gray-900"><?php echo $item['address']; ?></div>
                        <?php if ($locked): ?>
                        <div class="mt-2 flex items-center gap-2 text-[8px] font-black text-amber-500 uppercase">
                            <i data-lucide="clock" class="w-3 h-3"></i> Locked for 24h (Expires: <?php echo date('d M, H:i', strtotime($item['unlockedAt'])); ?>)
                        </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="delete_whitelist">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
                    </form>
                </div>
                <?php endforeach; if(empty($whitelist)) echo '<div class="text-center py-10 text-[10px] font-black text-gray-300 uppercase">No whitelisted addresses</div>'; ?>
            </div>
        </div>
    </div>
</div>

<div id="wlModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-md rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight">Add Whitelist</h3>
            <button onclick="document.getElementById('wlModal').classList.add('hidden')"><i data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_whitelist">

            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Label (e.g. My Ledger)</label>
                    <input type="text" name="label" placeholder="Wallet Label" class="w-full p-4 bg-gray-50 rounded-2xl font-bold" required>
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Type</label>
                    <select name="type" class="w-full p-4 bg-gray-50 rounded-2xl font-bold">
                        <option value="crypto">Crypto Wallet</option>
                        <option value="bank">Bank Account</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Address / Account</label>
                    <input type="text" name="address" placeholder="Paste address here" class="w-full p-4 bg-gray-50 rounded-2xl font-bold" required>
                </div>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl uppercase tracking-widest">Confirm Whitelist</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
