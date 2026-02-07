<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

// Handle Return to Admin
if (isset($_GET['action']) && $_GET['action'] === 'return_admin' && isset($_SESSION['original_admin_id'])) {
    $_SESSION['user_id'] = $_SESSION['original_admin_id'];
    $_SESSION['role'] = 'admin';
    unset($_SESSION['original_admin_id']);
    redirect('/admin/users');
}

$pageTitle = 'User Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $userId = $_POST['userId'] ?? null;
    $action = $_POST['action'];

    if ($action === 'suspend' && $userId) {
        $stmt = $pdo->prepare("UPDATE users SET isSuspended = NOT isSuspended WHERE id = ?");
        $stmt->execute([$userId]);
        $success = "User status updated.";
    } elseif ($action === 'adjust' && $userId) {
        $amount = (float)$_POST['amount'];
        $type = $_POST['adjustType'];
        updateWallet($pdo, $userId, $amount, $type);

        if ($type === 'credit') {
            $settings = fetchSettings($pdo);
            if ($amount >= $settings['minDepositAmount']) {
                $stmt = $pdo->prepare("UPDATE users SET hasCompletedInitialDeposit = 1 WHERE id = ?");
                $stmt->execute([$userId]);
            }
        }

        logTransaction($pdo, $userId, 'Admin Adjustment', $amount, 'successful', "Admin " . strtoupper($type) . " adjustment", 'Wallet', 'Admin');
        $success = "Balance adjusted successfully.";
    } elseif ($action === 'create') {
        $fullName = sanitize($_POST['fullName']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $balance = (float)$_POST['balance'];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or Email already exists.";
        } else {
            $newId = 'u' . bin2hex(random_bytes(4));
            $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, phone, password, role, walletBalance) VALUES (?, ?, ?, ?, ?, ?, 'user', ?)");
            $stmt->execute([$newId, $username, $fullName, $email, $phone, $password, $balance]);
            $success = "User created successfully.";
        }
    } elseif ($action === 'edit' && $userId) {
        $fullName = sanitize($_POST['fullName']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $tier = (int)$_POST['tier'];

        $stmt = $pdo->prepare("UPDATE users SET fullName = ?, email = ?, phone = ?, tier = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone, $tier, $userId]);
        $success = "User details updated.";
    } elseif ($action === 'login_as' && $userId) {
        $_SESSION['original_admin_id'] = $_SESSION['user_id'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'user';
        redirect('/dashboard');
    }
}

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM users WHERE role = 'user'";
$params = [];
if ($search) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR fullName LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col">
            <h2 class="text-2xl font-black uppercase tracking-tighter">User Directory</h2>
            <span class="text-[10px] font-bold text-gray-400 uppercase"><?php echo count($users); ?> Accounts Found</span>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="openCreateModal()" class="bg-billpay-green text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-green-100 flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Create User
            </button>
            <form class="relative w-64">
                <input name="search" class="w-full bg-gray-50 p-3 pl-10 rounded-2xl outline-none font-bold text-xs" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 w-4 h-4"></i>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($users as $user): ?>
            <div class="bg-white p-5 rounded-[32px] border border-gray-100 shadow-sm flex flex-col justify-between group hover:border-billpay-green transition-all">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-billpay-green/10 rounded-2xl flex items-center justify-center text-billpay-green font-black shrink-0">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-black text-gray-800 truncate"><?php echo $user['fullName']; ?></div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight truncate">@<?php echo $user['username']; ?> • T<?php echo $user['tier']; ?></div>
                        <div class="text-[10px] text-billpay-green font-black mt-1"><?php echo formatCurrency($user['walletBalance']); ?></div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                    <div class="flex gap-1.5">
                        <form method="POST" onsubmit="return confirm('Login to this account?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="login_as">
                            <input type="hidden" name="userId" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="p-2.5 bg-green-50 text-billpay-green rounded-xl hover:bg-billpay-green hover:text-white transition-colors" title="Login as User">
                                <i data-lucide="log-in" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:bg-gray-200 transition-colors" title="Edit User">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openAdjustModal('<?php echo $user['id']; ?>', '<?php echo $user['fullName']; ?>')" class="p-2.5 bg-blue-50 text-blue-500 rounded-xl hover:bg-blue-500 hover:text-white transition-colors" title="Adjust Balance">
                            <i data-lucide="arrow-up-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="userId" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-2 rounded-xl transition-all font-black text-[9px] uppercase <?php echo $user['isSuspended'] ? 'bg-red-50 text-red-500' : 'bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500'; ?>">
                            <i data-lucide="<?php echo $user['isSuspended'] ? 'user-check' : 'user-minus'; ?>" class="w-3.5 h-3.5"></i>
                            <?php echo $user['isSuspended'] ? 'Suspended' : 'Suspend'; ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Create User Modal -->
<div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-6 overflow-y-auto">
    <form method="POST" class="bg-white w-full max-w-md rounded-[40px] p-8 space-y-4 shadow-2xl animate-slide-up my-auto">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="create">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-black uppercase tracking-tight">Create New User</h3>
            <button type="button" onclick="closeCreateModal()" class="p-2 bg-gray-50 rounded-full hover:bg-gray-100"><i data-lucide="x" class="w-4.5 h-4.5"></i></button>
        </div>
        <div class="grid grid-cols-1 gap-4">
            <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Full Name</label><input type="text" name="fullName" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Username</label><input type="text" name="username" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
                <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Email</label><input type="email" name="email" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Phone</label><input type="tel" name="phone" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
                <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Initial Balance</label><input type="number" name="balance" value="0" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
            </div>
            <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Password</label><input type="password" name="password" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
        </div>
        <button type="submit" class="w-full py-4 rounded-[24px] font-black uppercase tracking-widest text-white shadow-xl bg-billpay-green mt-4">Create Account</button>
    </form>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-6 overflow-y-auto">
    <form method="POST" class="bg-white w-full max-w-md rounded-[40px] p-8 space-y-4 shadow-2xl animate-slide-up my-auto">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="userId" id="editUserId">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-black uppercase tracking-tight">Edit User Account</h3>
            <button type="button" onclick="closeEditModal()" class="p-2 bg-gray-50 rounded-full hover:bg-gray-100"><i data-lucide="x" class="w-4.5 h-4.5"></i></button>
        </div>
        <div class="grid grid-cols-1 gap-4">
            <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Full Name</label><input type="text" name="fullName" id="editFullName" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
            <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Email</label><input type="email" name="email" id="editEmail" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-[9px] font-black text-gray-400 uppercase ml-1">Phone</label><input type="tel" name="phone" id="editPhone" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm" required></div>
                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-1">User Tier</label>
                    <select name="tier" id="editTier" class="w-full p-3.5 bg-gray-50 rounded-2xl outline-none font-bold text-sm">
                        <option value="1">Tier 1</option>
                        <option value="2">Tier 2</option>
                        <option value="3">Tier 3</option>
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="w-full py-4 rounded-[24px] font-black uppercase tracking-widest text-white shadow-xl bg-gray-900 mt-4">Save Changes</button>
    </form>
</div>

<!-- Adjust Balance Modal -->
<div id="adjustModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6">
    <form method="POST" class="bg-white w-full max-w-sm rounded-[40px] p-8 space-y-6 shadow-2xl animate-slide-up">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="userId" id="adjustUserId">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-black uppercase tracking-tight">Adjust Balance</h3>
            <button type="button" onclick="closeAdjustModal()" class="p-2 bg-gray-50 rounded-full hover:bg-gray-100"><i data-lucide="x" class="w-4.5 h-4.5"></i></button>
        </div>
        <p class="text-xs text-gray-400 font-bold uppercase">Adjusting for <span id="adjustUserName" class="text-gray-800"></span></p>
        <div class="flex bg-gray-100 p-1 rounded-2xl">
            <button type="button" onclick="setAdjustType('credit')" id="typeCredit" class="flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all bg-white shadow-sm text-billpay-green">Credit</button>
            <button type="button" onclick="setAdjustType('debit')" id="typeDebit" class="flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all text-gray-400">Debit</button>
            <input type="hidden" name="adjustType" id="adjustTypeInput" value="credit">
        </div>
        <div class="relative">
            <input type="number" name="amount" placeholder="0.00" step="0.01" class="w-full p-5 bg-gray-50 rounded-2xl outline-none font-black text-2xl" required>
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-lg">₦</span>
        </div>
        <button type="submit" class="w-full py-5 rounded-[24px] font-black uppercase tracking-widest text-white shadow-xl bg-billpay-green">Confirm Adjustment</button>
    </form>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('createModal').classList.add('flex');
    }
    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('createModal').classList.remove('flex');
    }
    function openEditModal(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editFullName').value = user.fullName;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone;
        document.getElementById('editTier').value = user.tier;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    function openAdjustModal(id, name) {
        document.getElementById('adjustUserId').value = id;
        document.getElementById('adjustUserName').innerText = name;
        document.getElementById('adjustModal').classList.remove('hidden');
        document.getElementById('adjustModal').classList.add('flex');
    }
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.add('hidden');
        document.getElementById('adjustModal').classList.remove('flex');
    }
    function setAdjustType(type) {
        document.getElementById('adjustTypeInput').value = type;
        document.getElementById('typeCredit').classList.toggle('bg-white', type === 'credit');
        document.getElementById('typeCredit').classList.toggle('text-billpay-green', type === 'credit');
        document.getElementById('typeCredit').classList.toggle('text-gray-400', type !== 'credit');
        document.getElementById('typeDebit').classList.toggle('bg-white', type === 'debit');
        document.getElementById('typeDebit').classList.toggle('text-red-500', type === 'debit');
        document.getElementById('typeDebit').classList.toggle('text-gray-400', type !== 'debit');
    }
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
