<?php
/**
 * logout.php — Destroy session and redirect to login
 */
require_once __DIR__ . '/config/auth.php';
logout(); // defined in auth.php — destroys session + redirects
