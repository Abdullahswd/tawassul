<?php
/**
 * Admin AJAX: Manage packages (toggle / save / delete)
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

// التأكد من وجود أعمدة icon و original_price و service_ids في جدول packages
try {
    $db->exec("ALTER TABLE packages ADD COLUMN icon VARCHAR(20) NOT NULL DEFAULT '📦'");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE packages ADD COLUMN original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE packages ADD COLUMN service_ids TEXT DEFAULT NULL");
} catch (Exception $e) {}

$action = trim($_POST['action'] ?? '');

/* ── TOGGLE ── */
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف الباقة غير صالح']);
        exit;
    }

    $stmt = $db->prepare("SELECT is_active FROM packages WHERE id = ?");
    $stmt->execute([$id]);
    $package = $stmt->fetch();

    if (!$package) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'الباقة غير موجودة']);
        exit;
    }

    $new_status = $package['is_active'] ? 0 : 1;
    $update = $db->prepare("UPDATE packages SET is_active = ? WHERE id = ?");
    $ok = $update->execute([$new_status, $id]);

    ob_end_clean();
    echo json_encode(['success' => (bool)$ok, 'active' => $new_status]);
    exit;
}

/* ── SAVE (add / edit) ── */
if ($action === 'save') {
    $id             = (int)($_POST['id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $icon           = trim($_POST['icon'] ?? '📦');
    $price          = (float)($_POST['price'] ?? 0);
    $original_price = (float)($_POST['original_price'] ?? 0);
    $color          = trim($_POST['color'] ?? '#6366f1');
    $active         = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $service_ids_raw = $_POST['service_ids'] ?? '[]';
    $features_raw   = $_POST['features'] ?? '';

    // معالجة معرّفات الخدمات
    if (is_array($service_ids_raw)) {
        $service_ids_arr = array_map('intval', $service_ids_raw);
    } else {
        $decoded = json_decode($service_ids_raw, true);
        $service_ids_arr = is_array($decoded) ? array_map('intval', $decoded) : [];
    }
    $service_ids_json = json_encode(array_values($service_ids_arr));

    // معالجة النشرة/المميزات
    if (is_array($features_raw)) {
        $features_arr = $features_raw;
    } else {
        $features_arr = array_filter(array_map('trim', explode("\n", $features_raw)));
    }
    $features_json = json_encode(array_values($features_arr), JSON_UNESCAPED_UNICODE);

    if (!$name || $price <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'يرجى إدخال اسم الباقة وسعرها']);
        exit;
    }

    try {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE packages SET name = ?, icon = ?, price = ?, original_price = ?, color = ?, service_ids = ?, features_json = ?, is_active = ? WHERE id = ?");
            $ok   = $stmt->execute([$name, $icon, $price, $original_price, $color, $service_ids_json, $features_json, $active, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO packages (name, icon, price, original_price, color, service_ids, features_json, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ok   = $stmt->execute([$name, $icon, $price, $original_price, $color, $service_ids_json, $features_json, $active]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $db->lastInsertId()]);
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
        echo json_encode(['success' => false, 'message' => 'معرف الباقة غير صالح']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
        $ok   = $stmt->execute([$id]);
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
