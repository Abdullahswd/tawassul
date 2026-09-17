<?php
/** Admin AJAX: manage featured services (add / toggle / delete / move)
 * File: admin/ajax/manage_featured_service.php
 * Table: featured_services (id, service_id UNIQUE, badge, sort_order, is_active, created_at)
 */
// إخفاء أي تحذيرات/أخطاء عن المخرجات حتى تبقى الاستجابة JSON نقية
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
if (ob_get_level()) { while (ob_get_level() > 0) @ob_end_clean(); }
ob_start();

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

@header('Content-Type: application/json; charset=utf-8');

// وضع تشخيص: ?debug=1 يعرض حالة الجلسة والجدول (للتشخيص فقط)
if (isset($_GET['debug'])) {
    if (ob_get_length()) ob_clean();
    $info = [
        'session_user_id' => $_SESSION['user_id'] ?? null,
        'session_role'    => $_SESSION['user_role'] ?? null,
        'post'            => $_POST,
    ];
    try {
        $db = db();
        $info['table_exists'] = (bool)$db->query("SHOW TABLES LIKE 'featured_services'")->fetch();
        $info['db_ok'] = true;
    } catch (Throwable $e) { $info['db_ok'] = false; $info['db_error'] = $e->getMessage(); }
    echo json_encode(['success' => true, 'debug' => $info], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (ob_get_length()) ob_clean(); else @ob_end_clean();
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();

// تأمين: إنشاء الجدول إن لم يكن موجوداً (نفس كود صفحة services.php)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS featured_services (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        service_id INT UNSIGNED NOT NULL,
        badge VARCHAR(100) DEFAULT NULL COMMENT 'شارة مميزة مثل: الأكثر طلباً',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_service (service_id),
        KEY idx_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) { /* تجاهل */ }

$action = trim($_POST['action'] ?? '');

/* ── ADD / SAVE ── */
if ($action === 'save' || $action === 'add') {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $badge      = trim($_POST['badge'] ?? '');
    // حد الشارة 100 حرف حسب تعريف العمود
    if (function_exists('mb_strlen') && mb_strlen($badge, 'UTF-8') > 100) $badge = mb_substr($badge, 0, 100, 'UTF-8');
    elseif (strlen($badge) > 100) $badge = substr($badge, 0, 100);
    $active     = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    if ($service_id <= 0) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'اختر الخدمة أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        // تحقق أن الخدمة موجودة
        $chk = $db->prepare("SELECT id FROM services WHERE id = ? LIMIT 1");
        $chk->execute([$service_id]);
        if (!$chk->fetch()) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'الخدمة غير موجودة'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // هل مضافة مسبقاً؟
        $chk2 = $db->prepare("SELECT id FROM featured_services WHERE service_id = ? LIMIT 1");
        $chk2->execute([$service_id]);
        $exists = $chk2->fetch();
        if ($exists) {
            $up = $db->prepare("UPDATE featured_services SET badge = ?, is_active = ? WHERE service_id = ?");
            $up->execute([$badge !== '' ? $badge : null, $active, $service_id]);
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => true, 'updated' => true, 'id' => (int)$exists['id']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $max = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM featured_services")->fetchColumn();
        $ins = $db->prepare("INSERT INTO featured_services (service_id, badge, sort_order, is_active) VALUES (?,?,?,?)");
        $ins->execute([$service_id, $badge !== '' ? $badge : null, $max + 1, $active]);
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('manage_featured_service save failed: ' . $e->getMessage());
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ── TOGGLE ── */
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { if (ob_get_length()) ob_clean(); echo json_encode(['success'=>false,'message'=>'معرف غير صالح'], JSON_UNESCAPED_UNICODE); exit; }
    $st = $db->prepare("SELECT is_active FROM featured_services WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { if (ob_get_length()) ob_clean(); echo json_encode(['success'=>false,'message'=>'غير موجود'], JSON_UNESCAPED_UNICODE); exit; }
    $new = $row['is_active'] ? 0 : 1;
    $db->prepare("UPDATE featured_services SET is_active = ? WHERE id = ?")->execute([$new, $id]);
    if (ob_get_length()) ob_clean();
    echo json_encode(['success'=>true,'active'=>(bool)$new], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── MOVE (up/down) ── */
if ($action === 'move') {
    $id = (int)($_POST['id'] ?? 0);
    $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
    if (!$id) { if (ob_get_length()) ob_clean(); echo json_encode(['success'=>false,'message'=>'معرف غير صالح'], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $all = $db->query("SELECT id FROM featured_services ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $pos = array_search((string)$id, array_map('strval', $all), true);
        if ($pos === false) { if (ob_get_length()) ob_clean(); echo json_encode(['success'=>false,'message'=>'غير موجود'], JSON_UNESCAPED_UNICODE); exit; }
        $pos = (int)$pos;
        $newPos = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($newPos < 0 || $newPos >= count($all)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success'=>true,'moved'=>false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $tmp = $all[$pos]; $all[$pos] = $all[$newPos]; $all[$newPos] = $tmp;
        $up = $db->prepare("UPDATE featured_services SET sort_order = ? WHERE id = ?");
        foreach ($all as $i => $fid) { $up->execute([$i+1, (int)$fid]); }
        if (ob_get_length()) ob_clean();
        echo json_encode(['success'=>true,'moved'=>true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ── DELETE ── */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { if (ob_get_length()) ob_clean(); echo json_encode(['success'=>false,'message'=>'معرف غير صالح'], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $db->prepare("DELETE FROM featured_services WHERE id = ?")->execute([$id]);
        if (ob_get_length()) ob_clean();
        echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (ob_get_length()) ob_clean();
echo json_encode(['success' => false, 'message' => 'إجراء غير صالح'], JSON_UNESCAPED_UNICODE);