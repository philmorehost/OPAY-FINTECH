<?php
require_once __DIR__ . '/includes/config.php';
if (!isLoggedIn()) redirect('/login');

$pageTitle = 'Support';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_ticket') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    $id = 'TKT-' . strtoupper(bin2hex(random_bytes(4)));

    $stmt = $pdo->prepare("INSERT INTO support_tickets (id, userId, subject, message, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->execute([$id, $currentUser['id'], $subject, $message]);

    // Notify User
    sendMail($pdo, $currentUser['email'], "Support Ticket Created", "Hi {$currentUser['fullName']},<br><br>Your support ticket has been received.<br><br>Ticket ID: $id<br>Subject: $subject<br><br>Our team will get back to you shortly.");

    $success = "Ticket created!";
}

$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE userId = ? ORDER BY createdAt DESC");
$stmt->execute([$currentUser['id']]);
$tickets = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <a href="/dashboard"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900 uppercase tracking-tight">Support Hub</h1>
    </div>

    <div class="p-6 space-y-8 flex-1">
        <?php if (isset($success)): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST" class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="new_ticket">
            <h3 class="text-sm font-black uppercase tracking-tight text-gray-800">New Support Ticket</h3>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Subject</label>
                <input type="text" name="subject" placeholder="What can we help you with?" class="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold text-sm" required>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Message</label>
                <textarea name="message" placeholder="Describe your issue..." class="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold text-sm min-h-[120px]" required></textarea>
            </div>

            <button type="submit" class="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 transition-all uppercase">Submit Ticket</button>
        </form>

        <div class="space-y-4">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 px-2">Recent Tickets</h3>
            <?php foreach ($tickets as $t): ?>
                <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-gray-800"><?php echo $t['subject']; ?></div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase mt-1"><?php echo date('M d, Y', strtotime($t['createdAt'])); ?></div>
                    </div>
                    <span class="px-3 py-1 bg-amber-50 text-amber-600 text-[9px] font-black rounded-full uppercase"><?php echo $t['status']; ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
                <div class="text-center py-10 text-gray-300 font-black uppercase text-[10px]">No tickets found</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
