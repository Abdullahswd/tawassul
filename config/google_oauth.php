<?php
/**
 * config/google_oauth.php
 * Handles Google OAuth2 Logic
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

// Read credentials from centralized env()
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));

/**
 * Returns Google OAuth Redirect URI.
 * Reads from .env (GOOGLE_REDIRECT_URI). If empty or not set, dynamically falls back
 * to the current environment URL (rootUrl() . '/google-callback.php').
 */
function getGoogleRedirectUri(): string
{
    $uri = env('GOOGLE_REDIRECT_URI', '');
    if (!empty($uri)) {
        return $uri;
    }

    if (function_exists('rootUrl')) {
        return rootUrl() . '/google-callback.php';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . '/google-callback.php';
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getGoogleRedirectUri());
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
