<?php
/**
 * Admin AJAX: manage featured academics (image upload / save / toggle / delete)
 * File: admin/ajax/manage_featured_academic.php
 */
ob_start();

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db     = db();
$action = trim($_POST['action'] ?? '');

/* ── UPLOAD IMAGE (multipart) ── */
if ($action === 'upload') {
    try {
        if (empty($_FILES['image'])) {
            throw new RuntimeException('لم يتم استلام الصورة');
        }

        $file = $_FILES['image'];

        // أخطاء الرفع الشائعة من PHP
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
            throw new RuntimeException($errMsg);
        }

        $tmpPath  = $file['tmp_name'];
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new RuntimeException('صيغة الصورة غير مدعومة (JPG/PNG/GIF/WEBP)');
        }

        // التأكد أنه ملف صورة حقيقي.
        // نفضّل Fileinfo، ونتعامل مع غيابها (شائع على بعض الخوادم) عبر getimagesize.
        $isRealImage = false;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file((string) $tmpPath); // OOP method – متوافق على كل الخوادم
            $isRealImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        } else {
            $info = @getimagesize($tmpPath);
            $isRealImage = is_array($info) && !empty($info['mime'])
                && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        }

        if (!$isRealImage) {
            throw new RuntimeException('الملف ليس صورة صالحة');
        }

        $uploadDir = dirname(__DIR__) . '/../uploads/featured';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            throw new RuntimeException('مجلد رفع الصور (uploads/featured) غير قابل للكتابة');
        }

        $fileName = 'featured_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest     = rtrim($uploadDir, '/\\') . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $dest)) {
            throw new RuntimeException('فشل حفظ الصورة على الخادم');
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'image' => 'uploads/featured/' . $fileName]);
        exit;
    } catch (Throwable $e) {
        ob_end_clean();
        http_response_code(200);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

/* ── SAVE (add / edit) ── */
if ($action === 'save') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $image     = trim($_POST['image'] ?? '');
    $active    = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if (!$name) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'يرجى إدخال اسم الأكاديمي']);
        exit;
    }

    try {
        if ($id > 0) {
            $stmt = $db->prepare(
                "SELECT image FROM featured_academics WHERE id = ?"
            );
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if ($image === '' && $current && !empty($current['image'])) {
                $image = $current['image'];
            }

            $stmt = $db->prepare(
                "UPDATE featured_academics
                 SET name = ?, specialty = ?, bio = ?, image = ?, is_active = ?
                 WHERE id = ?"
            );
            $ok = $stmt->execute([$name, $specialty, $bio, $image, $active, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO featured_academics (name, specialty, bio, image, sort_order, is_active)
                 VALUES (?, ?, ?, ?, COALESCE((SELECT MAX(sort_order) FROM (SELECT sort_order FROM featured_academics) t), 0) + 1, ?)"
            );
            $ok = $stmt->execute([$name, $specialty, $bio, $image, $active]);
            $newId = (int) $db->lastInsertId();
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $newId]);
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ── TOGGLE ── */
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف غير صالح']);
        exit;
    }
    $stmt = $db->prepare("SELECT is_active FROM featured_academics WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'غير موجود']);
        exit;
    }
    $new = $row['is_active'] ? 0 : 1;
    $db->prepare("UPDATE featured_academics SET is_active = ? WHERE id = ?")->execute([$new, $id]);
    ob_end_clean();
    echo json_encode(['success' => true, 'active' => (bool)$new]);
    exit;
}

/* ── DELETE ── */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف غير صالح']);
        exit;
    }

    try {
        // حذف الصورة من الخادم إن وُجدت
        $stmt = $db->prepare("SELECT image FROM featured_academics WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['image'])) {
            $path = dirname(__DIR__) . '/../' . $row['image'];
            if (is_file($path)) @unlink($path);
        }
        $db->prepare("DELETE FROM featured_academics WHERE id = ?")->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

ob_end_clean();
echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);