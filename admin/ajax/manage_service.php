<?php
/**
 * Admin AJAX: Manage services (toggle / save / delete)
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
    echo json_encode(['success' => (bool)$ok, 'active' => $new_status]);
    exit;
}

/* ── SAVE (add / edit) ── */
if ($action === 'save') {
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $icon   = trim($_POST['icon'] ?? '🔬');
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if (!$name) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'يرجى إدخال اسم الخدمة']);
        exit;
    }

    try {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE services SET name = ?, icon = ?, is_active = ? WHERE id = ?");
            $ok   = $stmt->execute([$name, $icon, $active, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO services (name, icon, is_active) VALUES (?, ?, ?)");
            $ok   = $stmt->execute([$name, $icon, $active]);
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
        echo json_encode(['success' => false, 'message' => 'معرف الخدمة غير صالح']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
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
