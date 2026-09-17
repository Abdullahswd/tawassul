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

// التأكد من وجود عمود platform_percent (نسبة المنصة) في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN platform_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

// التأكد من وجود عمود discount_percent (نسبة الخصم) في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

// التأكد من وجود عمود icon_image (صورة الأيقونة المرفوعة) في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN icon_image VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

// التأكد من وجود عمود needs_multiple_academics (هل الخدمة تحتاج أكثر من أكاديمي) في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN needs_multiple_academics TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

$action = trim($_POST['action'] ?? '');

/* ── UPLOAD ICON (multipart) ── */
if ($action === 'upload_icon') {
    try {
        if (empty($_FILES['image'])) {
            throw new RuntimeException('لم يتم استلام الصورة');
        }

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
            throw new RuntimeException($errMsg);
        }

        $tmpPath = $file['tmp_name'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new RuntimeException('صيغة الصورة غير مدعومة (JPG/PNG/GIF/WEBP/SVG)');
        }

        $isRealImage = false;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file((string) $tmpPath);
            $isRealImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
        } else {
            $info = @getimagesize($tmpPath);
            $isRealImage = is_array($info) && !empty($info['mime'])
                && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        }

        if (!$isRealImage) {
            throw new RuntimeException('الملف ليس صورة صالحة');
        }

        $uploadDir = dirname(__DIR__) . '/../uploads/services';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            throw new RuntimeException('مجلد رفع الصور (uploads/services) غير قابل للكتابة');
        }

        $fileName = 'svc_icon_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest     = rtrim($uploadDir, '/\\') . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $dest)) {
            throw new RuntimeException('فشل حفظ الصورة على الخادم');
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'image' => 'uploads/services/' . $fileName]);
        exit;
    } catch (Throwable $e) {
        ob_end_clean();
        http_response_code(200);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

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
    $iconImage= trim($_POST['icon_image'] ?? '');
    $iconImageCleared = isset($_POST['icon_image_cleared']) ? (int)$_POST['icon_image_cleared'] : 0;
    $active   = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $price    = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    $platformPercent = isset($_POST['platform_percent']) ? (float)$_POST['platform_percent'] : 0.00;
    $discountPercent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0.00;
    $needsMultipleAcademics = isset($_POST['needs_multiple_academics']) ? (int)$_POST['needs_multiple_academics'] : 0;

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

        // إتاحة السعر ونسبة المنصة والخصم فقط للمستوى 3
        if ($level !== 3) {
            $price = 0.00;
            $platformPercent = 0.00;
            $discountPercent = 0.00;
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

            // إذا لم يحدد رفع صورة جديدة، نحافظ على الصورة القديمة
            if ($iconImageCleared) {
                $iconImage = null;
            } elseif ($iconImage === '') {
                $stmt = $db->prepare("SELECT icon_image FROM services WHERE id = ?");
                $stmt->execute([$id]);
                $iconImage = (string)$stmt->fetchColumn();
            }

            $stmt = $db->prepare("UPDATE services SET name = ?, icon = ?, icon_image = ?, price = ?, platform_percent = ?, discount_percent = ?, needs_multiple_academics = ?, is_active = ?, parent_id = ?, level = ?, sort_order = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $icon, $iconImage, $price, $platformPercent, $discountPercent, $needsMultipleAcademics, $active, $parentId, $level, $sortOrder, $id]);
            ob_end_clean();
            echo json_encode(['success' => (bool)$ok, 'id' => $id, 'level' => $level]);
        } else {
            // إضافة جديدة
            $stmt = $db->prepare("INSERT INTO services (parent_id, name, icon, icon_image, price, platform_percent, discount_percent, needs_multiple_academics, is_active, level, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$parentId, $name, $icon, $iconImage, $price, $platformPercent, $discountPercent, $needsMultipleAcademics, $active, $level, $sortOrder]);
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

/* ── MOVE (ترتيب يدوي لأعلى/أسفل بنفس المستوى) ── */
if ($action === 'move') {
    $id        = (int)($_POST['id'] ?? 0);
    $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';

    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'معرف العنصر غير صالح']);
        exit;
    }

    try {
        // جلب العنصر الحالي
        $stmt = $db->prepare("SELECT id, parent_id FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        if (!$current) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'العنصر غير موجود']);
            exit;
        }

        $parentId = ($current['parent_id'] === null || $current['parent_id'] === '') ? null : (int)$current['parent_id'];

        // جلب جميع عناصر نفس المستوى/الأب مرتبة (الترتيب الفعلي)
        if ($parentId === null) {
            $stmt = $db->prepare("SELECT id FROM services WHERE parent_id IS NULL ORDER BY sort_order ASC, id ASC");
            $stmt->execute();
        } else {
            $stmt = $db->prepare("SELECT id FROM services WHERE parent_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$parentId]);
        }
        $siblings = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pos = array_search((string)$id, array_map('strval', $siblings), true);
        if ($pos === false) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'لم يتم العثور على العنصر في قائمة الترتيب']);
            exit;
        }
        $pos = (int)$pos;

        $newPos = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($newPos < 0 || $newPos >= count($siblings)) {
            ob_end_clean();
            echo json_encode(['success' => true, 'moved' => false, 'message' => $direction === 'up' ? 'هذا العنصر في المقدمة بالفعل' : 'هذا العنصر في النهاية بالفعل']);
            exit;
        }

        // تبديل المواقع في المصفوفة
        $tmp = $siblings[$pos];
        $siblings[$pos] = $siblings[$newPos];
        $siblings[$newPos] = $tmp;

        // إعادة ترقيم متسلسلة (1, 2, 3, ...) لضمان عدم تكرار القيم مستقبلاً
        $stmt = $db->prepare("UPDATE services SET sort_order = ? WHERE id = ?");
        foreach ($siblings as $i => $sid) {
            $stmt->execute([$i + 1, (int)$sid]);
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'moved' => true]);
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