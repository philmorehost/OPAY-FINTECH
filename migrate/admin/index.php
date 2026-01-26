<?php
require_once __DIR__ . '/../includes/config.php';

if (!isAdmin()) {
    redirect('/login');
}

// Auto-migration helper
function addColumnIfNotExists($pdo, $table, $column, $definition) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD `$column` $definition");
        }
    } catch (PDOException $e) {
        // Handle error or silent fail
    }
}

// Auto-migration
try {
    addColumnIfNotExists($pdo, 'settings', 'templateId', "INT DEFAULT 1");
    addColumnIfNotExists($pdo, 'settings', 'primaryColor', "VARCHAR(20) DEFAULT '#00c689'");
    addColumnIfNotExists($pdo, 'settings', 'isKycEnforced', "TINYINT(1) DEFAULT 1");
    addColumnIfNotExists($pdo, 'transactions', 'token', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'transactions', 'provider', "VARCHAR(50)");
    addColumnIfNotExists($pdo, 'kyc_submissions', 'selfieImageUrl', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'settings', 'isMinDepositForced', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'users', 'hasCompletedInitialDeposit', "TINYINT(1) DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image VARCHAR(255),
        gradientFrom VARCHAR(20),
        gradientTo VARCHAR(20),
        textColor VARCHAR(20) DEFAULT '#ffffff',
        expiryDate DATETIME NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    addColumnIfNotExists($pdo, 'offers', 'useGradient', "TINYINT(1) DEFAULT 1");
} catch (PDOException $e) {
    // Silent fail
}

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/header.php';

// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(walletBalance) FROM users");
$platformBalance = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending'");
$pendingKyc = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
$totalTransactions = $stmt->fetchColumn();
?>

<div class="space-y-8 animate-fade-in text-gray-900">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-7 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-500"><i data-lucide="users" class="w-7 h-7"></i></div>
            <div>
                <div class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Total Users</div>
                <div class="text-xl font-black text-gray-800 tracking-tight"><?php echo $totalUsers; ?></div>
            </div>
        </div>
        <div class="bg-white p-7 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-green-50 flex items-center justify-center text-green-500"><i data-lucide="credit-card" class="w-7 h-7"></i></div>
            <div>
                <div class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Platform Balance</div>
                <div class="text-xl font-black text-gray-800 tracking-tight"><?php echo formatCurrency($platformBalance); ?></div>
            </div>
        </div>
        <div class="bg-white p-7 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center text-purple-500"><i data-lucide="shield-check" class="w-7 h-7"></i></div>
            <div>
                <div class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Pending KYC</div>
                <div class="text-xl font-black text-gray-800 tracking-tight"><?php echo $pendingKyc; ?></div>
            </div>
        </div>
        <div class="bg-white p-7 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500"><i data-lucide="arrow-right-left" class="w-7 h-7"></i></div>
            <div>
                <div class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Total Transactions</div>
                <div class="text-xl font-black text-gray-800 tracking-tight"><?php echo $totalTransactions; ?></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-sm font-black uppercase tracking-widest">Platform Activity</h3>
                <span class="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-black rounded-full">+12.5% Growth</span>
            </div>
            <div class="h-[300px] w-full flex items-center justify-center bg-gray-50 rounded-3xl border-2 border-dashed border-gray-100">
                <div class="text-center">
                    <i data-lucide="trending-up" class="w-12 h-12 text-gray-200 mx-auto mb-2"></i>
                    <p class="text-[10px] font-black text-gray-300 uppercase">Charts will appear here</p>
                </div>
            </div>
        </div>
        <div class="bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm">
            <h3 class="text-sm font-black uppercase tracking-widest mb-6">Quick Access</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="/admin/deposits" class="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors">
                    <i data-lucide="wallet" class="text-purple-500 mb-3 w-6 h-6"></i>
                    <span class="text-[9px] font-black uppercase">Deposits</span>
                </a>
                <a href="/admin/kyc" class="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors">
                    <i data-lucide="shield-check" class="text-blue-500 mb-3 w-6 h-6"></i>
                    <span class="text-[9px] font-black uppercase">KYC Review</span>
                </a>
                <a href="/admin/transactions" class="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors">
                    <i data-lucide="file-text" class="text-pink-500 mb-3 w-6 h-6"></i>
                    <span class="text-[9px] font-black uppercase">All Tx</span>
                </a>
                <a href="/admin/settings" class="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors">
                    <i data-lucide="settings" class="text-blue-500 mb-3 w-6 h-6"></i>
                    <span class="text-[9px] font-black uppercase">Settings</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
