<?php
session_start();

// Block access if already installed
if (file_exists(__DIR__ . '/includes/db.php')) {
    try {
        require_once __DIR__ . '/includes/db.php';
        // If we reach here, db.php exists and might be valid.
        // Check if settings table exists to be sure.
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() > 0) {
            die("System already installed. Please delete install.php for security.");
        }
    } catch (PDOException $e) {
        // Continue if connection fails, as db.php might be broken
    }
}

$stage = isset($_GET['stage']) ? (int)$_GET['stage'] : 1;

if ($stage === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Stage 2: Database Setup
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];

    try {
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name` ");

        // Save DB config to session temporarily
        $_SESSION['db_config'] = [
            'host' => $db_host,
            'name' => $db_name,
            'user' => $db_user,
            'pass' => $db_pass
        ];

        // Create Schema
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(50) PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                fullName VARCHAR(100) NOT NULL,
                walletBalance DECIMAL(15, 2) DEFAULT 0.00,
                bonusCoins INT DEFAULT 0,
                role ENUM('user', 'admin') DEFAULT 'user',
                phone VARCHAR(20),
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                paymentPin VARCHAR(10) DEFAULT '0000',
                isSuspended BOOLEAN DEFAULT FALSE,
                streakCount INT DEFAULT 0,
                referralCount INT DEFAULT 0,
                referralEarnings DECIMAL(15, 2) DEFAULT 0.00,
                kycStatus ENUM('none', 'pending', 'verified', 'rejected') DEFAULT 'none',
                tier INT DEFAULT 1,
                loginAlertsEnabled BOOLEAN DEFAULT TRUE,
                biometricEnabled BOOLEAN DEFAULT FALSE,
                marketingEmailsEnabled BOOLEAN DEFAULT TRUE,
                smsAlertsEnabled BOOLEAN DEFAULT FALSE,
                lastPurchaseDate DATETIME,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS transactions (
                id VARCHAR(50) PRIMARY KEY,
                userId VARCHAR(50) NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                status ENUM('pending', 'successful', 'failed') DEFAULT 'pending',
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                details TEXT,
                recipient VARCHAR(100),
                provider VARCHAR(50),
                refunded BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS deposit_requests (
                id VARCHAR(50) PRIMARY KEY,
                userId VARCHAR(50) NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                method ENUM('manual', 'paystack') NOT NULL,
                status ENUM('pending', 'successful', 'rejected') DEFAULT 'pending',
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                senderName VARCHAR(100),
                reference VARCHAR(100),
                charge DECIMAL(15, 2) DEFAULT 0.00,
                FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                id INT PRIMARY KEY DEFAULT 1,
                bonusPerDay INT DEFAULT 20,
                referralBonus INT DEFAULT 100,
                welcomeBonus INT DEFAULT 50,
                streakBonus INT DEFAULT 150,
                maxCoinThreshold INT DEFAULT 200,
                conversionRate INT DEFAULT 20,
                bankAccount VARCHAR(50),
                bankName VARCHAR(100),
                accountName VARCHAR(100),
                manualDepositCharge DECIMAL(15, 2) DEFAULT 50.00,
                paystackChargePercent DECIMAL(5, 2) DEFAULT 1.50,
                paystackPublicKey VARCHAR(255),
                paystackSecretKey VARCHAR(255),
                maxDailyTxPerId INT DEFAULT 5,
                minDepositAmount DECIMAL(15, 2) DEFAULT 100.00,
                minAirtimePurchase DECIMAL(15, 2) DEFAULT 50.00,
                isMaintenanceMode BOOLEAN DEFAULT FALSE,
                adminTheme ENUM('light', 'dark') DEFAULT 'light',
                smtpHost VARCHAR(255),
                smtpPort VARCHAR(10),
                smtpUser VARCHAR(255),
                smtpPass VARCHAR(255),
                senderName VARCHAR(100),
                fromEmail VARCHAR(100),
                smsRate DECIMAL(15, 2) DEFAULT 4.50,
                nellobyteUserId VARCHAR(255),
                nellobyteApiKey VARCHAR(255),
                dataGiftingApiKey VARCHAR(255),
                examApiKey VARCHAR(255),
                vtPassApiKey VARCHAR(255),
                vtPassPublicKey VARCHAR(255),
                vtPassEmail VARCHAR(255),
                vtPassPassword VARCHAR(255),
                kudiSmsToken VARCHAR(255),
                stripeSecretKey VARCHAR(255),
                tremendousApiKey VARCHAR(255),
                juicywayApiKey VARCHAR(255),
                dataNetworks TEXT,
                cableProviders TEXT,
                electricProviders TEXT,
                bettingProviders TEXT,
                airtimeDiscounts TEXT,
                dataProducts TEXT
            )",
            "CREATE TABLE IF NOT EXISTS support_tickets (
                id VARCHAR(50) PRIMARY KEY,
                userId VARCHAR(50) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('open', 'closed') DEFAULT 'open',
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS support_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticketId VARCHAR(50) NOT NULL,
                author VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticketId) REFERENCES support_tickets(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS kyc_submissions (
                id VARCHAR(50) PRIMARY KEY,
                userId VARCHAR(50) NOT NULL,
                fullName VARCHAR(100) NOT NULL,
                dob DATE,
                address TEXT,
                idType VARCHAR(50),
                idNumber VARCHAR(50),
                idImageUrl VARCHAR(255),
                addressImageUrl VARCHAR(255),
                status ENUM('none', 'pending', 'verified', 'rejected') DEFAULT 'pending',
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                rejectionReason TEXT,
                FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS virtual_cards (
                id VARCHAR(50) PRIMARY KEY,
                userId VARCHAR(50) NOT NULL,
                cardNumber VARCHAR(20) NOT NULL,
                expiry VARCHAR(10) NOT NULL,
                cvv VARCHAR(5) NOT NULL,
                balance DECIMAL(15, 2) DEFAULT 0.00,
                type ENUM('Visa', 'Mastercard') NOT NULL,
                isFrozen BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
            )"
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        // Insert default settings if not exists
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (id, dataNetworks, cableProviders, electricProviders, bettingProviders, airtimeDiscounts) VALUES (1, ?, ?, ?, ?, ?)");
        $stmt->execute([
            json_encode([
                ['id' => 'mtn', 'name' => 'MTN', 'apiCode' => '01'],
                ['id' => 'airtel', 'name' => 'Airtel', 'apiCode' => '04'],
                ['id' => 'glo', 'name' => 'Glo', 'apiCode' => '02'],
                ['id' => 'mobile9', 'name' => '9mobile', 'apiCode' => '03']
            ]),
            json_encode([
                ['id' => 'dstv', 'name' => 'DSTV', 'serviceId' => 'dstv', 'enabled' => true, 'discountPercent' => 1.5, 'variations' => []],
                ['id' => 'gotv', 'name' => 'GOtv', 'serviceId' => 'gotv', 'enabled' => true, 'discountPercent' => 1.5, 'variations' => []],
                ['id' => 'startimes', 'name' => 'Startimes', 'serviceId' => 'startimes', 'enabled' => true, 'discountPercent' => 2.0, 'variations' => []],
                ['id' => 'showmax', 'name' => 'Showmax', 'serviceId' => 'showmax', 'enabled' => true, 'discountPercent' => 1.5, 'variations' => []]
            ]),
            json_encode([
                ['id' => 'ekedc', 'name' => 'Eko Electric', 'serviceId' => 'eko-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'eedc', 'name' => 'Enugu Electric', 'serviceId' => 'enugu-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'ikedc', 'name' => 'Ikeja Electric', 'serviceId' => 'ikeja-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'jedc', 'name' => 'Jos Electric', 'serviceId' => 'jos-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'kedco', 'name' => 'Kano Electric', 'serviceId' => 'kano-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'ibedc', 'name' => 'Ibadan Electric', 'serviceId' => 'ibadan-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'phed', 'name' => 'PH Electric', 'serviceId' => 'portharcourt-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'aedc', 'name' => 'Abuja Electric', 'serviceId' => 'abuja-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'yedc', 'name' => 'Yola Electric', 'serviceId' => 'yola-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'bedc', 'name' => 'Benin Electric', 'serviceId' => 'benin-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'aba', 'name' => 'Aba Electric', 'serviceId' => 'aba-electric', 'enabled' => true, 'discountPercent' => 1.0],
                ['id' => 'kaedco', 'name' => 'Kaduna Electric', 'serviceId' => 'kaduna-electric', 'enabled' => true, 'discountPercent' => 1.0]
            ]),
            json_encode([
                ['id' => 'msport', 'name' => 'MSport', 'enabled' => true, 'discountPercent' => 0],
                ['id' => 'naijabet', 'name' => 'NaijaBet', 'enabled' => true, 'discountPercent' => 0],
                ['id' => 'nairabet', 'name' => 'NairaBet', 'enabled' => true, 'discountPercent' => 0]
            ]),
            json_encode([
                'mtn' => 3,
                'glo' => 8,
                'airtel' => 3,
                'nineMobile' => 7
            ])
        ]);

        header('Location: install.php?stage=3');
        exit;
    } catch (PDOException $e) {
        $error = "Connection failed: " . $e->getMessage();
    }
}

if ($stage === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Stage 3: Admin Setup
    $admin_email = $_POST['admin_email'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);

    $db = $_SESSION['db_config'];
    try {
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, password, role, tier, kycStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin-' . bin2hex(random_bytes(4)), $admin_user, 'Administrator', $admin_email, $admin_pass, 'admin', 3, 'verified']);

        // Create db.php
        $db_content = "<?php\n"
                    . "define('DB_HOST', '{$db['host']}');\n"
                    . "define('DB_NAME', '{$db['name']}');\n"
                    . "define('DB_USER', '{$db['user']}');\n"
                    . "define('DB_PASS', '{$db['pass']}');\n\n"
                    . "\$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);\n"
                    . "\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";

        file_put_contents(__DIR__ . '/includes/db.php', $db_content);

        header('Location: install.php?stage=4');
        exit;
    } catch (PDOException $e) {
        $error = "Failed to create admin: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billpay Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        .billpay-green { color: #00c689; }
        .bg-billpay-green { background-color: #00c689; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-xl w-full bg-white rounded-[40px] shadow-2xl overflow-hidden border border-gray-100">
        <div class="p-10 border-b border-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-2xl">B</div>
                <div>
                    <h1 class="text-xl font-black uppercase tracking-tight">System Installer</h1>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Stage <?php echo $stage; ?> of 4</p>
                </div>
            </div>
            <div class="flex gap-1">
                <?php for($i=1; $i<=4; $i++): ?>
                <div class="w-8 h-1.5 rounded-full <?php echo $i <= $stage ? 'bg-billpay-green' : 'bg-gray-100'; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="p-10">
            <?php if (isset($error)): ?>
                <div class="mb-8 p-4 bg-red-50 text-red-500 rounded-2xl text-xs font-bold border border-red-100"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($stage === 1): ?>
                <div class="space-y-6">
                    <h2 class="text-2xl font-black text-gray-800">Welcome to Billpay</h2>
                    <p class="text-sm text-gray-500 font-medium leading-relaxed">Let's get your fintech platform up and running. First, we need to check if your server meets the requirements.</p>

                    <div class="space-y-3">
                        <?php
                        $reqs = [
                            'PHP Version (>= 7.4)' => PHP_VERSION_ID >= 70400,
                            'PDO Extension' => extension_loaded('pdo_mysql'),
                            'OpenSSL Extension' => extension_loaded('openssl'),
                            'Write Permissions' => is_writable(__DIR__ . '/includes/')
                        ];
                        $all_ok = true;
                        foreach($reqs as $label => $ok):
                            if (!$ok) $all_ok = false;
                        ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                            <span class="text-[11px] font-black uppercase tracking-wider text-gray-400"><?php echo $label; ?></span>
                            <?php if ($ok): ?>
                                <span class="px-3 py-1 bg-green-50 text-green-600 text-[9px] font-black rounded-full">PASSED</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-red-50 text-red-600 text-[9px] font-black rounded-full">FAILED</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pt-6">
                        <?php if ($all_ok): ?>
                            <a href="?stage=2" class="block w-full py-5 bg-billpay-green text-white text-center rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 hover:scale-[1.02] transition-all">Begin Installation</a>
                        <?php else: ?>
                            <button disabled class="w-full py-5 bg-gray-100 text-gray-400 rounded-[24px] font-black uppercase tracking-widest cursor-not-allowed">Requirements Not Met</button>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($stage === 2): ?>
                <form method="POST" class="space-y-6">
                    <h2 class="text-2xl font-black text-gray-800">Database Setup</h2>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Database Host</label>
                            <input type="text" name="db_host" value="localhost" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Database Name</label>
                            <input type="text" name="db_name" placeholder="billpay_db" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Database User</label>
                            <input type="text" name="db_user" placeholder="root" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Database Password</label>
                            <input type="password" name="db_pass" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 mt-4">Install Schema</button>
                </form>

            <?php elseif ($stage === 3): ?>
                <form method="POST" class="space-y-6">
                    <h2 class="text-2xl font-black text-gray-800">Admin Account</h2>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Admin Username</label>
                            <input type="text" name="admin_user" placeholder="admin" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Admin Email</label>
                            <input type="email" name="admin_email" placeholder="admin@example.com" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase ml-1">Admin Password</label>
                            <input type="password" name="admin_pass" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none focus:border-billpay-green font-bold text-sm" required>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-5 bg-billpay-green text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-green-100 mt-4">Complete Setup</button>
                </form>

            <?php elseif ($stage === 4): ?>
                <div class="text-center space-y-6 py-4">
                    <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-black text-gray-800">Installation Complete!</h2>
                    <p class="text-sm text-gray-500 font-medium leading-relaxed px-4">Your platform is ready. You can now login to the admin panel to configure your API gateways and branding.</p>

                    <div class="p-6 bg-amber-50 rounded-[32px] border border-amber-100 text-left">
                        <h4 class="text-[10px] font-black text-amber-700 uppercase tracking-widest mb-2">Important Next Steps:</h4>
                        <ul class="text-[10px] font-bold text-amber-600 space-y-2 list-disc ml-4 uppercase">
                            <li>Delete the <span class="text-amber-800">install.php</span> file for security.</li>
                            <li>Configure SMTP settings in Admin -> Settings.</li>
                            <li>Set your API Keys in Admin -> API Hub.</li>
                        </ul>
                    </div>

                    <div class="pt-6">
                        <a href="/login" class="block w-full py-5 bg-gray-900 text-white rounded-[24px] font-black uppercase tracking-widest shadow-xl shadow-gray-200">Go to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
