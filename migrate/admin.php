<?php
// migrate/admin.php
require_once 'includes/header.php';
require_login();
require_admin();

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
        $stmt = $pdo->prepare("UPDATE settings SET smsRate = ?, kudiSmsToken = ?");
        $stmt->execute([$_POST['smsRate'], $_POST['kudiSmsToken']]);
        redirect('admin?page=settings&success=Settings updated');
    } elseif ($action === 'update_exam') {
        $providers = get_json_setting('examProviders') ?? [];
        foreach ($providers as &$p) { if ($p['id'] === $_POST['id']) { $p['userPrice'] = (float)$_POST['price']; $p['enabled'] = isset($_POST['enabled']); } }
        $stmt = $pdo->prepare("UPDATE settings SET examProviders = ?"); $stmt->execute([json_encode($providers)]);
        redirect('admin?page=api&success=Exam product updated');
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'login_as' && isset($_GET['id'])) { $_SESSION['user_id'] = $_GET['id']; redirect('dashboard'); }
$stmt = $pdo->query("SELECT COUNT(*) FROM users"); $totalUsers = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT SUM(walletBalance) FROM users"); $platformBalance = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM sms_sender_ids WHERE status = 'pending'"); $pendingSMS = $stmt->fetchColumn();
$menuItems = [['label' => 'Overview', 'icon' => 'layout-dashboard', 'page' => 'overview'], ['label' => 'Users', 'icon' => 'users', 'page' => 'users'], ['label' => 'Deposits', 'icon' => 'wallet', 'page' => 'deposits'], ['label' => 'SMS IDs', 'icon' => 'message-circle', 'page' => 'sms'], ['label' => 'API Manager', 'icon' => 'database', 'page' => 'api'], ['label' => 'Settings', 'icon' => 'settings', 'page' => 'settings']];
?>
<div class="flex min-h-screen bg-gray-50 text-gray-900">
  <aside class="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100 shadow-sm">
    <div class="p-8 flex items-center gap-3"><div class="w-10 h-10 bg-opay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">O</div><span class="font-black text-lg tracking-tight">Admin Portal</span></div>
    <nav class="flex-1 px-4 py-4 space-y-1"><?php foreach ($menuItems as $item): ?><a href="?page=<?php echo $item['page']; ?>" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $page === $item['page'] ? 'bg-opay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'; ?>"><i data-lucide="<?php echo $item['icon']; ?>" size="20"></i><?php echo h($item['label']); ?></a><?php endforeach; ?></nav>
    <div class="p-6 border-t border-gray-100"><a href="logout" class="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><i data-lucide="log-out" size="20"></i> Sign Out</a></div>
  </aside>
  <main class="flex-1 ml-72 p-12">
    <?php if ($success): ?><div class="mb-8 p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-8 p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>
    <div class="max-w-6xl mx-auto animate-fade-in">
        <?php if ($page === 'overview'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Total Users</div><div class="text-xl font-black"><?php echo $totalUsers; ?></div></div><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Platform Balance</div><div class="text-xl font-black"><?php echo format_currency($platformBalance); ?></div></div><div class="bg-white p-7 rounded-3xl border border-gray-100"><div class="text-[10px] text-gray-400 font-black uppercase mb-1">Pending SMS</div><div class="text-xl font-black text-amber-500"><?php echo $pendingSMS; ?></div></div></div>
        <?php elseif ($page === 'users'): ?>
            <h2 class="text-2xl font-black mb-6">User Management</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Balance</th><th class="px-8 py-4">Status</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT * FROM users"); while ($u = $stmt->fetch()): ?><tr><td class="px-8 py-5 flex flex-col"><span class="text-xs font-bold"><?php echo h($u['fullName']); ?></span><span class="text-[9px] text-gray-400">@<?php echo h($u['username']); ?></span></td><td class="px-8 py-5 text-xs font-black text-opay-green"><?php echo format_currency($u['walletBalance']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo $u['isSuspended'] ? '<span class="text-red-500">Suspended</span>' : '<span class="text-green-500">Active</span>'; ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><button onclick='editUser(<?php echo json_encode($u); ?>)' class="p-2 text-blue-500 bg-blue-50 rounded-xl"><i data-lucide="edit-3" size="16"></i></button><a href="?page=users&action=login_as&id=<?php echo $u['id']; ?>" class="p-2 text-indigo-500 bg-indigo-50 rounded-xl" title="Login as User"><i data-lucide="log-in" size="16"></i></a><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="toggle_suspend"><input type="hidden" name="userId" value="<?php echo $u['id']; ?>"><button type="submit" class="p-2 <?php echo $u['isSuspended'] ? 'text-green-500 bg-green-50' : 'text-red-500 bg-red-50'; ?> rounded-xl"><i data-lucide="<?php echo $u['isSuspended'] ? 'unlock' : 'lock'; ?>" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'deposits'): ?>
            <h2 class="text-2xl font-black mb-6">Deposit Requests</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">User</th><th class="px-8 py-4">Amount</th><th class="px-8 py-4">Method</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT d.*, u.fullName FROM deposit_requests d JOIN users u ON d.userId = u.id WHERE d.status = 'pending'"); while ($r = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($r['fullName']); ?><br><small class="text-gray-400"><?php echo h($r['senderName']); ?></small></td><td class="px-8 py-5 text-xs font-black text-indigo-600"><?php echo format_currency($r['amount']); ?></td><td class="px-8 py-5 text-[10px] font-black uppercase"><?php echo h($r['method']); ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="approve_deposit"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="p-2 text-green-500 bg-green-50 rounded-xl"><i data-lucide="check" size="16"></i></button></form><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="reject_deposit"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="p-2 text-red-500 bg-red-50 rounded-xl"><i data-lucide="x" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'sms'): ?>
            <h2 class="text-2xl font-black mb-6">SMS Sender IDs</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left"><thead><tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th class="px-8 py-4">Sender ID</th><th class="px-8 py-4">Sample Message</th><th class="px-8 py-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-50"><?php $stmt = $pdo->query("SELECT s.*, u.fullName FROM sms_sender_ids s JOIN users u ON s.userId = u.id WHERE s.status = 'pending'"); while ($s = $stmt->fetch()): ?><tr><td class="px-8 py-5 font-bold text-xs"><?php echo h($s['name']); ?><br><small class="text-gray-400">By <?php echo h($s['fullName']); ?></small></td><td class="px-8 py-5 text-[10px] max-w-xs truncate"><?php echo h($s['sampleMessage']); ?></td><td class="px-8 py-5 text-right flex justify-end gap-2"><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="manage_sms"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="status" value="approved"><button type="submit" class="p-2 text-green-500 bg-green-50 rounded-xl"><i data-lucide="check" size="16"></i></button></form><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="manage_sms"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="status" value="rejected"><button type="submit" class="p-2 text-red-500 bg-red-50 rounded-xl"><i data-lucide="x" size="16"></i></button></form></td></tr><?php endwhile; ?></tbody></table></div>
        <?php elseif ($page === 'api'): ?>
            <h2 class="text-2xl font-black mb-6">API Manager (Exam Products)</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 p-8 space-y-6"><?php $providers = get_json_setting('examProviders') ?? []; foreach ($providers as $p): ?><form method="POST" class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="update_exam"><input type="hidden" name="id" value="<?php echo $p['id']; ?>"><div class="font-bold"><?php echo h($p['name']); ?></div><div class="flex gap-4"><input type="number" name="price" value="<?php echo $p['userPrice']; ?>" class="p-2 w-24 rounded-lg border text-sm font-bold"><label class="flex items-center gap-2 text-xs font-bold uppercase"><input type="checkbox" name="enabled" <?php echo $p['enabled'] ? 'checked' : ''; ?>> Enabled</label><button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-black uppercase">Update</button></div></form><?php endforeach; ?></div>
        <?php elseif ($page === 'settings'): ?>
            <h2 class="text-2xl font-black mb-6">General Settings</h2><div class="bg-white rounded-[40px] shadow-sm border border-gray-100 p-8 max-w-2xl"><form method="POST" class="space-y-6"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="update_settings"><div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">SMS Price (per unit)</label><input type="number" step="0.01" name="smsRate" value="<?php echo $settings['smsRate']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div><div><label class="block text-[10px] font-black text-gray-400 uppercase mb-2">KudiSMS API Token</label><input type="text" name="kudiSmsToken" value="<?php echo $settings['kudiSmsToken']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold"></div><button type="submit" class="w-full bg-opay-green text-white py-4 rounded-2xl font-black shadow-lg">SAVE SETTINGS</button></form></div>
        <?php endif; ?>
    </div>
  </main>
</div>
<div id="editUserModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-6"><div class="bg-white w-full max-w-sm rounded-[40px] p-8"><h3 class="text-lg font-black mb-6">Edit User</h3><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="userId" id="editUserId"><div class="space-y-4"><input type="text" name="fullName" id="editFullName" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Full Name"><input type="email" name="email" id="editEmail" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Email"><input type="number" step="0.01" name="balance" id="editBalance" class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm font-bold" placeholder="Balance"><button type="submit" class="w-full bg-opay-green text-white py-4 rounded-2xl font-black">SAVE CHANGES</button><button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')" class="w-full text-gray-400 font-bold text-xs uppercase mt-4">Cancel</button></div></form></div></div>
<script> function editUser(u) { document.getElementById('editUserId').value = u.id; document.getElementById('editFullName').value = u.fullName; document.getElementById('editEmail').value = u.email; document.getElementById('editBalance').value = u.walletBalance; document.getElementById('editUserModal').classList.remove('hidden'); } </script>
<?php require_once 'includes/footer.php'; ?>
