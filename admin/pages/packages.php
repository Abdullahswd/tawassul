<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Fetch packages
$packages_stmt = $db->query("
    SELECT p.id, p.name, p.price, p.color, p.features_json, p.is_active AS active,
           (SELECT COUNT(*) FROM orders o WHERE o.package_id = p.id) AS orders
    FROM packages p
    ORDER BY p.price ASC
");
$packages = $packages_stmt->fetchAll();

$packages = array_map(function($p) {
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['active'] = (bool)$p['active'];
    $p['orders'] = (int)$p['orders'];
    $p['features'] = json_decode($p['features_json'] ?? '[]', true);
    if (!is_array($p['features'])) {
        $p['features'] = [];
    }
    return $p;
}, $packages);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الباقات - تواصل Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الباقات</span></div>
          <h1 class="page-header-title">إدارة الباقات</h1>
          <p class="page-header-subtitle">تسعير وتخصيص باقات الخدمات الأكاديمية</p>
        </div>
        <button class="btn btn-primary" onclick="openEditPackage(null)">+ إضافة باقة</button>
      </div>

      <!-- Packages Grid -->
      <div id="packagesGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;margin-bottom:28px"></div>

      <!-- Orders Per Package Chart -->
      <div class="chart-card animate-fadeInUp delay-3">
        <div class="chart-header">
          <h3 class="chart-title">📊 توزيع الطلبات حسب الباقة</h3>
        </div>
        <canvas id="packagesChart" style="width:100%;height:220px;display:block"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Edit Package Modal -->
<div class="modal-overlay" id="packageModal">
  <div class="modal-box" style="max-width:540px">
    <div class="modal-header"><h3 class="modal-title" id="packageModalTitle">تعديل الباقة</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="editPackageId" />
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group"><label class="form-label">اسم الباقة</label><input class="form-input" id="pkgName" /></div>
        <div class="form-group"><label class="form-label">السعر (ريال سعودي)</label><input class="form-input" id="pkgPrice" type="number" /></div>
        <div class="form-group"><label class="form-label">لون الباقة</label><input class="form-input" id="pkgColor" type="color" /></div>
        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:22px">
          <label class="form-label" style="margin:0">تفعيل الباقة</label>
          <label class="toggle-switch"><input type="checkbox" id="pkgActive" checked /><span class="toggle-slider"></span></label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">المميزات (سطر لكل ميزة)</label>
        <textarea class="form-input" id="pkgFeatures" rows="5" placeholder="مهمة واحدة&#10;مراجعة بسيطة&#10;تسليم خلال 7 أيام"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="savePackage()">حفظ</button>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px"><div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div><div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:64px;margin-bottom:16px">⚠️</div><p id="confirmMessage" style="color:var(--text-secondary);font-size:15px"></p></div><div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmBtn">تأكيد</button></div></div>
</div>

<script src="../assets/js/main.js"></script>
<script>
MOCK_DATA.packages = <?= json_encode($packages) ?>;

document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

const tierIcons = ['🌱','🚀','🔍','📦','👑'];

function renderPackages() {
  const grid = document.getElementById('packagesGrid');
  if (!grid) return;
  grid.innerHTML = MOCK_DATA.packages.map((p, i) => `
    <div class="stat-card animate-fadeInUp" style="padding:0;overflow:hidden;border-top:4px solid ${p.color};animation-delay:${i*0.1}s;opacity:0">
      <div style="padding:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div style="font-size:32px">${tierIcons[i] || '📦'}</div>
          <div style="display:flex;align-items:center;gap:8px">
            ${p.id === 4 ? '<span class="badge badge-warning">الأكثر شيوعاً</span>' : ''}
            <label class="toggle-switch"><input type="checkbox" ${p.active ? 'checked' : ''} onchange="togglePackage(${p.id})" /><span class="toggle-slider"></span></label>
          </div>
        </div>
        <h3 style="font-size:22px;font-weight:800;color:var(--text-primary);margin-bottom:4px">باقة ${p.name}</h3>
        <div style="font-size:32px;font-weight:900;color:${p.color};margin-bottom:4px">${p.price} <span style="font-size:16px;font-weight:500;color:var(--text-secondary)">ر.س</span></div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:20px"><strong style="color:var(--primary)">${p.orders}</strong> طلب مكتمل</div>
        <div class="divider"></div>
        <ul style="list-style:none;margin-top:16px">
          ${(p.features || []).map(f => `<li style="display:flex;align-items:center;gap:8px;padding:6px 0;color:var(--text-primary);font-size:14px"><span style="width:20px;height:20px;border-radius:50%;background:${p.color}22;color:${p.color};display:inline-flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0">✓</span>${f}</li>`).join('')}
        </ul>
      </div>
      <div style="padding:12px 20px;background:var(--bg-main);display:flex;gap:8px">
        <button class="btn btn-sm" style="flex:1;background:${p.color}22;color:${p.color};border:1px solid ${p.color}44" onclick="openEditPackage(${p.id})">✏️ تعديل</button>
        <button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none" onclick="deletePackage(${p.id},'${p.name}')">🗑</button>
      </div>
    </div>
  `).join('');
}

function togglePackage(id) {
  const p = MOCK_DATA.packages.find(x => x.id === id);
  if (!p) return;
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('id', id);
  fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        p.active = res.active ? true : false;
        Toast.show(`تم ${p.active ? 'تفعيل' : 'إيقاف'} باقة ${p.name}`, p.active ? 'success' : 'warning');
        renderPackages();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
        renderPackages();
      }
    })
    .catch(() => {
      Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
      renderPackages();
    });
}

function openEditPackage(id) {
  document.getElementById('editPackageId').value = id || '';
  if (id) {
    const p = MOCK_DATA.packages.find(x => x.id === id);
    if (!p) return;
    document.getElementById('packageModalTitle').textContent = `تعديل باقة ${p.name}`;
    document.getElementById('pkgName').value = p.name;
    document.getElementById('pkgPrice').value = p.price;
    document.getElementById('pkgColor').value = p.color;
    document.getElementById('pkgActive').checked = p.active;
    document.getElementById('pkgFeatures').value = (p.features || []).join('\n');
  } else {
    document.getElementById('packageModalTitle').textContent = 'إضافة باقة جديدة';
    document.getElementById('pkgName').value = '';
    document.getElementById('pkgPrice').value = '';
    document.getElementById('pkgColor').value = '#6366f1';
    document.getElementById('pkgActive').checked = true;
    document.getElementById('pkgFeatures').value = '';
  }
  Modal.open('packageModal');
}

function savePackage() {
  const id = parseInt(document.getElementById('editPackageId').value);
  const name = document.getElementById('pkgName').value.trim();
  const price = parseFloat(document.getElementById('pkgPrice').value);
  const color = document.getElementById('pkgColor').value;
  const active = document.getElementById('pkgActive').checked ? 1 : 0;
  const featuresStr = document.getElementById('pkgFeatures').value;
  const features = featuresStr.split('\n').map(f => f.trim()).filter(f => f);
  
  if (!name || isNaN(price) || price <= 0) { Toast.show('يرجى ملء جميع الحقول بصورة صحيحة', 'error'); return; }
  
  const fd = new FormData();
  fd.append('action', 'save');
  fd.append('id', id || 0);
  fd.append('name', name);
  fd.append('price', price);
  fd.append('color', color);
  fd.append('active', active);
  fd.append('features', features.join('\n'));
  
  fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (id) {
          const p = MOCK_DATA.packages.find(x => x.id === id);
          if (p) Object.assign(p, { name, price, color, active: active ? true : false, features });
          Toast.show('تم تحديث الباقة بنجاح', 'success');
        } else {
          MOCK_DATA.packages.push({ id: parseInt(res.id), name, price, color, features, active: active ? true : false, orders: 0 });
          Toast.show('تم إضافة الباقة بنجاح', 'success');
        }
        Modal.close('packageModal');
        renderPackages();
        drawChart();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
      }
    })
    .catch(() => {
      Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
    });
}

function deletePackage(id, name) {
  Modal.confirm('حذف الباقة', `هل تريد حذف باقة "${name}"؟`, () => {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('../ajax/manage_package.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const idx = MOCK_DATA.packages.findIndex(p => p.id === id);
          if (idx > -1) MOCK_DATA.packages.splice(idx, 1);
          Toast.show('تم حذف الباقة بنجاح', 'success');
          renderPackages();
          drawChart();
        } else {
          Toast.show(res.message || 'حدث خطأ ما', 'error');
        }
      })
      .catch(() => {
        Toast.show('حدث خطأ في الاتصال بالخادم', 'error');
      });
  });
}

function drawChart() {
  const names = MOCK_DATA.packages.map(p => `باقة ${p.name}`);
  const orders = MOCK_DATA.packages.map(p => p.orders);
  Charts.drawBarChart('packagesChart', orders, names, '#6366f1');
}

document.addEventListener('DOMContentLoaded', () => {
  renderPackages();
  setTimeout(drawChart, 200);
});
window.addEventListener('resize', drawChart);
</script>
</body>
</html>
