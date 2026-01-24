<?php
// migrate/support.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }

    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    if ($subject && $message) {
        $stmt = $pdo->prepare("INSERT INTO tickets (id, userId, subject, message, status, createdAt) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([generate_id(), $user['id'], $subject, $message, 'open', date('c')]);
        $success = "Support ticket created. We will get back to you soon.";
    } else {
        $error = "Please fill in all fields.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE userId = ? ORDER BY createdAt DESC");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Support Center</h1>
    </div>

    <div class="p-6 space-y-6">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl font-bold text-sm"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl font-bold text-sm"><?php echo h($error); ?></div><?php endif; ?>

        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
            <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest mb-6">Create New Ticket</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="text" name="subject" required placeholder="Subject" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <textarea name="message" required placeholder="Describe your issue..." class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold h-32"></textarea>
                <button type="submit" class="w-full bg-vtu-green text-white font-black py-5 rounded-2xl shadow-xl">SUBMIT TICKET</button>
            </form>
        </div>

        <div class="space-y-4">
            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Your Tickets</h3>
            <?php if (empty($tickets)): ?>
                <div class="py-12 text-center text-gray-300 font-black text-[10px] uppercase tracking-widest bg-white rounded-[32px] border border-gray-100 border-dashed">No tickets found</div>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <div class="bg-white p-5 rounded-[24px] border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-black text-gray-900"><?php echo h($t['subject']); ?></div>
                            <div class="text-[8px] text-gray-400 font-bold uppercase mt-1"><?php echo date('M d, Y', strtotime($t['createdAt'])); ?></div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $t['status'] === 'open' ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500'; ?>"><?php echo h($t['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
