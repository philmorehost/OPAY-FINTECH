<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/migrate/login');

$pageTitle = 'Email Hub';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'broadcast') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $subject = sanitize($_POST['subject']);
    $content = $_POST['content'];
    $targetTier = $_POST['targetTier'];

    $sql = "SELECT email FROM users WHERE role = 'user'";
    if ($targetTier !== 'all') {
        $sql .= " AND tier = " . (int)$targetTier;
    }
    $stmt = $pdo->query($sql);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($emails) > 0) {
        $success = "Broadcast queued for " . count($emails) . " users! (Simulation)";
    } else {
        $error = "No users found in this category.";
    }
}

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div class="flex flex-col">
            <h2 class="text-2xl font-black uppercase tracking-tighter">Email Hub</h2>
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Global Outreach Engine</span>
        </div>
        <i data-lucide="mail" class="text-billpay-green w-8 h-8"></i>
    </div>

    <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="p-4 bg-red-50 text-red-800 rounded-2xl text-xs font-black border border-red-100 uppercase text-center"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
           <form method="POST" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="broadcast">

                <div class="space-y-4">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Email Subject</label>
                    <input name="subject" class="w-full p-4 bg-gray-50 rounded-2xl font-bold border-2 border-transparent focus:border-billpay-green outline-none" placeholder="Platform Maintenance / Promotion..." required>
                </div>
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Broadcast Content</label>
                    <textarea name="content" class="w-full p-4 bg-gray-50 rounded-2xl font-bold border-2 border-transparent focus:border-billpay-green outline-none min-h-[300px]" placeholder="Type message here..." required></textarea>
                </div>

                <div class="space-y-4">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Distribution Tier</label>
                    <select name="targetTier" class="w-full p-4 bg-gray-50 rounded-2xl font-bold outline-none">
                        <option value="all">All Users</option>
                        <option value="1">Tier 1</option>
                        <option value="2">Tier 2</option>
                        <option value="3">Tier 3</option>
                    </select>
                </div>

                <button type="submit" class="w-full bg-billpay-green text-white py-5 rounded-3xl font-black uppercase shadow-xl flex items-center justify-center gap-3">
                    <i data-lucide="send" class="w-5 h-5"></i> INITIALIZE BROADCAST
                </button>
           </form>
        </div>

        <div class="space-y-6">
           <div class="bg-gray-900 p-8 rounded-[40px] text-white space-y-6 shadow-xl relative overflow-hidden">
              <div class="absolute -right-8 -top-8 w-28 h-28 bg-billpay-green/20 rounded-full blur-3xl"></div>
              <h3 class="text-xs font-black uppercase tracking-widest opacity-60">Status</h3>
              <p class="text-xs font-bold text-gray-400 uppercase leading-relaxed">Ensure SMTP is configured in settings for live delivery.</p>
           </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
