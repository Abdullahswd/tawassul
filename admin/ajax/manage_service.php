<?php
/**
 * Admin AJAX: Manage services (toggle / save / delete) with hierarchical support
 */
ob_start();

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Auth check – return JSON instead of redirect for AJAX
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db     = db();

// التأكد من وجود عمود price في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

$action = trim($_POST['action'] ?? '');

/* ── TOGGLE ── */
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف الخدمة غير صالح']);
        exit;
    }

    $stmt = $db->prepare("SELECT is_active FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();

    if (!$service) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'الخدمة غير موجودة']);
        exit;
    }

    $new_status = $service['is_active'] ? 0 : 1;
    $update = $db->prepare("UPDATE services SET is_active = ? WHERE id = ?");
    $ok = $update->execute([$new_status, $id]);

    ob_end_clean();
    echo json_encode(['success' => (bool)$ok, 'active' => (bool)$new_status]);
    exit;
}

/* ── SAVE (add / edit) ── */
if ($action === 'save') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $icon     = trim($_POST['icon'] ?? '📚');
    $active   = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $price    = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;

    if (!$name) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'يرجى إدخال اسم الخدمة']);
        exit;
    }

    try {
        // حساب المستوى (level)
        if ($parentId) {
            $stmt = $db->prepare("SELECT level FROM services WHERE id = ?");
            $stmt->execute([$parentId]);
            $parentLevel = $stmt->fetchColumn();
            if ($parentLevel === false) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'الخدمة الأب غير موجودة']);
                exit;
            }
            $level = $parentLevel + 1;
            if ($level > 3) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'لا يمكن إضافة خدمة في مستوى رابع (الحد الأقصى 3 مستويات)']);
                exit;
            }
        } else {
            $level = 1;
        }

        // إتاحة السعر فقط للمستوى 3
        if ($level !== 3) {
            $price = 0.00;
        }

        // تعيين sort_order: نأخذ أكبر قيمة حالية لنفس الأب +1
        if ($parentId) {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM services WHERE parent_id = ?");
            $stmt->execute([$parentId]);
        } else {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM services WHERE parent_id IS NULL");
            $stmt->execute();
        }
        $maxOrder = (int)$stmt->fetchColumn();
        $sortOrder = $maxOrder + 1;

        if ($id > 0) {
            // تحديث: نمنع جعل الخدمة أباً لنفسها
            if ($parentId && $parentId == $id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'لا يمكن جعل الخدمة أباً لنفسها']);
                exit;
            }
            $stmt = $db->prepare("UPDATE services SET name = ?, icon = ?, price = ?, is_active = ?, parent_id = ?, level = ?, sort_order = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $icon, $price, $active, $parentId, $level, $sortOrder, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id, 'level' => $level]);
        } else {
            // إضافة جديدة
            $stmt = $db->prepare("INSERT INTO services (parent_id, name, icon, price, is_active, level, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$parentId, $name, $icon, $price, $active, $level, $sortOrder]);
            $newId = $db->lastInsertId();
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $newId, 'level' => $level]);
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ── DELETE ── */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف الخدمة غير صالح']);
        exit;
    }

    try {
        // الحذف التتالي مفعّل عبر ON DELETE CASCADE في قاعدة البيانات
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $ok = $stmt->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => (bool)$ok]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

ob_end_clean();
echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);