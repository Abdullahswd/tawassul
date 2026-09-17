<?php
/**
 * ============================================================
 *  Eduroad - Admin Payment Methods AJAX Handler
 *  Manages the platform's bank accounts / wallets (طرق الدفع)
 * ============================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

function sendError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Admin auth
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    sendError('غير مصرح', 401);
}

require_once __DIR__ . '/../../config/functions.php';
if (!function_exists('db')) sendError('دالة db غير موجودة', 500);
$db = db();
if (!$db) sendError('فشل الاتصال بقاعدة البيانات', 500);

// التأكد من وجود عمود logo (شعار البنك/المحفظة)
try {
    $db->exec("ALTER TABLE platform_payment_methods ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

$action = trim($_POST['action'] ?? '');

/* ─────────────────────────────────────────────
   UPLOAD LOGO (شعار البنك أو المحفظة)
   ───────────────────────────────────────────── */
if ($action === 'upload_logo') {
    if (empty($_FILES['image'])) sendError('لم يتم استلام الصورة.');
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMsg = [
            1 => 'حجم الصورة يتجاوز حد الخادم (upload_max_filesize)',
            2 => 'حجم الصورة يتجاوز الحد المسموح في النموذج',
            3 => 'تم رفع جزء فقط من الصورة',
            4 => 'لم يتم تحديد صورة',
            6 => 'مجلد المؤقتات غير متوفر على الخادم',
            7 => 'تعذر كتابة الصورة على القرص',
            8 => 'إضافة PHP ملف مرفوض من السيرفر',
        ][$file['error']] ?? 'خطأ في استلام الصورة';
        sendError($errMsg);
    }

    $tmpPath = $file['tmp_name'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) sendError('صيغة الصورة غير مدعومة (JPG/PNG/GIF/WEBP/SVG).');

    $isRealImage = false;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$tmpPath);
        $isRealImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
    } else {
        $info = @getimagesize($tmpPath);
        $isRealImage = is_array($info) && !empty($info['mime'])
            && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
    if (!$isRealImage) sendError('الملف ليس صورة صالحة.');

    $uploadDir = dirname(__DIR__) . '/../uploads/payment_methods';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) sendError('مجلد رفع الصور (uploads/payment_methods) غير قابل للكتابة.');

    $fileName = 'pm_logo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dest = rtrim($uploadDir, '/\\') . '/' . $fileName;
    if (!move_uploaded_file($tmpPath, $dest)) sendError('فشل حفظ الصورة على الخادم.');

    echo json_encode(['success' => true, 'image' => 'uploads/payment_methods/' . $fileName]);
    exit;
}

/* ─────────────────────────────────────────────
   GET ALL METHODS
   ───────────────────────────────────────────── */
if ($action === 'get_methods') {
    $stmt = $db->query("SELECT * FROM platform_payment_methods ORDER BY sort_order ASC, id ASC");
    echo json_encode(['success' => true, 'methods' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ─────────────────────────────────────────────
   ADD / UPDATE METHOD
   ───────────────────────────────────────────── */
if ($action === 'add_method' || $action === 'update_method') {
    $isEdit = ($action === 'update_method');
    $id = intval($_POST['id'] ?? 0);
    $accountType = trim($_POST['account_type'] ?? 'bank');
    $accountName = trim($_POST['account_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $holderName = trim($_POST['holder_name'] ?? '');
    $isActive = isset($_POST['is_active']) ? (intval($_POST['is_active']) ? 1 : 0) : 1;
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $logo = trim($_POST['logo'] ?? '');
    $logoCleared = isset($_POST['logo_cleared']) ? (intval($_POST['logo_cleared']) ? true : false) : false;

    if (!in_array($accountType, ['bank', 'wallet'], true)) $accountType = 'bank';
    if (!$accountName || !$accountNumber) {
        sendError('يرجى إدخال اسم البنك أو المحفظة والرقم.');
    }

    try {
        if ($isEdit) {
            $check = $db->prepare("SELECT id, logo FROM platform_payment_methods WHERE id = ?");
            $check->execute([$id]);
            $existing = $check->fetch();
            if (!$existing) sendError('طريقة الدفع غير موجودة.');

            // معالجة الشعار: إزالة صريحة / صورة جديدة / الاحتفاظ بالقديمة
            if ($logoCleared) {
                $logo = null;
            } elseif ($logo === '') {
                $logo = $existing['logo'] ?? null;
            }

            $db->prepare(
                "UPDATE platform_payment_methods SET account_type=?, account_name=?, account_number=?, holder_name=?, logo=?, is_active=?, sort_order=? WHERE id=?"
            )->execute([$accountType, $accountName, $accountNumber, $holderName, $logo, $isActive, $sortOrder, $id]);
            sendJSON(true, 'تم تحديث طريقة الدفع بنجاح.');
        } else {
            if ($logoCleared) $logo = null;
            $nextSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM platform_payment_methods")->fetchColumn();
            $db->prepare(
                "INSERT INTO platform_payment_methods (account_type, account_name, account_number, holder_name, logo, is_active, sort_order) VALUES (?,?,?,?,?,?,?)"
            )->execute([$accountType, $accountName, $accountNumber, $holderName, $logo, $isActive, $sortOrder ?: $nextSort]);
            sendJSON(true, 'تمت إضافة طريقة الدفع بنجاح.');
        }
    } catch (Exception $e) {
        sendError('فشل الحفظ: ' . $e->getMessage());
    }
}

/* ─────────────────────────────────────────────
   TOGGLE ACTIVE
   ───────────────────────────────────────────── */
if ($action === 'toggle_method') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) sendError('معرّف غير صالح.');
    try {
        $stmt = $db->prepare("UPDATE platform_payment_methods SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);
        $active = (int)$db->query("SELECT is_active FROM platform_payment_methods WHERE id=$id")->fetchColumn();
        echo json_encode(['success' => true, 'active' => ($active == 1), 'message' => $active ? 'تم تفعيل طريقة الدفع.' : 'تم إيقاف طريقة الدفع.'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        sendError('فشل التحديث: ' . $e->getMessage());
    }
}

/* ─────────────────────────────────────────────
   DELETE METHOD
   ───────────────────────────────────────────── */
if ($action === 'delete_method') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) sendError('معرّف غير صالح.');
    try {
        $db->prepare("DELETE FROM platform_payment_methods WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'تم حذف طريقة الدفع.'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        sendError('فشل الحذف: ' . $e->getMessage());
    }
}

sendError('إجراء غير معروف');

function sendJSON($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}