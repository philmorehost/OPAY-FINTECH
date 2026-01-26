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
    addColumnIfNotExists($pdo, 'settings', 'isMinDepositForced', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'settings', 'minDepositAmount', "DECIMAL(15, 2) DEFAULT 100.00");
    addColumnIfNotExists($pdo, 'settings', 'pwaEnabled', "TINYINT(1) DEFAULT 1");
    addColumnIfNotExists($pdo, 'settings', 'pwaIcon', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'settings', 'pwaSplash', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'settings', 'siteDescription', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'isBiometricEnforced', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'settings', 'siteVersion', "VARCHAR(50) DEFAULT '1.0.0'");
    addColumnIfNotExists($pdo, 'transactions', 'token', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'transactions', 'provider', "VARCHAR(50)");
    addColumnIfNotExists($pdo, 'kyc_submissions', 'selfieImageUrl', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'users', 'hasCompletedInitialDeposit', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'users', 'biometricEnabled', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'users', 'biometricCredentialId', "TEXT");
    addColumnIfNotExists($pdo, 'users', 'biometricPublicKey', "TEXT");

    $pdo->exec("CREATE TABLE IF NOT EXISTS virtual_cards (
        id VARCHAR(50) PRIMARY KEY,
        userId VARCHAR(50) NOT NULL,
        cardNumber VARCHAR(20) NOT NULL,
        expiry VARCHAR(10) NOT NULL,
        cvv VARCHAR(5) NOT NULL,
        balance DECIMAL(15, 2) DEFAULT 0.00,
        type ENUM('Visa', 'Mastercard') NOT NULL,
        isFrozen BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
    )");

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

// Platform Activity Data (Last 7 Days)
$activityData = [];
$activityLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $activityLabels[] = date('D', strtotime($date));

    $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE DATE(date) = ? AND status = 'successful'");
    $stmt->execute([$date]);
    $activityData[] = (float)$stmt->fetchColumn() ?: 0;
}

// Calculate Growth
$currentPeriodSum = array_sum($activityData);
$prevPeriodSum = 0;
for ($i = 13; $i >= 7; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE DATE(date) = ? AND status = 'successful'");
    $stmt->execute([$date]);
    $prevPeriodSum += (float)$stmt->fetchColumn() ?: 0;
}

$growthPercent = 0;
if ($prevPeriodSum > 0) {
    $growthPercent = (($currentPeriodSum - $prevPeriodSum) / $prevPeriodSum) * 100;
} elseif ($currentPeriodSum > 0) {
    $growthPercent = 100;
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                <?php
                $growthClass = $growthPercent >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600';
                $growthSign = $growthPercent >= 0 ? '+' : '';
                ?>
                <span class="px-3 py-1 <?php echo $growthClass; ?> text-[10px] font-black rounded-full"><?php echo $growthSign . number_format($growthPercent, 1); ?>% Growth</span>
            </div>
            <div class="h-[300px] w-full relative">
                <canvas id="activityChart"></canvas>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart').getContext('2d');
        const primaryColor = '<?php echo $settings['primaryColor'] ?? '#00c689'; ?>';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($activityLabels); ?>,
                datasets: [{
                    label: 'Transaction Volume',
                    data: <?php echo json_encode($activityData); ?>,
                    borderColor: primaryColor,
                    backgroundColor: 'rgba(' + hexToRgb(primaryColor) + ', 0.1)',
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: primaryColor,
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#111827',
                        titleFont: { size: 10, weight: 'bold' },
                        bodyFont: { size: 12, weight: '900' },
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '800' }, color: '#9ca3af' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6', drawBorder: false },
                        ticks: {
                            font: { size: 10, weight: '800' },
                            color: '#9ca3af',
                            callback: function(value) {
                                if (value >= 1000) return '₦' + (value/1000) + 'k';
                                return '₦' + value;
                            }
                        }
                    }
                }
            }
        });

        function hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? parseInt(result[1], 16) + ',' + parseInt(result[2], 16) + ',' + parseInt(result[3], 16) : '0,198,137';
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
