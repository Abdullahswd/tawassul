<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Fetch all services
$services_stmt = $db->query("
    SELECT s.id, s.name, s.icon, s.is_active AS active,
           (SELECT COUNT(*) FROM orders o WHERE o.service_id = s.id) AS orders
    FROM services s
    ORDER BY s.id ASC
");
$services = $services_stmt->fetchAll();

$services = array_map(function($s) {
    $s['id'] = (int)$s['id'];
    $s['active'] = (bool)$s['active'];
    $s['orders'] = (int)$s['orders'];
    return $s;
}, $services);
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
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="admin-layout">

  <?php include '../components/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <?php include '../components/navbar.php'; ?>

    <div class="page-content">
      <div class="page-header animate-fadeInUp">
        <div>
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الخدمات</span></div>
          <h1 class="page-header-title">إدارة الخدمات</h1>
          <p class="page-header-subtitle">إدارة أقسام الخدمات الأكاديمية</p>
        </div>
        <div class="page-header-actions">
          <button class="btn btn-primary" onclick="openAddService()">+ إضافة خدمة</button>
        </div>
      </div>

      <!-- Services Grid -->
      <div id="servicesGrid" class="grid-responsive-2" style="margin-bottom:24px"></div>

    </div>
  </div>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3 class="modal-title" id="serviceModalTitle">إضافة خدمة جديدة</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="editServiceId" value="" />
      <div class="form-group"><label class="form-label">اسم الخدمة</label><input class="form-input" id="serviceName" placeholder="مثال: الأبحاث والدراسات" /></div>
      <div class="form-group"><label class="form-label">الأيقونة</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px" id="iconPicker">
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent;transition:all 0.2s" onclick="selectIcon(this,'🔬')">🔬</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'📜')">📜</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'🌐')">🌐</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'✏️')">✏️</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'📊')">📊</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'📋')">📋</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'📚')">📚</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'💻')">💻</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'🎨')">🎨</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'💬')">💬</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'📰')">📰</span>
          <span class="icon-opt" style="font-size:24px;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent" onclick="selectIcon(this,'🎓')">🎓</span>
        </div>
        <input type="hidden" id="serviceIcon" value="🔬" />
      </div>
      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between">
        <label class="form-label" style="margin:0">تفعيل الخدمة</label>
        <label class="toggle-switch"><input type="checkbox" id="serviceActive" checked /><span class="toggle-slider"></span></label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" id="serviceSubmitBtn" onclick="saveService()">حفظ الخدمة</button>
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
MOCK_DATA.services = <?= json_encode($services) ?>;

document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

let selectedIcon = '🔬';
let editId = null;

function selectIcon(el, icon) {
  document.querySelectorAll('.icon-opt').forEach(e => e.style.borderColor = 'transparent');
  el.style.borderColor = '#6366f1';
  selectedIcon = icon;
  document.getElementById('serviceIcon').value = icon;
}

function renderServices() {
  const grid = document.getElementById('servicesGrid');
  if (!grid) return;
  grid.innerHTML = MOCK_DATA.services.map((s, i) => `
    <div class="stat-card animate-fadeInUp" style="padding:0;overflow:hidden;animation-delay:${i*0.06}s;opacity:0">
      <div style="padding:20px 20px 0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
          <div style="font-size:40px">${s.icon}</div>
          <label class="toggle-switch" onclick="event.stopPropagation()">
            <input type="checkbox" ${s.active ? 'checked' : ''} onchange="toggleService(${s.id})" />
            <span class="toggle-slider"></span>
          </label>
        </div>
        <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:4px">${s.name}</h3>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">قسم خدمة أكاديمية</p>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--border-color)">
          <div>
            <span style="font-size:22px;font-weight:800;color:var(--primary)">${s.orders}</span>
            <span style="font-size:12px;color:var(--text-secondary);margin-right:4px">طلب</span>
          </div>
          <span class="badge ${s.active ? 'badge-success' : 'badge-secondary'}">${s.active ? 'نشط' : 'معطل'}</span>
        </div>
      </div>
      <div style="padding:12px 20px;background:var(--bg-main);display:flex;gap:8px">
        <button class="btn btn-outline btn-sm" style="flex:1" onclick="editService(${s.id})">✏️ تعديل</button>
        <button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none" onclick="deleteService(${s.id},'${s.name}')">🗑</button>
      </div>
    </div>
  `).join('');
}

function toggleService(id) {
  const s = MOCK_DATA.services.find(x => x.id === id);
  if (!s) return;
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('id', id);
  fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        s.active = res.active ? true : false;
        Toast.show(`تم ${s.active ? 'تفعيل' : 'إيقاف'} خدمة ${s.name}`, s.active ? 'success' : 'warning');
        renderServices();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
        renderServices();
      }
    })
    .catch(() => {
      Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
      renderServices();
    });
}

function openAddService() {
  editId = null;
  document.getElementById('serviceModalTitle').textContent = 'إضافة خدمة جديدة';
  document.getElementById('serviceName').value = '';
  document.getElementById('serviceActive').checked = true;
  selectedIcon = '🔬';
  document.querySelectorAll('.icon-opt').forEach(e => e.style.borderColor = 'transparent');
  document.querySelector('.icon-opt').style.borderColor = '#6366f1';
  Modal.open('serviceModal');
}

function editService(id) {
  const s = MOCK_DATA.services.find(x => x.id === id);
  if (!s) return;
  editId = id;
  document.getElementById('serviceModalTitle').textContent = 'تعديل الخدمة';
  document.getElementById('serviceName').value = s.name;
  document.getElementById('serviceActive').checked = s.active;
  selectedIcon = s.icon;
  document.querySelectorAll('.icon-opt').forEach(e => {
    e.style.borderColor = e.textContent.trim() === s.icon ? '#6366f1' : 'transparent';
  });
  Modal.open('serviceModal');
}

function saveService() {
  const name = document.getElementById('serviceName').value.trim();
  if (!name) { Toast.show('يرجى إدخال اسم الخدمة', 'error'); return; }
  const icon = selectedIcon;
  const active = document.getElementById('serviceActive').checked ? 1 : 0;
  
  const fd = new FormData();
  fd.append('action', 'save');
  fd.append('id', editId || 0);
  fd.append('name', name);
  fd.append('icon', icon);
  fd.append('active', active);
  
  fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (editId) {
          const s = MOCK_DATA.services.find(x => x.id === editId);
          if (s) { s.name = name; s.icon = icon; s.active = active ? true : false; }
          Toast.show('تم تحديث الخدمة بنجاح', 'success');
        } else {
          MOCK_DATA.services.push({ id: parseInt(res.id), name, icon, orders: 0, active: active ? true : false });
          Toast.show('تم إضافة الخدمة بنجاح', 'success');
        }
        Modal.close('serviceModal');
        renderServices();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
      }
    })
    .catch(() => {
      Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
    });
}

function deleteService(id, name) {
  Modal.confirm('حذف الخدمة', `هل تريد حذف خدمة "${name}"؟`, () => {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('../ajax/manage_service.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const idx = MOCK_DATA.services.findIndex(s => s.id === id);
          if (idx > -1) MOCK_DATA.services.splice(idx, 1);
          Toast.show('تم حذف الخدمة', 'success');
          renderServices();
        } else {
          Toast.show(res.message || 'حدث خطأ ما', 'error');
        }
      })
      .catch(() => {
        Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
      });
  });
}

document.addEventListener('DOMContentLoaded', renderServices);
</script>
</body>
</html>
