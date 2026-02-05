<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_GET['code'])) {
    redirect('/login');
}

$code = $_GET['code'];
$clientId = $settings['googleClientId'] ?? '';
$clientSecret = $settings['googleClientSecret'] ?? '';
$redirectUri = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/google-callback.php";

// Exchange code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$payload = [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    die('Google Auth Failed: ' . ($tokenData['error_description'] ?? 'Unknown error'));
}

$accessToken = $tokenData['access_token'];

// Get user info
$userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $accessToken;
$ch = curl_init($userUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfoResponse = curl_exec($ch);
curl_close($ch);
$userInfo = json_decode($userInfoResponse, true);

if (!isset($userInfo['id'])) {
    die('Failed to get Google user info');
}

$googleId = $userInfo['id'];
$email = $userInfo['email'];
$name = $userInfo['name'];

// Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE googleId = ? OR email = ?");
$stmt->execute([$googleId, $email]);
$user = $stmt->fetch();

if ($user) {
    // Update googleId if not set
    if (empty($user['googleId'])) {
        $pdo->prepare("UPDATE users SET googleId = ? WHERE id = ?")->execute([$googleId, $user['id']]);
    }

    if (!empty($user['phone'])) {
        // Full user, login directly or go to verification if MFA is enabled
        $_SESSION['pending_login_id'] = $user['id'];
        redirect('/login-verify');
    } else {
        // User exists but no phone (shouldn't happen with normal flow, but let's be safe)
        $_SESSION['temp_google_user'] = [
            'googleId' => $googleId,
            'email' => $email,
            'name' => $name,
            'id' => $user['id']
        ];
        redirect('/complete-registration');
    }
} else {
    // New User
    $_SESSION['temp_google_user'] = [
        'googleId' => $googleId,
        'email' => $email,
        'name' => $name
    ];
    redirect('/complete-registration');
}
