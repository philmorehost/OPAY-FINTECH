<?php
// migrate/sms.php
require_once 'includes/header.php';
require_login();
$user = get_current_user_data();

$activeTab = $_GET['tab'] ?? 'compose';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die('CSRF Token Mismatch'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'send_sms') {
        $senderId = $_POST['senderId'];
        $numbersRaw = $_POST['numbers'];
        $message = $_POST['message'];

        $recipients = array_filter(array_unique(preg_split('/[,\s\n]+/', $numbersRaw)), function($n) { return strlen($n) >= 10; });
        if (empty($recipients)) { $error = "Enter valid recipients"; }
        elseif (empty($senderId)) { $error = "Select a Sender ID"; }
        else {
            $costData = calculate_sms_cost($message, count($recipients));
            if ($user['walletBalance'] < $costData['totalCost']) {
                $error = "Insufficient balance. Required: " . format_currency($costData['totalCost']);
            } else {
                $recipientsStr = implode(',', $recipients);
                $res = send_kudi_sms($recipientsStr, $message, $senderId);

                if ($res && isset($res['status']) && $res['status'] === 'success') {
                    update_user_balance($user['id'], -$costData['totalCost']);
                    log_transaction($user['id'], 'Bulk SMS', $costData['totalCost'], 'successful', "Sent to " . count($recipients) . " recipients via $senderId. Pages: " . $costData['pages'], count($recipients) . " numbers", 'KudiSMS');
                    check_and_apply_loyalty_bonus($user['id']);
                    redirect('sms?tab=compose&success=SMS sent successfully');
                } else {
                    $error = "API Error: " . ($res['msg'] ?? 'Unknown error');
                }
            }
        }
    } elseif ($action === 'register_id') {
        $name = strtoupper(trim($_POST['senderId']));
        $sample = $_POST['sampleMessage'];
        if (strlen($name) > 11) { $error = "Sender ID too long"; }
        else {
            $stmt = $pdo->prepare("INSERT INTO sms_sender_ids (id, userId, name, sampleMessage, status, createdAt) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([generate_id(), $user['id'], $name, $sample, 'pending', date('c')]);
            $success = "Sender ID submitted for approval";
        }
    } elseif ($action === 'add_contact') {
        $stmt = $pdo->prepare("INSERT INTO phone_book (id, userId, name, phone, createdAt) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([generate_id(), $user['id'], $_POST['name'], $_POST['phone'], date('c')]);
        redirect('sms?tab=contacts&success=Contact added');
    }
}

if (isset($_GET['delete_contact'])) {
    $stmt = $pdo->prepare("DELETE FROM phone_book WHERE id = ? AND userId = ?");
    $stmt->execute([$_GET['delete_contact'], $user['id']]);
    redirect('sms?tab=contacts&success=Contact deleted');
}

// Auto-check status of pending IDs if on the IDs tab
if ($activeTab === 'ids') {
    $stmt = $pdo->prepare("SELECT * FROM sms_sender_ids WHERE userId = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pending = $stmt->fetchAll();
    foreach ($pending as $p) {
        $res = check_kudi_sender_id_status($p['name']);
        if ($res && isset($res['status']) && $res['status'] === 'success' && (strpos(strtolower($res['msg']), 'approved') !== false)) {
            $pdo->prepare("UPDATE sms_sender_ids SET status = 'approved' WHERE id = ?")->execute([$p['id']]);
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM sms_sender_ids WHERE userId = ? AND status = 'approved'"); $stmt->execute([$user['id']]);
$myApprovedIds = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sms_sender_ids WHERE userId = ? ORDER BY createdAt DESC"); $stmt->execute([$user['id']]);
$mySmsHistory = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM phone_book WHERE userId = ? ORDER BY name ASC"); $stmt->execute([$user['id']]);
$myContacts = $stmt->fetchAll();
?>
<div class="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
    <div class="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-40">
        <a href="dashboard" class="p-2"><i data-lucide="arrow-left" class="text-gray-900"></i></a>
        <h1 class="text-lg font-black text-gray-900">Bulk SMS Hub</h1>
    </div>

    <div class="p-4 space-y-6 flex-1 overflow-y-auto">
        <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 border border-green-200 rounded-2xl flex items-center gap-3 animate-fade-in"><i data-lucide="check-circle-2" size="20"></i><span class="text-sm font-bold"><?php echo h($success); ?></span></div><?php endif; ?>
        <?php if ($error): ?><div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded-2xl flex items-center gap-3 animate-fade-in"><i data-lucide="alert-circle" size="20"></i><span class="text-sm font-bold"><?php echo h($error); ?></span></div><?php endif; ?>

        <div class="flex bg-gray-200 p-1.5 rounded-[24px]">
            <a href="?tab=compose" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'compose' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'; ?>">Compose</a>
            <a href="?tab=ids" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'ids' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'; ?>">Sender IDs</a>
            <a href="?tab=contacts" class="flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center transition-all <?php echo $activeTab === 'contacts' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'; ?>">Phone Book</a>
        </div>

        <?php if ($activeTab === 'compose'): ?>
            <div class="space-y-6 animate-fade-in">
                <div class="bg-gradient-to-br from-opay-green to-emerald-600 p-6 rounded-[32px] text-white shadow-lg relative overflow-hidden">
                    <div class="flex justify-between items-start mb-4">
                        <div><div class="text-[10px] font-black uppercase tracking-widest opacity-70">Current Rate</div><div class="text-2xl font-black"><?php echo format_currency($settings['smsRate']); ?><span class="text-xs font-bold opacity-60"> / SMS</span></div></div>
                        <div class="bg-white/20 p-2 rounded-xl"><i data-lucide="info" size="16"></i></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/10"><div class="text-[8px] font-black uppercase opacity-60">Char Limit</div><div class="text-xs font-black">160 Characters</div></div>
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/10"><div class="text-[8px] font-black uppercase opacity-60">Multi-page</div><div class="text-xs font-black">Supported</div></div>
                    </div>
                </div>

                <form method="POST" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="send_sms">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Approved Sender ID</label>
                        <?php if (empty($myApprovedIds)): ?>
                            <div class="p-4 bg-orange-50 rounded-2xl border border-orange-100 text-[10px] font-bold text-orange-600">No approved IDs. Register one in the 'Sender IDs' tab.</div>
                        <?php else: ?>
                            <select name="senderId" required class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold appearance-none cursor-pointer">
                                <option value="">Select Sender ID</option>
                                <?php foreach ($myApprovedIds as $id): ?><option value="<?php echo h($id['name']); ?>"><?php echo h($id['name']); ?></option><?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2 px-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients</label>
                            <button type="button" onclick="openContactPicker()" class="text-[10px] font-black text-opay-green uppercase flex items-center gap-1 bg-green-50 px-3 py-1.5 rounded-xl border border-green-100"><i data-lucide="book-open" size="12"></i> Contact Book</button>
                        </div>
                        <textarea name="numbers" id="sms_recipients" required class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[120px] placeholder:text-gray-300 text-sm" placeholder="Paste numbers here (separated by comma, space or new line)" oninput="updateSmsStats()"></textarea>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2 px-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Message</label>
                            <span id="sms_pages" class="text-[9px] font-black px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">1 Page</span>
                        </div>
                        <textarea name="message" id="sms_message" required class="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[150px] placeholder:text-gray-300 text-sm" placeholder="Type message content..." oninput="updateSmsStats()"></textarea>
                        <div class="flex justify-between mt-3 px-1">
                            <span id="sms_chars" class="text-[9px] font-black text-gray-400 uppercase">0 Characters Used</span>
                            <span class="text-[9px] font-black text-opay-green uppercase"><?php echo format_currency($settings['smsRate']); ?> per page</span>
                        </div>
                    </div>
                    <div class="bg-gray-900 p-6 rounded-[32px] text-white space-y-4 shadow-xl">
                        <div class="flex justify-between items-center"><span class="text-[10px] font-bold opacity-70">Unique Numbers</span><span id="calc_numbers" class="text-xs font-black">0</span></div>
                        <div class="flex justify-between items-center"><span class="text-[10px] font-bold opacity-70">Total Unit(s)</span><span id="calc_units" class="text-xs font-black">0</span></div>
                        <div class="h-px bg-white/10"></div>
                        <div class="flex justify-between items-center"><span class="text-sm font-black text-white/50 uppercase">Total Amount</span><div id="calc_amount" class="text-xl font-black text-opay-green">₦0.00</div></div>
                    </div>
                    <button type="submit" class="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i data-lucide="send" size="18"></i> BROADCAST SMS
                    </button>
                </form>
            </div>
        <?php elseif ($activeTab === 'ids'): ?>
            <div class="space-y-8 animate-fade-in">
                <form method="POST" class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="register_id">
                    <div class="flex justify-between items-center"><h3 class="text-xs font-black text-gray-800 uppercase tracking-widest flex items-center gap-2"><i data-lucide="plus" class="text-opay-green" size="16"></i> Register Sender ID</h3></div>
                    <div>
                        <label class="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Proposed ID (Max 11 Chars)</label>
                        <input type="text" name="senderId" maxlength="11" required placeholder="e.g. OPAY CLONE" class="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold uppercase border-2 border-transparent focus:border-opay-green">
                        <p class="text-[8px] font-bold text-gray-400 mt-2 px-1">Sender IDs must not exceed 11 characters. Special characters are discouraged.</p>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Sample SMS Content</label>
                        <textarea name="sampleMessage" required placeholder="Type a sample message..." class="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-medium min-h-[100px] text-sm"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl active:scale-95 transition-all flex items-center justify-center">SUBMIT FOR APPROVAL</button>
                </form>
                <div class="space-y-4">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Recent Requests</h3>
                    <div class="space-y-3">
                        <?php if (empty($mySmsHistory)): ?>
                            <div class="py-12 flex flex-col items-center gap-4 bg-white rounded-[32px] border border-gray-100 border-dashed"><i data-lucide="shield-check" size="32" class="text-gray-100"></i><p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">No history found</p></div>
                        <?php else: ?>
                            <?php foreach ($mySmsHistory as $id): ?>
                                <div class="bg-white p-5 rounded-[24px] border border-gray-100 flex items-center justify-between shadow-sm">
                                    <div><div class="text-sm font-black text-gray-800 uppercase"><?php echo h($id['name']); ?></div><div class="text-[9px] text-gray-400 font-bold mt-1 uppercase tracking-widest flex items-center gap-2"><i data-lucide="clock" size="10"></i> <?php echo date('Y-m-d', strtotime($id['createdAt'])); ?></div></div>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $id['status'] === 'approved' ? 'bg-green-50 text-green-600' : ($id['status'] === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'); ?>"><?php echo h($id['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($activeTab === 'contacts'): ?>
            <div class="space-y-8 animate-fade-in">
                <div class="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
                    <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest">Add Contact</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add_contact">
                        <div class="grid grid-cols-2 gap-4">
                            <input type="text" name="name" required placeholder="Name" class="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs">
                            <input type="tel" name="phone" required placeholder="Phone" class="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs">
                        </div>
                        <button type="submit" class="w-full bg-opay-green text-white font-black py-4 rounded-2xl shadow-xl active:scale-95 transition-all">ADD TO PHONE BOOK</button>
                    </form>
                </div>
                <div class="space-y-4">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Your Contacts (<?php echo count($myContacts); ?>)</h3>
                    <div class="space-y-2 pb-10">
                        <?php if (empty($myContacts)): ?>
                            <div class="py-20 text-center text-gray-300 font-black text-[10px] uppercase tracking-widest border-2 border-dashed border-gray-200 rounded-[40px]">No contacts found.</div>
                        <?php else: ?>
                            <?php foreach ($myContacts as $c): ?>
                                <div class="bg-white p-4 rounded-[20px] border border-gray-50 flex items-center justify-between group shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-opay-green font-black text-[10px] uppercase"><?php echo substr($c['name'], 0, 2); ?></div>
                                        <div><div class="text-xs font-black text-gray-800"><?php echo h($c['name']); ?></div><div class="text-[9px] text-gray-400 font-bold"><?php echo h($c['phone']); ?></div></div>
                                    </div>
                                    <a href="?tab=contacts&delete_contact=<?php echo $c['id']; ?>" class="p-2 text-red-500 bg-red-50 rounded-lg opacity-0 group-hover:opacity-100 transition-all"><i data-lucide="trash-2" size="14"></i></a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="contactPicker" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-end">
    <div class="w-full max-w-md mx-auto bg-white rounded-t-[40px] p-8 max-h-[85vh] flex flex-col shadow-2xl">
        <div class="flex justify-between items-center mb-6"><div><h3 class="text-xl font-black text-gray-900">Pick Contacts</h3></div><button type="button" onclick="closeContactPicker()" class="p-2 bg-gray-50 rounded-full"><i data-lucide="x" class="text-gray-400"></i></button></div>
        <div class="flex-1 overflow-y-auto space-y-2 pr-1 scrollbar-hide">
            <?php foreach ($myContacts as $c): ?>
                <div onclick="addContactToSms('<?php echo h($c['phone']); ?>')" class="p-4 rounded-[24px] bg-gray-50 flex items-center justify-between cursor-pointer hover:bg-green-50 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-opay-green font-black uppercase"><?php echo substr($c['name'], 0, 1); ?></div>
                        <div><div class="text-xs font-black text-gray-800"><?php echo h($c['name']); ?></div><div class="text-[9px] text-gray-400 font-bold"><?php echo h($c['phone']); ?></div></div>
                    </div>
                    <i data-lucide="plus" size="16" class="text-gray-300"></i>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
const smsRate = <?php echo (float)$settings['smsRate']; ?>;
function updateSmsStats() {
    const numbers = document.getElementById('sms_recipients').value;
    const message = document.getElementById('sms_message').value;

    const recipients = numbers.split(/[,\s\n]+/).filter(n => n.trim().length >= 10);
    const unique = [...new Set(recipients)];

    const charLimit = 160;
    const pages = Math.ceil(message.length / charLimit) || 1;
    const totalUnits = unique.length * pages;
    const totalCost = totalUnits * smsRate;

    document.getElementById('sms_pages').innerText = `${pages} Page${pages > 1 ? 's' : ''}`;
    document.getElementById('sms_chars').innerText = `${message.length} Characters Used`;
    document.getElementById('calc_numbers').innerText = unique.length;
    document.getElementById('calc_units').innerText = totalUnits;
    document.getElementById('calc_amount').innerText = '₦' + totalCost.toFixed(2);
}
function openContactPicker() { document.getElementById('contactPicker').classList.remove('hidden'); }
function closeContactPicker() { document.getElementById('contactPicker').classList.add('hidden'); }
function addContactToSms(phone) {
    const area = document.getElementById('sms_recipients');
    if (area.value.trim().length > 0 && !area.value.endsWith('\n') && !area.value.endsWith(',')) area.value += ', ';
    area.value += phone;
    updateSmsStats();
    closeContactPicker();
}
updateSmsStats();
</script>
<?php include 'includes/nav.php'; ?>
<?php require_once 'includes/footer.php'; ?>
