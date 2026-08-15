<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// جلب جميع الخدمات مع ترتيبها
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الخدمات - تواصل Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    /* =============================================
       إصلاح الرأس العلوي
       ============================================= */
    .main-content {
      padding-top: 70px !important;
    }
    .page-header {
      background: white;
      padding: 20px 24px;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    .page-header-title {
      font-size: 24px;
      font-weight: 800;
      color: #0f172a;
      margin: 0;
    }
    .page-header-subtitle {
      font-size: 14px;
      color: #64748b;
      margin: 4px 0 0;
    }
    .breadcrumb {
      font-size: 13px;
      color: #94a3b8;
      margin-bottom: 6px;
    }
    .breadcrumb a { color: #6366f1; text-decoration: none; }
    .breadcrumb span { margin: 0 4px; }
    .page-header-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    /* =============================================
       أنماط الشجرة
       ============================================= */
    .tree-level-1 { border-right: 4px solid #6366f1; background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
    .tree-level-2 { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 12px; margin-right: 16px; }
    .tree-level-3 { background: #f1f5f9; border-radius: 8px; padding: 8px 12px; margin-bottom: 6px; margin-right: 24px; display: flex; align-items: center; justify-content: space-between; transition: 0.2s; flex-wrap: wrap; gap: 8px; }
    .tree-level-3:hover { background: #e2e8f0; }
    .toggle-children { cursor: pointer; transition: transform 0.3s; display: inline-block; }
    .toggle-children.collapsed { transform: rotate(-90deg); }
    .children-container { overflow: hidden; transition: max-height 0.4s ease; max-height: 9999px; }
    .children-container.hidden { max-height: 0 !important; }
    .level-badge { font-size: 10px; padding: 2px 8px; border-radius: 20px; font-weight: 700; }
    .level-1-badge { background: #6366f1; color: white; }
    .level-2-badge { background: #8b5cf6; color: white; }
    .level-3-badge { background: #94a3b8; color: white; }
    .stat-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .form-input { border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; width: 100%; }
    .btn { padding: 8px 16px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer; transition: 0.2s; }
    .btn-primary { background: #6366f1; color: white; }
    .btn-primary:hover { background: #4f46e5; }
    .btn-outline { background: transparent; border: 1px solid #e2e8f0; }
    .btn-outline:hover { background: #f1f5f9; }
    .btn-sm { padding: 4px 10px; font-size: 13px; }
    .btn-danger { background: #ef4444; color: white; }
    .btn-danger:hover { background: #dc2626; }
    .badge { font-size: 12px; padding: 2px 10px; border-radius: 20px; }
    .badge-success { background: #dcfce7; color: #16a34a; }
    .badge-secondary { background: #f1f5f9; color: #64748b; }

    /* =============================================
       أزرار التبديل - تصميم مخصص لتجنب التعارض
       ============================================= */
    .custom-toggle-switch {
      position: relative;
      display: inline-block;
      width: 48px !important;      /* عرض أكبر قليلاً */
      height: 26px !important;
      flex-shrink: 0;
      vertical-align: middle;
      cursor: pointer;
    }
    /* إخفاء الـ checkbox الأصلي */
    .custom-toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
      position: absolute;
      pointer-events: none;
    }
    /* شريط التبديل */
    .custom-toggle-switch .toggle-slider-custom {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #cbd5e1 !important;
      border-radius: 34px;
      transition: background-color 0.3s ease;
      box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }
    /* الدائرة البيضاء */
    .custom-toggle-switch .toggle-slider-custom::before {
      content: "";
      position: absolute;
      height: 20px !important;      /* حجم الدائرة */
      width: 20px !important;
      left: 3px;
      bottom: 3px;
      background-color: #ffffff !important;
      border-radius: 50%;
      transition: transform 0.3s ease, background-color 0.3s ease;
      box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    }
    /* حالة التفعيل (checked) */
    .custom-toggle-switch input:checked + .toggle-slider-custom {
      background-color: #6366f1 !important;
    }
    .custom-toggle-switch input:checked + .toggle-slider-custom::before {
      transform: translateX(22px) !important; /* 48 - 20 - 3 - 3 = 22 */
    }
    /* تأثير التركيز */
    .custom-toggle-switch input:focus + .toggle-slider-custom {
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
    }

    /* =============================================
       أنماط المودال
       ============================================= */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 16px; max-width: 520px; width: 90%; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 18px; font-weight: 700; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
    .modal-body { padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #0f172a; }
    .icon-opt { font-size: 24px; cursor: pointer; padding: 6px; border-radius: 8px; border: 2px solid transparent; transition: 0.2s; }
    .icon-opt:hover { border-color: #6366f1; }
    .animate-fadeInUp { animation: fadeInUp 0.4s ease both; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="admin-layout">
  <?php include '../components/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include '../components/navbar.php'; ?>

    <div class="page-content" style="padding: 20px 24px;">
      <!-- رأس الصفحة -->
      <div class="page-header">
        <div>
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الخدمات</span></div>
          <h1 class="page-header-title">إدارة الخدمات (الهيكل الهرمي)</h1>
          <p class="page-header-subtitle">إدارة الأقسام والخدمات بثلاث مستويات (مرحلة ➜ قسم ➜ خدمة)</p>
        </div>
        <div class="page-header-actions">
          <select id="levelFilter" class="form-input" style="width:auto;padding:8px 12px;" onchange="renderServices()">
            <option value="all">الكل</option>
            <option value="1">المستوى 1</option>
            <option value="2">المستوى 2</option>
            <option value="3">المستوى 3</option>
          </select>
          <input type="text" id="searchInput" class="form-input" placeholder="🔍 بحث..." style="width:180px;" oninput="renderServices()">
          <button class="btn btn-primary" onclick="openAddService()">+ إضافة خدمة</button>
        </div>
      </div>

      <!-- شبكة العرض الهرمية -->
      <div id="servicesTreeContainer" style="margin-bottom:24px;"></div>
    </div>
  </div>
</div>

<!-- Modal الإضافة / التعديل -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="serviceModalTitle">إضافة خدمة جديدة</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editServiceId" value="" />
      <div class="form-group">
        <label class="form-label">اسم الخدمة</label>
        <input class="form-input" id="serviceName" placeholder="مثال: تحليل البيانات" />
      </div>
      <div class="form-group">
        <label class="form-label">الخدمة الأب (المستوى الأعلى)</label>
        <select class="form-input" id="parentIdSelect">
          <option value="">-- لا يوجد (مستوى أول) --</option>
        </select>
        <small style="color:var(--text-secondary);font-size:12px;">اختر الأب حسب المستوى المطلوب (لا يمكن إضافة مستوى رابع)</small>
      </div>
      <div class="form-group">
        <label class="form-label">الأيقونة</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px" id="iconPicker">
          <span class="icon-opt" style="border-color:#6366f1;" onclick="selectIcon(this,'📚')">📚</span>
          <span class="icon-opt" onclick="selectIcon(this,'⚙️')">⚙️</span>
          <span class="icon-opt" onclick="selectIcon(this,'🏛️')">🏛️</span>
          <span class="icon-opt" onclick="selectIcon(this,'📊')">📊</span>
          <span class="icon-opt" onclick="selectIcon(this,'✏️')">✏️</span>
          <span class="icon-opt" onclick="selectIcon(this,'📖')">📖</span>
          <span class="icon-opt" onclick="selectIcon(this,'🌐')">🌐</span>
          <span class="icon-opt" onclick="selectIcon(this,'📰')">📰</span>
          <span class="icon-opt" onclick="selectIcon(this,'💼')">💼</span>
          <span class="icon-opt" onclick="selectIcon(this,'🚀')">🚀</span>
        </div>
        <input type="hidden" id="serviceIcon" value="📚" />
      </div>
      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between">
        <label class="form-label" style="margin:0">تفعيل الخدمة</label>
        <label class="custom-toggle-switch">
          <input type="checkbox" id="serviceActive" checked />
          <span class="toggle-slider-custom"></span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="saveService()">حفظ الخدمة</button>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:64px;margin-bottom:16px">⚠️</div><p id="confirmMessage" style="color:var(--text-secondary);font-size:15px"></p></div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmBtn">تأكيد</button></div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// البيانات من PHP
const treeData = <?= json_encode($tree) ?>;
let flatData = <?= json_encode($services_flat) ?>;

let selectedIcon = '📚';
let editId = null;

// دوال مساعدة
function buildTreeFromFlat(flat, parentId, filterIds) {
    const branch = [];
    flat.forEach(item => {
        if (item.parent_id === parentId) {
            const hasChildren = flat.some(i => i.parent_id === item.id && filterIds.has(i.id));
            if (filterIds.has(item.id) || hasChildren) {
                const children = buildTreeFromFlat(flat, item.id, filterIds);
                const node = { ...item };
                if (children.length > 0) node.children = children;
                branch.push(node);
            }
        }
    });
    return branch;
}

function renderTree(tree, level = 1) {
    let html = '';
    tree.forEach((node, index) => {
        const hasChildren = node.children && node.children.length > 0;
        const childHtml = hasChildren ? renderTree(node.children, level + 1) : '';
        const orders = node.orders || 0;
        
        html += `<div class="tree-level-${level} animate-fadeInUp" style="animation-delay:${index*0.05}s;">`;
        html += `<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">`;
        html += `<div style="display:flex;align-items:center;gap:12px;">`;
        if (hasChildren) {
            html += `<span class="toggle-children" onclick="toggleChildren(this)" style="font-size:18px;">▼</span>`;
        } else {
            html += `<span style="width:24px;"></span>`;
        }
        html += `<span style="font-size:28px;">${node.icon}</span>`;
        html += `<div>`;
        html += `<span style="font-weight:700;font-size:${level === 1 ? '20px' : '16px'};color:var(--text-primary);">${node.name}</span>`;
        html += ` <span class="level-badge level-${level}-badge">المستوى ${level}</span>`;
        if (level === 3) {
            html += ` <span style="font-size:13px;color:var(--text-secondary);margin-right:8px;">📦 ${orders} طلب</span>`;
        }
        html += `</div>`;
        html += `</div>`;
        
        html += `<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">`;
        // زر التبديل باستخدام class مخصص
        html += `<label class="custom-toggle-switch" onclick="event.stopPropagation();">
                    <input type="checkbox" ${node.is_active ? 'checked' : ''} onchange="toggleService(${node.id})" />
                    <span class="toggle-slider-custom"></span>
                 </label>`;
        html += `<span class="badge ${node.is_active ? 'badge-success' : 'badge-secondary'}">${node.is_active ? 'نشط' : 'معطل'}</span>`;
        html += `<button class="btn btn-outline btn-sm" onclick="editService(${node.id})">✏️</button>`;
        html += `<button class="btn btn-sm" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none;" onclick="deleteService(${node.id},'${node.name}')">🗑</button>`;
        html += `</div>`;
        html += `</div>`;
        
        if (hasChildren) {
            html += `<div class="children-container" id="children-${node.id}">${childHtml}</div>`;
        }
        html += `</div>`;
    });
    return html;
}

function renderServices() {
    const container = document.getElementById('servicesTreeContainer');
    const levelFilter = document.getElementById('levelFilter').value;
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();

    let filtered = flatData;
    if (levelFilter !== 'all') {
        filtered = filtered.filter(item => item.level == levelFilter);
    }
    if (searchTerm) {
        filtered = filtered.filter(item => item.name.includes(searchTerm));
    }
    const filteredIds = new Set(filtered.map(i => i.id));
    const finalTree = buildTreeFromFlat(flatData, null, filteredIds);
    
    if (finalTree.length === 0) {
        container.innerHTML = `<div class="stat-card" style="text-align:center;padding:40px;">لا توجد خدمات تطابق معايير البحث</div>`;
        return;
    }
    container.innerHTML = renderTree(finalTree);
}

function toggleChildren(el) {
    const container = el.closest('.tree-level-1')?.querySelector('.children-container') || 
                      el.closest('.tree-level-2')?.querySelector('.children-container');
    if (container) {
        container.classList.toggle('hidden');
        el.classList.toggle('collapsed');
    }
}

// عمليات AJAX
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
                Toast.show(`تم ${s.is_active ? 'تفعيل' : 'إيقاف'} الخدمة`, s.is_active ? 'success' : 'warning');
                renderServices();
            } else {
                Toast.show(res.message || 'حدث خطأ', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

function openAddService() {
    editId = null;
    document.getElementById('serviceModalTitle').textContent = 'إضافة خدمة جديدة';
    document.getElementById('serviceName').value = '';
    document.getElementById('serviceActive').checked = true;
    selectedIcon = '📚';
    document.querySelectorAll('.icon-opt').forEach(e => e.style.borderColor = 'transparent');
    document.querySelector('.icon-opt').style.borderColor = '#6366f1';
    populateParentSelect(null);
    Modal.open('serviceModal');
}

function editService(id) {
    const s = flatData.find(x => x.id === id);
    if (!s) return;
    editId = id;
    document.getElementById('serviceModalTitle').textContent = 'تعديل الخدمة';
    document.getElementById('serviceName').value = s.name;
    document.getElementById('serviceActive').checked = s.is_active;
    selectedIcon = s.icon;
    document.querySelectorAll('.icon-opt').forEach(e => {
        e.style.borderColor = e.textContent.trim() === s.icon ? '#6366f1' : 'transparent';
    });
    populateParentSelect(s.parent_id);
    Modal.open('serviceModal');
}

function populateParentSelect(selectedId) {
    const select = document.getElementById('parentIdSelect');
    select.innerHTML = '<option value="">-- لا يوجد (مستوى أول) --</option>';
    const parents = flatData.filter(item => item.level < 3);
    parents.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        let prefix = (p.level === 1) ? '📌 ' : '  └─ ';
        opt.textContent = prefix + p.name;
        if (selectedId && p.id === selectedId) opt.selected = true;
        select.appendChild(opt);
    });
}

function selectIcon(el, icon) {
    document.querySelectorAll('.icon-opt').forEach(e => e.style.borderColor = 'transparent');
    el.style.borderColor = '#6366f1';
    selectedIcon = icon;
    document.getElementById('serviceIcon').value = icon;
}

function saveService() {
    const name = document.getElementById('serviceName').value.trim();
    if (!name) { Toast.show('يرجى إدخال اسم الخدمة', 'error'); return; }
    const parentId = document.getElementById('parentIdSelect').value;
    const active = document.getElementById('serviceActive').checked ? 1 : 0;
    
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', editId || 0);
    fd.append('name', name);
    fd.append('icon', selectedIcon);
    fd.append('active', active);
    fd.append('parent_id', parentId || '');
    
    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show(res.message || 'تم الحفظ بنجاح', 'success');
                Modal.close('serviceModal');
                location.reload();
            } else {
                Toast.show(res.message || 'حدث خطأ', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

function deleteService(id, name) {
    Modal.confirm('حذف الخدمة', `هل تريد حذف "${name}" وجميع فروعها؟`, () => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Toast.show('تم الحذف', 'success');
                    location.reload();
                } else {
                    Toast.show(res.message || 'حدث خطأ', 'error');
                }
            })
            .catch(() => Toast.show('خطأ في الاتصال', 'error'));
    });
}

// تهيئة الصفحة
document.addEventListener('DOMContentLoaded', function() {
    renderServices();
    document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
    document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});
    setTimeout(() => {
        document.querySelectorAll('.tree-level-1 .children-container').forEach(el => el.classList.remove('hidden'));
    }, 300);
});
</script>
</body>
</html>ئ