<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $clientId = $settings['googleClientId'];
    $clientSecret = $settings['googleClientSecret'];
    $redirectUri = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/google-callback.php';

    // Exchange code for token
    $tokenRes = callApi("https://oauth2.googleapis.com/token", 'POST', [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ], ['Content-Type: application/x-www-form-urlencoded']);

    if (isset($tokenRes['id_token'])) {
        $idToken = $tokenRes['id_token'];
        // Verify ID Token (Simple verification via Google's endpoint)
        $verifyRes = callApi("https://oauth2.googleapis.com/tokeninfo?id_token=$idToken");

        if (isset($verifyRes['email'])) {
            $email = $verifyRes['email'];
            $googleId = $verifyRes['sub'];
            $fullName = $verifyRes['name'] ?? 'Google User';

            // Check if user exists by Google ID or Email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE googleId = ? OR email = ?");
            $stmt->execute([$googleId, $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Update Google ID if not set
                if (empty($user['googleId'])) {
                    $pdo->prepare("UPDATE users SET googleId = ? WHERE id = ?")->execute([$googleId, $user['id']]);
                }

                if (!empty($user['phone'])) {
                    // Fully registered user
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    redirect('/dashboard');
                } else {
                    // Missing phone number
                    $_SESSION['pending_google_reg'] = $user['id'];
                    redirect('/complete-registration');
                }
            } else {
                // New User: Create record
                $userId = 'u' . bin2hex(random_bytes(4));
                $username = strtolower(explode('@', $email)[0]) . rand(100, 999);
                // Ensure username uniqueness
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) $username .= rand(10, 99);

                $stmt = $pdo->prepare("INSERT INTO users (id, username, fullName, email, role, googleId, walletBalance, bonusCoins) VALUES (?, ?, ?, ?, 'user', ?, ?, ?)");
                $stmt->execute([$userId, $username, $fullName, $email, $googleId, $settings['welcomeBonus'] / $settings['conversionRate'], $settings['welcomeBonus']]);

                $_SESSION['pending_google_reg'] = $userId;
                redirect('/complete-registration');
            }
        } else {
            die("Google verification failed: " . ($verifyRes['error_description'] ?? 'Unknown error'));
        }
    } else {
        die("Token exchange failed: " . ($tokenRes['error_description'] ?? 'Unknown error'));
    }
} else {
    redirect('/login');
}
