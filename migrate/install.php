<?php
// migrate/install.php
session_start();
define('CONFIG_PATH', __DIR__ . '/includes/db.php');

$stage = $_GET['stage'] ?? 1;

// Already installed check
if (file_exists(CONFIG_PATH) && $stage == 1) {
    require_once CONFIG_PATH;
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableExists = false;
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($check && $check->fetch()) $tableExists = true;
        } catch (Exception $e) { $tableExists = false; }

        if ($tableExists) {
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
    if ($stage == 'db_config') {
        $host = $_POST['db_host'];
        $name = $_POST['db_name'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        try {
            $test = new PDO("mysql:host=$host", $user, $pass);
            $test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $test->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $configContent = "<?php\ndefine('DB_TYPE', 'mysql');\ndefine('DB_HOST', '$host');\ndefine('DB_NAME', '$name');\ndefine('DB_USER', '$user');\ndefine('DB_PASS', '$pass');";
            file_put_contents(CONFIG_PATH, $configContent);
            header('Location: install.php?stage=2'); exit;
        } catch (PDOException $e) { $error = "Connection failed: " . $e->getMessage(); }
    } elseif ($stage == 2) {
        if (!file_exists(CONFIG_PATH)) { header('Location: install.php?stage=1'); exit; }
        require_once CONFIG_PATH;
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pk = "VARCHAR(100) PRIMARY KEY";
            $num = "DECIMAL(20,2)";
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $db->exec("CREATE TABLE IF NOT EXISTS users (id $pk, username VARCHAR(100) UNIQUE, fullName TEXT, walletBalance $num DEFAULT 0, bonusCoins INTEGER DEFAULT 0, role VARCHAR(20), phone VARCHAR(20), password TEXT, isSuspended INTEGER DEFAULT 0, streakCount INTEGER DEFAULT 0, lastPurchaseDate TEXT, referralCount INTEGER DEFAULT 0, referralEarnings $num DEFAULT 0, kycStatus VARCHAR(20) DEFAULT 'none', tier INTEGER DEFAULT 1, email VARCHAR(100), loginAlertsEnabled INTEGER DEFAULT 1, biometricEnabled INTEGER DEFAULT 0, authorizedDevices TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS transactions (id $pk, userId VARCHAR(100), type TEXT, amount $num, status TEXT, date TEXT, details TEXT, recipient TEXT, provider TEXT, refunded INTEGER DEFAULT 0)");
            $db->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTO_INCREMENT, bonusPerDay $num, referralBonus $num, welcomeBonus $num, streakBonus $num, maxCoinThreshold $num, conversionRate $num, bankAccount TEXT, bankName TEXT, accountName TEXT, manualDepositCharge $num, paystackChargePercent $num, paystackPublicKey TEXT, paystackSecretKey TEXT, maxDailyTxPerId INTEGER, minDepositAmount $num, minAirtimePurchase $num, isMaintenanceMode INTEGER, adminTheme TEXT, smtpHost TEXT, smtpPort TEXT, smtpUser TEXT, smtpPass TEXT, senderName TEXT, fromEmail TEXT, smsRate $num, kudiSmsToken TEXT, apiKeys TEXT, offers TEXT, nellobyteUserId TEXT, nellobyteApiKey TEXT, dataGiftingApiKey TEXT, examApiKey TEXT, vtPassApiKey TEXT, vtPassPublicKey TEXT, vtPassEmail TEXT, vtPassPassword TEXT, examProviders TEXT, dataNetworks TEXT, cableProviders TEXT, airtimeDiscounts TEXT, siteName TEXT, siteDescription TEXT, logoPath TEXT, adminWhatsapp TEXT, dailyLimitPhone INTEGER DEFAULT 10, dailyLimitSmartCard INTEGER DEFAULT 5, dailyLimitBetting INTEGER DEFAULT 5, dailyLimitMeter INTEGER DEFAULT 5, referralBonusFirstTx $num DEFAULT 100, milestoneBonuses TEXT, darkModeEnabled INTEGER DEFAULT 0)");
            $db->exec("CREATE TABLE IF NOT EXISTS deposit_requests (id $pk, userId VARCHAR(100), amount $num, method TEXT, status TEXT, date TEXT, senderName TEXT, reference TEXT, charge $num)");
            $db->exec("CREATE TABLE IF NOT EXISTS sms_sender_ids (id $pk, userId VARCHAR(100), name TEXT, sampleMessage TEXT, status TEXT, createdAt TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS gift_card_requests (id $pk, userId VARCHAR(100), cardBrand TEXT, amount $num, nairaAmount $num, type TEXT, code TEXT, status TEXT, date TEXT, rate $num)");
            $db->exec("CREATE TABLE IF NOT EXISTS tickets (id $pk, userId VARCHAR(100), subject TEXT, message TEXT, status TEXT, createdAt TEXT, replies TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS virtual_cards (id $pk, userId VARCHAR(100), cardNumber TEXT, expiry TEXT, cvv TEXT, balance $num, type TEXT, isFrozen INTEGER DEFAULT 0)");
            $db->exec("CREATE TABLE IF NOT EXISTS kyc_submissions (id $pk, userId VARCHAR(100), fullName TEXT, dob TEXT, address TEXT, idType TEXT, idNumber TEXT, idImageUrl TEXT, addressImageUrl TEXT, status TEXT, date TEXT, rejectionReason TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS phone_book (id $pk, userId VARCHAR(100), name TEXT, phone TEXT, createdAt TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS data_products (id $pk, networkId TEXT, type TEXT, size TEXT, apiQuantityCode TEXT, userPrice $num, enabled INTEGER DEFAULT 1)");
            $db->exec("CREATE TABLE IF NOT EXISTS sms_logs (id $pk, userId VARCHAR(100), senderId TEXT, recipients TEXT, message TEXT, pages INTEGER, cost $num, status TEXT, date TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS notifications (id $pk, userId VARCHAR(100), title TEXT, message TEXT, type TEXT, isRead INTEGER DEFAULT 0, createdAt TEXT)");
            $db->exec("CREATE TABLE IF NOT EXISTS crypto_wallets (id $pk, userId VARCHAR(100), coinType TEXT, balance $num DEFAULT 0, address TEXT)");

            // Update users table with KYC/Profile fields if not already there
            try { $db->exec("ALTER TABLE users ADD COLUMN dob TEXT"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE users ADD COLUMN address TEXT"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE users ADD COLUMN gender TEXT"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE users ADD COLUMN occupation TEXT"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE users ADD COLUMN referredBy VARCHAR(100)"); } catch(Exception $e) {}

            // Update settings table - check columns before adding
            $existing_cols = [];
            $q = $db->query("DESCRIBE settings");
            while ($row = $q->fetch()) { $existing_cols[] = $row['Field']; }
            $new_cols = [
                'dailyLimitPhone' => 'INTEGER DEFAULT 10',
                'dailyLimitSmartCard' => 'INTEGER DEFAULT 5',
                'dailyLimitBetting' => 'INTEGER DEFAULT 5',
                'dailyLimitMeter' => 'INTEGER DEFAULT 5',
                'referralBonusFirstTx' => "$num DEFAULT 100",
                'milestoneBonuses' => 'TEXT',
                'darkModeEnabled' => 'INTEGER DEFAULT 0'
            ];
            foreach ($new_cols as $col => $def) {
                if (!in_array($col, $existing_cols)) {
                    $db->exec("ALTER TABLE settings ADD COLUMN $col $def");
                }
            }

            $settingsCount = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            if ($settingsCount == 0) {
                $settings = [
                    'bonusPerDay' => 20, 'referralBonus' => 100, 'welcomeBonus' => 50, 'streakBonus' => 150, 'maxCoinThreshold' => 200, 'conversionRate' => 20,
                    'bankAccount' => '1234567890', 'bankName' => 'Digital Bank', 'accountName' => 'VTU-Fintech TECH', 'manualDepositCharge' => 50,
                    'paystackChargePercent' => 1.5, 'paystackPublicKey' => '', 'paystackSecretKey' => '', 'maxDailyTxPerId' => 3, 'minDepositAmount' => 100, 'minAirtimePurchase' => 50,
                    'isMaintenanceMode' => 0, 'adminTheme' => 'light', 'smtpHost' => '', 'smtpPort' => '', 'smtpUser' => '', 'smtpPass' => '', 'senderName' => 'VTU-Fintech Support', 'fromEmail' => '', 'smsRate' => 4.5, 'kudiSmsToken' => '',
                    'apiKeys' => json_encode([]), 'offers' => json_encode([]), 'nellobyteUserId' => '', 'nellobyteApiKey' => '', 'dataGiftingApiKey' => '', 'examApiKey' => '', 'vtPassApiKey' => '', 'vtPassPublicKey' => '',
                    'siteName' => 'VTU-Fintech', 'siteDescription' => 'Reliable VTU and Fintech solutions.', 'logoPath' => '', 'adminWhatsapp' => '2349000000000',
                    'examProviders' => json_encode([['id' => 'waec', 'name' => 'WAEC PIN', 'userPrice' => 3500, 'enabled' => true], ['id' => 'neco', 'name' => 'NECO PIN', 'userPrice' => 1200, 'enabled' => true]]),
                    'dataNetworks' => json_encode([['id' => 'mtn', 'name' => 'MTN'], ['id' => 'airtel', 'name' => 'Airtel'], ['id' => 'glo', 'name' => 'Glo'], ['id' => 'mobile9', 'name' => '9mobile']]),
                    'cableProviders' => json_encode([['id' => 'dstv', 'name' => 'DSTV'], ['id' => 'gotv', 'name' => 'GOTV'], ['id' => 'startimes', 'name' => 'Startimes']]),
                    'airtimeDiscounts' => json_encode(['mtn' => 3, 'glo' => 8, 'airtel' => 3, 'nineMobile' => 7])
                ];
                $cols = implode(', ', array_keys($settings));
                $placeholders = implode(', ', array_fill(0, count($settings), '?'));
                $stmt = $db->prepare("INSERT INTO settings ($cols) VALUES ($placeholders)");
                $stmt->execute(array_values($settings));

                $dataPlans = [
                    ['id' => 'mtn-1gb', 'networkId' => 'mtn', 'type' => 'SME', 'size' => '1GB', 'apiQuantityCode' => '1000', 'userPrice' => 250],
                    ['id' => 'mtn-2gb', 'networkId' => 'mtn', 'type' => 'SME', 'size' => '2GB', 'apiQuantityCode' => '2000', 'userPrice' => 500],
                    ['id' => 'airtel-1gb', 'networkId' => 'airtel', 'type' => 'CG', 'size' => '1GB', 'apiQuantityCode' => '1000', 'userPrice' => 240],
                ];
                foreach ($dataPlans as $dp) {
                    $db->prepare("INSERT INTO data_products (id, networkId, type, size, apiQuantityCode, userPrice) VALUES (?, ?, ?, ?, ?, ?)")->execute(array_values($dp));
                }
            }
            header('Location: install.php?stage=3'); exit;
        } catch (PDOException $e) { $error = "DB Error: " . $e->getMessage(); }
    } elseif ($stage == 3) {
        require_once CONFIG_PATH;
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $username = $_POST['username'] ?? '';
            $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
            $email = $_POST['email'] ?? '';
            $stmt = $db->prepare("INSERT INTO users (id, username, password, email, role, fullName, tier, kycStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([ 'admin-1', $username, $password, $email, 'admin', 'Administrator', 3, 'verified' ]);
            header('Location: install.php?stage=4'); exit;
        } catch (PDOException $e) { $error = "Setup Error: " . $e->getMessage(); }
    }
}
$php_version = phpversion();
$pdo_mysql = extension_loaded('pdo_mysql');
$openssl = extension_loaded('openssl');
$ready = version_compare($php_version, '7.4.0', '>=') && $pdo_mysql && $openssl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Script Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6">
    <div class="bg-white p-10 rounded-[40px] shadow-2xl w-full max-w-lg border border-gray-100">
        <?php if ($stage == 1): ?>
            <h2 class="text-2xl font-black mb-6">Welcome to VTU-Fintech</h2>
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center text-sm"><span>PHP Version (>= 7.4)</span><span class="<?php echo version_compare($php_version, '7.4.0', '>=') ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $php_version; ?></span></div>
                <div class="flex justify-between items-center text-sm"><span>PDO MySQL Extension</span><span class="<?php echo $pdo_mysql ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $pdo_mysql ? 'OK' : 'Missing'; ?></span></div>
                <div class="flex justify-between items-center text-sm"><span>OpenSSL Extension</span><span class="<?php echo $openssl ? 'text-green-500' : 'text-red-500'; ?> font-bold"><?php echo $openssl ? 'OK' : 'Missing'; ?></span></div>
            </div>
            <?php if ($ready): ?>
                <form action="?stage=db_config" method="POST" class="space-y-4">
                    <h3 class="font-black text-xs uppercase tracking-widest text-gray-400">MySQL Database Configuration</h3>
                    <input type="text" name="db_host" placeholder="Database Host (e.g. localhost)" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <input type="text" name="db_name" placeholder="Database Name" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <input type="text" name="db_user" placeholder="Database User" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <input type="password" name="db_pass" placeholder="Database Password" class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                    <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold shadow-lg">CONTINUE SETUP</button>
                </form>
            <?php else: ?><p class="text-red-500 text-center font-bold">Please fix requirements to continue.</p><?php endif; ?>

        <?php elseif ($stage == 2): ?>
            <h2 class="text-2xl font-black mb-6">Database Schema</h2>
            <p class="text-gray-500 mb-8">We will now create the required tables in your MySQL database.</p>
            <?php if ($error): ?><p class="text-red-500 mb-4"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST"><button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">INSTALL TABLES</button></form>

        <?php elseif ($stage == 3): ?>
            <h2 class="text-2xl font-black mb-6">Admin Account</h2>
            <?php if ($error): ?><p class="text-red-500 mb-4"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Admin Username" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <input type="email" name="email" placeholder="Admin Email" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <input type="password" name="password" placeholder="Admin Password" required class="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-bold">
                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">FINALIZE SETUP</button>
            </form>

        <?php elseif ($stage == 4): ?>
            <h2 class="text-2xl font-black text-green-600 mb-4">Installation Successful!</h2>
            <p class="text-gray-600 mb-6">VTU-Fintech has been successfully configured.</p>
            <div class="bg-gray-50 p-6 rounded-2xl space-y-4 mb-8">
                <h3 class="font-black text-xs uppercase tracking-widest text-gray-400">Next Steps:</h3>
                <ul class="text-sm space-y-2 font-bold"><li>1. Delete <code class="text-red-500">install.php</code>.</li><li>2. Login to the Admin Portal.</li><li>3. Configure Branding & SEO in Settings.</li></ul>
            </div>
            <a href="login" class="block w-full bg-green-500 text-white text-center py-4 rounded-2xl font-bold shadow-lg">LOGIN NOW</a>
        <?php endif; ?>
    </div>
</body>
</html>
