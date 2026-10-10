<?php
/**
 * config/google_oauth.php
 * Handles Google OAuth2 Logic
 */

// Minimal .env parser
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

// Read from .env
define('GOOGLE_CLIENT_ID', $env['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $env['GOOGLE_CLIENT_SECRET'] ?? '');

// Redirect URI matches the callback file location
// If you face redirect_uri_mismatch, make sure this exactly matches what is in Google Console
define('GOOGLE_REDIRECT_URI', 'https://academics.tawassu.com/google-callback.php');

function getGoogleRedirectUri(): string
{
    return GOOGLE_REDIRECT_URI;
}

function getGoogleLoginUrl(): string
{
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => getGoogleRedirectUri(),
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'state' => bin2hex(random_bytes(16))
    ];

    // Save state to session to verify in callback
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['google_oauth_state'] = $params['state'];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function fetchGoogleAccessToken(string $code): ?array
{
    $url = 'https://oauth2.googleapis.com/token';
    $postData = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => getGoogleRedirectUri(),
        'grant_type' => 'authorization_code',
        'code' => $code
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    }
    return null;
}

function fetchGoogleUserProfile(string $accessToken): ?array
{
    $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    }
    return null;
}
