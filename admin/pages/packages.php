<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// التأكد من وجود الأعمدة المطلوبة في جدول packages
try { $db->exec("ALTER TABLE packages ADD COLUMN icon VARCHAR(20) NOT NULL DEFAULT '📦'"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE packages ADD COLUMN original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE packages ADD COLUMN service_ids TEXT DEFAULT NULL"); } catch (Exception $e) {}

// جلب الباقات
$packages_stmt = $db->query("
    SELECT p.id, p.name, p.icon, p.price, p.original_price, p.color, p.service_ids, p.features_json, p.is_active AS active,
           (SELECT COUNT(*) FROM orders o WHERE o.package_id = p.id) AS orders
    FROM packages p
    ORDER BY p.price ASC
");
$packages = $packages_stmt->fetchAll();

$packages = array_map(function($p) {
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['original_price'] = (float)($p['original_price'] ?? 0);
    $p['icon'] = $p['icon'] ?: '📦';
    $p['active'] = (bool)$p['active'];
    $p['orders'] = (int)$p['orders'];
    $p['features'] = json_decode($p['features_json'] ?? '[]', true) ?: [];
    $p['service_ids'] = json_decode($p['service_ids'] ?? '[]', true) ?: [];
    return $p;
}, $packages);

// جلب جميع الخدمات الأكاديمية لبناء الشجرة
$services_stmt = $db->query("
    SELECT id, parent_id, name, icon, price, level, is_active 
    FROM services 
    WHERE is_active = 1
    ORDER BY level ASC, sort_order ASC, id ASC
");
$services_flat = $services_stmt->fetchAll();

$services_flat = array_map(function($s) {
    $s['id'] = (int)$s['id'];
    $s['parent_id'] = $s['parent_id'] ? (int)$s['parent_id'] : null;
    $s['level'] = (int)$s['level'];
    $s['price'] = (float)($s['price'] ?? 0);
    $s['is_active'] = (bool)$s['is_active'];
    return $s;
}, $services_flat);

function buildServicesTree($items, $parentId = null) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parent_id'] === $parentId) {
            $children = buildServicesTree($items, $item['id']);
            if ($children) $item['children'] = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}

$services_tree = buildServicesTree($services_flat, null);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>إدارة الباقات - تواصل Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    body { background-color: #f8fafc; color: #0f172a; }
    .main-content { padding-top: 75px !important; }

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

    /* شبكة الباقات */
    .pkg-card {
      background: #ffffff;
      border-radius: 20px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      transition: all 0.25s ease;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      position: relative;
    }
    .pkg-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }
    .pkg-header {
      padding: 24px;
      position: relative;
    }
    .pkg-icon {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin-bottom: 14px;
    }
    .pkg-title { font-size: 22px; font-weight: 800; color: #0f172a; }
    
    .price-box {
      margin-top: 10px;
      display: flex;
      align-items: baseline;
      gap: 10px;
    }
    .price-new { font-size: 32px; font-weight: 900; line-height: 1; }
    .price-old { font-size: 16px; color: #94a3b8; text-decoration: line-through; font-weight: 600; }
    .discount-badge {
      background: #ef4444;
      color: white;
      font-size: 11px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 20px;
    }

    .pkg-body {
      padding: 20px 24px;
      background: #fafafa;
      border-top: 1px solid #f1f5f9;
      flex: 1;
    }
    .pkg-service-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #334155;
      font-weight: 600;
      padding: 6px 0;
    }
    .pkg-service-item .check {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #dcfce7;
      color: #166534;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      flex-shrink: 0;
    }

    .pkg-footer {
      padding: 14px 20px;
      background: #ffffff;
      border-top: 1px solid #eef2f6;
      display: flex;
      gap: 10px;
    }

    /* شجرة اختيار الخدمات داخل المودال */
    .tree-box {
      max-height: 340px;
      overflow-y: auto;
      border: 1px solid #cbd5e1;
      border-radius: 14px;
      padding: 14px;
      background: #f8fafc;
    }
    .tree-l1 {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 12px;
    }
    .tree-l1-title {
      font-size: 15px;
      font-weight: 800;
      color: #0f172a;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding-bottom: 8px;
      border-bottom: 1px solid #f1f5f9;
    }
    .tree-l2 {
      margin-top: 8px;
      margin-right: 12px;
      padding: 8px 12px;
      background: #f8fafc;
      border-radius: 8px;
      border-right: 3px solid #8b5cf6;
    }
    .tree-l2-title {
      font-size: 14px;
      font-weight: 700;
      color: #334155;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px;
    }
    .tree-l3-item {
      margin-top: 4px;
      margin-right: 16px;
      font-size: 13px;
      color: #475569;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 4px 8px;
      background: white;
      border-radius: 6px;
      border: 1px solid #eef2f6;
    }

    /* Modal Styling */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: white;
      border-radius: 20px;
      max-width: 640px;
      width: 94%;
      max-height: 92vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      animation: modalPop 0.25s ease-out;
    }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
    .modal-body { padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }

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

    .icon-grid { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .icon-opt {
      font-size: 22px;
      padding: 6px;
      border-radius: 10px;
      border: 2px solid transparent;
      cursor: pointer;
      transition: 0.2s;
      background: #f8fafc;
    }
    .icon-opt:hover { background: #f1f5f9; }
    .icon-opt.active { border-color: #6366f1; background: #eef2ff; }

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
    }
    .btn-add-sub {
      background: #f1f5f9;
      color: #475569;
      font-size: 13px;
      font-weight: 700;
      padding: 8px 16px;
      border-radius: 10px;
      border: 1px solid #cbd5e1;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .toast-container { position: fixed; bottom: 24px; left: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
    .toast { padding: 14px 20px; border-radius: 12px; color: white; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; }
    .toast.success { background: #10b981; }
    .toast.error { background: #ef4444; }

    @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
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
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a> <span>‹</span> <span>الباقات</span></div>
            <h1 class="page-header-title">🎁 إدارة وتخصيص الباقات الأكاديمية</h1>
            <p class="page-header-subtitle">إنشاء باقات مخصصة بالاعتماد على الخدمات وحساب إجمالي السعر والتخفيض تلقائياً</p>
          </div>
          <div>
            <button class="btn-add-main" onclick="openEditPackage(null)">
              <span>➕</span>
              <span>إضافة باقة جديدة</span>
            </button>
          </div>
        </div>
      </div>

      <!-- شبكة عرض الباقات -->
      <div id="packagesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8"></div>

    </div>
  </div>
</div>

<!-- مودال الإضافة والتعديل التفاعلي -->
<div class="modal-overlay" id="packageModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="packageModalTitle">إضافة باقة مخصصة</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editPackageId" />

      <!-- معلومات الباقة الأساسية -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-bold text-slate-800 mb-1">اسم الباقة</label>
          <input class="form-input" id="pkgName" placeholder="مثال: الباقة الذهبية الشاملة" />
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-800 mb-1">لون الباقة المميز</label>
          <input class="form-input" id="pkgColor" type="color" value="#6366f1" style="height:42px;padding:4px;" />
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1">اختر أيقونة الباقة</label>
        <div class="icon-grid" id="iconPicker">
          <span class="icon-opt active" onclick="selectPkgIcon(this,'📦')">📦</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'🚀')">🚀</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'👑')">👑</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'💎')">💎</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'🎓')">🎓</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'⚡')">⚡</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'🌟')">🌟</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'🏛️')">🏛️</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'🎯')">🎯</span>
          <span class="icon-opt" onclick="selectPkgIcon(this,'📖')">📖</span>
        </div>
        <input type="hidden" id="pkgIcon" value="📦" />
      </div>

      <!-- قسم اختيار الخدمات المشمولة بالباقة (الشجرة 3 مستويات) -->
      <div class="form-group mb-4">
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-bold text-slate-800">📂 الخدمات التي تشملها هذه الباقة</label>
          <span class="text-xs text-indigo-600 font-bold">يمكنك تحديد قسم رئيسي كامل أو خدمات مختارة</span>
        </div>
        
        <div class="tree-box" id="servicesTreeBox">
          <!-- يتم ملؤها ديناميكياً بـ JS -->
        </div>
      </div>

      <!-- ملخص الحساب التلقائي والخصم -->
      <div class="bg-indigo-50/70 p-4 rounded-xl border border-indigo-100 mb-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-slate-600">إجمالي السعر الأصلي للخدمات المختارة:</span>
          <span class="text-base font-extrabold text-slate-800" id="calculatedOriginalPrice">0.00 ر.س</span>
        </div>
        <div class="flex items-center justify-between border-t border-indigo-100 pt-2 mb-3">
          <span class="text-xs font-bold text-emerald-700">قيمة التوفير والخصم:</span>
          <span class="text-sm font-extrabold text-emerald-600" id="discountSummaryText">0.00 ر.س (0%)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">💰 سعر الباقة الجديد (سعر العرض)</label>
            <input type="number" step="0.01" min="0" class="form-input" id="pkgPrice" placeholder="0.00" oninput="calculateSavings()" style="font-weight:800;color:#4338ca;" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">السعر الأصلي (تلقائي)</label>
            <input type="number" step="0.01" class="form-input bg-slate-100" id="pkgOriginalPriceInput" readonly style="font-weight:700;color:#64748b;" />
          </div>
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="block text-sm font-bold text-slate-800 mb-1">ملاحظات أو مميزات إضافية (اختياري)</label>
        <textarea class="form-input" id="pkgFeatures" rows="3" placeholder="مثال: مراجعة دقيقة مجانية&#10;تسليم سريع في غضون 5 أيام"></textarea>
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100">
        <span class="text-sm font-bold text-slate-800">تفعيل الباقة</span>
        <label class="custom-toggle">
          <input type="checkbox" id="pkgActive" checked />
          <span class="slider"></span>
        </label>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn-add-sub" data-modal-close>إلغاء</button>
      <button class="btn-add-main" onclick="savePackage()">حفظ الباقة</button>
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

const packagesData = <?= json_encode($packages) ?>;
const servicesTree = <?= json_encode($services_tree) ?>;
const servicesFlat = <?= json_encode($services_flat) ?>;

let selectedPkgIcon = '📦';
let editPkgId = null;

// بناء قائمة الباقات في الصفحة الرئيسية
function renderPackages() {
  const grid = document.getElementById('packagesGrid');
  if (!grid) return;

  if (!packagesData.length) {
    grid.innerHTML = `
      <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-dashed border-slate-200">
        <span class="text-6xl block mb-4">🎁</span>
        <h3 class="text-xl font-bold text-slate-800 mb-2">لا توجد باقات بعد</h3>
        <p class="text-slate-500 text-sm mb-6">قم بإضافة باقة مخصصة تجمع خدماتك الأكاديمية بسعر مخفض.</p>
        <button class="btn-add-main" onclick="openEditPackage(null)">+ إضافة باقة جديدة</button>
      </div>
    `;
    return;
  }

  grid.innerHTML = packagesData.map((p) => {
    const origPrice = p.original_price > 0 ? p.original_price : p.price;
    const discountAmount = origPrice > p.price ? (origPrice - p.price) : 0;
    const discountPercent = origPrice > 0 ? Math.round((discountAmount / origPrice) * 100) : 0;

    // الخدمات المشمولة بالباقة
    const includedServices = servicesFlat.filter(s => p.service_ids.includes(s.id));

    return `
      <div class="pkg-card" style="border-top: 5px solid ${p.color};">
        <div class="pkg-header">
          <div class="flex items-center justify-between mb-2">
            <div class="pkg-icon" style="background:${p.color}15; color:${p.color};">${p.icon || '📦'}</div>
            <div class="flex items-center gap-2">
              ${discountPercent > 0 ? `<span class="discount-badge">خصم ${discountPercent}%</span>` : ''}
              <label class="custom-toggle">
                <input type="checkbox" ${p.active ? 'checked' : ''} onchange="togglePackage(${p.id})" />
                <span class="slider"></span>
              </label>
            </div>
          </div>
          
          <h3 class="pkg-title">${p.name}</h3>
          
          <div class="price-box">
            <span class="price-new" style="color:${p.color};">${Number(p.price).toFixed(2)} <span style="font-size:15px;font-weight:600;">ر.س</span></span>
            ${discountAmount > 0 ? `<span class="price-old">${Number(origPrice).toFixed(2)} ر.س</span>` : ''}
          </div>
          
          <div class="text-xs text-slate-400 font-bold mt-2">
            📦 ${p.orders || 0} طلب مكتمل بهذا الباقة
          </div>
        </div>

        <div class="pkg-body">
          <div class="text-xs font-bold text-slate-500 mb-2">الخدمات المشمولة بالباقة (${includedServices.length}):</div>
          ${includedServices.length > 0 ? `
            <div class="space-y-1">
              ${includedServices.slice(0, 5).map(s => `
                <div class="pkg-service-item">
                  <span class="check">✓</span>
                  <span class="truncate">${s.icon || '•'} ${s.name}</span>
                </div>
              `).join('')}
              ${includedServices.length > 5 ? `<div class="text-xs text-indigo-600 font-bold pt-1">+ ${includedServices.length - 5} خدمات إضافية متضمنة</div>` : ''}
            </div>
          ` : '<div class="text-slate-400 text-xs">لا توجد خدمات محددة بالباقة</div>'}
        </div>

        <div class="pkg-footer">
          <button class="btn-add-sub flex-1 text-center" onclick="openEditPackage(${p.id})">✏️ تعديل الباقة والخدمات</button>
          <button class="btn-add-sub text-red-500 hover:bg-red-50" onclick="deletePackage(${p.id}, '${escapeJs(p.name)}')">🗑</button>
        </div>
      </div>
    `;
  }).join('');
}

// عرض شجرة خيارات الخدمات داخل المودال
function renderServicesTreeBox(selectedIds = []) {
  const box = document.getElementById('servicesTreeBox');
  if (!box) return;

  if (!servicesTree.length) {
    box.innerHTML = '<div class="text-slate-400 text-xs text-center py-4">لا توجد خدمات مسجلة بالنظام. يرجى إضافة خدمات أولاً من صفحة الخدمات.</div>';
    return;
  }

  const selectedSet = new Set(selectedIds.map(Number));

  let html = '';
  servicesTree.forEach(l1 => {
    const l1Services = getLeafServices(l1);
    const l1ServiceIds = l1Services.map(s => s.id);
    const isL1AllSelected = l1ServiceIds.length > 0 && l1ServiceIds.every(id => selectedSet.has(id));

    html += `
      <div class="tree-l1" data-l1-id="${l1.id}">
        <div class="tree-l1-title">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" class="l1-checkbox rounded text-indigo-600" ${isL1AllSelected ? 'checked' : ''} onchange="toggleL1Category(${l1.id}, this.checked)" />
            <span>${l1.icon || '🏛️'} ${l1.name} (قسم رئيسي كامل)</span>
          </label>
          <span class="text-xs text-slate-400 font-normal">شامل جميع الفروع والخدمات</span>
        </div>
    `;

    if (l1.children && l1.children.length > 0) {
      l1.children.forEach(l2 => {
        const l2Services = getLeafServices(l2);
        const l2ServiceIds = l2Services.map(s => s.id);
        const isL2AllSelected = l2ServiceIds.length > 0 && l2ServiceIds.every(id => selectedSet.has(id));

        html += `
          <div class="tree-l2" data-l2-id="${l2.id}">
            <div class="tree-l2-title">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" class="l2-checkbox rounded text-purple-600" ${isL2AllSelected ? 'checked' : ''} onchange="toggleL2Category(${l2.id}, this.checked)" />
                <span>${l2.icon || '📂'} ${l2.name}</span>
              </label>
            </div>
        `;

        if (l2.children && l2.children.length > 0) {
          l2.children.forEach(l3 => {
            const isChecked = selectedSet.has(l3.id);
            html += `
              <div class="tree-l3-item">
                <label class="flex items-center gap-2 cursor-pointer flex-1">
                  <input type="checkbox" value="${l3.id}" data-price="${l3.price}" class="l3-checkbox rounded text-emerald-600" ${isChecked ? 'checked' : ''} onchange="onServiceCheckChange()" />
                  <span>${l3.icon || '•'} ${l3.name}</span>
                </label>
                <span class="font-bold text-emerald-700 text-xs">${Number(l3.price).toFixed(2)} ر.س</span>
              </div>
            `;
          });
        }

        html += `</div>`;
      });
    }

    html += `</div>`;
  });

  box.innerHTML = html;
  calculateOriginalPrice();
}

function getLeafServices(node) {
  let leaves = [];
  if (node.level === 3) {
    leaves.push(node);
  } else if (node.children) {
    node.children.forEach(c => {
      leaves = leaves.concat(getLeafServices(c));
    });
  }
  return leaves;
}

function toggleL1Category(l1Id, isChecked) {
  const l1Box = document.querySelector(`.tree-l1[data-l1-id="${l1Id}"]`);
  if (l1Box) {
    l1Box.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = isChecked);
  }
  calculateOriginalPrice();
}

function toggleL2Category(l2Id, isChecked) {
  const l2Box = document.querySelector(`.tree-l2[data-l2-id="${l2Id}"]`);
  if (l2Box) {
    l2Box.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = isChecked);
  }
  calculateOriginalPrice();
}

function onServiceCheckChange() {
  calculateOriginalPrice();
}

// حساب السعر الأصلي تلقائياً والتوفير
function calculateOriginalPrice() {
  let total = 0;
  document.querySelectorAll('.l3-checkbox:checked').forEach(cb => {
    total += parseFloat(cb.getAttribute('data-price') || 0);
  });

  document.getElementById('calculatedOriginalPrice').textContent = total.toFixed(2) + ' ر.س';
  document.getElementById('pkgOriginalPriceInput').value = total.toFixed(2);
  
  calculateSavings();
}

function calculateSavings() {
  const origPrice = parseFloat(document.getElementById('pkgOriginalPriceInput').value) || 0;
  const newPrice = parseFloat(document.getElementById('pkgPrice').value) || 0;

  const summaryEl = document.getElementById('discountSummaryText');
  
  if (origPrice > 0 && newPrice > 0 && origPrice > newPrice) {
    const diff = origPrice - newPrice;
    const percent = Math.round((diff / origPrice) * 100);
    summaryEl.textContent = `${diff.toFixed(2)} ر.س (توفير ${percent}%)`;
    summaryEl.className = 'text-sm font-extrabold text-emerald-600';
  } else {
    summaryEl.textContent = '0.00 ر.س (0%)';
    summaryEl.className = 'text-sm font-bold text-slate-400';
  }
}

function openEditPackage(id) {
  editPkgId = id;
  if (id) {
    const p = packagesData.find(x => x.id === id);
    if (!p) return;
    document.getElementById('packageModalTitle').textContent = `تعديل باقة ${p.name}`;
    document.getElementById('editPackageId').value = p.id;
    document.getElementById('pkgName').value = p.name;
    document.getElementById('pkgColor').value = p.color || '#6366f1';
    document.getElementById('pkgPrice').value = p.price;
    document.getElementById('pkgActive').checked = p.active;
    document.getElementById('pkgFeatures').value = (p.features || []).join('\n');
    selectedPkgIcon = p.icon || '📦';
    document.getElementById('pkgIcon').value = selectedPkgIcon;

    document.querySelectorAll('.icon-opt').forEach(el => {
      el.classList.toggle('active', el.textContent.trim() === selectedPkgIcon);
    });

    renderServicesTreeBox(p.service_ids || []);
  } else {
    document.getElementById('packageModalTitle').textContent = 'إضافة باقة مخصصة جديدة';
    document.getElementById('editPackageId').value = '';
    document.getElementById('pkgName').value = '';
    document.getElementById('pkgColor').value = '#6366f1';
    document.getElementById('pkgPrice').value = '';
    document.getElementById('pkgActive').checked = true;
    document.getElementById('pkgFeatures').value = '';
    selectedPkgIcon = '📦';
    document.getElementById('pkgIcon').value = '📦';

    document.querySelectorAll('.icon-opt').forEach(el => el.classList.remove('active'));
    document.querySelector('.icon-opt').classList.add('active');

    renderServicesTreeBox([]);
  }

  Modal.open('packageModal');
}

function selectPkgIcon(el, icon) {
  document.querySelectorAll('.icon-opt').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  selectedPkgIcon = icon;
  document.getElementById('pkgIcon').value = icon;
}

function savePackage() {
  const id = document.getElementById('editPackageId').value;
  const name = document.getElementById('pkgName').value.trim();
  const price = parseFloat(document.getElementById('pkgPrice').value) || 0;
  const origPrice = parseFloat(document.getElementById('pkgOriginalPriceInput').value) || 0;
  const color = document.getElementById('pkgColor').value;
  const active = document.getElementById('pkgActive').checked ? 1 : 0;
  const featuresStr = document.getElementById('pkgFeatures').value;
  
  // تجميع معرّفات الخدمات المختارة بالمستوى الثالث
  const serviceIds = [];
  document.querySelectorAll('.l3-checkbox:checked').forEach(cb => {
    serviceIds.push(parseInt(cb.value));
  });

  if (!name || price <= 0) {
    Toast.show('يرجى كتابة اسم الباقة وسعر العرض بصورة صحيحة', 'error');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'save');
  fd.append('id', id || 0);
  fd.append('name', name);
  fd.append('icon', selectedPkgIcon);
  fd.append('price', price);
  fd.append('original_price', origPrice);
  fd.append('color', color);
  fd.append('active', active);
  fd.append('service_ids', JSON.stringify(serviceIds));
  fd.append('features', featuresStr);

  fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(res.message || 'تم حفظ الباقة بنجاح', 'success');
        Modal.close('packageModal');
        location.reload();
      } else {
        Toast.show(res.message || 'حدث خطأ أثناء الحفظ', 'error');
      }
    })
    .catch(() => Toast.show('خطأ في الاتصال بالخادم', 'error'));
}

function togglePackage(id) {
  const p = packagesData.find(x => x.id === id);
  if (!p) return;
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('id', id);
  fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        p.active = res.active ? true : false;
        Toast.show(`تم ${p.active ? 'تفعيل' : 'إيقاف'} الباقة بنجاح`, p.active ? 'success' : 'error');
        renderPackages();
      } else {
        Toast.show(res.message || 'حدث خطأ', 'error');
      }
    })
    .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

function deletePackage(id, name) {
  document.getElementById('confirmMessage').textContent = `هل تريد حذف باقة "${name}" نهائياً؟`;
  Modal.open('confirmModal');
  document.getElementById('confirmBtn').onclick = function() {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          Toast.show('تم حذف الباقة بنجاح', 'success');
          Modal.close('confirmModal');
          location.reload();
        } else {
          Toast.show(res.message || 'حدث خطأ أثناء الحذف', 'error');
        }
      })
      .catch(() => Toast.show('خطأ في الاتصال بالسيرفر', 'error'));
  };
}

function escapeJs(str) {
  return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

const Modal = {
  open: (id) => { document.getElementById(id).classList.add('active'); },
  close: (id) => { document.getElementById(id).classList.remove('active'); },
};

document.addEventListener('DOMContentLoaded', () => {
  renderPackages();

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
