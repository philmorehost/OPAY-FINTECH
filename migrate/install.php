<?php
// migrate/install.php
session_start();
define('DB_PATH', __DIR__ . '/database.db');
$stage = $_GET['stage'] ?? 1;
if (file_exists(DB_PATH) && $stage == 1) {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $checkSettings = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        $checkUsers = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");

        if ($checkSettings->fetch() && $checkUsers->fetch()) {
            $adminCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($adminCheck > 0) {
                die("System already installed. Please delete install.php for security.");
            }
        }
    } catch (Exception $e) {}
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage == 2) {
        // Stage 2: Create DB and Schema
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $db->exec("CREATE TABLE IF NOT EXISTS users (id TEXT PRIMARY KEY, username TEXT UNIQUE, fullName TEXT, walletBalance REAL DEFAULT 0, bonusCoins INTEGER DEFAULT 0, role TEXT, phone TEXT, password TEXT, isSuspended INTEGER DEFAULT 0, streakCount INTEGER DEFAULT 0, lastPurchaseDate TEXT, referralCount INTEGER DEFAULT 0, referralEarnings REAL DEFAULT 0, kycStatus TEXT DEFAULT 'none', tier INTEGER DEFAULT 1, email TEXT, loginAlertsEnabled INTEGER DEFAULT 1, biometricEnabled INTEGER DEFAULT 0, authorizedDevices TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS transactions (id TEXT PRIMARY KEY, userId TEXT, type TEXT, amount REAL, status TEXT, date TEXT, details TEXT, recipient TEXT, provider TEXT, refunded INTEGER DEFAULT 0, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY, bonusPerDay REAL, referralBonus REAL, welcomeBonus REAL, streakBonus REAL, maxCoinThreshold REAL, conversionRate REAL, bankAccount TEXT, bankName TEXT, accountName TEXT, manualDepositCharge REAL, paystackChargePercent REAL, paystackPublicKey TEXT, paystackSecretKey TEXT, maxDailyTxPerId INTEGER, minDepositAmount REAL, minAirtimePurchase REAL, isMaintenanceMode INTEGER, adminTheme TEXT, smtpHost TEXT, smtpPort TEXT, smtpUser TEXT, smtpPass TEXT, senderName TEXT, fromEmail TEXT, smsRate REAL, kudiSmsToken TEXT, apiKeys TEXT, offers TEXT, nellobyteUserId TEXT, nellobyteApiKey TEXT, dataGiftingApiKey TEXT, examApiKey TEXT, vtPassApiKey TEXT, vtPassPublicKey TEXT, vtPassEmail TEXT, vtPassPassword TEXT, examProviders TEXT, dataNetworks TEXT, cableProviders TEXT, airtimeDiscounts TEXT, siteName TEXT, siteDescription TEXT, logoPath TEXT, adminWhatsapp TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS deposit_requests (id TEXT PRIMARY KEY, userId TEXT, amount REAL, method TEXT, status TEXT, date TEXT, senderName TEXT, reference TEXT, charge REAL, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS sms_sender_ids (id TEXT PRIMARY KEY, userId TEXT, name TEXT, sampleMessage TEXT, status TEXT, createdAt TEXT, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS gift_card_requests (id TEXT PRIMARY KEY, userId TEXT, cardBrand TEXT, amount REAL, nairaAmount REAL, type TEXT, code TEXT, status TEXT, date TEXT, rate REAL, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS tickets (id TEXT PRIMARY KEY, userId TEXT, subject TEXT, message TEXT, status TEXT, createdAt TEXT, replies TEXT, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS virtual_cards (id TEXT PRIMARY KEY, userId TEXT, cardNumber TEXT, expiry TEXT, cvv TEXT, balance REAL, type TEXT, isFrozen INTEGER DEFAULT 0, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS kyc_submissions (id TEXT PRIMARY KEY, userId TEXT, fullName TEXT, dob TEXT, address TEXT, idType TEXT, idNumber TEXT, idImageUrl TEXT, addressImageUrl TEXT, status TEXT, date TEXT, rejectionReason TEXT, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS phone_book (id TEXT PRIMARY KEY, userId TEXT, name TEXT, phone TEXT, createdAt TEXT, FOREIGN KEY(userId) REFERENCES users(id))");
            $db->exec("CREATE TABLE IF NOT EXISTS data_products (id TEXT PRIMARY KEY, networkId TEXT, type TEXT, size TEXT, apiQuantityCode TEXT, userPrice REAL, enabled INTEGER DEFAULT 1)");

            // Seed default settings
            $settings = [
                'bonusPerDay' => 20, 'referralBonus' => 100, 'welcomeBonus' => 50, 'streakBonus' => 150, 'maxCoinThreshold' => 200, 'conversionRate' => 20,
                'bankAccount' => '1234567890', 'bankName' => 'O-Pay Digital Bank', 'accountName' => 'VTU-Fintech CLONE TECH', 'manualDepositCharge' => 50,
                'paystackChargePercent' => 1.5, 'paystackPublicKey' => '', 'paystackSecretKey' => '', 'maxDailyTxPerId' => 3, 'minDepositAmount' => 100, 'minAirtimePurchase' => 50,
                'isMaintenanceMode' => 0, 'adminTheme' => 'light', 'smtpHost' => '', 'smtpPort' => '', 'smtpUser' => '', 'smtpPass' => '', 'senderName' => 'VTU-Fintech Support', 'fromEmail' => '', 'smsRate' => 4.5, 'kudiSmsToken' => '',
                'apiKeys' => json_encode([]), 'offers' => json_encode([]), 'nellobyteUserId' => '', 'nellobyteApiKey' => '', 'dataGiftingApiKey' => '', 'examApiKey' => '', 'vtPassApiKey' => '', 'vtPassPublicKey' => '',
                'siteName' => 'VTU-Fintech', 'siteDescription' => 'The most reliable VTU and Fintech platform.', 'logoPath' => '', 'adminWhatsapp' => '2349000000000',
                'examProviders' => json_encode([
                    ['id' => 'waec', 'name' => 'WAEC PIN', 'userPrice' => 3500, 'enabled' => true],
                    ['id' => 'neco', 'name' => 'NECO PIN', 'userPrice' => 1200, 'enabled' => true]
                ]),
                'dataNetworks' => json_encode([
                    ['id' => 'mtn', 'name' => 'MTN'], ['id' => 'airtel', 'name' => 'Airtel'], ['id' => 'glo', 'name' => 'Glo'], ['id' => 'mobile9', 'name' => '9mobile']
                ]),
                'cableProviders' => json_encode([
                    ['id' => 'dstv', 'name' => 'DSTV'], ['id' => 'gotv', 'name' => 'GOTV'], ['id' => 'startimes', 'name' => 'Startimes']
                ]),
                'airtimeDiscounts' => json_encode(['mtn' => 3, 'glo' => 8, 'airtel' => 3, 'nineMobile' => 7])
            ];
            $cols = implode(', ', array_keys($settings));
            $placeholders = implode(', ', array_fill(0, count($settings), '?'));
            $stmt = $db->prepare("INSERT INTO settings ($cols) VALUES ($placeholders)");
            $stmt->execute(array_values($settings));

            // Seed some data products
            $dataPlans = [
                ['id' => 'mtn-1gb', 'networkId' => 'mtn', 'type' => 'SME', 'size' => '1GB', 'apiQuantityCode' => '1000', 'userPrice' => 250],
                ['id' => 'mtn-2gb', 'networkId' => 'mtn', 'type' => 'SME', 'size' => '2GB', 'apiQuantityCode' => '2000', 'userPrice' => 500],
                ['id' => 'airtel-1gb', 'networkId' => 'airtel', 'type' => 'CG', 'size' => '1GB', 'apiQuantityCode' => '1000', 'userPrice' => 240],
            ];
            foreach ($dataPlans as $dp) {
                $db->prepare("INSERT INTO data_products (id, networkId, type, size, apiQuantityCode, userPrice) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute(array_values($dp));
            }

            header('Location: install.php?stage=3');
            exit;
        } catch (PDOException $e) {
            $error = "DB Error: " . $e->getMessage();
        }
    } elseif ($stage == 3) {
        // Stage 3: Admin Setup
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $username = $_POST['username'] ?? '';
            $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
            $email = $_POST['email'] ?? '';

            $stmt = $db->prepare("INSERT INTO users (id, username, password, email, role, fullName, tier, kycStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([ 'admin-1', $username, $password, $email, 'admin', 'Administrator', 3, 'verified' ]);

            header('Location: install.php?stage=4');
            exit;
        } catch (PDOException $e) {
            $error = "Setup Error: " . $e->getMessage();
        }
    }
}

// Requirements Check for Stage 1
$php_version = phpversion();
$pdo_sqlite = extension_loaded('pdo_sqlite');
$openssl = extension_loaded('openssl');
$ready = version_compare($php_version, '7.4.0', '>=') && $pdo_sqlite && $openssl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Script Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-10 rounded-[40px] shadow-2xl w-full max-w-md border border-gray-100">
        <?php if ($stage == 1): ?>
            <h2 class="text-2xl font-black mb-6">Welcome to VTU-Fintech Clone</h2>
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center text-sm">
                    <span>PHP Version (>= 7.4)</span>
                    <span class="<?php echo version_compare($php_version, '7.4.0', '>=') ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $php_version; ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span>PDO SQLite Extension</span>
                    <span class="<?php echo $pdo_sqlite ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $pdo_sqlite ? 'OK' : 'Missing'; ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span>OpenSSL Extension</span>
                    <span class="<?php echo $openssl ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $openssl ? 'OK' : 'Missing'; ?></span>
                </div>
            </div>
            <?php if ($ready): ?>
                <a href="?stage=2" class="block w-full bg-blue-600 text-white text-center py-4 rounded-2xl font-bold">START INSTALLATION</a>
            <?php else: ?>
                <p class="text-red-500 text-center font-bold">Please fix requirements to continue.</p>
            <?php endif; ?>

        <?php elseif ($stage == 2): ?>
            <h2 class="text-2xl font-black mb-6">Database Setup</h2>
            <p class="text-gray-500 mb-8">We will now create the SQLite database and install the required tables.</p>
            <?php if ($error): ?><p class="text-red-500 mb-4"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST">
                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">CREATE DATABASE</button>
            </form>

        <?php elseif ($stage == 3): ?>
            <h2 class="text-2xl font-black mb-6">Admin Account</h2>
            <form method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Admin Username" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <input type="email" name="email" placeholder="Admin Email" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <input type="password" name="password" placeholder="Admin Password" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">COMPLETE SETUP</button>
            </form>

        <?php elseif ($stage == 4): ?>
            <h2 class="text-2xl font-black text-green-600 mb-4">Congratulations!</h2>
            <p class="text-gray-600 mb-6">VTU-Fintech Clone has been successfully installed.</p>
            <div class="bg-gray-50 p-6 rounded-2xl space-y-4 mb-8">
                <h3 class="font-black text-xs uppercase tracking-widest text-gray-400">Next Steps:</h3>
                <ul class="text-sm space-y-2 font-bold">
                    <li>1. Delete <code class="text-red-500">install.php</code> for security.</li>
                    <li>2. Login to the Admin Portal.</li>
                    <li>3. Configure API keys in API Manager.</li>
                    <li>4. Add Data plans and Exam products.</li>
                </ul>
            </div>
            <a href="login" class="block w-full bg-vtu-green text-white text-center py-4 rounded-2xl font-bold">LOGIN NOW</a>
        <?php endif; ?>
    </div>
</body>
</html>
