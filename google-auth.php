<?php
/**
 * google-auth.php — Redirects to Google Login
 */
require_once __DIR__ . '/config/google_oauth.php';

$url = getGoogleLoginUrl();
header('Location: ' . $url);
exit;
