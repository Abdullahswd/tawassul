<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// التأكد من وجود عمود price في جدول services
try {
    $db->exec("ALTER TABLE services ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {
    // العمود موجود مسبقاً
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
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .l3-item:hover {
      background: #ffffff;
      border-color: #cbd5e1;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .l3-info {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1;
    }
    .l3-name {
      font-size: 14px;
      font-weight: 600;
      color: #334155;
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
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100">
        <span class="text-sm font-bold text-slate-800">تفعيل العنصر</span>
        <label class="custom-toggle">
          <input type="checkbox" id="serviceActive" checked />
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
let expandedCards = new Set(); // يحتفظ بالأقسام المفتوحة

let selectedIcon = '📚';
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
    finalTree.forEach(l1 => {
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
                <div class="l1-icon">${l1.icon || '📚'}</div>
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
              <span>${l2.icon || '📂'}</span>
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
                  <div class="l3-info">
                    <span class="text-base">${l3.icon || '•'}</span>
                    <span class="l3-name">• ${l3.name}</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <!-- السعر فقط للمستوى الثالث -->
                    <span class="l3-price">💵 ${Number(l3.price).toFixed(2)} ر.س</span>
                    ${l3.orders > 0 ? `<span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full font-bold">📦 ${l3.orders} طلب</span>` : ''}

                    <label class="custom-toggle">
                      <input type="checkbox" ${l3.is_active ? 'checked' : ''} onchange="toggleService(${l3.id})" />
                      <span class="slider"></span>
                    </label>
                    <button class="btn-icon" onclick="editService(${l3.id})" title="تعديل">✏️</button>
                    <button class="btn-icon danger" onclick="deleteService(${l3.id}, '${escapeJs(l3.name)}')" title="حذف">🗑</button>
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
    document.getElementById('serviceActive').checked = true;
    document.getElementById('serviceParentId').value = defaultParentId || '';
    selectedIcon = '📚';
    
    document.querySelectorAll('.icon-opt').forEach(el => el.classList.remove('active'));
    document.querySelector('.icon-opt').classList.add('active');

    // تحديث عنوان المودال ورؤية حقل السعر والمعلومات
    updateModalState(targetLevel, defaultParentId);
    Modal.open('serviceModal');
}

function updateModalState(level, parentId = null) {
    const titleEl = document.getElementById('serviceModalTitle');
    const priceGroup = document.getElementById('priceGroup');
    const nameLabel = document.getElementById('nameLabel');
    const parentInfoText = document.getElementById('parentInfoText');

    if (level === 1) {
        titleEl.textContent = 'إضافة قسم رئيسي (المستوى الأول)';
        nameLabel.textContent = 'اسم القسم الرئيسي';
        parentInfoText.textContent = 'قسم رئيسي في الواجهة الرئيسية (لا يوجد قسم أب)';
        priceGroup.style.display = 'none';
    } else if (level === 2) {
        titleEl.textContent = 'إضافة قسم فرعي (المستوى الثاني)';
        nameLabel.textContent = 'اسم القسم الفرعي';
        const parent = flatData.find(x => x.id == parentId);
        parentInfoText.textContent = parent ? `يندرج تحت القسم الرئيسي: "${parent.name}"` : 'قسم فرعي';
        priceGroup.style.display = 'none';
    } else {
        titleEl.textContent = 'إضافة خدمة جديدة (المستوى الثالث)';
        nameLabel.textContent = 'اسم الخدمة الأكاديمية';
        const parent = flatData.find(x => x.id == parentId);
        parentInfoText.textContent = parent ? `تندرج تحت القسم الفرعي: "${parent.name}"` : 'خدمة أكاديمية';
        priceGroup.style.display = 'block';
    }
}

function editService(id) {
    const s = flatData.find(x => x.id === id);
    if (!s) return;
    editId = id;
    
    document.getElementById('serviceName').value = s.name;
    document.getElementById('servicePrice').value = s.price || '';
    document.getElementById('serviceActive').checked = s.is_active;
    document.getElementById('serviceParentId').value = s.parent_id || '';
    selectedIcon = s.icon || '📚';

    document.querySelectorAll('.icon-opt').forEach(el => {
        el.classList.toggle('active', el.textContent.trim() === selectedIcon);
    });

    updateModalState(s.level, s.parent_id);
    Modal.open('serviceModal');
}

function selectIcon(el, icon) {
    document.querySelectorAll('.icon-opt').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    selectedIcon = icon;
    document.getElementById('serviceIcon').value = icon;
}

function saveService() {
    const name = document.getElementById('serviceName').value.trim();
    if (!name) { Toast.show('يرجى إدخال الاسم', 'error'); return; }
    
    const parentId = document.getElementById('serviceParentId').value;
    const active = document.getElementById('serviceActive').checked ? 1 : 0;
    const price = document.getElementById('servicePrice').value || 0;

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', editId || 0);
    fd.append('name', name);
    fd.append('icon', selectedIcon);
    fd.append('active', active);
    fd.append('parent_id', parentId || '');
    fd.append('price', price);

    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show(res.message || 'تم حفظ البيانات بنجاح', 'success');
                Modal.close('serviceModal');
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
              { name: 'المساعدة في اختيار عنوان البحث.', icon: '🎯', price: 150.00 },
              { name: 'تقديم استشارات في إعداد خطة البحث (Proposal).', icon: '📝', price: 300.00 },
              { name: 'تقديم استشارات في إعداد الإطار النظري.', icon: '📖', price: 450.00 }
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
              return fetch('../ajax/manage_service.php', { method: 'POST', body: fd3 }).then(r => r.json());
            });

            return Promise.all(promises);
          });
      })
      .then(() => {
        Toast.show('تم إنشاء الهيكل والخدمات النموذجية بنجاح!', 'success');
        location.reload();
      })
      .catch(err => Toast.show('خطأ: ' + err.message, 'error'));
}

const Modal = {
    open: (id) => { document.getElementById(id).classList.add('active'); },
    close: (id) => { document.getElementById(id).classList.remove('active'); },
};

document.addEventListener('DOMContentLoaded', function() {
    renderServices();

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
</body>
</html>