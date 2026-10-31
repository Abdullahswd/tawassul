<?php
/**
 * Admin AJAX: Manage Colleges, Departments, and Specializations
 * File: admin/ajax/manage_hierarchy.php
 */
ob_start();

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/academic_hierarchy.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure tables exist
ensureHierarchyTables();

// Auth check - return JSON instead of redirect
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول.']);
    exit;
}

$db = db();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function sendResp($success, $message, $extra = []) {
    ob_end_clean();
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 1. GET DATA
// ============================================================
if ($action === 'get_tree') {
    $tree = getHierarchyFullTree(false);
    sendResp(true, 'تم جلب البيانات بنجاح', ['data' => $tree]);
}

if ($action === 'get_departments') {
    $collegeId = (int)($_GET['college_id'] ?? $_POST['college_id'] ?? 0);
    $depts = getDepartmentsByCollege($collegeId, false);
    sendResp(true, 'تم جلب الأقسام بنجاح', ['data' => $depts]);
}

if ($action === 'get_specializations') {
    $deptId = (int)($_GET['department_id'] ?? $_POST['department_id'] ?? 0);
    $specs = getSpecializationsByDepartment($deptId, false);
    sendResp(true, 'تم جلب التخصصات بنجاح', ['data' => $specs]);
}

// ============================================================
// 2. COLLEGES CRUD
// ============================================================
if ($action === 'add_college') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $icon = trim($_POST['icon'] ?? '🏛️');
    $desc = trim($_POST['description'] ?? '');

    if (!$name) {
        sendResp(false, 'يرجى إدخال اسم الكلية.');
    }

    try {
        $stmt = $db->prepare("INSERT INTO colleges (name, code, icon, description, sort_order, is_active) VALUES (?, ?, ?, ?, 0, 1)");
        $stmt->execute([$name, $code ?: null, $icon ?: '🏛️', $desc ?: null]);
        $newId = (int) $db->lastInsertId();
        sendResp(true, 'تمت إضافة الكلية بنجاح.', ['id' => $newId]);
    } catch (Exception $e) {
        sendResp(false, 'فشل إضافة الكلية: ' . $e->getMessage());
    }
}

if ($action === 'edit_college') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $icon = trim($_POST['icon'] ?? '🏛️');
    $desc = trim($_POST['description'] ?? '');

    if (!$id || !$name) {
        sendResp(false, 'بيانات الكلية غير مكتملة.');
    }

    try {
        $stmt = $db->prepare("UPDATE colleges SET name = ?, code = ?, icon = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $code ?: null, $icon ?: '🏛️', $desc ?: null, $id]);
        sendResp(true, 'تم تعديل بيانات الكلية بنجاح.');
    } catch (Exception $e) {
        sendResp(false, 'فشل تعديل الكلية: ' . $e->getMessage());
    }
}

if ($action === 'toggle_college') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        $stmt = $db->prepare("SELECT is_active FROM colleges WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) sendResp(false, 'الكلية غير موجودة.');

        $newVal = $row['is_active'] ? 0 : 1;
        $db->prepare("UPDATE colleges SET is_active = ? WHERE id = ?")->execute([$newVal, $id]);
        sendResp(true, 'تم تحديث حالة الكلية.', ['is_active' => $newVal]);
    } catch (Exception $e) {
        sendResp(false, 'فشل تحديث الحالة: ' . $e->getMessage());
    }
}

if ($action === 'delete_college') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        // Cascading deletion of specializations -> departments -> college
        $db->beginTransaction();
        $db->prepare("DELETE FROM specializations WHERE department_id IN (SELECT id FROM departments WHERE college_id = ?)")->execute([$id]);
        $db->prepare("DELETE FROM departments WHERE college_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM colleges WHERE id = ?")->execute([$id]);
        $db->commit();
        sendResp(true, 'تم حذف الكلية مع كافة الأقسام والتخصصات التابعة لها بنجاح.');
    } catch (Exception $e) {
        $db->rollBack();
        sendResp(false, 'فشل حذف الكلية: ' . $e->getMessage());
    }
}

// ============================================================
// 3. DEPARTMENTS CRUD
// ============================================================
if ($action === 'add_department') {
    $collegeId = (int)($_POST['college_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$collegeId) {
        sendResp(false, 'يرجى اختيار الكلية التابع لها القسم.');
    }
    if (!$name) {
        sendResp(false, 'يرجى إدخال اسم القسم.');
    }

    try {
        $stmt = $db->prepare("INSERT INTO departments (college_id, name, code, description, sort_order, is_active) VALUES (?, ?, ?, ?, 0, 1)");
        $stmt->execute([$collegeId, $name, $code ?: null, $desc ?: null]);
        $newId = (int) $db->lastInsertId();
        sendResp(true, 'تمت إضافة القسم بنجاح.', ['id' => $newId]);
    } catch (Exception $e) {
        sendResp(false, 'فشل إضافة القسم: ' . $e->getMessage());
    }
}

if ($action === 'edit_department') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$id || !$name) {
        sendResp(false, 'بيانات القسم غير مكتملة.');
    }

    try {
        $stmt = $db->prepare("UPDATE departments SET name = ?, code = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $code ?: null, $desc ?: null, $id]);
        sendResp(true, 'تم تعديل بيانات القسم بنجاح.');
    } catch (Exception $e) {
        sendResp(false, 'فشل تعديل القسم: ' . $e->getMessage());
    }
}

if ($action === 'toggle_department') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        $stmt = $db->prepare("SELECT is_active FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) sendResp(false, 'القسم غير موجود.');

        $newVal = $row['is_active'] ? 0 : 1;
        $db->prepare("UPDATE departments SET is_active = ? WHERE id = ?")->execute([$newVal, $id]);
        sendResp(true, 'تم تحديث حالة القسم.', ['is_active' => $newVal]);
    } catch (Exception $e) {
        sendResp(false, 'فشل تحديث الحالة: ' . $e->getMessage());
    }
}

if ($action === 'delete_department') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM specializations WHERE department_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        $db->commit();
        sendResp(true, 'تم حذف القسم مع التخصصات التابعة له بنجاح.');
    } catch (Exception $e) {
        $db->rollBack();
        sendResp(false, 'فشل حذف القسم: ' . $e->getMessage());
    }
}

// ============================================================
// 4. SPECIALIZATIONS CRUD
// ============================================================
if ($action === 'add_specialization') {
    $deptId = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$deptId) {
        sendResp(false, 'يرجى اختيار القسم التابع له التخصص.');
    }
    if (!$name) {
        sendResp(false, 'يرجى إدخال اسم التخصص.');
    }

    try {
        $stmt = $db->prepare("INSERT INTO specializations (department_id, name, code, description, sort_order, is_active) VALUES (?, ?, ?, ?, 0, 1)");
        $stmt->execute([$deptId, $name, $code ?: null, $desc ?: null]);
        $newId = (int) $db->lastInsertId();
        sendResp(true, 'تمت إضافة التخصص بنجاح.', ['id' => $newId]);
    } catch (Exception $e) {
        sendResp(false, 'فشل إضافة التخصص: ' . $e->getMessage());
    }
}

if ($action === 'edit_specialization') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$id || !$name) {
        sendResp(false, 'بيانات التخصص غير مكتملة.');
    }

    try {
        $stmt = $db->prepare("UPDATE specializations SET name = ?, code = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $code ?: null, $desc ?: null, $id]);
        sendResp(true, 'تم تعديل بيانات التخصص بنجاح.');
    } catch (Exception $e) {
        sendResp(false, 'فشل تعديل التخصص: ' . $e->getMessage());
    }
}

if ($action === 'toggle_specialization') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        $stmt = $db->prepare("SELECT is_active FROM specializations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) sendResp(false, 'التخصص غير موجود.');

        $newVal = $row['is_active'] ? 0 : 1;
        $db->prepare("UPDATE specializations SET is_active = ? WHERE id = ?")->execute([$newVal, $id]);
        sendResp(true, 'تم تحديث حالة التخصص.', ['is_active' => $newVal]);
    } catch (Exception $e) {
        sendResp(false, 'فشل تحديث الحالة: ' . $e->getMessage());
    }
}

if ($action === 'delete_specialization') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) sendResp(false, 'معرّف غير صالح.');

    try {
        $stmt = $db->prepare("DELETE FROM specializations WHERE id = ?");
        $stmt->execute([$id]);
        sendResp(true, 'تم حذف التخصص بنجاح.');
    } catch (Exception $e) {
        sendResp(false, 'فشل حذف التخصص: ' . $e->getMessage());
    }
}

sendResp(false, 'إجراء غير معروف.');
