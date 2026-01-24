<?php
// migrate/notifications.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE userId = ? ORDER BY createdAt DESC LIMIT 50");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Mark all as read
$pdo->prepare("UPDATE notifications SET isRead = 1 WHERE userId = ?")->execute([$user['id']]);
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24 animate-fade-in">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Notifications</h1>
    </div>

    <div class="p-6 space-y-4">
        <?php if (empty($notifications)): ?>
            <div class="py-20 text-center text-gray-300 font-black text-[10px] uppercase tracking-widest border-2 border-dashed border-gray-200 rounded-[40px]">No new notifications</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="bg-white p-5 rounded-[24px] border border-gray-100 shadow-sm flex gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo $n['type'] === 'success' ? 'bg-green-50 text-green-600' : ($n['type'] === 'error' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'); ?>">
                        <i data-lucide="<?php echo $n['type'] === 'success' ? 'check-circle' : ($n['type'] === 'error' ? 'alert-circle' : 'info'); ?>" size="20"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-gray-900"><?php echo h($n['title']); ?></h4>
                        <p class="text-[10px] text-gray-500 font-medium mt-1 leading-relaxed"><?php echo h($n['message']); ?></p>
                        <div class="text-[8px] text-gray-300 font-black uppercase tracking-widest mt-2"><?php echo date('M d, H:i', strtotime($n['createdAt'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php include 'includes/nav.php'; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
