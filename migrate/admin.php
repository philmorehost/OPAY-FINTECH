<?php
// migrate/admin.php
require_once 'includes/header.php';
require_login();

// Handle return to admin BEFORE require_admin check
if (isset($_GET['action']) && $_GET['action'] === 'return_to_admin' && isset($_SESSION['original_admin_id'])) {
    $_SESSION['user_id'] = $_SESSION['original_admin_id'];
    unset($_SESSION['original_admin_id']);
    redirect('admin');
}

require_admin();

// Auto-migrate settings table for missing columns - more robust check
$cols_to_add = [
    'dailyLimitPhone' => 'INTEGER DEFAULT 10',
    'dailyLimitSmartCard' => 'INTEGER DEFAULT 5',
    'dailyLimitBetting' => 'INTEGER DEFAULT 5',
    'dailyLimitMeter' => 'INTEGER DEFAULT 5',
    'referralBonusFirstTx' => 'DECIMAL(20,2) DEFAULT 100',
    'darkModeEnabled' => 'INTEGER DEFAULT 0'
];
$existing_cols = [];
try {
    $q = $pdo->query("DESCRIBE settings");
    while ($row = $q->fetch()) { $existing_cols[] = $row['Field']; }
    foreach ($cols_to_add as $col => $def) {
        if (!in_array($col, $existing_cols)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN $col $def");
        }
    }
    // Refresh settings global
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch() ?: [];
} catch (Exception $e) {}

$user = get_current_user_data();
$page = $_GET['page'] ?? 'overview';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_user') {
        $stmt = $pdo->prepare("UPDATE users SET fullName = ?, email = ?, walletBalance = ? WHERE id = ?");
        $stmt->execute([$_POST['fullName'], $_POST['email'], $_POST['balance'], $_POST['userId']]);
        redirect('admin?page=users&success=User updated');
    } elseif ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['fullName'] ?? '');
        $password = $_POST['password'] ?? '';
        $balance = (float)($_POST['balance'] ?? 0);

        if ($username && $password) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                redirect('admin?page=users&error=Username already exists');
            } else {
                $id = 'user-' . generate_id(8);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, password, walletBalance, role) VALUES (?, ?, ?, ?, ?, ?, 'user')");
                $stmt->execute([$id, $username, $fullName, $email, $hashedPassword, $balance]);
                redirect('admin?page=users&success=User created successfully');
            }
        } else {
            redirect('admin?page=users&error=Username and Password are required');
        }
    } elseif ($action === 'toggle_suspend') {
        $stmt = $pdo->prepare("UPDATE users SET isSuspended = 1 - isSuspended WHERE id = ?");
        $stmt->execute([$_POST['userId']]);
        redirect('admin?page=users&success=Status updated');
    } elseif ($action === 'approve_deposit') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM deposit_requests WHERE id = ?"); $stmt->execute([$id]); $req = $stmt->fetch();
        if ($req && $req['status'] === 'pending') {
            $stmt = $pdo->prepare("UPDATE deposit_requests SET status = 'successful' WHERE id = ?"); $stmt->execute([$id]);
            update_user_balance($req['userId'], $req['amount']);
            log_transaction($req['userId'], 'Deposit', $req['amount'], 'successful', "Manual Deposit Approved", 'Wallet', 'Admin');
            redirect('admin?page=deposits&success=Deposit Approved');
        }
    } elseif ($action === 'reject_deposit') {
        $stmt = $pdo->prepare("UPDATE deposit_requests SET status = 'rejected' WHERE id = ?"); $stmt->execute([$_POST['id']]);
        redirect('admin?page=deposits&error=Deposit Rejected');
    } elseif ($action === 'manage_sms') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        if ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM sms_sender_ids WHERE id = ?"); $stmt->execute([$id]); $s = $stmt->fetch();
            if ($s) {
                $res = submit_kudi_sender_id($s['name'], $s['sampleMessage']);
                if ($res['status'] !== 'success') { redirect("admin?page=sms&error=" . urlencode($res['msg'])); }
            }
        }
        $stmt = $pdo->prepare("UPDATE sms_sender_ids SET status = ? WHERE id = ?"); $stmt->execute([$status, $id]);
        redirect('admin?page=sms&success=SMS ID Status updated');
    } elseif ($action === 'update_settings') {
        // Ensure settings row exists
        $count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        if ($count == 0) { $pdo->exec("INSERT INTO settings (siteName) VALUES ('VTU-Fintech')"); }

        $stmt = $pdo->prepare("UPDATE settings SET smsRate = ?, kudiSmsToken = ?, siteName = ?, siteDescription = ?, adminWhatsapp = ?, dailyLimitPhone = ?, dailyLimitSmartCard = ?, dailyLimitBetting = ?, dailyLimitMeter = ?, referralBonusFirstTx = ?, darkModeEnabled = ? WHERE id > 0 LIMIT 1");
        $stmt->execute([
            $_POST['smsRate'] ?? 0,
            $_POST['kudiSmsToken'] ?? '',
            $_POST['siteName'] ?? '',
            $_POST['siteDescription'] ?? '',
            $_POST['adminWhatsapp'] ?? '',
            $_POST['dailyLimitPhone'] ?? 10,
            $_POST['dailyLimitSmartCard'] ?? 5,
            $_POST['dailyLimitBetting'] ?? 5,
            $_POST['dailyLimitMeter'] ?? 5,
            $_POST['referralBonusFirstTx'] ?? 100,
            isset($_POST['darkModeEnabled']) ? 1 : 0
        ]);

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logoName = 'logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], 'uploads/' . $logoName);
            $pdo->prepare("UPDATE settings SET logoPath = ?")->execute(['uploads/' . $logoName]);
        }

        redirect('admin?page=settings&success=Settings updated');
    } elseif ($action === 'update_exam') {
        $providers = get_json_setting('examProviders') ?? [];
        foreach ($providers as &$p) { if ($p['id'] === $_POST['id']) { $p['userPrice'] = (float)$_POST['price']; $p['enabled'] = isset($_POST['enabled']); } }
        $stmt = $pdo->prepare("UPDATE settings SET examProviders = ?"); $stmt->execute([json_encode($providers)]);
        redirect('admin?page=api&success=Exam product updated');
    } elseif ($action === 'approve_kyc') {
        $stmt = $pdo->prepare("UPDATE kyc_submissions SET status = 'verified' WHERE id = ?"); $stmt->execute([$_POST['id']]);
        $stmt = $pdo->prepare("UPDATE users SET kycStatus = 'verified' WHERE id = ?"); $stmt->execute([$_POST['userId']]);
        add_notification($_POST['userId'], "KYC Verified", "Your account identity has been verified successfully.", 'success');
        redirect('admin?page=kyc&success=KYC Approved');
    } elseif ($action === 'reject_kyc') {
        $stmt = $pdo->prepare("UPDATE kyc_submissions SET status = 'rejected', rejectionReason = ? WHERE id = ?"); $stmt->execute([$_POST['reason'], $_POST['id']]);
        $stmt = $pdo->prepare("UPDATE users SET kycStatus = 'none' WHERE id = ?"); $stmt->execute([$_POST['userId']]);
        add_notification($_POST['userId'], "KYC Rejected", "Your identity verification was rejected: " . $_POST['reason'], 'error');
        redirect('admin?page=kyc&error=KYC Rejected');
    } elseif ($action === 'close_ticket') {
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?"); $stmt->execute([$_POST['id']]);
        redirect('admin?page=tickets&success=Ticket closed');
    } elseif ($action === 'broadcast') {
        $title = $_POST['title'];
        $message = $_POST['message'];
        $stmt = $pdo->query("SELECT id FROM users");
        while ($u = $stmt->fetch()) {
            add_notification($u['id'], $title, $message, 'info');
        }
        redirect('admin?page=overview&success=Broadcast sent to all users');
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'login_as' && isset($_GET['id'])) {
    $_SESSION['original_admin_id'] = $_SESSION['user_id'];
    $_SESSION['user_id'] = $_GET['id'];
    redirect('dashboard');
}
$stmt = $pdo->query("SELECT COUNT(*) FROM users"); $totalUsers = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT SUM(walletBalance) FROM users"); $platformBalance = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM sms_sender_ids WHERE status = 'pending'"); $pendingSMS = $stmt->fetchColumn();
$menuItems = [
    ['label' => 'Overview', 'icon' => 'layout-dashboard', 'page' => 'overview'],
    ['label' => 'Users', 'icon' => 'users', 'page' => 'users'],
    ['label' => 'KYC Review', 'icon' => 'shield-check', 'page' => 'kyc'],
    ['label' => 'Transactions', 'icon' => 'list', 'page' => 'transactions'],
    ['label' => 'Deposits', 'icon' => 'wallet', 'page' => 'deposits'],
    ['label' => 'Tickets', 'icon' => 'message-square', 'page' => 'tickets'],
    ['label' => 'SMS IDs', 'icon' => 'message-circle', 'page' => 'sms'],
    ['label' => 'Broadcast', 'icon' => 'megaphone', 'page' => 'broadcast'],
    ['label' => 'API Manager', 'icon' => 'database', 'page' => 'api'],
    ['label' => 'Settings', 'icon' => 'settings', 'page' => 'settings']
];
?>
<div class="flex min-h-screen bg-gray-50 text-gray-900">
  <aside class="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100 shadow-sm">
    <div class="p-8 flex items-center gap-3"><div class="w-10 h-10 bg-vtu-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">V</div><span class="font-black text-lg tracking-tight">Admin Portal</span></div>
    <nav class="flex-1 px-4 py-4 space-y-1"><?php foreach ($menuItems as $item): ?><a href="?page=<?php echo $item['page']; ?>" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $page === $item['page'] ? 'bg-vtu-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'; ?>"><i data-lucide="<?php echo $item['icon']; ?>" size="20"></i><?php echo h($item['label']); ?></a><?php endforeach; ?></nav>
    <div class="p-6 border-t border-gray-100"><a href="logout" class="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><i data-lucide="log-out" size="20"></i> Sign Out</a></div>
  </aside>
  <main class="flex-1 ml-72 p-12">
    <?php if ($success): ?><div class="mb-8 p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-8 p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>
    <div class="max-w-6xl mx-auto animate-fade-in">
        <?php if ($page === 'overview'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Total Users</div><div class="text-xl font-black"><?php echo $totalUsers; ?></div></div><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Platform Balance</div><div class="text-xl font-black"><?php echo format_currency($platformBalance); ?></div></div><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Pending SMS</div><div class="text-xl font-black text-amber-500"><?php echo $pendingSMS; ?></div></div></div>
        <?php elseif ($page === 'users'): ?>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-black">User Management</h2>
                <button onclick="document.getElementById('createUserModal').classList.remove('hidden')" class="bg-vtu-green text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg flex items-center gap-2">
                    <i data-lucide="plus" size="16"></i> Create User
                </button>
            </div>
            <div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Balance</th><th class="px-8 py-4">KYC</th><th class="px-8 py-4">Status</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT * FROM users"); while ($u = $stmt->fetch()): ?><tr><td class="px-8 py-5 flex flex-col"><span class="text-xs font-bold"><?php echo h($u['fullName']); ?></span><span class="text-[9px] text-gray-400">@<?php echo h($u['username']); ?></span></td><td class="px-8 py-5 text-xs font-black text-vtu-green"><?php echo format_currency($u['walletBalance']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo h($u['kycStatus']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo $u['isSuspended'] ? '<span class="text-red-500">Suspended</span>' : '<span class="text-green-500">Active</span>'; ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><button onclick='editUser(<?php echo json_encode($u); ?>)' class="p-2 text-blue-500 bg-blue-50 rounded-xl"><i data-lucide="edit-3" size="16"></i></button><a href="?page=users&action=login_as&id=<?php echo $u['id']; ?>" class="p-2 text-indigo-500 bg-indigo-50 rounded-xl" title="Login as User"><i data-lucide="log-in" size="16"></i></a><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="toggle_suspend"><input type="hidden" name="userId" value="<?php echo $u['id']; ?>"><button type="submit" class="p-2 <?php echo $u['isSuspended'] ? 'text-green-500 bg-green-50' : 'text-red-500 bg-red-50'; ?> rounded-xl"><i data-lucide="<?php echo $u['isSuspended'] ? 'unlock' : 'lock'; ?>" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'kyc'): ?>
            <h2 class="text-2xl font-black mb-6">KYC Submissions</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Type</th><th class="px-8 py-4">Document</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT * FROM kyc_submissions WHERE status = 'pending'"); while ($k = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($k['fullName']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo h($k['idType']); ?> (<?php echo h($k['idNumber']); ?>)</td><td class="px-8 py-5"><a href="<?php echo h($k['idImageUrl']); ?>" target="_blank" class="text-vtu-green text-[10px] font-black uppercase underline">View Doc</a></td><td class="px-8 py-5 text-right flex justify-end gap-2"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="approve_kyc"><input type="hidden" name="id" value="<?php echo $k['id']; ?>"><input type="hidden" name="userId" value="<?php echo $k['userId']; ?>"><button type="submit" class="p-2 text-green-500 bg-green-50 rounded-xl"><i data-lucide="check" size="16"></i></button></form><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="reject_kyc"><input type="hidden" name="id" value="<?php echo $k['id']; ?>"><input type="hidden" name="userId" value="<?php echo $k['userId']; ?>"><input type="text" name="reason" placeholder="Reason" class="text-[9px] p-1 border rounded" required><button type="submit" class="p-2 text-red-500 bg-red-50 rounded-xl"><i data-lucide="x" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'transactions'): ?>
            <h2 class="text-2xl font-black mb-6">Global Transactions</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Type</th><th class="px-8 py-4">Amount</th><th class="px-8 py-4 text-right">Status</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT t.*, u.fullName FROM transactions t JOIN users u ON t.userId = u.id ORDER BY t.date DESC LIMIT 50"); while ($t = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($t['fullName']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo h($t['type']); ?></td><td class="px-8 py-5 text-xs font-black"><?php echo format_currency($t['amount']); ?></td><td class="px-8 py-5 text-right"><span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $t['status'] === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>"><?php echo h($t['status']); ?></span></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'tickets'): ?>
            <h2 class="text-2xl font-black mb-6">Support Tickets</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Subject</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT t.*, u.fullName FROM tickets t JOIN users u ON t.userId = u.id WHERE t.status = 'open' ORDER BY t.createdAt DESC"); while ($t = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($t['fullName']); ?></td><td class="px-8 py-5 text-[10px] font-bold"><?php echo h($t['subject']); ?></td><td class="px-8 py-5 text-right"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="close_ticket"><input type="hidden" name="id" value="<?php echo $t['id']; ?>"><button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-black uppercase">Close</button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'broadcast'): ?>
            <h2 class="text-2xl font-black mb-6">Broadcast Message</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 p-8 max-w-xl"><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="broadcast"><div class="space-y-6"><div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Subject / Title</label><input type="text" name="title" required placeholder="e.g. System Maintenance" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div><div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Message Content</label><textarea name="message" required placeholder="Type your message to all users..." class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold h-32"></textarea></div><button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black shadow-lg">SEND BROADCAST</button></div></form></div>
        <?php elseif ($page === 'deposits'): ?>
            <h2 class="text-2xl font-black mb-6">Deposit Requests</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Amount</th><th class="px-8 py-4">Method</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT d.*, u.fullName FROM deposit_requests d JOIN users u ON d.userId = u.id WHERE d.status = 'pending'"); while ($r = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($r['fullName']); ?><br><small class="text-gray-400"><?php echo h($r['senderName']); ?></small></td><td class="px-8 py-5 text-xs font-black text-indigo-600"><?php echo format_currency($r['amount']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo h($r['method']); ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="approve_deposit"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="p-2 text-green-500 bg-green-50 rounded-xl"><i data-lucide="check" size="16"></i></button></form><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="reject_deposit"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="p-2 text-red-500 bg-red-50 rounded-xl"><i data-lucide="x" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'sms'): ?>
            <h2 class="text-2xl font-black mb-6">SMS Sender IDs</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">Sender ID</th><th class="px-8 py-4">Sample Message</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT s.*, u.fullName FROM sms_sender_ids s JOIN users u ON s.userId = u.id WHERE s.status = 'pending'"); while ($s = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($s['name']); ?><br><small class="text-gray-400">By <?php echo h($s['fullName']); ?></small></td><td class="px-8 py-5 text-[10px] max-w-xs truncate"><?php echo h($s['sampleMessage']); ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="manage_sms"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="status" value="approved"><button type="submit" class="p-2 text-green-500 bg-green-50 rounded-xl"><i data-lucide="check" size="16"></i></button></form><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="manage_sms"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="status" value="rejected"><button type="submit" class="p-2 text-red-500 bg-red-50 rounded-xl"><i data-lucide="x" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'api'): ?>
            <h2 class="text-2xl font-black mb-6">API Manager (Exam Products)</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 p-8 space-y-6"><?php $providers = get_json_setting('examProviders') ?? []; foreach ($providers as $p): ?><form method="POST" class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="update_exam"><input type="hidden" name="id" value="<?php echo $p['id']; ?>"><div class="font-bold"><?php echo h($p['name']); ?></div><div class="flex gap-4"><input type="number" name="price" value="<?php echo $p['userPrice']; ?>" class="p-2 w-24 rounded-lg border text-sm font-bold"><label class="flex items-center gap-2 text-xs font-bold uppercase"><input type="checkbox" name="enabled" <?php echo $p['enabled'] ? 'checked' : ''; ?>> Enabled</label><button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-black uppercase">Update</button></div></form><?php endforeach; ?></div>
        <?php elseif ($page === 'settings'): ?>
            <h2 class="text-2xl font-black mb-6">General Settings</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 p-8 max-w-2xl">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_settings">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Site Name</label><input type="text" name="siteName" value="<?php echo h($settings['siteName'] ?? 'VTU-Fintech'); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">WhatsApp Number</label><input type="text" name="adminWhatsapp" value="<?php echo h($settings['adminWhatsapp'] ?? ''); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                </div>
                <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Site Description (SEO)</label><textarea name="siteDescription" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold h-24"><?php echo h($settings['siteDescription'] ?? ''); ?></textarea></div>
                <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Site Logo</label>
                    <?php if (!empty($settings['logoPath'])): ?><img src="<?php echo h($settings['logoPath']); ?>" class="h-12 mb-2"><?php endif; ?>
                    <input type="file" name="logo" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">SMS Price (per unit)</label><input type="number" step="0.01" name="smsRate" value="<?php echo $settings['smsRate'] ?? 0; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">KudiSMS API Token</label><input type="text" name="kudiSmsToken" value="<?php echo h($settings['kudiSmsToken'] ?? ''); ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                </div>
                <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest pt-4">Daily Transaction Frequency Limits</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Airtime (per Phone)</label><input type="number" name="dailyLimitPhone" value="<?php echo $settings['dailyLimitPhone'] ?? 10; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Cable (per SmartCard)</label><input type="number" name="dailyLimitSmartCard" value="<?php echo $settings['dailyLimitSmartCard'] ?? 5; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Betting (per ID)</label><input type="number" name="dailyLimitBetting" value="<?php echo $settings['dailyLimitBetting'] ?? 5; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                    <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Electric (per Meter)</label><input type="number" name="dailyLimitMeter" value="<?php echo $settings['dailyLimitMeter'] ?? 5; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                </div>
                <div class="flex items-center gap-4 py-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="darkModeEnabled" <?php echo ($settings['darkModeEnabled'] ?? 0) ? 'checked' : ''; ?> class="w-5 h-5 accent-vtu-green">
                        <span class="text-xs font-black uppercase tracking-widest">Enable Dark Mode UI</span>
                    </label>
                </div>
                <div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">Referral Bonus (First Transaction)</label><input type="number" step="0.01" name="referralBonusFirstTx" value="<?php echo $settings['referralBonusFirstTx'] ?? 100; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div>
                <button type="submit" class="w-full bg-vtu-green text-white py-4 rounded-2xl font-black shadow-lg">SAVE ALL SETTINGS</button>
            </form></div>
        <?php endif; ?>
    </div>
  </main>
</div>
<div id="editUserModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6"><div class="bg-white w-full max-w-sm rounded-[40px] p-8"><h3 class="text-lg font-black mb-6">Edit User</h3><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="userId" id="editUserId"><div class="space-y-4"><input type="text" name="fullName" id="editFullName" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Full Name"><input type="email" name="email" id="editEmail" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Email"><input type="number" step="0.01" name="balance" id="editBalance" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Balance"><button type="submit" class="w-full bg-vtu-green text-white py-4 rounded-2xl font-black">SAVE CHANGES</button><button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')" class="w-full text-gray-400 font-bold text-xs uppercase mt-4">Cancel</button></div></form></div></div>

<div id="createUserModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-sm rounded-[40px] p-8">
        <h3 class="text-lg font-black mb-6">Create New User</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="create_user">
            <div class="space-y-4">
                <input type="text" name="fullName" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Full Name" required>
                <input type="text" name="username" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Username" required>
                <input type="email" name="email" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Email Address">
                <input type="password" name="password" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Password" required>
                <input type="number" step="0.01" name="balance" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Initial Balance (Optional)" value="0">
                <button type="submit" class="w-full bg-vtu-green text-white py-4 rounded-2xl font-black shadow-lg">CREATE ACCOUNT</button>
                <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')" class="w-full text-gray-400 font-bold text-xs uppercase mt-4">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script> function editUser(u) { document.getElementById('editUserId').value = u.id; document.getElementById('editFullName').value = u.fullName; document.getElementById('editEmail').value = u.email; document.getElementById('editBalance').value = u.walletBalance; document.getElementById('editUserModal').classList.remove('hidden'); } </script>
<?php require_once 'includes/footer.php'; ?>
