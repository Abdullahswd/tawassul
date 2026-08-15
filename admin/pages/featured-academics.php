<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// جلب بيانات الأكاديميين المتميزين
$stmt = $db->query("SELECT * FROM featured_academics ORDER BY sort_order ASC, id ASC");
$featured = $stmt->fetchAll();

$featured = array_map(function($f){
    $f['id']        = (int)$f['id'];
    $f['is_active'] = (bool)$f['is_active'];
    $f['sort_order']= (int)$f['sort_order'];
    return $f;
}, $featured);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الأكاديميون المتميزون - تواصل Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    .main-content { padding-top: 70px !important; }
    .page-header { background: white; padding: 20px 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .page-header-title { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; }
    .page-header-subtitle { font-size: 14px; color: #64748b; margin: 4px 0 0; }
    .breadcrumb { font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
    .breadcrumb a { color: #6366f1; text-decoration: none; }
    .breadcrumb span { margin: 0 4px; }
    .page-header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .form-input { border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; width: 100%; }
    .btn { padding: 8px 16px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer; transition: 0.2s; }
    .btn-primary { background: #6366f1; color: white; }
    .btn-primary:hover { background: #4f46e5; }
    .btn-outline { background: transparent; border: 1px solid #e2e8f0; }
    .btn-outline:hover { background: #f1f5f9; }
    .btn-sm { padding: 4px 10px; font-size: 13px; }
    .btn-danger { background: #ef4444; color: white; }
    .btn-danger:hover { background: #dc2626; }

    .fa-card { background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; display: flex; gap: 16px; align-items: center; margin-bottom: 14px; transition: 0.2s; }
    .fa-card:hover { box-shadow: 0 6px 16px rgba(99,102,241,.12); border-color: #c7d2fe; }
    .fa-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #e0e7ff; flex-shrink: 0; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 26px; color: #94a3b8; }
    .custom-toggle-switch { position: relative; display: inline-block; width: 46px !important; height: 24px !important; flex-shrink: 0; cursor: pointer; }
    .custom-toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; pointer-events: none; }
    .custom-toggle-switch .toggle-slider-custom { position: absolute; inset: 0; background-color: #cbd5e1 !important; border-radius: 34px; transition: background-color .3s; }
    .custom-toggle-switch .toggle-slider-custom::before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: transform .3s; }
    .custom-toggle-switch input:checked + .toggle-slider-custom { background-color: #6366f1 !important; }
    .custom-toggle-switch input:checked + .toggle-slider-custom::before { transform: translateX(22px); }
    .thumb-preview { width: 88px; height: 88px; border-radius: 12px; object-fit: cover; border: 2px dashed #cbd5e1; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 13px; margin-top: 8px; }
  </style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="admin-layout">
  <?php include '../components/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include '../components/navbar.php'; ?>

    <div class="page-content" style="padding: 20px 24px;">
      <div class="page-header">
        <div>
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الأكاديميون المتميزون</span></div>
          <h1 class="page-header-title">الأكاديميون المتميزون</h1>
          <p class="page-header-subtitle">إدارة بطاقات المستشارين المتميزين المعروضة في الصفحة الرئيسية</p>
        </div>
        <div class="page-header-actions">
          <button class="btn btn-primary" onclick="openAdd()">+ إضافة أكاديمي</button>
        </div>
      </div>

      <!-- شبكة البطاقات -->
      <div id="featuredList"></div>
    </div>
  </div>
</div>

<!-- Modal الإضافة / التعديل -->
<div class="modal-overlay" id="featuredModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="featuredModalTitle">إضافة أكاديمي متميز</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editId" value="" />

      <div class="form-group">
        <label class="form-label">الاسم</label>
        <input class="form-input" id="fName" placeholder="مثال: د. محمد النفيش" />
      </div>

      <div class="form-group">
        <label class="form-label">التخصص</label>
        <input class="form-input" id="fSpecialty" placeholder="مثال: أستاذ إدارة الأعمال المساعد" />
      </div>

      <div class="form-group">
        <label class="form-label">الصورة</label>
        <input type="file" class="form-input" id="fImage" accept="image/*" />
        <div class="thumb-preview" id="imagePreview">لا توجد صورة</div>
        <small style="color:#94a3b8;font-size:12px;">الصيغ المدعومة: JPG / PNG / GIF / WEBP</small>
      </div>

      <div class="form-group">
        <label class="form-label">النبذة</label>
        <textarea class="form-input" id="fBio" rows="4" placeholder="نبذة تعريفية عن الأكاديمي..."></textarea>
      </div>

      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between">
        <label class="form-label" style="margin:0">نشط (يظهر في الموقع)</label>
        <label class="custom-toggle-switch">
          <input type="checkbox" id="fActive" checked />
          <span class="toggle-slider-custom"></span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="saveFeatured()">حفظ</button>
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
const featuredData = <?= json_encode($featured) ?>;
let editId = null;
let currentImage = '';
let pendingImage = '';

function imageUrl(path) {
    if (!path) return '';
    return '../../' + path;
}

function renderFeatured() {
    const container = document.getElementById('featuredList');
    if (featuredData.length === 0) {
        container.innerHTML = `<div style="background:white;text-align:center;padding:40px;border-radius:14px;border:1px dashed #e2e8f0;color:#94a3b8;">لا يوجد أكاديميون متميزون بعد. اضغط "إضافة أكاديمي".</div>`;
        return;
    }
    container.innerHTML = featuredData.map((f) => `
        <div class="fa-card">
          ${f.image
            ? `<img class="fa-avatar" src="${imageUrl(f.image)}" alt="${f.name}" onerror="this.style.visibility='hidden';this.nextElementSibling.style.display='flex';"/><div class="fa-avatar" style="display:none;">🎓</div>`
            : `<div class="fa-avatar">🎓</div>`}
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <h3 style="margin:0;font-size:17px;font-weight:800;color:var(--text-primary);">${f.name}</h3>
              <span style="font-size:12px;color:#94a3b8;">#${String(f.sort_order).padStart(2,'0')}</span>
            </div>
            <p style="margin:4px 0 0;font-size:13px;font-weight:700;color:#6366f1;">${f.specialty || ''}</p>
            <p style="margin:6px 0 0;font-size:13px;color:var(--text-secondary);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${f.bio || ''}</p>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <label class="custom-toggle-switch">
              <input type="checkbox" ${f.is_active ? 'checked' : ''} onchange="toggleFeatured(${f.id})" />
              <span class="toggle-slider-custom"></span>
            </label>
            <button class="btn btn-outline btn-sm" onclick="editFeatured(${f.id})">✏️</button>
            <button class="btn btn-sm" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none;" onclick="deleteFeatured(${f.id},'${String(f.name).replace(/'/g,"\\'")}')">🗑</button>
          </div>
        </div>
    `).join('');
}
function openAdd() {
    editId = null;
    currentImage = '';
    pendingImage = '';
    document.getElementById('featuredModalTitle').textContent = 'إضافة أكاديمي متميز';
    document.getElementById('editId').value = '';
    document.getElementById('fName').value = '';
    document.getElementById('fSpecialty').value = '';
    document.getElementById('fBio').value = '';
    document.getElementById('fActive').checked = true;
    document.getElementById('fImage').value = '';
    const pv = document.getElementById('imagePreview');
    pv.innerHTML = 'لا توجد صورة';
    pv.style.backgroundImage = '';
    Modal.open('featuredModal');
}

function editFeatured(id) {
    const f = featuredData.find(x => x.id === id);
    if (!f) return;
    editId = id;
    currentImage = f.image || '';
    pendingImage = '';
    document.getElementById('featuredModalTitle').textContent = 'تعديل أكاديمي';
    document.getElementById('editId').value = id;
    document.getElementById('fName').value = f.name;
    document.getElementById('fSpecialty').value = f.specialty || '';
    document.getElementById('fBio').value = f.bio || '';
    document.getElementById('fActive').checked = f.is_active;
    document.getElementById('fImage').value = '';
    const pv = document.getElementById('imagePreview');
    pv.style.backgroundImage = imageUrl(f.image) ? `url('${imageUrl(f.image)}')` : '';
    pv.style.backgroundSize = 'cover';
    pv.style.backgroundPosition = 'center';
    pv.innerHTML = imageUrl(f.image) ? '' : 'لا توجد صورة';
    Modal.open('featuredModal');
}

// رفع الصورة بمجرد اختيارها
document.getElementById('fImage').addEventListener('change', function() {
    if (!this.files || !this.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('image', this.files[0]);
    Toast.show('جاري رفع الصورة...', 'info');
    fetch('../ajax/manage_featured_academic.php', { method: 'POST', body: fd })
        .then(r => r.text().then(t => ({ ok: r.ok, text: t })))
        .then(({ ok, text }) => {
            let res;
            try {
                res = JSON.parse(text);
            } catch (e) {
                Toast.show('استجابة غير صالحة من الخادم: ' + (text || ok ? '' : 'فشل الاتصال').slice(0, 120), 'error');
                return;
            }
            if (res.success) {
                pendingImage = res.image;
                const pv = document.getElementById('imagePreview');
                pv.style.backgroundImage = `url('${imageUrl(res.image)}')`;
                pv.style.backgroundSize = 'cover';
                pv.style.backgroundPosition = 'center';
                pv.innerHTML = '';
                Toast.show('تم رفع الصورة', 'success');
            } else {
                Toast.show(res.message || 'فشل رفع الصورة', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في رفع الصورة - تحقق من اتصالك بالخادم', 'error'));
});

function saveFeatured() {
    const name = document.getElementById('fName').value.trim();
    if (!name) { Toast.show('يرجى إدخال اسم الأكاديمي', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', editId || 0);
    fd.append('name', name);
    fd.append('specialty', document.getElementById('fSpecialty').value.trim());
    fd.append('bio', document.getElementById('fBio').value.trim());
    fd.append('image', pendingImage);
    fd.append('active', document.getElementById('fActive').checked ? 1 : 0);

    fetch('../ajax/manage_featured_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show('تم الحفظ بنجاح', 'success');
                Modal.close('featuredModal');
                location.reload();
            } else {
                Toast.show(res.message || 'حدث خطأ', 'error');
            }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
}

function toggleFeatured(id) {
    const f = featuredData.find(x => x.id === id);
    if (!f) return;
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('id', id);
    fetch('../ajax/manage_featured_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                f.is_active = res.active;
                Toast.show(res.active ? 'تم التفعيل' : 'تم الإيقاف', res.active ? 'success' : 'warning');
                renderFeatured();
            } else { Toast.show(res.message || 'خطأ', 'error'); }
        })
        .catch(() => Toast.show('خطأ', 'error'));
}

function deleteFeatured(id, name) {
    Modal.confirm('حذف الأكاديمي', `هل تريد حذف "${name}"؟`, () => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('../ajax/manage_featured_academic.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) { Toast.show('تم الحذف', 'success'); location.reload(); }
                else { Toast.show(res.message || 'خطأ', 'error'); }
            })
            .catch(() => Toast.show('خطأ', 'error'));
    });
}

document.addEventListener('DOMContentLoaded', function() {
    renderFeatured();
    document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
    document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});
});
</script>
</body>
</html>