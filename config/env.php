<?php
/**
 * ============================================================
 *  Eduroad - Centralized Environment (.env) Configuration Loader
 *  File: config/env.php
 * ============================================================
 * Provides a single, centralized loader for the .env file.
 * Automatically discovers .env at the project root or one directory above (production server root).
 */

if (!function_exists('loadEnv')) {
    /**
     * Loads and parses the .env file once into a static cache.
     * Searches multiple potential locations (project root, server root, etc.).
     */
    function loadEnv(): array {
        static $envCache = null;
        if ($envCache !== null) {
            return $envCache;
        }

        $envCache = [];

        // قائمة المسارات المحتملة لملف .env (الجذر الحالي، أو المجلد الأب خارج الويب على السيرفر)
        $candidates = [
            dirname(__DIR__) . '/.env',                            // جذر المشروع (Default Root)
            dirname(__DIR__, 2) . '/.env',                         // مجلد السيرفر الأب (Production Server Root)
            rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/.env' // Document root إذا كان مختلفاً
        ];

        $envFile = null;
        foreach ($candidates as $candidate) {
            if (!empty($candidate) && is_file($candidate) && is_readable($candidate)) {
                $envFile = $candidate;
                break;
            }
        }

        if ($envFile === null) {
            return $envCache;
        }

        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $envCache;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // تجاهل التعليقات والأسطر الفارغة
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // تنظيف علامات الاقتباس المحيطة بالقيم (" أو ')
                if ((strpos($val, '"') === 0 && substr($val, -1) === '"') ||
                    (strpos($val, "'") === 0 && substr($val, -1) === "'")) {
                    $val = substr($val, 1, -1);
                }

                $envCache[$key] = $val;

                // مزامنة مع $_ENV و getenv إن لم تكن مسجلة مسبقاً
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $val;
                }
                if (getenv($key) === false) {
                    putenv("{$key}={$val}");
                }
            }
        }

        return $envCache;
    }
}

if (!function_exists('env')) {
    /**
     * Retrieve an environment variable value with an optional fallback default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed {
        $data = loadEnv();
        if (array_key_exists($key, $data)) {
            $val = $data[$key];
            if (is_string($val)) {
                $lower = strtolower($val);
                if ($lower === 'true' || $lower === '(true)') return true;
                if ($lower === 'false' || $lower === '(false)') return false;
                if ($lower === 'null' || $lower === '(null)') return null;
                if ($lower === 'empty' || $lower === '(empty)') return '';
            }
            return $val;
        }

        $serverVal = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
        if ($serverVal !== false && $serverVal !== null) {
            return $serverVal;
        }

        return $default;
    }
}

