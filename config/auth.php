<?php
/**
 * ============================================================
 *  Tawassul - Authentication & Session Management
 *  File: config/auth.php
 * ============================================================
 */

require_once __DIR__ . '/db.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        header('Location: ' . rootUrl() . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require the user to be a logged-in admin.
 */
function requireAdmin(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ' . rootUrl() . '/login.php');
        exit;
    }
}

/**
 * Require the user to be a logged-in academic.
 */
function requireAcademic(): void {
    if (!isset($_SESSION['academic_id'])) {
        header('Location: ' . rootUrl() . '/academics/academic-register.php');
        exit;
    }
}

/**
 * Log in a user (student or admin) and set session.
 */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_avatar']= $user['avatar_initials'] ?? substr($user['name'], 0, 2);
}

/**
 * Log in an academic and set session.
 */
function loginAcademic(array $academic): void {
    session_regenerate_id(true);
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
    session_unset();
    session_destroy();
    header('Location: ' . rootUrl() . '/login.php');
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
 * Get root URL for redirects.
 */
function rootUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/tawassul';
}
