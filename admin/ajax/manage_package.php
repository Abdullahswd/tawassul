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
    $id           = (int)($_POST['id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $price        = (float)($_POST['price'] ?? 0);
    $color        = trim($_POST['color'] ?? '#6366f1');
    $active       = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $features_raw = $_POST['features'] ?? '';

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
            $stmt = $db->prepare("UPDATE packages SET name = ?, price = ?, color = ?, features_json = ?, is_active = ? WHERE id = ?");
            $ok   = $stmt->execute([$name, $price, $color, $features_json, $active, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO packages (name, price, color, features_json, is_active) VALUES (?, ?, ?, ?, ?)");
            $ok   = $stmt->execute([$name, $price, $color, $features_json, $active]);
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
