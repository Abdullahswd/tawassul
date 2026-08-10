<?php
/**
 * ============================================================
 *  Tawassul - Database Configuration
 *  File: config/db.php
 * ============================================================
 */

define('DB_HOST',     '76.13.36.168');
define('DB_NAME',     'tawassul');
define('DB_USER',     'tawassul');     
define('DB_PASS',     'Tawassul');          
define('DB_CHARSET',  'utf8mb4');

/**
 * Get PDO connection (singleton pattern)
 */
function db(): PDO {
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
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In production: log the error, don't expose details
            http_response_code(500);
            die(json_encode([
                'error'   => true,
                'message' => 'خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.'
            ]));
        }
    }

    return $pdo;
}
