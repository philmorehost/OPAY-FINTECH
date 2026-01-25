<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Support Hub';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    $ticketId = $_POST['ticketId'];
    if ($_POST['action'] === 'reply') {
        $message = sanitize($_POST['message']);
        $stmt = $pdo->prepare("INSERT INTO support_replies (ticketId, author, message) VALUES (?, 'Admin', ?)");
        $stmt->execute([$ticketId, $message]);

        // Notify User
        $stmt = $pdo->prepare("SELECT t.subject, u.email, u.fullName FROM support_tickets t JOIN users u ON t.userId = u.id WHERE t.id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        if ($ticket) {
            sendMail($pdo, $ticket['email'], "Re: " . $ticket['subject'], "Hi {$ticket['fullName']},<br><br>An administrator has replied to your support ticket.<br><br>Reply: $message<br><br>Log in to your dashboard to view more details.");
        }
    } elseif ($_POST['action'] === 'close') {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?");
        $stmt->execute([$ticketId]);
    }
}

$stmt = $pdo->query("SELECT t.*, u.username FROM support_tickets t JOIN users u ON t.userId = u.id ORDER BY t.createdAt DESC");
$tickets = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<div class="space-y-6 animate-fade-in">
    <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div class="flex flex-col"><h2 class="text-2xl font-black uppercase tracking-tighter">Support Hub</h2><span class="text-[10px] font-bold text-gray-400 uppercase">Customer Inquiry Portal</span></div>
    </div>

    <div class="space-y-4">
        <?php foreach ($tickets as $t): ?>
            <div class="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $t['status'] === 'open' ? 'bg-amber-50 text-amber-500' : 'bg-gray-100 text-gray-400'; ?>">
                        <i data-lucide="message-square" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-gray-800"><?php echo $t['subject']; ?></div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">@<?php echo $t['username']; ?> • <?php echo date('M d, Y', strtotime($t['createdAt'])); ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase <?php echo $t['status'] === 'open' ? 'bg-amber-600 text-white' : 'bg-green-600 text-white'; ?>"><?php echo $t['status']; ?></span>
                    <button onclick="openReplyModal('<?php echo $t['id']; ?>', '<?php echo addslashes($t['subject']); ?>')" class="p-3 bg-gray-50 text-gray-400 rounded-xl hover:bg-billpay-green/10 hover:text-billpay-green transition-all"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden items-center justify-center p-6">
    <div class="bg-white w-full max-w-lg rounded-[40px] overflow-hidden shadow-2xl animate-slide-up flex flex-col max-h-[85vh]">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center bg-gray-50">
            <div><h3 class="text-lg font-black uppercase" id="modalSubject"></h3></div>
            <button onclick="closeReplyModal()" class="p-2 bg-white rounded-full shadow-sm"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticketId" id="replyTicketId">
            <textarea name="message" class="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs min-h-[150px]" placeholder="Type your reply..." required></textarea>
            <div class="flex gap-4">
                <button type="button" onclick="closeTicket()" class="flex-1 py-4 text-red-500 font-black text-[10px] uppercase">Close Ticket</button>
                <button type="submit" class="flex-[2] py-4 bg-billpay-green text-white rounded-2xl font-black text-[10px] uppercase shadow-xl">Send Reply</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReplyModal(id, subject) {
        document.getElementById('replyTicketId').value = id;
        document.getElementById('modalSubject').innerText = subject;
        document.getElementById('replyModal').classList.remove('hidden');
        document.getElementById('replyModal').classList.add('flex');
    }
    function closeReplyModal() {
        document.getElementById('replyModal').classList.add('hidden');
        document.getElementById('replyModal').classList.remove('flex');
    }
    function closeTicket() {
        if (confirm('Close this ticket?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="ticketId" value="${document.getElementById('replyTicketId').value}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
