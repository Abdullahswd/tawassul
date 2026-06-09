<?php
/**
 * manage_academic.php
 * معالجة طلبات AJAX لإدارة الأكاديميين (إضافة، تعديل، حذف، تغيير الحالة)
 */

// إظهار الأخطاء للمساعدة في التصحيح (يمكن إزالة هذين السطرين بعد التأكد)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    sendError('غير مصرح: يرجى تسجيل الدخول كمدير', 401);
}

require_once __DIR__ . '/../../config/functions.php';

if (!function_exists('db')) {
    sendError('خطأ في التهيئة: دالة db غير موجودة في functions.php', 500);
}

$db = db();
if (!$db) {
    sendError('فشل الاتصال بقاعدة البيانات', 500);
}

$action = trim($_POST['action'] ?? '');

// ========== 1. إضافة أكاديمي جديد ==========
if ($action === 'add') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $degree    = trim($_POST['degree'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');

    if ($name === '' || $email === '') {
        sendError('الاسم والبريد الإلكتروني مطلوبان');
    }

    try {
        // التحقق من عدم وجود بريد مكرر
        $check = $db->prepare("SELECT id FROM academics WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            sendError('البريد الإلكتروني موجود بالفعل');
        }

        $stmt = $db->prepare("INSERT INTO academics (name, email, specialty, degree, bio, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$name, $email, $specialty, $degree, $bio]);

        echo json_encode(['success' => true, 'message' => 'تم إضافة الأكاديمي بنجاح']);
    } catch (PDOException $e) {
        sendError('خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
    }
    exit;
}

// ========== 2. تعديل بيانات أكاديمي ==========
if ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $degree    = trim($_POST['degree'] ?? '');

    if ($id <= 0 || $name === '' || $email === '') {
        sendError('بيانات غير مكتملة: الاسم والبريد الإلكتروني مطلوبان');
    }

    try {
        $check = $db->prepare("SELECT id FROM academics WHERE id = ?");
        $check->execute([$id]);
        if ($check->rowCount() === 0) {
            sendError('الأكاديمي غير موجود', 404);
        }

        $stmt = $db->prepare("UPDATE academics SET name = ?, email = ?, specialty = ?, degree = ? WHERE id = ?");
        $stmt->execute([$name, $email, $specialty, $degree, $id]);
        
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'تم تحديث البيانات بنجاح']);
        } else {
            echo json_encode(['success' => true, 'message' => 'لم تتغير البيانات (البيانات متطابقة)']);
        }
    } catch (PDOException $e) {
        sendError('خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
    }
    exit;
}

// ========== 3. حذف أكاديمي مع التحقق من القيود ==========
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendError('معرف الأكاديمي غير صالح');
    }

    try {
        // فحص المحادثات والطلبات المرتبطة
        $convStmt = $db->prepare("SELECT COUNT(*) FROM conversations WHERE academic_id = ?");
        $convStmt->execute([$id]);
        $convCount = (int)$convStmt->fetchColumn();

        $ordersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE academic_id = ?");
        $ordersStmt->execute([$id]);
        $ordersCount = (int)$ordersStmt->fetchColumn();

        if ($convCount > 0 || $ordersCount > 0) {
            $messages = [];
            if ($convCount > 0) $messages[] = "$convCount محادثة";
            if ($ordersCount > 0) $messages[] = "$ordersCount طلب";
            $details = implode(' و ', $messages);
            sendError("لا يمكن حذف هذا الأكاديمي لأنه مرتبط بـ: $details. قم بحذف هذه البيانات أولاً أو قم بتعطيل الأكاديمي بدلاً من الحذف.", 409);
        }

        $check = $db->prepare("SELECT id FROM academics WHERE id = ?");
        $check->execute([$id]);
        if ($check->rowCount() === 0) {
            sendError('الأكاديمي غير موجود', 404);
        }

        $stmt = $db->prepare("DELETE FROM academics WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف الأكاديمي بنجاح']);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1451) {
            sendError('لا يمكن حذف الأكاديمي لأنه مرتبط ببيانات أخرى (محادثات أو طلبات).', 409);
        } else {
            sendError('خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
        }
    }
    exit;
}

// ========== 4. تغيير حالة الأكاديمي ==========
if ($action === 'update_status') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($id <= 0 || !in_array($status, ['approved', 'rejected', 'pending'])) {
        sendError('بيانات غير صالحة: معرف أو حالة غير صحيحة');
    }

    try {
        $check = $db->prepare("SELECT id FROM academics WHERE id = ?");
        $check->execute([$id]);
        if ($check->rowCount() === 0) {
            sendError('الأكاديمي غير موجود', 404);
        }

        $stmt = $db->prepare("UPDATE academics SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($stmt->rowCount() > 0) {
            $msg = ($status === 'approved') ? 'تم قبول الأكاديمي' : (($status === 'rejected') ? 'تم رفض الأكاديمي' : 'تم تغيير الحالة إلى قيد المراجعة');
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => true, 'message' => 'الحالة مطابقة بالفعل']);
        }
    } catch (PDOException $e) {
        sendError('خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
    }
    exit;
}

sendError('إجراء غير معروف: ' . htmlspecialchars($action), 400);
?>