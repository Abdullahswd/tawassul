<?php

require_once __DIR__ . '/env.php';

if (!defined('DB_HOST'))
    define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_NAME'))
    define('DB_NAME', env('DB_NAME', 'acadimic'));
if (!defined('DB_USER'))
    define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS'))
    define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

/**
 * Get PDO connection (singleton pattern)
 */
if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // In production: log the error, don't expose details
                http_response_code(500);
                die(json_encode([
                    'error' => true,
                    'message' => 'خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.'
                ]));
            }
        }

        return $pdo;
    }
}
