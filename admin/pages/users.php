<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// جلب جميع الطلاب مع بيانات الطلبات والإنفاق
$students_stmt = $db->query("
    SELECT u.id, u.name, u.email, u.phone, u.status,
           COALESCE(u.avatar_initials, SUBSTRING(u.name,1,1)) AS avatar,
           DATE(u.created_at) AS joined,
           (SELECT COUNT(*) FROM orders o WHERE o.student_id = u.id) AS orders,
           (SELECT COALESCE(SUM(o.amount), 0) FROM orders o WHERE o.student_id = u.id AND o.status = 'completed') AS spending
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
");
$students = $students_stmt->fetchAll();

$students = array_map(function($s) {
    $s['id'] = (int)$s['id'];
    $s['orders'] = (int)$s['orders'];
    $s['spending'] = (float)$s['spending'];
    return $s;
}, $students);

$total_students = count($students);
$active_students = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
$suspended_students = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'suspended'")->fetchColumn();
$new_students_month = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الطلاب - تواصل Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الطلاب</span></div>
          <h1 class="page-header-title">إدارة الطلاب</h1>
          <p class="page-header-subtitle">عرض وإدارة حسابات الطلاب المسجلين على المنصة</p>
        </div>
        <button class="btn btn-primary" onclick="Modal.open('addUserModal')">+ إضافة طالب</button>
      </div>

      <!-- إحصائيات -->
      <div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px">
        <div class="stat-card"><div class="card-value"><?= $total_students ?></div><div class="card-label">إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="card-value"><?= $active_students ?></div><div class="card-label">نشطون</div></div>
        <div class="stat-card"><div class="card-value"><?= $suspended_students ?></div><div class="card-label">معلقون</div></div>
        <div class="stat-card"><div class="card-value"><?= $new_students_month ?></div><div class="card-label">جدد هذا الشهر</div></div>
      </div>

      <!-- جدول الطلاب -->
      <div class="table-container animate-fadeInUp">
        <div class="table-header">
          <h3 class="table-title">قائمة الطلاب</h3>
          <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="userSearch" placeholder="بحث..."></div>
          <select class="form-input form-select" id="statusFilter">
            <option value="all">جميع الحالات</option>
            <option value="active">نشط</option>
            <option value="suspended">معلق</option>
          </select>
          <button class="btn btn-outline btn-sm" onclick="exportTable()">📤 تصدير CSV</button>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead><tr><th><input type="checkbox" id="selectAll"></th><th>الطالب</th><th>البريد</th><th>الهاتف</th><th>الطلبات</th><th>الحالة</th><th>تاريخ التسجيل</th><th>الإجراءات</th></tr></thead>
            <tbody id="usersTableBody"></tbody>
          </table>
        </div>
        <div class="pagination" id="tablePagination"></div>
      </div>
    </div>
  </div>
</div>

<!-- مودال عرض التفاصيل -->
<div class="modal-overlay" id="viewUserModal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header"><h3 class="modal-title">تفاصيل الطالب</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" id="viewUserContent"></div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إغلاق</button><button class="btn btn-primary" id="editFromViewBtn">تعديل</button></div>
  </div>
</div>

<!-- مودال إضافة / تعديل طالب -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3 class="modal-title" id="userModalTitle">إضافة طالب جديد</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="editUserId" value="0">
      <div class="form-group"><label class="form-label">الاسم الكامل</label><input class="form-input" id="userName"></div>
      <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input" type="email" id="userEmail"></div>
      <div class="form-group"><label class="form-label">رقم الهاتف</label><input class="form-input" id="userPhone"></div>
      <div class="form-group"><label class="form-label" id="passwordLabel">كلمة المرور</label><input class="form-input" type="password" id="userPassword" placeholder="••••••••"><small class="form-text" id="passwordHint" style="display:none;">اتركها فارغة إذا لم ترد تغييرها</small></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-primary" id="saveUserBtn">حفظ</button></div>
  </div>
</div>

<!-- مودال التأكيد -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:64px">⚠️</div><p id="confirmMessage"></p></div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmBtn">تأكيد</button></div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// بيانات الطلاب من الخادم
let studentsData = <?= json_encode($students) ?>;
let filteredData = [...studentsData];
let currentPage = 1;
const rowsPerPage = 10;

function getAvatarColor(i) { const c=['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec489a']; return c[i%c.length]; }
function getStatusBadge(s) { return s==='active'?'<span class="badge badge-success">نشط</span>':'<span class="badge badge-danger">معلق</span>'; }
function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m){if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

function renderTable() {
  const tbody = document.getElementById('usersTableBody');
  if(!tbody) return;
  const start = (currentPage-1)*rowsPerPage;
  const pageData = filteredData.slice(start, start+rowsPerPage);
  if(pageData.length===0) { tbody.innerHTML='<tr><td colspan="8" style="text-align:center;padding:40px">لا توجد نتائج</td></tr>'; document.getElementById('tablePagination').innerHTML=''; return; }
  tbody.innerHTML = pageData.map((u,idx) => {
    const gi = studentsData.findIndex(s=>s.id===u.id);
    return `<tr>
      <td><input type="checkbox" class="userCheckbox" data-id="${u.id}"></td>
      <td><div style="display:flex;align-items:center;gap:12px"><div class="table-avatar" style="background:${getAvatarColor(gi)}">${escapeHtml(u.avatar||'ط')}</div><div><div style="font-weight:600">${escapeHtml(u.name)}</div><div style="font-size:12px">#${u.id}</div></div></div></td>
      <td>${escapeHtml(u.email)}</td><td>${u.phone||'—'}</td><td><strong>${u.orders}</strong></td>
      <td>${getStatusBadge(u.status)}</td><td>${u.joined}</td>
      <td><div style="display:flex;gap:6px"><button class="btn btn-sm btn-outline btn-icon" onclick="viewUser(${u.id})">👁</button><button class="btn btn-sm btn-outline btn-icon" onclick="openEditUser(${u.id})">✏️</button><button class="btn btn-sm btn-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;border:none" onclick="toggleUserStatus(${u.id},'${u.status}')">${u.status==='active'?'⊘':'▶'}</button><button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none" onclick="deleteUser(${u.id})">🗑</button></div></td>
    </tr>`;
  }).join('');
  const totalPages = Math.ceil(filteredData.length/rowsPerPage);
  let pagHtml = `<span class="pagination-info">${start+1}-${Math.min(start+rowsPerPage, filteredData.length)} من ${filteredData.length}</span><div class="pagination-pages">`;
  pagHtml += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="changePage(${currentPage-1})">‹</button>`;
  for(let i=1;i<=totalPages;i++) if(i===1||i===totalPages||(i>=currentPage-2&&i<=currentPage+2)) pagHtml+=`<button class="page-btn ${i===currentPage?'active':''}" onclick="changePage(${i})">${i}</button>`; else if(i===currentPage-3||i===currentPage+3) pagHtml+=`<span>...</span>`;
  pagHtml += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="changePage(${currentPage+1})">›</button></div>`;
  document.getElementById('tablePagination').innerHTML = pagHtml;
}
function changePage(p) { currentPage=p; renderTable(); }
function filterData() {
  const q = document.getElementById('userSearch').value.toLowerCase();
  const s = document.getElementById('statusFilter').value;
  filteredData = studentsData.filter(u => (u.name.toLowerCase().includes(q)||u.email.toLowerCase().includes(q)||(u.phone&&u.phone.includes(q))) && (s==='all'||u.status===s));
  currentPage=1; renderTable();
}
document.getElementById('userSearch').addEventListener('input', filterData);
document.getElementById('statusFilter').addEventListener('change', filterData);
document.getElementById('selectAll').addEventListener('change', e => document.querySelectorAll('.userCheckbox').forEach(cb=>cb.checked=e.target.checked));

function viewUser(id) {
  const u = studentsData.find(s=>s.id===id);
  if(!u) return;
  const gi = studentsData.indexOf(u);
  document.getElementById('viewUserContent').innerHTML = `<div style="display:flex;align-items:center;gap:20px;margin-bottom:24px"><div class="table-avatar" style="width:64px;height:64px;border-radius:16px;font-size:24px;background:${getAvatarColor(gi)}">${escapeHtml(u.avatar||'ط')}</div><div><h3 style="font-size:20px;font-weight:700">${escapeHtml(u.name)}</h3><p>${escapeHtml(u.email)}</p>${getStatusBadge(u.status)}</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><div><label class="form-label">الهاتف</label><p>${u.phone||'—'}</p></div><div><label class="form-label">تاريخ التسجيل</label><p>${u.joined}</p></div><div><label class="form-label">الطلبات</label><p style="font-size:20px;color:var(--primary)">${u.orders}</p></div><div><label class="form-label">الإنفاق</label><p style="font-size:20px;color:var(--success)">${u.spending.toLocaleString('ar')} ر.س</p></div></div>`;
  document.getElementById('editFromViewBtn').onclick = () => { Modal.close('viewUserModal'); openEditUser(id); };
  Modal.open('viewUserModal');
}
function openEditUser(id) {
  const u = studentsData.find(s=>s.id===id);
  if(!u) return;
  document.getElementById('userModalTitle').innerText = 'تعديل بيانات الطالب';
  document.getElementById('editUserId').value = u.id;
  document.getElementById('userName').value = u.name;
  document.getElementById('userEmail').value = u.email;
  document.getElementById('userPhone').value = u.phone||'';
  document.getElementById('userPassword').value = '';
  document.getElementById('passwordLabel').innerText = 'كلمة المرور الجديدة (اختياري)';
  document.getElementById('passwordHint').style.display = 'block';
  Modal.open('addUserModal');
}

// حفظ (إضافة أو تعديل) - تم إصلاح مشكلة "معرف غير صالح"
document.getElementById('saveUserBtn').onclick = function() {
    const id = document.getElementById('editUserId').value;
    const name = document.getElementById('userName').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const phone = document.getElementById('userPhone').value.trim();
    const password = document.getElementById('userPassword').value;

    if (!name) { Toast.show('الاسم مطلوب', 'warning'); return; }
    if (!email) { Toast.show('البريد الإلكتروني مطلوب', 'warning'); return; }
    // إذا كانت إضافة جديدة (id == 0 أو id == "") وتحقق من كلمة المرور
    const isAdd = (id === '0' || id === '');
    if (isAdd && !password) { Toast.show('كلمة المرور مطلوبة للإضافة', 'warning'); return; }

    const formData = new FormData();
    formData.append('action', isAdd ? 'add' : 'edit');
    if (!isAdd) formData.append('id', id);
    formData.append('name', name);
    formData.append('email', email);
    formData.append('phone', phone);
    if (password) formData.append('password', password);

    fetch('/admin/ajax/manage_user.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error('استجابة غير صالحة: ' + text.substring(0, 100));
        }
    })
    .then(res => {
        if (res.success) {
            Toast.show(res.message, 'success');
            Modal.close('addUserModal');
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.show(res.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Toast.show('خطأ في الاتصال: ' + err.message, 'error');
    });
};

function deleteUser(id) {
  const u = studentsData.find(s=>s.id===id);
  if(!u) return;
  Modal.confirm('حذف الطالب', `هل تريد حذف "${u.name}" نهائياً؟`, () => {
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    fetch('/tawassul/admin/ajax/manage_user.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(r=>r.json()).then(res=>{ if(res.success) { Toast.show(res.message,'success'); setTimeout(()=>location.reload(),800); } else Toast.show(res.message,'error'); })
      .catch(()=>Toast.show('خطأ في الاتصال','error'));
  });
}
function toggleUserStatus(id, curr) {
  const newStatus = curr==='active'?'suspended':'active';
  const actionText = newStatus==='active'?'تفعيل':'تعليق';
  const u = studentsData.find(s=>s.id===id);
  Modal.confirm(`${actionText} الحساب`, `هل تريد ${actionText} حساب "${u.name}"؟`, () => {
    const fd = new FormData(); fd.append('action','update_status'); fd.append('id',id); fd.append('status',newStatus);
    fetch('/tawassul/admin/ajax/manage_user.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(r=>r.json()).then(res=>{ if(res.success) { Toast.show(res.message,'success'); setTimeout(()=>location.reload(),800); } else Toast.show(res.message,'error'); })
      .catch(()=>Toast.show('خطأ في الاتصال','error'));
  });
}
function exportTable() {
  let csv = "ID,الاسم,البريد,الهاتف,الطلبات,الإنفاق,الحالة,تاريخ التسجيل\n";
  filteredData.forEach(u=>csv+=`${u.id},${u.name},${u.email},${u.phone||''},${u.orders},${u.spending},${u.status},${u.joined}\n`);
  const blob = new Blob(["\uFEFF"+csv], {type:"text/csv"});
  const a = document.createElement('a'); a.href=URL.createObjectURL(blob); a.download="students.csv"; a.click(); URL.revokeObjectURL(a.href);
  Toast.show('تم التصدير','success');
}

// منع إغلاق المودال عند النقر داخله (حل مشكلة الاختفاء)
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  const box = overlay.querySelector('.modal-box');
  if(box) box.addEventListener('click', e => e.stopPropagation());
  overlay.addEventListener('click', e => { if(e.target === overlay) Modal.close(overlay.id); });
});

// إعادة تعيين مودال الإضافة عند الفتح
const addModal = document.getElementById('addUserModal');
if(addModal) {
  const observer = new MutationObserver(() => {
    if(addModal.classList.contains('open') && (document.getElementById('editUserId').value === '0' || document.getElementById('editUserId').value === '')) {
      document.getElementById('userModalTitle').innerText = 'إضافة طالب جديد';
      document.getElementById('editUserId').value = '0';
      document.getElementById('userName').value = '';
      document.getElementById('userEmail').value = '';
      document.getElementById('userPhone').value = '';
      document.getElementById('userPassword').value = '';
      document.getElementById('passwordLabel').innerText = 'كلمة المرور';
      document.getElementById('passwordHint').style.display = 'none';
    }
  });
  observer.observe(addModal, { attributes: true, attributeFilter: ['class'] });
}

renderTable();
</script>
</body>
</html>