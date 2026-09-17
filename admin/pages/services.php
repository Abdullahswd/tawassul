<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// حساب المسار الأساسي للتطبيق (جذر المشروع) للاستخدام في الروابط عند ربط الصور
// مثال: /tawassul  أو  '' إذا كان المشروع في جذر الخادم
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptName = preg_replace('#/admin/pages/[^/]+\.php$#', '', $scriptName);
$appBaseUrl = rtrim($scriptName, '/');

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

// ============================================================
// قسم الخدمات المميزة — جدول منفصل: featured_services
// يُنشأ تلقائياً ضمن كود PHP أثناء فتح هذه الصفحة
// ============================================================
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
} catch (Exception $e) {
    // تجاهل خطأ الإنشاء (الجدول موجود مسبقاً)
}

// جلب الخدمات المميزة مع بيانات الخدمة الأصلية
$featured_services = [];
try {
    $fstmt = $db->query("
        SELECT f.id AS fid, f.service_id, f.badge, f.sort_order, f.is_active AS f_active, f.created_at,
               s.name AS service_name, s.icon AS service_icon, s.icon_image AS service_icon_image,
               s.price AS service_price, s.level AS service_level, s.is_active AS service_active
        FROM featured_services f
        LEFT JOIN services s ON s.id = f.service_id
        ORDER BY f.sort_order ASC, f.id ASC
    ");
    $featured_services = $fstmt->fetchAll();
    $featured_services = array_map(function($r){
        $r['fid'] = (int)$r['fid'];
        $r['service_id'] = (int)$r['service_id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['f_active'] = (bool)$r['f_active'];
        return $r;
    }, $featured_services);
} catch (Exception $e) {
    $featured_services = [];
}

// جلب جميع الخدمات مع ترتيبها وإحصائيات الطلبات
$stmt = $db->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM orders o WHERE o.service_id = s.id) AS orders,
           (SELECT name FROM services p WHERE p.id = s.parent_id) AS parent_name
    FROM services s
    ORDER BY s.level ASC, s.sort_order ASC, s.id ASC
");
$services_flat = $stmt->fetchAll();

$services_flat = array_map(function($s) {
    $s['id'] = (int)$s['id'];
    $s['parent_id'] = $s['parent_id'] ? (int)$s['parent_id'] : null;
    $s['is_active'] = (bool)$s['is_active'];
    $s['level'] = (int)$s['level'];
    $s['price'] = isset($s['price']) ? (float)$s['price'] : 0.00;
    $s['platform_percent'] = isset($s['platform_percent']) ? (float)$s['platform_percent'] : 0.00;
    $s['discount_percent'] = isset($s['discount_percent']) ? (float)$s['discount_percent'] : 0.00;
    $s['icon_image'] = isset($s['icon_image']) ? (string)$s['icon_image'] : '';
    $s['needs_multiple_academics'] = isset($s['needs_multiple_academics']) ? (bool)$s['needs_multiple_academics'] : false;
    $s['orders'] = (int)$s['orders'];
    $s['children'] = [];
    return $s;
}, $services_flat);

function buildTree($items, $parentId = null) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parent_id'] === $parentId) {
            $children = buildTree($items, $item['id']);
            if ($children) $item['children'] = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}

$tree = buildTree($services_flat, null);
$hasData = !empty($services_flat);

// إحصائيات سريعة
$cntL1 = count(array_filter($services_flat, fn($x) => $x['level'] === 1));
$cntL2 = count(array_filter($services_flat, fn($x) => $x['level'] === 2));
$cntL3 = count(array_filter($services_flat, fn($x) => $x['level'] === 3));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>إدارة الخدمات - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    body { background-color: #f8fafc; color: #0f172a; }
    .main-content { padding-top: 75px !important; }
    
    /* رأس الصفحة والتحكم */
    .page-header {
      background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
      padding: 24px 28px;
      border-radius: 20px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      margin-bottom: 24px;
    }
    .page-header-title { font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; }
    .page-header-subtitle { font-size: 14px; color: #64748b; margin-top: 4px; }
    .breadcrumb { font-size: 13px; color: #94a3b8; margin-bottom: 8px; }
    .breadcrumb a { color: #4f46e5; text-decoration: none; font-weight: 600; }
    
    /* كروت الإحصائيات السريعة */
    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 16px 20px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    /* شبكة المستوى الأول (مربعات جنب بعض) */
    .l1-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
      gap: 20px;
    }

    /* بطاقة المستوى الأول */
    .l1-card {
      background: #ffffff;
      border-radius: 20px;
      border: 2px solid #e2e8f0;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      transition: all 0.25s ease;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .l1-card:hover {
      border-color: #6366f1;
      box-shadow: 0 8px 25px rgba(99,102,241,0.12);
      transform: translateY(-2px);
    }
    .l1-card.active-expanded {
      border-color: #4f46e5;
      box-shadow: 0 8px 30px rgba(79,70,229,0.15);
    }

    /* رأس بطاقة المستوى الأول */
    .l1-header {
      padding: 20px;
      background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
      border-bottom: 1px solid #f1f5f9;
      cursor: pointer;
      user-select: none;
    }
    .l1-header-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 12px;
    }
    .l1-title-group {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
    }
    .l1-icon {
      width: 44px;
      height: 44px;
      background: #eef2ff;
      color: #4f46e5;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
    }
    .l1-icon .svc-icon-img,
    .l2-title .svc-icon-img,
    .l3-info .svc-icon-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      padding: 6px;
      border-radius: inherit;
    }
    .l2-title .svc-icon-img { width: 26px; height: 26px; padding: 2px; }
    .l3-info .svc-icon-img { width: 20px; height: 20px; padding: 0; }
    .l1-name {
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.3;
    }
    .l1-badge {
      background: #e2e8f0;
      color: #475569;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 30px;
    }

    .l1-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding-top: 12px;
      border-top: 1px dashed #e2e8f0;
    }
    .l1-action-btns {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* جسم بطاقة المستوى الأول (المستويات 2 و 3) */
    .l1-body {
      padding: 16px;
      background: #f8fafc;
      border-top: 1px solid #eef2f6;
      display: none;
    }
    .l1-body.open {
      display: block;
      animation: fadeIn 0.3s ease-in-out;
    }

    /* المستوى الثاني */
    .l2-block {
      background: #ffffff;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      border-right: 4px solid #8b5cf6;
      padding: 14px 16px;
      margin-bottom: 14px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }
    .l2-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      padding-bottom: 8px;
      border-bottom: 1px solid #f1f5f9;
    }
    .l2-title {
      font-size: 16px;
      font-weight: 800;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .l2-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* المستوى الثالث (الخدمات) */
    .l3-list {
      margin-top: 10px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .l3-item {
      background: #fafafa;
      border: 1px solid #eef2f6;
      border-radius: 10px;
      padding: 10px 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .l3-item:hover {
      background: #ffffff;
      border-color: #cbd5e1;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .l3-main {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }
    .l3-info {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1 1 auto;
      min-width: 0;
    }
    .l3-name {
      font-size: 14px;
      font-weight: 600;
      color: #334155;
      overflow-wrap: anywhere;
    }
    .l3-controls {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }
    .l3-badges {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      padding-top: 8px;
      border-top: 1px dashed #e2e8f0;
    }
    .l3-badges small {
      font-size: 11px;
      font-weight: 600;
      opacity: 0.85;
    }
    .l3-orders {
      background: #eef2ff;
      color: #4f46e5;
      border: 1px solid #e0e7ff;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .l3-price {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #a7f3d0;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .l3-percent {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #fed7aa;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .l3-multi {
      background: #f5f3ff;
      color: #5b21b6;
      border: 1px solid #ddd6fe;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .l3-discount {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .l3-price-del {
      color: #fca5a5;
      text-decoration: line-through;
      font-weight: 600;
    }

    /* الأزرار والمدخلات */
    .btn-add-main {
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      color: white;
      font-weight: 700;
      padding: 10px 22px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(79,70,229,0.25);
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: none;
      cursor: pointer;
    }
    .btn-add-main:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(79,70,229,0.35);
      background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
    }

    .btn-add-sub {
      background: #f1f5f9;
      color: #475569;
      font-size: 12px;
      font-weight: 700;
      padding: 5px 12px;
      border-radius: 8px;
      border: 1px solid #cbd5e1;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-add-sub:hover {
      background: #e2e8f0;
      color: #0f172a;
      border-color: #94a3b8;
    }

    .btn-add-service {
      background: #e0e7ff;
      color: #4338ca;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 8px;
      border: 1px solid #c7d2fe;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-add-service:hover {
      background: #c7d2fe;
      color: #3730a3;
    }

    .btn-icon {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 4px 8px;
      font-size: 13px;
      color: #475569;
      cursor: pointer;
      transition: 0.2s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-icon:hover { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }
    .btn-icon.danger { color: #ef4444; border-color: #fee2e2; }
    .btn-icon.danger:hover { background: #fef2f2; border-color: #fca5a5; }
    .btn-icon.order-btn { color: #4f46e5; border-color: #e0e7ff; background: #eef2ff; }
    .btn-icon.order-btn:hover { background: #e0e7ff; border-color: #c7d2fe; }
    .l1-action-btns { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }

    .form-input {
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 10px 14px;
      background: white;
      width: 100%;
      transition: 0.2s;
      font-size: 14px;
    }
    .form-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }

    /* رفع أيقونة صورة من الجهاز */
    .upload-icon-box {
      margin-top: 12px;
      border: 2px dashed #cbd5e1;
      border-radius: 12px;
      padding: 14px;
      background: #f8fafc;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .upload-icon-box.has-image { border-color: #86efac; background: #f0fdf4; }
    .upload-icon-preview {
      position: relative;
      width: 46px;
      height: 46px;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      background: white;
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .upload-icon-preview img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
    .upload-icon-remove {
      position: absolute;
      top: -6px;
      right: -6px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #ef4444;
      color: white;
      border: none;
      font-size: 10px;
      line-height: 1;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .upload-icon-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: white;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 700;
      color: #334155;
      cursor: pointer;
      transition: 0.2s;
    }
    .upload-icon-btn:hover { border-color: #6366f1; color: #4f46e5; background: #eef2ff; }
    .upload-icon-plus {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #6366f1;
      color: white;
      font-size: 14px;
      font-weight: 700;
    }

    /* Custom Switch */
    .custom-toggle {
      position: relative;
      display: inline-block;
      width: 36px;
      height: 20px;
      flex-shrink: 0;
      cursor: pointer;
    }
    .custom-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
    .custom-toggle .slider {
      position: absolute;
      inset: 0;
      background: #cbd5e1;
      border-radius: 34px;
      transition: background 0.25s;
    }
    .custom-toggle .slider::before {
      content: "";
      position: absolute;
      height: 14px;
      width: 14px;
      left: 3px;
      bottom: 3px;
      background: white;
      border-radius: 50%;
      transition: transform 0.25s;
    }
    .custom-toggle input:checked + .slider { background: #10b981; }
    .custom-toggle input:checked + .slider::before { transform: translateX(16px); }

    /* المودال */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: white;
      border-radius: 20px;
      max-width: 520px;
      width: 92%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      animation: modalPop 0.25s ease-out;
    }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
    .modal-body { padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }

    .icon-grid { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .icon-opt {
      font-size: 24px;
      padding: 8px;
      border-radius: 10px;
      border: 2px solid transparent;
      cursor: pointer;
      transition: 0.2s;
      background: #f8fafc;
    }
    .icon-opt:hover { background: #f1f5f9; }
    .icon-opt.active { border-color: #6366f1; background: #eef2ff; }

    .toast-container { position: fixed; bottom: 24px; left: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
    .toast { padding: 14px 20px; border-radius: 12px; color: white; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; }
    .toast.success { background: #10b981; }
    .toast.error { background: #ef4444; }

    /* ===== قسم الخدمات المميزة ⭐ ===== */
    .featured-section {
      background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 50%, #ffffff 100%);
      border: 2px solid #fcd34d;
      border-radius: 20px;
      padding: 22px 24px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(245,158,11,0.10);
    }
    .featured-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
    .featured-title { font-size: 20px; font-weight: 800; color: #92400e; margin: 0; display: flex; align-items: center; gap: 8px; }
    .featured-sub { font-size: 13px; color: #b45309; margin-top: 2px; }
    .btn-add-featured {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: white; border: none; border-radius: 10px;
      padding: 10px 18px; font-weight: 800; font-size: 14px; cursor: pointer;
      display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
      box-shadow: 0 4px 12px rgba(245,158,11,0.35);
    }
    .btn-add-featured:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(245,158,11,0.45); }
    .featured-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
    .featured-card {
      background: white; border: 1px solid #fde68a; border-radius: 14px;
      padding: 14px 16px; display: flex; gap: 12px; align-items: center;
      transition: 0.2s; position: relative;
    }
    .featured-card:hover { box-shadow: 0 6px 16px rgba(245,158,11,0.18); border-color: #f59e0b; }
    .featured-card.inactive { opacity: 0.55; filter: grayscale(0.6); }
    .featured-rank {
      width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
      background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
      display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px;
    }
    .featured-info { flex: 1; min-width: 0; }
    .featured-name { font-weight: 800; font-size: 14px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .featured-meta { font-size: 12px; color: #92400e; margin-top: 2px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .featured-badge { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; border-radius: 20px; padding: 1px 10px; font-size: 11px; font-weight: 700; }
    .featured-price { font-weight: 800; color: #059669; }
    .featured-actions { display: flex; gap: 4px; align-items: center; flex-shrink: 0; }
    .featured-empty { text-align: center; color: #b45309; font-size: 13px; padding: 18px; background: rgba(255,255,255,0.6); border: 1px dashed #fcd34d; border-radius: 12px; }
    .move-btn { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:13px; line-height:1; transition:0.2s; }
    .move-btn:hover { background:#fef3c7; }

    @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="admin-layout">
  <?php include '../components/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include '../components/navbar.php'; ?>

    <div class="page-content" style="padding: 24px;">
      
      <!-- رأس الصفحة -->
      <div class="page-header">
        <div class="flex flex-wrap justify-between items-center gap-4">
          <div>
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a> <span>‹</span> <span>الخدمات</span></div>
            <h1 class="page-header-title">🗂️ إدارة ورصيد الخدمات الأكاديمية</h1>
            <p class="page-header-subtitle">تصنيف الخدمات على 3 مستويات لسهولة الإدارة وتحديد أسعار خدمات المستوى الثالث</p>
          </div>
          <!-- زر إضافة قسم من المستوى الأول في الواجهة الرئيسية -->
          <div>
            <button class="btn-add-main" onclick="openAddService(1)">
              <span>➕</span>
              <span>إضافة قسم رئيسي (المستوى الأول)</span>
            </button>
          </div>
        </div>
      </div>

      <!-- إحصائيات سريعة -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#eef2ff;color:#4f46e5;">🏛️</div>
          <div>
            <div style="font-size:12px;color:#64748b;font-weight:700;">الأقسام الرئيسية (المستوى 1)</div>
            <div style="font-size:22px;font-weight:800;color:#0f172a;"><?= $cntL1 ?> قسم</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#f3e8ff;color:#9333ea;">📂</div>
          <div>
            <div style="font-size:12px;color:#64748b;font-weight:700;">الأقسام الفرعية (المستوى 2)</div>
            <div style="font-size:22px;font-weight:800;color:#0f172a;"><?= $cntL2 ?> قسم فرعي</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#ecfdf5;color:#059669;">💎</div>
          <div>
            <div style="font-size:12px;color:#64748b;font-weight:700;">الخدمات المسعرة (المستوى 3)</div>
            <div style="font-size:22px;font-weight:800;color:#0f172a;"><?= $cntL3 ?> خدمة</div>
          </div>
        </div>
      </div>

      <!-- أدوات الفلترة والبحث -->
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-3 flex-wrap flex-1">
          <input type="text" id="searchInput" class="form-input" placeholder="🔍 بحث عن قسم أو خدمة..." style="max-width:300px;" oninput="renderServices()" />
          <select id="levelFilter" class="form-input" style="max-width:180px;" onchange="renderServices()">
            <option value="all">جميع المستويات</option>
            <option value="1">المستوى 1 (الأقسام الرئيسية)</option>
            <option value="2">المستوى 2 (الأقسام الفرعية)</option>
            <option value="3">المستوى 3 (الخدمات)</option>
          </select>
        </div>
        <?php if (!$hasData): ?>
          <button onclick="addDemoData()" class="btn-add-sub" style="background:#fef9c3;border-color:#facc15;color:#854d0e;">
            ⚡ إضافة البيانات الأكاديمية النموذجية
          </button>
        <?php endif; ?>
      </div>

      <!-- حاوية الخدمات الشجرية والمربعات -->
      <div id="servicesContainer"></div>

      <!-- ===== قسم الخدمات المميزة ===== -->
      <div class="featured-section" id="featuredSection">
        <div class="featured-head">
          <div>
            <h2 class="featured-title">⭐ الخدمات المميزة</h2>
            <div class="featured-sub">خدمات مختارة للعرض المميز في الواجهة (<?= count($featured_services) ?> خدمة)</div>
          </div>
          <button type="button" class="btn-add-featured" id="addFeaturedBtn">
            <span>⭐</span><span>إضافة خدمة مميزة</span>
          </button>
        </div>
        <div id="featuredContainer"></div>
      </div>

    </div>
  </div>
</div>

<!-- مودال الإضافة والتعديل -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="serviceModalTitle">إضافة خدمة جديدة</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editServiceId" />
      
      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1" id="nameLabel">اسم العنصر</label>
        <input class="form-input" id="serviceName" placeholder="مثال: التأسيس والبناء الأكاديمي" />
      </div>

      <!-- التابع له (تحديد تلقائي بدون قائمة اختيار) -->
      <input type="hidden" id="serviceParentId" value="" />
      <div class="form-group mb-4" id="parentInfoGroup" style="background:#f8fafc; padding:12px 14px; border-radius:12px; border:1px solid #e2e8f0;">
        <span style="font-size:12px; color:#64748b; font-weight:700; display:block; margin-bottom:2px;">القسم التابع له:</span>
        <span id="parentInfoText" style="font-size:14px; font-weight:800; color:#0f172a;">قسم رئيسي (المستوى الأول)</span>
      </div>

      <!-- حقل السعر (يظهر فقط لخدمات المستوى 3) -->
      <div class="form-group mb-4" id="priceGroup" style="display:none; background:#f0fdf4; padding:14px; border-radius:12px; border:1px solid #bbf7d0;">
        <label class="block text-sm font-bold text-emerald-900 mb-1">💰 سعر الخدمة (ر.س)</label>
        <div class="relative">
          <input type="number" step="0.01" min="0" class="form-input" id="servicePrice" placeholder="0.00" style="font-weight:700; color:#065f46;" />
          <span style="position:absolute; left:14px; top:10px; font-weight:700; color:#059669; font-size:13px;">ر.س</span>
        </div>
        <small style="color:#166534;font-size:11px;display:block;margin-top:4px;">
          يتم تطبيق التسعير حصرياً للخدمات الفردية بالمستوى الثالث.
        </small>
      </div>

      <!-- حقل الخصم (يظهر فقط لخدمات المستوى 3) -->
      <div class="form-group mb-4" id="discountGroup" style="display:none; background:#fef2f2; padding:14px; border-radius:12px; border:1px solid #fecaca;">
        <label class="block text-sm font-bold text-red-900 mb-1">🏷️ الخصم على الخدمة (%)</label>
        <div class="relative">
          <input type="number" step="0.01" min="0" max="100" class="form-input" id="serviceDiscountPercent" placeholder="0.00" style="font-weight:700; color:#b91c1c;" />
          <span style="position:absolute; left:14px; top:10px; font-weight:700; color:#dc2626; font-size:13px;">%</span>
        </div>
        <small style="color:#991b1b;font-size:11px;display:block;margin-top:4px;">
          خصم يمنحه الموقع من سعر الخدمة الأساسي. مثال: 10% يعني أن العميل يدفع السعر بعد خصم 10%.
        </small>
      </div>

      <!-- حقل نسبة المنصة (يظهر فقط لخدمات المستوى 3) -->
      <div class="form-group mb-4" id="platformGroup" style="display:none; background:#fff7ed; padding:14px; border-radius:12px; border:1px solid #fed7aa;">
        <label class="block text-sm font-bold text-orange-900 mb-1">🏢 نسبة المنصة من سعر الخدمة (%)</label>
        <div class="relative">
          <input type="number" step="0.01" min="0" max="100" class="form-input" id="servicePlatformPercent" placeholder="0.00" style="font-weight:700; color:#c2410c;" />
          <span style="position:absolute; left:14px; top:10px; font-weight:700; color:#ea580c; font-size:13px;">%</span>
        </div>
        <small style="color:#9a3412;font-size:11px;display:block;margin-top:4px;">
          تُخصم هذه النسبة لصالح المنصة من سعر الخدمة عند إتمام عملية شراء. مثال: 20% يعني أن 20% من السعر يعود للمنصة.
        </small>
      </div>

      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1">اختر الأيقونة</label>
        <div class="icon-grid" id="iconPicker">
          <span class="icon-opt active" onclick="selectIcon(this,'📚')">📚</span>
          <span class="icon-opt" onclick="selectIcon(this,'🏛️')">🏛️</span>
          <span class="icon-opt" onclick="selectIcon(this,'🎓')">🎓</span>
          <span class="icon-opt" onclick="selectIcon(this,'📝')">📝</span>
          <span class="icon-opt" onclick="selectIcon(this,'📊')">📊</span>
          <span class="icon-opt" onclick="selectIcon(this,'🔍')">🔍</span>
          <span class="icon-opt" onclick="selectIcon(this,'💡')">💡</span>
          <span class="icon-opt" onclick="selectIcon(this,'⚙️')">⚙️</span>
          <span class="icon-opt" onclick="selectIcon(this,'🎯')">🎯</span>
          <span class="icon-opt" onclick="selectIcon(this,'💎')">💎</span>
        </div>
        <input type="hidden" id="serviceIcon" value="📚" />

        <!-- رفع أيقونة صورة من الجهاز -->
        <div class="upload-icon-box" id="uploadIconBox">
          <div class="upload-icon-preview" id="uploadIconPreview" style="display:none;">
            <img id="uploadIconImg" src="" alt="الأيقونة" />
            <button type="button" class="upload-icon-remove" onclick="removeUploadedIcon()" title="إزالة الصورة والعودة للإيموجي">✕</button>
          </div>
          <label class="upload-icon-btn" for="uploadIconFile">
            <span class="upload-icon-plus">＋</span>
            <span>رفع أيقونة (صورة) من الجهاز</span>
          </label>
          <input type="file" id="uploadIconFile" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none;" onchange="handleIconUpload(this)" />
          <input type="hidden" id="serviceIconImage" value="" />
        </div>
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100">
        <span class="text-sm font-bold text-slate-800">تفعيل العنصر</span>
        <label class="custom-toggle">
          <input type="checkbox" id="serviceActive" checked />
          <span class="slider"></span>
        </label>
      </div>

      <!-- زر تحتاج أكثر من أكاديمي (يظهر فقط لخدمات المستوى 3) -->
      <div class="flex items-center justify-between pt-3 border-t border-slate-100" id="multiGroup" style="display:none; background:#f5f3ff; padding:12px 10px; border-radius:12px;">
        <div>
          <span class="text-sm font-bold text-indigo-900">👥 تحتاج أكثر من أكاديمي</span>
          <div class="text-xs text-indigo-500" style="margin-top:2px;">حددها إذا كانت الخدمة تتطلب أكثر من أكاديمي للتنفيذ (الافتراضي: لا)</div>
        </div>
        <label class="custom-toggle">
          <input type="checkbox" id="serviceMultipleAcademics" />
          <span class="slider"></span>
        </label>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-add-sub" data-modal-close>إلغاء</button>
      <button class="btn-add-main" onclick="saveService()">حفظ وتحديث</button>
    </div>
  </div>
</div>

<!-- مودال تأكيد الحذف -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">تأكيد الحذف</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body text-center py-6">
      <div style="font-size:56px;margin-bottom:12px;">⚠️</div>
      <p id="confirmMessage" class="text-slate-600 font-medium text-sm"></p>
    </div>
    <div class="modal-footer">
      <button class="btn-add-sub" data-modal-close>إلغاء</button>
      <button class="btn-add-main" style="background:#ef4444;" id="confirmBtn">حذف نهائي</button>
    </div>
  </div>
</div>

<!-- مودال إضافة خدمة مميزة ⭐ -->
<div class="modal-overlay" id="featuredModal">
  <div class="modal-box">
    <div class="modal-header" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);">
      <h3 class="modal-title">⭐ إضافة خدمة مميزة</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1">اختر الخدمة (من المستوى الثالث)</label>
        <select class="form-input" id="featuredServiceId"></select>
        <small style="color:#b45309;font-size:11px;display:block;margin-top:4px;">تُعرض خدمات المستوى الثالث فقط لأنها الخدمات القابلة للطلب بسعر.</small>
      </div>
      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1">🏷️ الشارة المميزة (اختياري)</label>
        <input class="form-input" id="featuredBadge" placeholder="مثال: الأكثر طلباً ⭐" />
      </div>
      <div class="flex items-center justify-between pt-3 border-t border-slate-100" style="background:#fffbeb; padding:12px 10px; border-radius:12px;">
        <div>
          <span class="text-sm font-bold text-amber-900">✨ مفعّلة في العرض المميز</span>
          <div class="text-xs text-amber-600" style="margin-top:2px;">إيقافها يخفيها من الواجهة دون حذفها</div>
        </div>
        <label class="custom-toggle">
          <input type="checkbox" id="featuredActive" checked />
          <span class="slider"></span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-add-sub" data-modal-close>إلغاء</button>
      <button type="button" class="btn-add-featured" id="saveFeaturedBtn">⭐ حفظ كمميزة</button>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script>
const Toast = {
  show: function(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000);
  }
};

let flatData = <?= json_encode($services_flat) ?>;
let featuredData = <?= json_encode($featured_services, JSON_UNESCAPED_UNICODE) ?>;
const APP_BASE = '<?= htmlspecialchars($appBaseUrl, ENT_QUOTES) ?>';
let expandedCards = new Set(); // يحتفظ بالأقسام المفتوحة

// ── حفظ واستعادة حالة الواجهة (القوائم المفتوحة + موضع التمرير + البحث/الفلتر) ──
function saveUiState() {
    try {
        sessionStorage.setItem('svcExpanded', JSON.stringify([...expandedCards]));
        const searchInput = document.getElementById('searchInput');
        const levelFilter = document.getElementById('levelFilter');
        if (searchInput) sessionStorage.setItem('svcSearch', searchInput.value);
        if (levelFilter) sessionStorage.setItem('svcFilter', levelFilter.value);
        sessionStorage.setItem('svcScroll', String(window.scrollY || 0));
    } catch (e) { /* تجاهل أخطاء التخزين */ }
}

function restoreUiState() {
    try {
        const exp = sessionStorage.getItem('svcExpanded');
        if (exp) expandedCards = new Set(JSON.parse(exp));

        const search = sessionStorage.getItem('svcSearch');
        if (search !== null) {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.value = search;
        }
        const filter = sessionStorage.getItem('svcFilter');
        if (filter !== null) {
            const levelFilter = document.getElementById('levelFilter');
            if (levelFilter) levelFilter.value = filter;
        }

        const scroll = parseInt(sessionStorage.getItem('svcScroll') || '0', 10);
        if (scroll) {
            // الانتظار حتى تُبنى القوائم ثم إعادة التمرير إلى نفس المكان
            requestAnimationFrame(() => {
                window.scrollTo(0, scroll);
            });
        }

        // مسح الحالة بعد الاستخدام حتى لا تعاد عند التحديث اليدوي لاحقاً
        ['svcExpanded', 'svcSearch', 'svcFilter', 'svcScroll'].forEach(k => sessionStorage.removeItem(k));
    } catch (e) { /* تجاهل */ }
}

let selectedIcon = '📚';
let selectedIconImage = '';
let selectedIconImageCleared = false;
let editId = null;
let deleteTargetId = null;

// بناء الشجرة مع إمكانية الفلترة والبحث
function buildTreeFromFlat(flat, parentId, filterIds) {
    const branch = [];
    flat.forEach(item => {
        if (item.parent_id === parentId) {
            const hasChildren = flat.some(i => i.parent_id === item.id && filterIds.has(i.id));
            if (filterIds.has(item.id) || hasChildren) {
                const children = buildTreeFromFlat(flat, item.id, filterIds);
                const node = { ...item };
                if (children.length) node.children = children;
                branch.push(node);
            }
        }
    });
    return branch;
}

// عرض الواجهة كاملة (مربعات جنب بعض)
function renderServices() {
    const container = document.getElementById('servicesContainer');
    if (!container) return;

    const levelFilter = document.getElementById('levelFilter').value;
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();

    let filtered = flatData;
    if (levelFilter !== 'all') filtered = filtered.filter(item => item.level == levelFilter);
    if (searchTerm) filtered = filtered.filter(item => item.name.toLowerCase().includes(searchTerm));

    const filteredIds = new Set(filtered.map(i => i.id));
    const finalTree = buildTreeFromFlat(flatData, null, filteredIds);

    if (!flatData.length) {
        container.innerHTML = `
          <div class="bg-white rounded-2xl p-12 text-center border-2 border-dashed border-slate-200">
            <span class="text-6xl block mb-4">📭</span>
            <h3 class="text-xl font-bold text-slate-800 mb-2">لا توجد خدمات أو أقسام بعد</h3>
            <p class="text-slate-500 text-sm mb-6">يمكنك البدء بإضافة قسم رئيسي أو تحميل البيانات النموذجية.</p>
            <div class="flex justify-center gap-3">
              <button class="btn-add-main" onclick="openAddService(1)">+ إضافة قسم رئيسي</button>
              <button class="btn-add-sub" onclick="addDemoData()">⚡ إضافة نموذج جاهز</button>
            </div>
          </div>
        `;
        return;
    }

    if (!finalTree.length) {
        container.innerHTML = `
          <div class="bg-white rounded-2xl p-8 text-center border border-slate-200 text-slate-500">
            🔍 لا توجد عناصر تطابق معايير البحث الحالية.
          </div>
        `;
        return;
    }

    // بناء شبكة المستوى الأول
    let html = '<div class="l1-grid">';
    finalTree.forEach((l1, idx) => {
        const isFirst = idx === 0;
        const isLast = idx === finalTree.length - 1;
        const isOpen = expandedCards.has(l1.id) || searchTerm !== '' || levelFilter !== 'all';
        const subCount = l1.children ? l1.children.length : 0;
        
        let totalServicesCount = 0;
        if (l1.children) {
            l1.children.forEach(sub => {
                if (sub.children) totalServicesCount += sub.children.length;
            });
        }

        html += `
        <div class="l1-card ${isOpen ? 'active-expanded' : ''}" data-id="${l1.id}">
          <div class="l1-header" onclick="toggleCard(${l1.id})">
            <div class="l1-header-top">
              <div class="l1-title-group">
                <div class="l1-icon">${iconTag(l1, '📚')}</div>
                <div>
                  <div class="l1-name">${l1.name}</div>
                  <div style="font-size:12px;color:#64748b;margin-top:2px;">
                    المستوى الأول • ${subCount} أقسام فرعية (${totalServicesCount} خدمة)
                  </div>
                </div>
              </div>
              <span class="text-slate-400 font-bold text-lg">${isOpen ? '▲' : '▼'}</span>
            </div>

            <!-- أزرار التحكم بالمستوى الأول + زر إضافة المستوى الثاني -->
            <div class="l1-actions" onclick="event.stopPropagation()">
              <!-- زر إضافة قسم فرعي (المستوى الثاني) بجانب اسم/كارت القسم الأول -->
              <button class="btn-add-sub" onclick="openAddService(2, ${l1.id})">
                <span>➕</span>
                <span>إضافة قسم فرعي</span>
              </button>

              <div class="l1-action-btns">
                ${!isFirst ? `<button class="btn-icon order-btn" onclick="moveService(${l1.id}, 'up')" title="تحريك لأعلى">⬆️</button>` : ''}
                ${!isLast ? `<button class="btn-icon order-btn" onclick="moveService(${l1.id}, 'down')" title="تحريك لأسفل">⬇️</button>` : ''}
                <label class="custom-toggle" title="تفعيل / إيقاف">
                  <input type="checkbox" ${l1.is_active ? 'checked' : ''} onchange="toggleService(${l1.id})" />
                  <span class="slider"></span>
                </label>
                <button class="btn-icon" onclick="editService(${l1.id})" title="تعديل">✏️</button>
                <button class="btn-icon danger" onclick="deleteService(${l1.id}, '${escapeJs(l1.name)}')" title="حذف">🗑</button>
              </div>
            </div>
          </div>

          <!-- المحتوى الداخلي: المستوى الثاني والمستوى الثالث -->
          <div class="l1-body ${isOpen ? 'open' : ''}">
            ${l1.children && l1.children.length > 0 ? renderL2List(l1.children) : '<div class="text-slate-400 text-xs py-3 text-center">لا توجد أقسام فرعية مدرجة هنا</div>'}
          </div>
        </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

// عرض أقسام المستوى الثاني والخدمات التابعة لها
function renderL2List(l2Items) {
    let html = '';
    l2Items.forEach(l2 => {
        const services = l2.children || [];
        html += `
        <div class="l2-block">
          <div class="l2-header">
            <div class="l2-title">
              <span>${iconTag(l2, '📂')}</span>
              <span>${l2.name}</span>
            </div>
            
            <div class="l2-actions">
              <!-- زر إضافة خدمة (المستوى الثالث) بجانب خدمات المستوى الثاني -->
              <button class="btn-add-service" onclick="openAddService(3, ${l2.id})">
                <span>➕</span>
                <span>إضافة خدمة</span>
              </button>

              <label class="custom-toggle" title="تفعيل / إيقاف">
                <input type="checkbox" ${l2.is_active ? 'checked' : ''} onchange="toggleService(${l2.id})" />
                <span class="slider"></span>
              </label>
              <button class="btn-icon" onclick="editService(${l2.id})" title="تعديل">✏️</button>
              <button class="btn-icon danger" onclick="deleteService(${l2.id}, '${escapeJs(l2.name)}')" title="حذف">🗑</button>
            </div>
          </div>

          <!-- المستوى الثالث (الخدمات الفردية مع الأسعار) -->
          ${services.length > 0 ? `
            <div class="l3-list">
              ${services.map(l3 => `
                <div class="l3-item">
                  <div class="l3-main">
                    <div class="l3-info">
                      <span class="text-base">${iconTag(l3, '•')}</span>
                      <span class="l3-name">• ${l3.name}</span>
                    </div>
                    <div class="l3-controls">
                      <label class="custom-toggle" title="تفعيل / إيقاف">
                        <input type="checkbox" ${l3.is_active ? 'checked' : ''} onchange="toggleService(${l3.id})" />
                        <span class="slider"></span>
                      </label>
                      <button class="btn-icon" onclick="editService(${l3.id})" title="تعديل">✏️</button>
                      <button class="btn-icon danger" onclick="deleteService(${l3.id}, '${escapeJs(l3.name)}')" title="حذف">🗑</button>
                    </div>
                  </div>
                  <div class="l3-badges">
                    <span class="l3-price">💵 ${Number(l3.price).toFixed(2)} <small>ر.س</small></span>
                    ${Number(l3.discount_percent) > 0 ? `
                      <span class="l3-discount" title="السعر بعد الخصم">
                        🏷️ خصم ${Number(l3.discount_percent).toFixed(0)}%
                        <span class="l3-price-del">${Number(l3.price).toFixed(2)} ر.س</span>
                        <b>${Number((l3.price * (1 - l3.discount_percent / 100))).toFixed(2)} ر.س</b>
                      </span>
                    ` : ''}
                    ${Number(l3.platform_percent) > 0 ? `<span class="l3-percent" title="نسبة المنصة من سعر الخدمة">🏢 ${Number(l3.platform_percent).toFixed(2)}% للمنصة</span>` : ''}
                    ${l3.orders > 0 ? `<span class="l3-orders">📦 ${l3.orders} طلب</span>` : ''}
                    ${l3.needs_multiple_academics ? `<span class="l3-multi" title="هذه الخدمة تحتاج أكثر من أكاديمي للتنفيذ">👥 أكثر من أكاديمي</span>` : ''}
                  </div>
                </div>
              `).join('')}
            </div>
          ` : '<div class="text-slate-400 text-xs mt-2 pr-2">لا توجد خدمات مضافة في هذا القسم الفرعي</div>'}
        </div>
        `;
    });
    return html;
}

function toggleCard(cardId) {
    if (expandedCards.has(cardId)) {
        expandedCards.delete(cardId);
    } else {
        expandedCards.add(cardId);
    }
    renderServices();
}

function escapeJs(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// فتح المودال مع تهيئة المستوى والأب تلقائياً
function openAddService(targetLevel = 1, defaultParentId = null) {
    editId = null;
    document.getElementById('serviceName').value = '';
    document.getElementById('servicePrice').value = '';
    document.getElementById('servicePlatformPercent').value = '';
    document.getElementById('serviceDiscountPercent').value = '';
    document.getElementById('serviceActive').checked = true;
    document.getElementById('serviceMultipleAcademics').checked = false;
    document.getElementById('serviceParentId').value = defaultParentId || '';
    selectedIcon = '📚';
    selectedIconImage = '';
    selectedIconImageCleared = false;
    document.getElementById('serviceIcon').value = '📚';
    document.getElementById('serviceIconImage').value = '';
    document.getElementById('uploadIconFile').value = '';
    resetUploadedIconPreview();
    
    document.querySelectorAll('.icon-opt').forEach(el => el.classList.remove('active'));
    document.querySelector('.icon-opt').classList.add('active');

    // تحديث عنوان المودال ورؤية حقل السعر والمعلومات
    updateModalState(targetLevel, defaultParentId);
    Modal.open('serviceModal');
}

function updateModalState(level, parentId = null) {
    const titleEl = document.getElementById('serviceModalTitle');
    const priceGroup = document.getElementById('priceGroup');
    const platformGroup = document.getElementById('platformGroup');
    const discountGroup = document.getElementById('discountGroup');
    const multiGroup = document.getElementById('multiGroup');
    const nameLabel = document.getElementById('nameLabel');
    const parentInfoText = document.getElementById('parentInfoText');

    if (level === 1) {
        titleEl.textContent = 'إضافة قسم رئيسي (المستوى الأول)';
        nameLabel.textContent = 'اسم القسم الرئيسي';
        parentInfoText.textContent = 'قسم رئيسي في الواجهة الرئيسية (لا يوجد قسم أب)';
        priceGroup.style.display = 'none';
        platformGroup.style.display = 'none';
        discountGroup.style.display = 'none';
        multiGroup.style.display = 'none';
    } else if (level === 2) {
        titleEl.textContent = 'إضافة قسم فرعي (المستوى الثاني)';
        nameLabel.textContent = 'اسم القسم الفرعي';
        const parent = flatData.find(x => x.id == parentId);
        parentInfoText.textContent = parent ? `يندرج تحت القسم الرئيسي: "${parent.name}"` : 'قسم فرعي';
        priceGroup.style.display = 'none';
        platformGroup.style.display = 'none';
        discountGroup.style.display = 'none';
        multiGroup.style.display = 'none';
    } else {
        titleEl.textContent = 'إضافة خدمة جديدة (المستوى الثالث)';
        nameLabel.textContent = 'اسم الخدمة الأكاديمية';
        const parent = flatData.find(x => x.id == parentId);
        parentInfoText.textContent = parent ? `تندرج تحت القسم الفرعي: "${parent.name}"` : 'خدمة أكاديمية';
        priceGroup.style.display = 'block';
        platformGroup.style.display = 'block';
        discountGroup.style.display = 'block';
        multiGroup.style.display = 'flex';
    }
}

function editService(id) {
    const s = flatData.find(x => x.id === id);
    if (!s) return;
    editId = id;
    
    document.getElementById('serviceName').value = s.name;
    document.getElementById('servicePrice').value = s.price || '';
    document.getElementById('servicePlatformPercent').value = s.platform_percent || '';
    document.getElementById('serviceDiscountPercent').value = s.discount_percent || '';
    document.getElementById('serviceActive').checked = s.is_active;
    document.getElementById('serviceMultipleAcademics').checked = !!s.needs_multiple_academics;
    document.getElementById('serviceParentId').value = s.parent_id || '';
    selectedIcon = s.icon || '📚';
    selectedIconImage = s.icon_image || '';
    selectedIconImageCleared = false;
    document.getElementById('serviceIcon').value = selectedIcon;
    document.getElementById('serviceIconImage').value = selectedIconImage;
    document.getElementById('uploadIconFile').value = '';

    document.querySelectorAll('.icon-opt').forEach(el => {
        el.classList.toggle('active', el.textContent.trim() === selectedIcon);
    });

    // عرض المعاينة للصورة المرفوعة إن وجدت
    if (selectedIconImage) {
        showUploadedIconPreview(selectedIconImage);
    } else {
        resetUploadedIconPreview();
    }

    updateModalState(s.level, s.parent_id);
    Modal.open('serviceModal');
}

function selectIcon(el, icon) {
    document.querySelectorAll('.icon-opt').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    selectedIcon = icon;
    document.getElementById('serviceIcon').value = icon;
    // عند اختيار إيموجي نُلغي الصورة المرفوعة
    selectedIconImage = '';
    selectedIconImageCleared = true;
    document.getElementById('serviceIconImage').value = '';
    document.getElementById('uploadIconFile').value = '';
    resetUploadedIconPreview();
}

// رفع الأيقونة من الجهاز عبر AJAX
function handleIconUpload(input) {
    const file = input.files && input.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('action', 'upload_icon');
    fd.append('image', file);

    Toast.show('جارٍ رفع الصورة...', 'loading');

    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                selectedIconImage = res.image;
                selectedIconImageCleared = false;
                document.getElementById('serviceIconImage').value = res.image;
                showUploadedIconPreview(res.image);
                // إلغاء اختيار الإيموجي
                document.querySelectorAll('.icon-opt').forEach(e => e.classList.remove('active'));
                Toast.show('تم رفع الأيقونة بنجاح', 'success');
            } else {
                input.value = '';
                Toast.show(res.message || 'فشل رفع الصورة', 'error');
            }
        })
        .catch(() => {
            input.value = '';
            Toast.show('خطأ في اتصال السيرفر', 'error');
        });
}

// إزالة الصورة المرفوعة والعودة للإيموجي
function removeUploadedIcon() {
    selectedIconImage = '';
    selectedIconImageCleared = true;
    document.getElementById('serviceIconImage').value = '';
    document.getElementById('uploadIconFile').value = '';
    resetUploadedIconPreview();
    // إعادة تفعيل الإيموجي الافتراضي
    selectIcon(document.querySelector('.icon-opt'), '📚');
}

function showUploadedIconPreview(src) {
    const preview = document.getElementById('uploadIconPreview');
    const img = document.getElementById('uploadIconImg');
    const box = document.getElementById('uploadIconBox');
    img.src = assetUrl(src);
    preview.style.display = 'inline-flex';
    box.classList.add('has-image');
}

function resetUploadedIconPreview() {
    const preview = document.getElementById('uploadIconPreview');
    const img = document.getElementById('uploadIconImg');
    const box = document.getElementById('uploadIconBox');
    img.removeAttribute('src');
    preview.style.display = 'none';
    box.classList.remove('has-image');
}

// تحويل مسار مخزن نسبي إلى رابط كامل يعمل من أي مجلد
function assetUrl(path) {
    if (!path) return '';
    if (/^(https?:)?\/\//.test(path)) return path; // رابط خارجي كامل
    return APP_BASE + '/' + String(path).replace(/^\/+/, '');
}

// إرجاع وسوم الأيقونة: صورة إن كانت مرفوعة، أو الإيموجي كبديل
function iconTag(obj, fallback) {
    if (obj && obj.icon_image) {
        return `<img class="svc-icon-img" src="${assetUrl(obj.icon_image).replace(/'/g, "\\'")}" alt="أيقونة" />`;
    }
    return (obj && obj.icon) || fallback;
}

function saveService() {
    const name = document.getElementById('serviceName').value.trim();
    if (!name) { Toast.show('يرجى إدخال الاسم', 'error'); return; }
    
    const parentId = document.getElementById('serviceParentId').value;
    const active = document.getElementById('serviceActive').checked ? 1 : 0;
    const price = document.getElementById('servicePrice').value || 0;
    const platformPercent = document.getElementById('servicePlatformPercent').value || 0;
    const discountPercent = document.getElementById('serviceDiscountPercent').value || 0;
    const needsMultipleAcademics = document.getElementById('serviceMultipleAcademics').checked ? 1 : 0;

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', editId || 0);
    fd.append('name', name);
    fd.append('icon', selectedIcon);
    fd.append('icon_image', selectedIconImage);
    fd.append('icon_image_cleared', selectedIconImageCleared ? 1 : 0);
    fd.append('active', active);
    fd.append('parent_id', parentId || '');
    fd.append('price', price);
    fd.append('platform_percent', platformPercent);
    fd.append('discount_percent', discountPercent);
    fd.append('needs_multiple_academics', needsMultipleAcademics);

    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show(res.message || 'تم حفظ البيانات بنجاح', 'success');
                Modal.close('serviceModal');
                saveUiState();
                location.reload();
            } else {
                Toast.show(res.message || 'حدث خطأ أثناء الحفظ', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال بالسيرفر', 'error'));
}

function toggleService(id) {
    const s = flatData.find(x => x.id === id);
    if (!s) return;
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('id', id);
    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                s.is_active = res.active ? true : false;
                Toast.show(`تم ${s.is_active ? 'تفعيل' : 'إيقاف'} العنصر بنجاح`, s.is_active ? 'success' : 'error');
                renderServices();
            } else {
                Toast.show(res.message || 'حدث خطأ', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

// ترتيب يدوي: تحريك العنصر لأعلى/أسفل بنفس المستوى
function moveService(id, direction) {
    const fd = new FormData();
    fd.append('action', 'move');
    fd.append('id', id);
    fd.append('direction', direction);
    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (res.moved) {
                    saveUiState();
                    location.reload();
                } else {
                    Toast.show(res.message || 'لا يمكن التحريك أكثر', 'info');
                }
            } else {
                Toast.show(res.message || 'حدث خطأ أثناء الترتيب', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

function deleteService(id, name) {
    deleteTargetId = id;
    document.getElementById('confirmMessage').textContent = `هل تريد حذف "${name}" وكافة فروعها المرتبطة بها؟`;
    Modal.open('confirmModal');
    document.getElementById('confirmBtn').onclick = function() {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', deleteTargetId);
        fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Toast.show('تم الحذف بنجاح', 'success');
                    Modal.close('confirmModal');
                    saveUiState();
                    location.reload();
                } else {
                    Toast.show(res.message || 'حدث خطأ أثناء الحذف', 'error');
                }
            })
            .catch(() => Toast.show('خطأ في الاتصال', 'error'));
    };
}

// إضافة نموذج البيانات التجريبية كما طلب المستخدم
function addDemoData() {
    if (!confirm('سيتم إضافة أقسام وخدمات نموذجية متكاملة (3 مستويات). استمرار؟')) return;
    
    // المستوى 1
    const l1 = { name: '1. التأسيس والبناء الأكاديمي (مرحلة الإعداد والتصميم)', icon: '🏛️', parent_id: '', price: 0 };
    
    const fd1 = new FormData();
    fd1.append('action', 'save');
    fd1.append('id', 0);
    fd1.append('name', l1.name);
    fd1.append('icon', l1.icon);
    fd1.append('active', 1);

    fetch('../ajax/manage_service.php', { method: 'POST', body: fd1 })
      .then(r => r.json())
      .then(res1 => {
        if (!res1.success) throw new Error(res1.message);
        const l1Id = res1.id;

        // المستوى 2
        const l2 = { name: '1.1 خدمات التأسيس الأكاديمي (قبل بدء البحث)', icon: '📂', parent_id: l1Id, price: 0 };
        const fd2 = new FormData();
        fd2.append('action', 'save');
        fd2.append('id', 0);
        fd2.append('name', l2.name);
        fd2.append('icon', l2.icon);
        fd2.append('active', 1);
        fd2.append('parent_id', l1Id);

        return fetch('../ajax/manage_service.php', { method: 'POST', body: fd2 })
          .then(r => r.json())
          .then(res2 => {
            if (!res2.success) throw new Error(res2.message);
            const l2Id = res2.id;

            // المستوى 3 (الخدمات مع الأسعار)
            const l3Items = [
              { name: 'المساعدة في اختيار عنوان البحث.', icon: '🎯', price: 150.00, platform_percent: 20, discount_percent: 10 },
              { name: 'تقديم استشارات في إعداد خطة البحث (Proposal).', icon: '📝', price: 300.00, platform_percent: 20, discount_percent: 0 },
              { name: 'تقديم استشارات في إعداد الإطار النظري.', icon: '📖', price: 450.00, platform_percent: 20, discount_percent: 15 }
            ];

            const promises = l3Items.map(item => {
              const fd3 = new FormData();
              fd3.append('action', 'save');
              fd3.append('id', 0);
              fd3.append('name', item.name);
              fd3.append('icon', item.icon);
              fd3.append('active', 1);
              fd3.append('parent_id', l2Id);
              fd3.append('price', item.price);
              fd3.append('platform_percent', item.platform_percent || 0);
              fd3.append('discount_percent', item.discount_percent || 0);
              return fetch('../ajax/manage_service.php', { method: 'POST', body: fd3 }).then(r => r.json());
            });

            return Promise.all(promises);
          });
      })
      .then(() => {
        Toast.show('تم إنشاء الهيكل والخدمات النموذجية بنجاح!', 'success');
        saveUiState();
        location.reload();
      })
      .catch(err => Toast.show('خطأ: ' + err.message, 'error'));
}

/* ============================================================
   قسم الخدمات المميزة - دوال الإضافة والحفظ
   (ملاحظة: escapeJs معرفة مسبقاً أعلى الملف — لا نعيد تعريفها)
   ============================================================ */
const Modal = {
    open: (id) => { var m = document.getElementById(id); if (m) m.classList.add('active'); },
    close: (id) => { var m = document.getElementById(id); if (m) m.classList.remove('active'); },
};
function escapeHtmlFeat(s) {
    const d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
}
function leafServices() {
    return (flatData || []).filter(s => Number(s.level) === 3);
}

function openAddFeatured() {
    try { console.log('openAddFeatured clicked'); } catch(e){}
    var sel = document.getElementById('featuredServiceId');
    if (!sel) { alert('عنصر القائمة غير موجود'); return; }
    const leaves = leafServices();
    const usedIds = new Set((featuredData || []).map(f => Number(f.service_id)));
    const avail = leaves.filter(s => !usedIds.has(Number(s.id)));
    const list = avail.length > 0 ? avail : leaves;
    if (!list.length) { Toast.show('لا توجد خدمات لتمييزها', 'error'); return; }
    var optsHtml = '';
    for (var i = 0; i < list.length; i++) {
        optsHtml += '<option value="' + list[i].id + '">' + escapeHtmlFeat(list[i].name) + ' - ' + Number(list[i].price).toFixed(2) + '</option>';
    }
    sel.innerHTML = optsHtml;
    document.getElementById('featuredBadge').value = '';
    document.getElementById('featuredActive').checked = true;
    var fm = document.getElementById('featuredModal');
    if (fm) { fm.classList.add('active'); }
    else { alert('نافذة المميزة غير موجودة'); }
}

function saveFeatured() {
    if (window._featSaving) return;
    window._featSaving = true;
    const service_id = document.getElementById('featuredServiceId').value;
    const badge = document.getElementById('featuredBadge').value.trim();
    const active = document.getElementById('featuredActive').checked ? 1 : 0;
    if (!service_id) { window._featSaving = false; Toast.show('اختر الخدمة اولا', 'error'); return; }
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('service_id', service_id);
    fd.append('badge', badge);
    fd.append('active', active);
    const saveBtn = document.getElementById('saveFeaturedBtn');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.style.opacity = '0.6'; }
    fetch('../ajax/manage_featured_service.php', { method: 'POST', body: fd })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(t => {
            let res;
            try { res = JSON.parse(t); }
            catch(e) {
                const preview = String(t || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
                Toast.show('استجابة غير صالحة من الخادم: ' + (preview || ('HTTP ' + 'خطأ')), 'error');
                console.log('saveFeatured raw:', t);
                return;
            }
            if (res.success) {
                Toast.show('تمت الاضافة الى المميزة', 'success');
                Modal.close('featuredModal');
                saveUiState();
                location.reload();
            } else { Toast.show(res.message || 'حدث خطأ', 'error'); }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'))
        .finally(() => { window._featSaving = false; if (saveBtn) { saveBtn.disabled = false; saveBtn.style.opacity = ''; } });
}

function toggleFeatured(id) {
    if (window._featToggling) return;
    window._featToggling = true;
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('id', id);
    fetch('../ajax/manage_featured_service.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(t => {
            let res;
            try { res = JSON.parse(t); }
            catch(e) { Toast.show('استجابة غير صالحة من الخادم', 'error'); console.log('toggleFeatured raw:', t); return; }
            if (res.success) {
                const f = featuredData.find(x => Number(x.fid) === Number(id));
                if (f) f.f_active = res.active ? true : false;
                Toast.show(res.active ? 'تم التفعيل' : 'تم الايقاف', res.active ? 'success' : 'error');
                renderFeatured();
            } else { Toast.show(res.message || 'خطأ', 'error'); }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'))
        .finally(() => { window._featToggling = false; });
}

function moveFeatured(id, direction) {
    if (window._featMoving) return;
    window._featMoving = true;
    const fd = new FormData();
    fd.append('action', 'move');
    fd.append('id', id);
    fd.append('direction', direction);
    fetch('../ajax/manage_featured_service.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(t => {
            let res;
            try { res = JSON.parse(t); }
            catch(e) { Toast.show('استجابة غير صالحة من الخادم', 'error'); console.log('moveFeatured raw:', t); return; }
            if (res.success) {
                if (res.moved) { saveUiState(); location.reload(); }
                else { Toast.show('لا يمكن التحريك اكثر', 'error'); }
            } else { Toast.show(res.message || 'خطأ', 'error'); }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'))
        .finally(() => { window._featMoving = false; });
}

function deleteFeatured(id, name) {
    if (window._featDeleting) return;
    deleteTargetId = id;
    document.getElementById('confirmMessage').textContent = 'هل تريد ازالة "' + name + '" من المميزة؟ (لن تحذف الخدمة الاصلية)';
    Modal.open('confirmModal');
    document.getElementById('confirmBtn').onclick = function() {
        if (window._featDeleting) return;
        window._featDeleting = true;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', deleteTargetId);
        fetch('../ajax/manage_featured_service.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(t => {
                let res;
                try { res = JSON.parse(t); }
                catch(e) { Toast.show('استجابة غير صالحة من الخادم', 'error'); console.log('deleteFeatured raw:', t); return; }
                if (res.success) {
                    Toast.show('تمت الازالة من المميزة', 'success');
                    Modal.close('confirmModal');
                    saveUiState();
                    location.reload();
                } else { Toast.show(res.message || 'خطأ', 'error'); }
            })
            .catch(() => Toast.show('خطأ في الاتصال', 'error'))
            .finally(() => { window._featDeleting = false; });
    };
}

function renderFeatured() {
    const container = document.getElementById('featuredContainer');
    if (!container) return;
    const list = featuredData || [];
    if (!list.length) {
        container.innerHTML = '<div class="featured-empty">لا توجد خدمات مميزة بعد.</div>';
        return;
    }
    let html = '<div class="featured-grid">';
    list.forEach((f, idx) => {
        const sName = f.service_name || ('خدمة #' + f.service_id);
        const icon = f.service_icon_image
            ? '<img src="' + assetUrl(f.service_icon_image) + '" style="width:34px;height:34px;object-fit:contain;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;" />'
            : '<span style="font-size:26px;">' + escapeHtmlFeat(f.service_icon || '*') + '</span>';
        const badge = f.badge ? '<span class="featured-badge">' + escapeHtmlFeat(f.badge) + '</span>' : '';
        const price = (f.service_price !== null && f.service_price !== undefined) ? '<span class="featured-price">' + Number(f.service_price).toFixed(2) + ' ر.س</span>' : '';
        html += '<div class="featured-card ' + (f.f_active ? '' : 'inactive') + '">'
          + '<div class="featured-rank">' + (idx + 1) + '</div>'
          + '<div style="flex-shrink:0;">' + icon + '</div>'
          + '<div class="featured-info"><div class="featured-name">' + escapeHtmlFeat(sName) + '</div>'
          + '<div class="featured-meta">' + badge + ' ' + price + '</div></div>'
          + '<div class="featured-actions">'
          + '<button class="move-btn" onclick="moveFeatured(' + f.fid + ", 'up'" + ')" title="لأعلى">▲</button>'
          + '<button class="move-btn" onclick="moveFeatured(' + f.fid + ", 'down'" + ')" title="لأسفل">▼</button>'
          + '<label class="custom-toggle" style="margin:0 2px;"><input type="checkbox" ' + (f.f_active ? 'checked' : '') + ' onchange="toggleFeatured(' + f.fid + ')" /><span class="slider"></span></label>'
          + '<button class="btn-icon danger" onclick="deleteFeatured(' + f.fid + ", '" + escapeJs(sName) + "'" + ')" title="ازالة">🗑</button>'
          + '</div></div>';
    });
    html += '</div>';
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        // استعادة الحالة المحفوظة (القوائم المفتوحة + موضع التمرير + البحث/الفلتر)
        if (typeof restoreUiState === 'function') restoreUiState();
    } catch(e) { console.log('restoreUiState:', e); }
    try {
        if (typeof renderServices === 'function') renderServices();
    } catch(e) { console.log('renderServices:', e); }
    try {
        if (typeof renderFeatured === 'function') renderFeatured();
    } catch(e) { console.log('renderFeatured:', e); }

    // زر إضافة خدمة مميزة — ربط مرة واحدة فقط (data-feat-bound يمنع التكرار مع السكربت الاحتياطي)
    var addFeatBtn = document.getElementById('addFeaturedBtn');
    if (addFeatBtn && !addFeatBtn.dataset.featBound) {
        addFeatBtn.dataset.featBound = '1';
        addFeatBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopImmediatePropagation();
            try { openAddFeatured(); } catch(err) { alert('خطأ: ' + err.message); }
        });
    }
    var saveFeatBtn = document.getElementById('saveFeaturedBtn');
    if (saveFeatBtn && !saveFeatBtn.dataset.featBound) {
        saveFeatBtn.dataset.featBound = '1';
        saveFeatBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopImmediatePropagation();
            try { saveFeatured(); } catch(err) { alert('خطأ: ' + err.message); }
        });
    }

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) Modal.close(modal.id);
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) Modal.close(this.id);
        });
    });
});
</script>

<!-- تم تعطيل السكربت الاحتياطي: السكربت الرئيسي وحده يتولى الربط لمنع تكرار الإشعارات -->
<script>
(function() {
    return; // تعطيل النسخة الاحتياطية — منعاً لازدواج التنفيذ والإشعارات
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s === null || s === undefined) ? '' : String(s);
        return d.innerHTML;
    }
    // إذا كان السكربت الرئيسي سليماً (الدوال معرفة) فلا نفعل شيئاً — منعاً للتكرار
    var mainOk = false;
    try { mainOk = (typeof openAddFeatured === 'function') && (typeof saveFeatured === 'function'); } catch(e) {}
    function leaves() {
        try {
            if (typeof flatData === 'undefined' || !flatData) return [];
            return flatData.filter(function(s) { return Number(s.level) === 3; });
        } catch(e) { return []; }
    }
    // نعرّف نسخة احتياطية فقط عند غياب الرئيسية
    if (!mainOk) {
        window.openAddFeatured = function() {
            try {
                var sel = document.getElementById('featuredServiceId');
                if (!sel) { alert('عنصر القائمة غير موجود'); return false; }
                var ls = leaves();
                var used = {};
                try {
                    (featuredData || []).forEach(function(f) { used[Number(f.service_id)] = true; });
                } catch(e) {}
                var avail = ls.filter(function(s) { return !used[Number(s.id)]; });
                var list = avail.length > 0 ? avail : ls;
                if (!list.length) { alert('لا توجد خدمات (مستوى ثالث) لتمييزها — أضف خدمة أولاً'); return false; }
                var h = '';
                for (var i = 0; i < list.length; i++) {
                    h += '<option value="' + list[i].id + '">' + esc(list[i].name) + ' - ' + Number(list[i].price).toFixed(2) + '</option>';
                }
                sel.innerHTML = h;
                document.getElementById('featuredBadge').value = '';
                document.getElementById('featuredActive').checked = true;
                var fm = document.getElementById('featuredModal');
                if (fm) fm.classList.add('active');
                else alert('نافذة المميزة غير موجودة');
            } catch(err) { alert('خطأ: ' + err.message); }
            return false;
        };
        window.saveFeatured = function() {
            if (window._featSavingFallback) return false;
            window._featSavingFallback = true;
            try {
                var sid = document.getElementById('featuredServiceId').value;
                var badge = document.getElementById('featuredBadge').value;
                var active = document.getElementById('featuredActive').checked ? 1 : 0;
                if (!sid) { alert('اختر الخدمة أولاً'); window._featSavingFallback = false; return false; }
                var fd = new FormData();
                fd.append('action', 'save');
                fd.append('service_id', sid);
                fd.append('badge', badge || '');
                fd.append('active', active);
                fetch('../ajax/manage_featured_service.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.text(); })
                    .then(function(t) {
                        var res;
                        try { res = JSON.parse(t); }
                        catch(e) { alert('استجابة غير صالحة من الخادم'); return; }
                        if (res.success) { alert('تمت الإضافة إلى المميزة'); location.reload(); }
                        else { alert(res.message || 'حدث خطأ'); }
                    })
                    .catch(function() { alert('خطأ في الاتصال'); })
                    .finally(function() { window._featSavingFallback = false; });
            } catch(err) { alert('خطأ: ' + err.message); window._featSavingFallback = false; }
            return false;
        };
    }
    document.addEventListener('DOMContentLoaded', function() {
        // ربط مرة واحدة فقط (data-feat-bound يمنع التكرار مع السكربت الرئيسي)
        var b1 = document.getElementById('addFeaturedBtn');
        if (b1 && !b1.dataset.featBound) {
            b1.dataset.featBound = '1';
            b1.addEventListener('click', function(e) { e.preventDefault(); e.stopImmediatePropagation(); window.openAddFeatured(); });
        }
        var b2 = document.getElementById('saveFeaturedBtn');
        if (b2 && !b2.dataset.featBound) {
            b2.dataset.featBound = '1';
            b2.addEventListener('click', function(e) { e.preventDefault(); e.stopImmediatePropagation(); window.saveFeatured(); });
        }
        try {
            var c = document.getElementById('featuredContainer');
            if (c && !c.innerHTML.trim()) {
                var arr = (typeof featuredData !== 'undefined' && featuredData) ? featuredData : [];
                if (!arr || !arr.length) c.innerHTML = '<div class="featured-empty">لا توجد خدمات مميزة بعد.</div>';
            }
        } catch(e) {}
    });
})();
</script>
</body>
</html>