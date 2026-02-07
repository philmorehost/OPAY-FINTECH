<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$id = sanitize($_GET['id'] ?? '');
$stmt = $pdo->prepare("SELECT t.*, u.fullName FROM transactions t JOIN users u ON t.userId = u.id WHERE t.id = ?");
$stmt->execute([$id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx || ($tx['userId'] !== $currentUser['id'] && !isAdmin())) {
    die("Transaction not found.");
}

$pageTitle = 'Transaction Receipt';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b lg:hidden">
        <a href="/transactions"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Receipt</h1>
    </div>

    <div class="p-6 space-y-8 flex-1" id="receiptContent">
        <div class="text-center mb-4 hidden print:block">
            <h1 class="text-2xl font-black uppercase tracking-tighter text-gray-900"><?php echo $settings['senderName'] ?? 'Billpay'; ?></h1>
        </div>
        <!-- Modern Receipt Design -->
        <div class="bg-white rounded-[50px] shadow-2xl overflow-hidden border border-gray-100 relative">
            <?php
                $statusColor = 'bg-billpay-green';
                $statusIcon = 'check-circle-2';
                $statusText = 'Successful';
                if ($tx['status'] === 'failed') {
                    $statusColor = 'bg-red-500';
                    $statusIcon = 'x-circle';
                    $statusText = 'Failed';
                } elseif ($tx['status'] === 'pending') {
                    $statusColor = 'bg-amber-500';
                    $statusIcon = 'clock';
                    $statusText = 'Pending';
                }
            ?>
            <div class="<?php echo $statusColor; ?> p-12 text-white text-center relative">
                <div class="text-[10px] font-black uppercase tracking-[0.3em] mb-4 opacity-60 lg:block hidden"><?php echo $settings['senderName'] ?? 'Billpay'; ?></div>
                <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-[30px] flex items-center justify-center mx-auto mb-6 border border-white/30">
                    <i data-lucide="<?php echo $statusIcon; ?>" class="w-10 h-10"></i>
                </div>
                <h2 class="text-3xl font-black uppercase tracking-tighter mb-2"><?php echo $statusText; ?></h2>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60">Transaction <?php echo ucfirst($tx['status']); ?></div>

                <!-- Decorative Circles -->
                <div class="absolute -left-4 -bottom-4 w-8 h-8 bg-gray-50 rounded-full"></div>
                <div class="absolute -right-4 -bottom-4 w-8 h-8 bg-gray-50 rounded-full"></div>
            </div>

            <div class="p-10 space-y-8">
                <div class="text-center">
                    <div class="text-4xl font-black text-gray-900 tracking-tighter"><?php echo formatCurrency($tx['amount']); ?></div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2"><?php echo $tx['type']; ?></div>
                </div>

                <div class="space-y-6 pt-6 border-t-2 border-dashed border-gray-100">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipient</span>
                        <span class="text-sm font-black text-gray-800"><?php echo $tx['recipient']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Provider</span>
                        <span class="text-sm font-black text-gray-800 uppercase"><?php echo $tx['provider'] ?: 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Reference</span>
                        <span class="text-sm font-bold text-gray-600 uppercase font-mono"><?php echo $tx['id']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Date & Time</span>
                        <span class="text-sm font-bold text-gray-600"><?php echo date('M d, Y • H:i', strtotime($tx['date'])); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</span>
                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest <?php echo $tx['status'] === 'successful' ? 'bg-green-50 text-green-600' : ($tx['status'] === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'); ?>">
                            <?php echo $tx['status']; ?>
                        </span>
                    </div>

                    <?php if ($tx['type'] === 'Electricity' && $tx['token']): ?>
                    <div class="bg-gray-50 p-6 rounded-3xl border border-gray-100 mt-8">
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 text-center">Meter Token</div>
                        <div class="text-2xl font-black text-billpay-green text-center tracking-[0.2em]"><?php echo $tx['token']; ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="bg-indigo-50/50 p-6 rounded-3xl border border-indigo-100 mt-8">
                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Description</div>
                        <p class="text-[11px] font-bold text-indigo-900 leading-relaxed uppercase"><?php echo $tx['details']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sharing Actions -->
        <div class="grid grid-cols-2 gap-4 no-print">
            <button onclick="downloadAsImage()" class="bg-gray-900 text-white p-5 rounded-3xl font-black text-[10px] uppercase tracking-widest shadow-xl flex items-center justify-center gap-3">
                <i data-lucide="image" class="w-4 h-4 text-billpay-green"></i> Save Image
            </button>
            <button onclick="window.print()" class="bg-white text-gray-900 border border-gray-100 p-5 rounded-3xl font-black text-[10px] uppercase tracking-widest shadow-xl flex items-center justify-center gap-3">
                <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i> Print PDF
            </button>
        </div>
    </div>
</div>

<style>
@page {
    size: auto;
    margin: 0mm;
}
@media print {
    .no-print { display: none !important; }
    aside, header, nav, .lg\:hidden, footer { display: none !important; }
    main { margin-left: 0 !important; padding: 0 !important; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    #receiptContent { padding: 20mm !important; max-width: 100% !important; }
    .rounded-[50px] { border-radius: 20px !important; }
    .shadow-2xl { shadow: none !important; box-shadow: none !important; }
    .bg-gray-50 { background-color: white !important; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    function downloadAsImage() {
        const element = document.getElementById('receiptContent');
        const actions = document.querySelector('.no-print');
        actions.style.display = 'none';

        html2canvas(element, {
            backgroundColor: '#f9fafb',
            scale: 2,
            useCORS: true
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Receipt-<?php echo $tx['id']; ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            actions.style.display = 'grid';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
