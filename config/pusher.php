<?php
/**
 * ============================================================
 *  Eduroad - Simple Pusher Client
 *  File: config/pusher.php
 * ============================================================
 */

// NOTE: Replace these with your actual Pusher credentials from pusher.com
define('PUSHER_APP_ID', '1800000'); // Example ID
define('PUSHER_KEY', 'YOUR_PUSHER_KEY'); 
define('PUSHER_SECRET', 'YOUR_PUSHER_SECRET');
define('PUSHER_CLUSTER', 'eu');

/**
 * Trigger a Pusher event using cURL (No external libraries required)
 */
function triggerPusherEvent(string $channel, string $event, array $data): bool {
    if (!PUSHER_KEY || PUSHER_KEY === 'YOUR_PUSHER_KEY') return false; // Skip if not configured

    $host = 'api-' . PUSHER_CLUSTER . '.pusher.com';
    $path = '/apps/' . PUSHER_APP_ID . '/events';
    
    // Pusher expects 'data' to be a string (usually JSON stringified)
    $payload = json_encode([
        'name' => $event,
        'channels' => [$channel],
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
    ]);

    $auth_timestamp = time();
    $auth_version = '1.0';
    $body_md5 = md5($payload);
    
    $auth_query = "auth_key=" . PUSHER_KEY .
                  "&auth_timestamp=" . $auth_timestamp .
                  "&auth_version=" . $auth_version .
                  "&body_md5=" . $body_md5;

    $string_to_sign = "POST\n" . $path . "\n" . $auth_query;
    $auth_signature = hash_hmac('sha256', $string_to_sign, PUSHER_SECRET);
    
    $url = "https://" . $host . $path . "?" . $auth_query . "&auth_signature=" . $auth_signature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Do not block for too long
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}
