<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Sales & Profit Reports';

// Stats queries
$today = date('Y-m-d');
$month = date('Y-m');

$stats = [
    'today_sales' => $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'successful' AND DATE(date) = '$today' AND type NOT IN ('Deposit', 'Daily Reward')")->fetchColumn() ?: 0,
    'today_profit' => $pdo->query("SELECT SUM(profit) FROM transactions WHERE status = 'successful' AND DATE(date) = '$today'")->fetchColumn() ?: 0,
    'month_sales' => $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'successful' AND DATE(date) LIKE '$month%' AND type NOT IN ('Deposit', 'Daily Reward')")->fetchColumn() ?: 0,
    'month_profit' => $pdo->query("SELECT SUM(profit) FROM transactions WHERE status = 'successful' AND DATE(date) LIKE '$month%'")->fetchColumn() ?: 0,
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn() ?: 0,
    'pending_kyc' => $pdo->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending'")->fetchColumn() ?: 0
];

// Service breakdown
$services = $pdo->query("SELECT type, SUM(amount) as total_sales, SUM(profit) as total_profit, COUNT(*) as tx_count FROM transactions WHERE status = 'successful' AND type NOT IN ('Deposit', 'Daily Reward') GROUP BY type ORDER BY total_sales DESC")->fetchAll(PDO::FETCH_ASSOC);

// Chart Data (Last 7 Days) - Optimized Query
$chartLabels = [];
$chartSales = [];
$chartProfit = [];
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = ['sales' => 0, 'profit' => 0, 'label' => date('M d', strtotime($d))];
}

$startDate = date('Y-m-d', strtotime("-6 days"));
$stmt = $pdo->prepare("SELECT DATE(date) as d, SUM(CASE WHEN type NOT IN ('Deposit', 'Daily Reward') THEN amount ELSE 0 END) as sales, SUM(profit) as profit FROM transactions WHERE status = 'successful' AND DATE(date) >= ? GROUP BY DATE(date)");
$stmt->execute([$startDate]);
while ($row = $stmt->fetch()) {
    if (isset($days[$row['d']])) {
        $days[$row['d']]['sales'] = (float)$row['sales'];
        $days[$row['d']]['profit'] = (float)$row['profit'];
    }
}

foreach ($days as $d => $data) {
    $chartLabels[] = $data['label'];
    $chartSales[] = $data['sales'];
    $chartProfit[] = $data['profit'];
}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tight">Platform Analytics</h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Real-time sales and profit monitoring</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-6 py-3 bg-white border border-gray-100 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm hover:bg-gray-50 transition-all flex items-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Today's Sales</div>
            <div class="text-2xl font-black text-gray-900"><?php echo formatCurrency($stats['today_sales']); ?></div>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-black text-green-500 uppercase">
                <i data-lucide="trending-up" class="w-4 h-4"></i> +12% from yesterday
            </div>
        </div>
        <div class="bg-billpay-green p-8 rounded-[40px] shadow-lg text-white">
            <div class="text-[10px] font-black text-white/60 uppercase tracking-widest mb-1">Today's Net Profit</div>
            <div class="text-2xl font-black"><?php echo formatCurrency($stats['today_profit']); ?></div>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-black uppercase">
                <i data-lucide="zap" class="w-4 h-4"></i> Pure Revenue
            </div>
        </div>
        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Monthly Sales</div>
            <div class="text-2xl font-black text-gray-900"><?php echo formatCurrency($stats['month_sales']); ?></div>
            <div class="mt-4 text-[10px] font-black text-gray-400 uppercase"><?php echo date('F Y'); ?></div>
        </div>
        <div class="bg-gray-900 p-8 rounded-[40px] shadow-lg text-white">
            <div class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-1">Monthly Profit</div>
            <div class="text-2xl font-black"><?php echo formatCurrency($stats['month_profit']); ?></div>
            <div class="mt-4 text-[10px] font-black text-billpay-green uppercase tracking-widest">Growth Phase</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xs font-black uppercase tracking-widest mb-8">Revenue Stream (Last 7 Days)</h3>
            <div class="relative h-[300px] w-full">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Service Breakdown -->
        <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xs font-black uppercase tracking-widest mb-8">Sales by Service</h3>
            <div class="space-y-6">
                <?php foreach ($services as $svc): ?>
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-[10px] font-black uppercase">
                        <span class="text-gray-900"><?php echo $svc['type']; ?></span>
                        <span class="text-billpay-green"><?php echo formatCurrency($svc['total_sales']); ?></span>
                    </div>
                    <div class="w-full h-2 bg-gray-50 rounded-full overflow-hidden">
                        <?php $percent = $stats['month_sales'] > 0 ? ($svc['total_sales'] / $stats['month_sales'] * 100) : 0; ?>
                        <div class="h-full bg-billpay-green rounded-full" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-[8px] font-bold text-gray-400 uppercase">
                        <span><?php echo $svc['tx_count']; ?> Transactions</span>
                        <span>Profit: <?php echo formatCurrency($svc['total_profit']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Sales (₦)',
                    data: <?php echo json_encode($chartSales); ?>,
                    borderColor: '#00c689',
                    backgroundColor: 'rgba(0, 198, 137, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 4,
                    pointRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#00c689',
                    pointBorderWidth: 3
                },
                {
                    label: 'Profit (₦)',
                    data: <?php echo json_encode($chartProfit); ?>,
                    borderColor: '#111827',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#111827',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 10 } }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
