<?php
/**
 * Automated Database Migrations
 * Ensures all required columns and tables exist
 */

if (!function_exists('addColumnIfNotExists')) {
function addColumnIfNotExists($pdo, $table, $column, $definition) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD `$column` $definition");
        }
    } catch (PDOException $e) {
        // Silent fail
    }
}
}

try {
    // Settings Table Updates
    addColumnIfNotExists($pdo, 'settings', 'templateId', "INT DEFAULT 1");
    addColumnIfNotExists($pdo, 'settings', 'primaryColor', "VARCHAR(20) DEFAULT '#00c689'");
    addColumnIfNotExists($pdo, 'settings', 'isKycEnforced', "TINYINT(1) DEFAULT 1");
    addColumnIfNotExists($pdo, 'settings', 'isMinDepositForced', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'settings', 'minDepositAmount', "DECIMAL(15, 2) DEFAULT 100.00");
    addColumnIfNotExists($pdo, 'settings', 'pwaEnabled', "TINYINT(1) DEFAULT 1");
    addColumnIfNotExists($pdo, 'settings', 'pwaIcon', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'settings', 'pwaSplash', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'settings', 'siteDescription', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'isBiometricEnforced', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'settings', 'siteVersion', "VARCHAR(50) DEFAULT '1.0.0'");
    addColumnIfNotExists($pdo, 'settings', 'vcardIssuanceFee', "DECIMAL(15, 2) DEFAULT 1500.00");

    // API Hub Restructured Settings
    addColumnIfNotExists($pdo, 'settings', 'airtimeSettings', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'dataSettings', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'utilitySettings', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'financialSettings', "TEXT");
    addColumnIfNotExists($pdo, 'settings', 'otherApiSettings', "TEXT");

    // Transactions Table Updates
    addColumnIfNotExists($pdo, 'transactions', 'token', "VARCHAR(255)");
    addColumnIfNotExists($pdo, 'transactions', 'provider', "VARCHAR(50)");
    addColumnIfNotExists($pdo, 'transactions', 'apiAmount', "DECIMAL(15, 2) DEFAULT 0.00");
    addColumnIfNotExists($pdo, 'transactions', 'profit', "DECIMAL(15, 2) DEFAULT 0.00");

    // KYC Submissions Table Updates
    addColumnIfNotExists($pdo, 'kyc_submissions', 'selfieImageUrl', "VARCHAR(255)");

    // Users Table Updates
    addColumnIfNotExists($pdo, 'users', 'hasCompletedInitialDeposit', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'users', 'biometricEnabled', "TINYINT(1) DEFAULT 0");
    addColumnIfNotExists($pdo, 'users', 'biometricCredentialId', "TEXT");
    addColumnIfNotExists($pdo, 'users', 'biometricPublicKey', "TEXT");

    // Virtual Cards Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS virtual_cards (
        id VARCHAR(50) PRIMARY KEY,
        userId VARCHAR(50) NOT NULL,
        cardNumber VARCHAR(20) NOT NULL,
        expiry VARCHAR(10) NOT NULL,
        cvv VARCHAR(5) NOT NULL,
        balance DECIMAL(15, 2) DEFAULT 0.00,
        type ENUM('Visa', 'Mastercard') NOT NULL,
        isFrozen BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Virtual Accounts Table (Paystack Static Accounts)
    $pdo->exec("CREATE TABLE IF NOT EXISTS virtual_accounts (
        userId VARCHAR(50) PRIMARY KEY,
        bankName VARCHAR(100),
        accountNumber VARCHAR(20),
        accountName VARCHAR(100),
        bankCode VARCHAR(20),
        customerCode VARCHAR(50),
        FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Offers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image VARCHAR(255),
        gradientFrom VARCHAR(20),
        gradientTo VARCHAR(20),
        textColor VARCHAR(20) DEFAULT '#ffffff',
        useGradient TINYINT(1) DEFAULT 1,
        expiryDate DATETIME NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Beneficiaries Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS beneficiaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        userId VARCHAR(50) NOT NULL,
        type ENUM('bank', 'crypto', 'internal', 'interac') NOT NULL,
        currency VARCHAR(10) NOT NULL,
        details TEXT NOT NULL,
        description VARCHAR(255) NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (userId)
    )");

    // Payment Links Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_links (
        id VARCHAR(100) PRIMARY KEY,
        userId VARCHAR(50) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('pending', 'paid', 'expired') DEFAULT 'pending',
        paidAt DATETIME,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (userId)
    )");

} catch (PDOException $e) {
    // Silent fail
}
