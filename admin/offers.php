<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Promotional Offers';

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $gradientFrom = sanitize($_POST['gradientFrom']);
        $gradientTo = sanitize($_POST['gradientTo']);
        $textColor = sanitize($_POST['textColor']);
        $expiryDate = $_POST['expiryDate'];

        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadDir = __DIR__ . '/../uploads/offers/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = 'uploads/offers/offer_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../' . $image);
        }

        $stmt = $pdo->prepare("INSERT INTO offers (title, content, image, gradientFrom, gradientTo, textColor, expiryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $content, $image, $gradientFrom, $gradientTo, $textColor, $expiryDate])) {
            $success = "Offer added successfully!";
        } else {
            $error = "Failed to add offer.";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM offers WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Offer deleted successfully!";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM offers ORDER BY createdAt DESC");
$offers = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-3xl font-black uppercase tracking-tighter">Promotions Hub</h2>
        <button onclick="document.getElementById('addOfferModal').classList.remove('hidden')" class="bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase shadow-xl hover:bg-black transition-all flex items-center gap-3">
            <i data-lucide="plus" class="w-4 h-4"></i> New Offer
        </button>
    </div>

    <?php if ($success): ?>
        <div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($offers as $offer): ?>
            <div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden flex flex-col group">
                <div class="h-48 relative overflow-hidden flex flex-col justify-center p-8" style="background: linear-gradient(to bottom right, <?php echo $offer['gradientFrom']; ?>, <?php echo $offer['gradientTo']; ?>); color: <?php echo $offer['textColor']; ?>;">
                    <?php if ($offer['image']): ?>
                        <img src="/<?php echo $offer['image']; ?>" class="absolute top-0 left-0 w-full h-full object-cover opacity-20 group-hover:scale-110 transition-transform duration-500">
                    <?php endif; ?>
                    <div class="relative z-10">
                        <h4 class="text-xl font-black leading-tight"><?php echo $offer['title']; ?></h4>
                        <div class="text-[10px] font-bold uppercase opacity-60 mt-2 tracking-widest">Expires: <?php echo date('M d, Y', strtotime($offer['expiryDate'])); ?></div>
                    </div>
                </div>
                <div class="p-8 flex-1 flex flex-col">
                    <p class="text-xs text-gray-500 font-medium leading-relaxed mb-6 flex-1"><?php echo $offer['content']; ?></p>
                    <div class="flex justify-between items-center">
                        <?php
                        $isExpired = strtotime($offer['expiryDate']) < time();
                        ?>
                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $isExpired ? 'bg-red-50 text-red-500' : 'bg-green-50 text-green-600'; ?>">
                            <?php echo $isExpired ? 'Expired' : 'Active'; ?>
                        </span>
                        <form method="POST" onsubmit="return confirm('Are you sure?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $offer['id']; ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600 transition-colors"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Offer Modal -->
<div id="addOfferModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden items-center justify-center p-6 flex">
    <div class="bg-white w-full max-w-2xl rounded-[40px] shadow-2xl overflow-hidden animate-slide-up">
        <div class="p-10 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-xl font-black uppercase tracking-tight text-gray-900">New Promotion</h3>
            <button onclick="document.getElementById('addOfferModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-10 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Offer Title</label>
                    <input type="text" name="title" required class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Expiry Date</label>
                    <input type="datetime-local" name="expiryDate" required class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Description / Content</label>
                <textarea name="content" rows="3" required class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Gradient From</label>
                    <input type="color" name="gradientFrom" value="#00c689" class="w-full h-12 p-1 bg-gray-50 rounded-xl border border-gray-100 outline-none cursor-pointer">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Gradient To</label>
                    <input type="color" name="gradientTo" value="#00a672" class="w-full h-12 p-1 bg-gray-50 rounded-xl border border-gray-100 outline-none cursor-pointer">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Text Color</label>
                    <input type="color" name="textColor" value="#ffffff" class="w-full h-12 p-1 bg-gray-50 rounded-xl border border-gray-100 outline-none cursor-pointer">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Background Image (Optional)</label>
                <input type="file" name="image" accept="image/*" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
            </div>

            <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl hover:bg-black transition-all mt-4">Publish Offer</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
