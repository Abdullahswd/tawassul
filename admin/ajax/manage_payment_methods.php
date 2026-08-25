<?php
/**
 * ============================================================
 *  Tawassul - Admin Payment Methods AJAX Handler
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

$action = trim($_POST['action'] ?? '');

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

    if (!in_array($accountType, ['bank', 'wallet'], true)) $accountType = 'bank';
    if (!$accountName || !$accountNumber) {
        sendError('يرجى إدخال اسم البنك أو المحفظة والرقم.');
    }

    try {
        if ($isEdit) {
            $check = $db->prepare("SELECT id FROM platform_payment_methods WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) sendError('طريقة الدفع غير موجودة.');
            $db->prepare(
                "UPDATE platform_payment_methods SET account_type=?, account_name=?, account_number=?, holder_name=?, is_active=?, sort_order=? WHERE id=?"
            )->execute([$accountType, $accountName, $accountNumber, $holderName, $isActive, $sortOrder, $id]);
            sendJSON(true, 'تم تحديث طريقة الدفع بنجاح.');
        } else {
            $nextSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM platform_payment_methods")->fetchColumn();
            $db->prepare(
                "INSERT INTO platform_payment_methods (account_type, account_name, account_number, holder_name, is_active, sort_order) VALUES (?,?,?,?,?,?)"
            )->execute([$accountType, $accountName, $accountNumber, $holderName, $isActive, $sortOrder ?: $nextSort]);
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