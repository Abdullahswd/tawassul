<?php
/**
 * manage_user.php
 * معالجة AJAX للطلاب (إضافة، تعديل، حذف، تغيير الحالة)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) session_start();

// دالة إرجاع خطأ
function sendError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// التحقق من الصلاحية (يمكن تفعيله بعد التأكد من تسجيل الدخول)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') sendError('غير مصرح', 401);

// تحميل دوال قاعدة البيانات
require_once __DIR__ . '/../../config/functions.php';
if (!function_exists('db')) sendError('دالة db غير موجودة', 500);
$db = db();
if (!$db) sendError('فشل الاتصال بقاعدة البيانات', 500);

// الحصول على الإجراء
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// ------------------------------
// 1. إضافة طالب جديد
// ------------------------------
if ($action === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name)) sendError('الاسم مطلوب');
    if (empty($email)) sendError('البريد الإلكتروني مطلوب');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendError('بريد إلكتروني غير صالح');
    if (empty($password)) sendError('كلمة المرور مطلوبة');

    try {
        // فحص البريد المكرر
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) sendError('البريد الإلكتروني موجود بالفعل');

        // تحضير البيانات
        $initials = mb_substr($name, 0, 1, 'UTF-8');
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, email, phone, password, role, status, avatar_initials, created_at) 
                VALUES (?, ?, ?, ?, 'student', 'active', ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$name, $email, $phone, $hashed, $initials]);

        echo json_encode(['success' => true, 'message' => 'تم إضافة الطالب بنجاح']);
    } catch (PDOException $e) {
        sendError('خطأ في الإضافة: ' . $e->getMessage(), 500);
    }
    exit;
}

// ------------------------------
// 2. تعديل طالب
// ------------------------------
if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($id <= 0) sendError('معرف غير صالح');
    if (empty($name)) sendError('الاسم مطلوب');
    if (empty($email)) sendError('البريد مطلوب');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendError('بريد غير صالح');

    try {
        // التأكد من وجود الطالب
        $check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $check->execute([$id]);
        if ($check->rowCount() === 0) sendError('الطالب غير موجود', 404);

        $initials = mb_substr($name, 0, 1, 'UTF-8');
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET name=?, email=?, phone=?, password=?, avatar_initials=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $hashed, $initials, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, email=?, phone=?, avatar_initials=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $initials, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'تم تحديث البيانات']);
    } catch (PDOException $e) {
        sendError('خطأ في التحديث: ' . $e->getMessage(), 500);
    }
    exit;
}

// ------------------------------
// 3. حذف طالب
// ------------------------------
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) sendError('معرف غير صالح');

    try {
        // التأكد من عدم وجود طلبات مرتبطة
        $orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE student_id = ?");
        $orders->execute([$id]);
        if ($orders->fetchColumn() > 0) {
            sendError('لا يمكن حذف الطالب لأن لديه طلبات', 409);
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'تم حذف الطالب']);
    } catch (PDOException $e) {
        sendError('خطأ في الحذف: ' . $e->getMessage(), 500);
    }
    exit;
}

// ------------------------------
// 4. تغيير الحالة (تفعيل/تعليق)
// ------------------------------
if ($action === 'update_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id <= 0) sendError('معرف غير صالح');
    if (!in_array($status, ['active', 'suspended'])) sendError('حالة غير صالحة');
    try {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
        $stmt->execute([$status, $id]);
        $msg = ($status === 'active') ? 'تم تفعيل الحساب' : 'تم تعليق الحساب';
        echo json_encode(['success' => true, 'message' => $msg]);
    } catch (PDOException $e) {
        sendError('خطأ: ' . $e->getMessage(), 500);
    }
    exit;
}

// إذا وصلنا هنا فالإجراء غير معروف
sendError('إجراء غير معروف: ' . $action);
?>