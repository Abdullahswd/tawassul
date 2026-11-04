<?php
/**
 * ============================================================
 *  Eduroad - Authentication & Session Management
 *  File: config/auth.php
 * ============================================================
 */

require_once __DIR__ . '/db.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // يجب ضبط cache limiter قبل بدء الجلسة وإلا يتجاهل PHP الترويسات اليدوية
    // ويطلق تحذير session_cache_limiter مع أي header() لاحق
    session_cache_limiter('nocache');
    session_start();
}

require_once __DIR__ . '/middleware.php';

// Prevent browser caching for authenticated pages
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

/* ─────────────────────────────────────────────
   SESSION HELPERS
───────────────────────────────────────────── */

/**
 * Check if any user (student, admin, or academic) is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) || isset($_SESSION['academic_id']);
}

/**
 * Require the user to be a logged-in student.
 * Redirects to login if not.
 */
function requireStudent(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        if (!empty($_SESSION['pending_2fa']) && ($_SESSION['pending_2fa']['user_type'] ?? '') === 'student') {
            header('Location: ' . rootUrl() . '/verify-otp.php');
            exit;
        }
        header('Location: ' . rootUrl() . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require the user to be a logged-in admin.
 */
function requireAdmin(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ' . rootUrl() . '/admin/');
        exit;
    }
}

/**
 * Require the user to be a logged-in academic.
 */
function requireAcademic(): void {
    if (!isset($_SESSION['academic_id'])) {
        if (!empty($_SESSION['pending_2fa']) && ($_SESSION['pending_2fa']['user_type'] ?? '') === 'academic') {
            header('Location: ' . rootUrl() . '/verify-otp.php');
            exit;
        }
        header('Location: ' . rootUrl() . '/login.php?type=academic&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Check if academic status has been suspended or rejected
    $acId = (int)$_SESSION['academic_id'];
    $stmt = db()->prepare('SELECT status FROM academics WHERE id = ? LIMIT 1');
    $stmt->execute([$acId]);
    $currStatus = $stmt->fetchColumn();

    if ($currStatus === 'suspended') {
        unset($_SESSION['academic_id'], $_SESSION['academic_name'], $_SESSION['academic_email'], $_SESSION['academic_status'], $_SESSION['academic_avatar']);
        header('Location: ' . rootUrl() . '/login.php?error=' . urlencode('تم إيقاف / تجميد حسابك الأكاديمي من قِبل الإدارة. يرجى مراجعة إدارة المنصة.'));
        exit;
    } elseif ($currStatus === 'rejected') {
        unset($_SESSION['academic_id'], $_SESSION['academic_name'], $_SESSION['academic_email'], $_SESSION['academic_status'], $_SESSION['academic_avatar']);
        header('Location: ' . rootUrl() . '/login.php?error=' . urlencode('تم رفض حسابك من قِبل الإدارة. للاستفسار تواصل معنا.'));
        exit;
    }
}

/**
 * Log in a user (student or admin) and set session.
 */
function loginUser(array $user): void {
    session_regenerate_id(true);
    // Unset academic session to prevent cross-role contamination
    unset($_SESSION['academic_id'], $_SESSION['academic_name'], $_SESSION['academic_email'], $_SESSION['academic_status'], $_SESSION['academic_avatar']);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_avatar']= $user['avatar_initials'] ?? substr($user['name'], 0, 2);
    $_SESSION['user_avatar_path'] = $user['avatar_path'] ?? null;
}

/**
 * Log in an academic and set session.
 */
function loginAcademic(array $academic): void {
    session_regenerate_id(true);
    // Unset user session to prevent cross-role contamination
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_avatar'], $_SESSION['user_avatar_path']);

    $_SESSION['academic_id']     = $academic['id'];
    $_SESSION['academic_name']   = $academic['name'];
    $_SESSION['academic_email']  = $academic['email'];
    $_SESSION['academic_status'] = $academic['status'];
    $_SESSION['academic_avatar'] = $academic['avatar_initials'] ?? substr($academic['name'], 0, 2);
}

/**
 * Log out any currently logged-in user.
 */
function logout(): void {
    $isAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
    session_unset();
    session_destroy();
    if ($isAdmin) {
        header('Location: ' . rootUrl() . '/admin/');
    } else {
        header('Location: ' . rootUrl() . '/login.php');
    }
    exit;
}

/**
 * Get the current logged-in user's data (from session).
 */
function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'name'   => $_SESSION['user_name'],
        'email'  => $_SESSION['user_email'],
        'role'   => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'],
        'avatar_path' => $_SESSION['user_avatar_path'] ?? null,
    ];
}

/**
 * Get the current logged-in academic's data (from session).
 */
function currentAcademic(): ?array {
    if (!isset($_SESSION['academic_id'])) return null;
    return [
        'id'     => $_SESSION['academic_id'],
        'name'   => $_SESSION['academic_name'],
        'email'  => $_SESSION['academic_email'],
        'status' => $_SESSION['academic_status'],
        'avatar' => $_SESSION['academic_avatar'],
    ];
}

/**
 * Get root URL for redirects (production safe)
 */
function rootUrl(): string {
    $appUrl = env('APP_URL', '');
    if (!empty($appUrl)) {
        return rtrim($appUrl, '/');
    }

    $protocol = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // حساب مسار التطبيق (المجلد الفرعي) تلقائياً، مثلاً /eduroad عند العمل عبر XAMPP
    $base = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT'])
        : '';
    if ($docRoot !== '') {
        $appDir = str_replace('\\', '/', dirname(__DIR__)); // جذر المشروع (المجلد الحاوي على config/auth.php)
        if (strpos($appDir, $docRoot) === 0) {
            $base = substr($appDir, strlen($docRoot));
        }
    }

    return rtrim($protocol . '://' . $host . $base, '/');
}
