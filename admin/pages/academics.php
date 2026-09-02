<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Fetch all academics with order count
$academics_stmt = $db->query("
    SELECT a.id, a.name, a.email, a.specialty, a.degree, a.rating, a.status,
           DATE(a.created_at) AS joined,
           COALESCE(CONCAT(SUBSTRING(a.name,1,1), SUBSTRING(SUBSTRING_INDEX(a.name,' ',-1),1,1)), 'أك') AS avatar,
           (SELECT COUNT(*) FROM orders o WHERE o.academic_id = a.id) AS orders
    FROM academics a
    ORDER BY a.created_at DESC
");
$academics = $academics_stmt->fetchAll();

// Stats
$cnt_all      = count($academics);
$cnt_approved = (int) $db->query("SELECT COUNT(*) FROM academics WHERE status = 'approved'")->fetchColumn();
$cnt_pending  = (int) $db->query("SELECT COUNT(*) FROM academics WHERE status = 'pending'")->fetchColumn();
$cnt_rejected = (int) $db->query("SELECT COUNT(*) FROM academics WHERE status = 'rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الأكاديميون - Eduroad Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الأكاديميون</span></div>
          <h1 class="page-header-title">إدارة الأكاديميين</h1>
          <p class="page-header-subtitle">قبول ورفض وإدارة حسابات الأكاديميين</p>
        </div>
        <div class="page-header-actions">
          <button class="btn btn-primary" onclick="Modal.open('addAcademicModal')">+ إضافة أكاديمي</button>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid-responsive-4">
        <div class="stat-card animate-fadeInUp delay-1" style="padding:18px">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:26px" data-counter="<?= $cnt_all ?>"><?= $cnt_all ?></div><div class="card-label">إجمالي الأكاديميين</div></div><div class="card-icon" style="background:rgba(99,102,241,0.1);margin:0">🎓</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-2" style="padding:18px">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:26px" data-counter="<?= $cnt_approved ?>"><?= $cnt_approved ?></div><div class="card-label">مقبولون</div></div><div class="card-icon" style="background:rgba(16,185,129,0.1);margin:0">✅</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-3" style="padding:18px">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:26px" data-counter="<?= $cnt_pending ?>"><?= $cnt_pending ?></div><div class="card-label">قيد المراجعة</div></div><div class="card-icon" style="background:rgba(245,158,11,0.1);margin:0">⏳</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:18px">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:26px" data-counter="<?= $cnt_rejected ?>"><?= $cnt_rejected ?></div><div class="card-label">مرفوضون</div></div><div class="card-icon" style="background:rgba(239,68,68,0.1);margin:0">❌</div></div>
        </div>
      </div>

      <!-- Tabs -->
      <div data-tabs class="animate-fadeInUp delay-2">
        <div class="tabs-container">
          <div class="tabs-list">
            <button class="tab-btn active" data-tab="tab-all">الكل (<?= $cnt_all ?>)</button>
            <button class="tab-btn" data-tab="tab-approved">مقبولون (<?= $cnt_approved ?>)</button>
            <button class="tab-btn" data-tab="tab-pending">قيد المراجعة (<?= $cnt_pending ?>)</button>
            <button class="tab-btn" data-tab="tab-rejected">مرفوضون (<?= $cnt_rejected ?>)</button>
          </div>
        </div>

        <!-- Tab: All -->
        <div id="tab-all" class="tab-panel active">
          <div class="table-container">
            <div class="table-header">
              <h3 class="table-title">جميع الأكاديميين</h3>
              <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="searchAll" placeholder="بحث..." oninput="filterTable('all', this.value)" /></div>
              <select class="form-input form-select" id="filterAll" style="width:auto;padding-left:32px;font-size:14px" onchange="filterTable('all','')">
                <option value="">جميع التخصصات</option>
              </select>
            </div>
            <div style="overflow-x:auto">
              <table class="data-table">
                <thead><tr><th>الأكاديمي</th><th>التخصص</th><th>الشهادة</th><th>الطلبات</th><th>التقييم</th><th>الحالة</th><th>تاريخ التسجيل</th><th>الإجراءات</th></tr></thead>
                <tbody id="tbody-all"></tbody>
              </table>
            </div>
            <div class="pagination" id="pag-all"></div>
          </div>
        </div>

        <!-- Tab: Approved -->
        <div id="tab-approved" class="tab-panel">
          <div class="table-container">
            <div class="table-header">
              <h3 class="table-title">الأكاديميون المقبولون</h3>
              <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="searchApproved" placeholder="بحث..." oninput="filterTable('approved', this.value)" /></div>
            </div>
            <div style="overflow-x:auto">
              <table class="data-table">
                <thead><tr><th>الأكاديمي</th><th>التخصص</th><th>الشهادة</th><th>الطلبات</th><th>التقييم</th><th>الحالة</th><th>تاريخ التسجيل</th><th>الإجراءات</th></tr></thead>
                <tbody id="tbody-approved"></tbody>
              </table>
            </div>
            <div class="pagination" id="pag-approved"></div>
          </div>
        </div>

        <!-- Tab: Pending -->
        <div id="tab-pending" class="tab-panel">
          <div id="pendingCardsList" style="padding:4px 0"></div>
        </div>

        <!-- Tab: Rejected -->
        <div id="tab-rejected" class="tab-panel">
          <div class="table-container">
            <div class="table-header"><h3 class="table-title">الأكاديميون المرفوضون</h3></div>
            <div style="overflow-x:auto">
              <table class="data-table">
                <thead><tr><th>الأكاديمي</th><th>التخصص</th><th>الشهادة</th><th>الطلبات</th><th>التقييم</th><th>الحالة</th><th>تاريخ التسجيل</th><th>الإجراءات</th></tr></thead>
                <tbody id="tbody-rejected"></tbody>
              </table>
            </div>
            <div class="pagination" id="pag-rejected"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- View / Edit Academic Modal -->
<div class="modal-overlay" id="viewAcademicModal">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header"><h3 class="modal-title" id="viewAcademicTitle">تفاصيل الأكاديمي</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" id="viewAcademicContent"></div>
    <div class="modal-footer" id="academicModalFooter"></div>
  </div>
</div>

<!-- Edit Academic Modal -->
<div class="modal-overlay" id="editAcademicModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3 class="modal-title">تعديل بيانات الأكاديمي</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="editAcademicId" />
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group"><label class="form-label">الاسم الكامل</label><input class="form-input" id="editName" placeholder="د. محمد أحمد" /></div>
        <div class="form-group"><label class="form-label">التخصص</label><input class="form-input" id="editSpecialty" placeholder="الرياضيات" /></div>
        <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input" type="email" id="editEmail" /></div>
        <div class="form-group"><label class="form-label">الشهادة العلمية</label>
          <select class="form-input form-select" id="editDegree" style="padding-left:36px">
            <option>بكالوريوس</option><option>ماجستير</option><option>دكتوراه</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="saveAcademicEdit()">💾 حفظ التعديلات</button>
    </div>
  </div>
</div>

<!-- Add Academic Modal -->
<div class="modal-overlay" id="addAcademicModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3 class="modal-title">إضافة أكاديمي جديد</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group"><label class="form-label">الاسم الكامل</label><input class="form-input" id="addName" placeholder="د. محمد أحمد" /></div>
        <div class="form-group"><label class="form-label">التخصص</label><input class="form-input" id="addSpecialty" placeholder="الرياضيات والإحصاء" /></div>
        <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input" type="email" id="addEmail" /></div>
        <div class="form-group"><label class="form-label">الشهادة العلمية</label>
          <select class="form-input form-select" id="addDegree" style="padding-left:36px">
            <option>بكالوريوس</option><option>ماجستير</option><option>دكتوراه</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">نبذة تعريفية</label><textarea class="form-input" id="addBio" rows="3" placeholder="نبذة عن الأكاديمي..."></textarea></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
<button class="btn btn-primary" onclick="addAcademic()">حفظ</button>
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
/* ── Inject DB data ── */
MOCK_DATA.academics = <?= json_encode($academics) ?>;

/* ── Pagination state per tab ── */
const PAGE_SIZE = 10;
const state = { all: 1, approved: 1, rejected: 1 };
const filtered = {
  all:      [...MOCK_DATA.academics],
  approved: MOCK_DATA.academics.filter(a => a.status === 'approved'),
  rejected: MOCK_DATA.academics.filter(a => a.status === 'rejected'),
};

/* ── Build specialty filter options ── */
(function buildFilter() {
  const specialties = [...new Set(MOCK_DATA.academics.map(a => a.specialty))].filter(Boolean);
  const sel = document.getElementById('filterAll');
  specialties.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s; opt.textContent = s;
    sel.appendChild(opt);
  });
})();

/* ── Render a table body with pagination ── */
function renderTable(tab, data, page) {
  const tbody = document.getElementById(`tbody-${tab}`);
  const pag   = document.getElementById(`pag-${tab}`);
  if (!tbody) return;

  const total = data.length;
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  page = Math.min(page, pages);
  state[tab] = page;

  const slice = data.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  tbody.innerHTML = slice.length === 0
    ? `<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:40px">لا توجد نتائج</td></tr>`
    : slice.map((a, i) => {
        const gi = MOCK_DATA.academics.indexOf(a);
        return `
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:12px">
              <div class="table-avatar" style="background:${getAvatarColor(gi)};width:42px;height:42px;border-radius:12px;font-size:16px">${a.avatar}</div>
              <div>
                <div style="font-weight:600;color:var(--text-primary)">${a.name}</div>
                <div style="font-size:12px;color:var(--text-secondary)">${a.email}</div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;color:var(--text-secondary)">${a.specialty ?? '—'}</td>
          <td><span class="badge badge-primary">${a.degree ?? '—'}</span></td>
          <td><strong style="color:var(--primary)">${a.orders ?? 0}</strong></td>
          <td><span style="color:#f59e0b;font-weight:700">⭐ ${a.rating ?? '0.0'}</span></td>
          <td>${getStatusBadge(a.status)}</td>
          <td style="color:var(--text-secondary);font-size:13px">${a.joined ?? '—'}</td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm btn-outline btn-icon" title="عرض" onclick="viewAcademic(${a.id})">👁</button>
              ${a.status === 'pending' ? `
                <button class="btn btn-sm btn-icon" style="background:rgba(16,185,129,0.1);color:#10b981;border:none" onclick="approveAcademic(${a.id},'${escHtml(a.name)}')">✓</button>
                <button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none" onclick="rejectAcademic(${a.id},'${escHtml(a.name)}')">✗</button>
              ` : `
                <button class="btn btn-sm btn-outline btn-icon" title="تعديل" onclick="openEditModal(${a.id})">✏️</button>
                <button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;border:none" title="حذف" onclick="deleteAcademic(${a.id},'${escHtml(a.name)}')">🗑</button>
              `}
            </div>
          </td>
        </tr>`;
      }).join('');

  /* Pagination footer */
  if (!pag) return;
  if (pages <= 1) { pag.innerHTML = `<span class="pagination-info">إجمالي: <strong>${total}</strong></span>`; return; }

  const from = (page - 1) * PAGE_SIZE + 1;
  const to   = Math.min(page * PAGE_SIZE, total);
  let btns = '';
  for (let p = 1; p <= pages; p++) {
    btns += `<button class="page-btn${p === page ? ' active' : ''}" onclick="goPage('${tab}',${p})">${p}</button>`;
  }
  pag.innerHTML = `
    <span class="pagination-info">عرض ${from}–${to} من <strong>${total}</strong></span>
    <div class="pagination-pages">
      <button class="page-btn" ${page===1?'disabled':''} onclick="goPage('${tab}',${page-1})">‹</button>
      ${btns}
      <button class="page-btn" ${page===pages?'disabled':''} onclick="goPage('${tab}',${page+1})">›</button>
    </div>`;
}

function goPage(tab, page) { renderTable(tab, filtered[tab], page); }

/* ── Pending cards ── */
function renderPendingCards() {
  const list = document.getElementById('pendingCardsList');
  if (!list) return;
  const pending = MOCK_DATA.academics.filter(a => a.status === 'pending');
  list.innerHTML = pending.length === 0
    ? '<div class="alert alert-success" style="margin:16px">لا يوجد أكاديميون قيد المراجعة حالياً ✅</div>'
    : pending.map((a, i) => `
      <div class="stat-card" style="margin-bottom:16px;padding:20px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
          <div style="display:flex;align-items:center;gap:16px">
            <div class="table-avatar" style="width:56px;height:56px;border-radius:14px;font-size:20px;background:${getAvatarColor(i)}">${a.avatar}</div>
            <div>
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary)">${a.name}</h3>
              <p style="color:var(--text-secondary);font-size:13px">${a.email}</p>
              <div style="display:flex;gap:8px;margin-top:8px">
                <span class="badge badge-primary">${a.degree ?? '—'}</span>
                <span class="badge badge-secondary">${a.specialty ?? '—'}</span>
              </div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
            <span class="badge badge-warning">⏳ قيد المراجعة</span>
            <div style="display:flex;gap:8px">
              <button class="btn btn-success btn-sm" onclick="approveAcademic(${a.id},'${escHtml(a.name)}')">✓ قبول</button>
              <button class="btn btn-danger btn-sm" onclick="rejectAcademic(${a.id},'${escHtml(a.name)}')">✗ رفض</button>
              <button class="btn btn-outline btn-sm" onclick="viewAcademic(${a.id})">👁 عرض</button>
            </div>
          </div>
        </div>
      </div>`).join('');
}

/* ── Filter ── */
function filterTable(tab, q) {
  const specialty = tab === 'all' ? (document.getElementById('filterAll')?.value || '') : '';
  const qLow = q.toLowerCase().trim();
  let base = MOCK_DATA.academics;
  if (tab === 'approved') base = base.filter(a => a.status === 'approved');
  if (tab === 'rejected') base = base.filter(a => a.status === 'rejected');
  filtered[tab] = base.filter(a => {
    const matchQ = !qLow || a.name.toLowerCase().includes(qLow) || (a.specialty ?? '').toLowerCase().includes(qLow) || (a.email ?? '').toLowerCase().includes(qLow);
    const matchS = !specialty || a.specialty === specialty;
    return matchQ && matchS;
  });
  renderTable(tab, filtered[tab], 1);
}

/* ── View modal ── */
function viewAcademic(id) {
  const a = MOCK_DATA.academics.find(x => +x.id === +id);
  if (!a) return;
  const i = MOCK_DATA.academics.indexOf(a);
  document.getElementById('viewAcademicTitle').textContent = 'تفاصيل: ' + a.name;
  document.getElementById('viewAcademicContent').innerHTML = `
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding:20px;background:var(--bg-main);border-radius:14px">
      <div class="table-avatar" style="width:72px;height:72px;border-radius:18px;font-size:26px;background:${getAvatarColor(i)}">${a.avatar}</div>
      <div>
        <h3 style="font-size:22px;font-weight:800;color:var(--text-primary)">${a.name}</h3>
        <p style="color:var(--text-secondary);margin-top:4px">${a.email}</p>
        <div style="display:flex;gap:8px;margin-top:10px">${getStatusBadge(a.status)}<span class="badge badge-primary">${a.degree ?? '—'}</span></div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="background:var(--bg-main);padding:16px;border-radius:12px"><div class="form-label">التخصص</div><div style="font-weight:600;color:var(--text-primary);margin-top:4px">${a.specialty ?? '—'}</div></div>
      <div style="background:var(--bg-main);padding:16px;border-radius:12px"><div class="form-label">تاريخ التسجيل</div><div style="font-weight:600;color:var(--text-primary);margin-top:4px">${a.joined ?? '—'}</div></div>
      <div style="background:var(--bg-main);padding:16px;border-radius:12px"><div class="form-label">عدد الطلبات</div><div style="font-size:24px;font-weight:800;color:var(--primary)">${a.orders ?? 0}</div></div>
      <div style="background:var(--bg-main);padding:16px;border-radius:12px"><div class="form-label">التقييم</div><div style="font-size:24px;font-weight:800;color:#f59e0b">⭐ ${a.rating ?? '0.0'}</div></div>
    </div>`;

  let footer = `<button class="btn btn-outline" data-modal-close>إغلاق</button>`;
  if (a.status === 'pending') {
    footer += `<button class="btn btn-success" onclick="approveAcademic(${a.id},'${escHtml(a.name)}');Modal.close('viewAcademicModal')">✓ قبول</button>
               <button class="btn btn-danger"  onclick="rejectAcademic(${a.id},'${escHtml(a.name)}');Modal.close('viewAcademicModal')">✗ رفض</button>`;
  } else {
    footer += `<button class="btn btn-primary" onclick="Modal.close('viewAcademicModal');openEditModal(${a.id})">✏️ تعديل البيانات</button>`;
  }
  document.getElementById('academicModalFooter').innerHTML = footer;
  Modal.open('viewAcademicModal');
}

/* ── Edit modal ── */
function openEditModal(id) {
  const a = MOCK_DATA.academics.find(x => +x.id === +id);
  if (!a) return;
  document.getElementById('editAcademicId').value    = a.id;
  document.getElementById('editName').value          = a.name;
  document.getElementById('editEmail').value         = a.email;
  document.getElementById('editSpecialty').value     = a.specialty ?? '';
  document.getElementById('editDegree').value        = a.degree ?? 'بكالوريوس';
  Modal.open('editAcademicModal');
}

/* ── SAVE EDIT (using FormData) ── */
function saveAcademicEdit() {
  const id        = document.getElementById('editAcademicId').value;
  const name      = document.getElementById('editName').value.trim();
  const email     = document.getElementById('editEmail').value.trim();
  const specialty = document.getElementById('editSpecialty').value.trim();
  const degree    = document.getElementById('editDegree').value;

  if (!name || !email) {
    Toast.show('يرجى تعبئة الاسم والبريد الإلكتروني', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'edit');
  formData.append('id', id);
  formData.append('name', name);
  formData.append('email', email);
  formData.append('specialty', specialty);
  formData.append('degree', degree);

  fetch('../ajax/manage_academic.php', {
    method: 'POST',
    body: formData
  })
  .then(async response => {
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
  })
  .then(res => {
    if (res.success) {
      Toast.show(res.message || 'تم تحديث البيانات بنجاح', 'success');
      Modal.close('editAcademicModal');
      setTimeout(() => location.reload(), 800);
    } else {
      Toast.show(res.message || 'حدث خطأ أثناء الحفظ', 'error');
    }
  })
  .catch(err => {
    console.error(err);
    Toast.show('حدث خطأ في الاتصال بالخادم: ' + err.message, 'error');
  });
}

/* ── DELETE (using FormData) with clear error message ── */
function deleteAcademic(id, name) {
  Modal.confirm('حذف الأكاديمي', `هل تريد حذف "${name}" نهائياً من المنصة؟`, () => {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('../ajax/manage_academic.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(`تم حذف ${name} بنجاح`, 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        // عرض الرسالة الواردة من الخادم (مثل: لا يمكن الحذف لوجود محادثات)
        Toast.show(res.message || 'حدث خطأ أثناء الحذف', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
  });
}

/* ── Approve / Reject (using FormData) ── */
function approveAcademic(id, name) {
  Modal.confirm('قبول الأكاديمي', `هل تريد قبول "${name}" في المنصة؟`, () => {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id', id);
    formData.append('status', 'approved');

    fetch('../ajax/manage_academic.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(`تم قبول ${name} بنجاح`, 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        Toast.show(res.message || 'حدث خطأ', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
  });
}

function rejectAcademic(id, name) {
  Modal.confirm('رفض الأكاديمي', `هل تريد رفض طلب "${name}"؟`, () => {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id', id);
    formData.append('status', 'rejected');

    fetch('../ajax/manage_academic.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(`تم رفض طلب ${name}`, 'error');
        setTimeout(() => location.reload(), 800);
      } else {
        Toast.show(res.message || 'حدث خطأ', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
  });
}

/* ── Helper ── */
function escHtml(str) { return (str ?? '').replace(/'/g, "\\'"); }

/* ── Profile dropdown ── */
document.getElementById('profileDropdown')?.addEventListener('click', function(e) {
  e.stopPropagation(); this.classList.toggle('open');
});
document.addEventListener('click', () => { document.getElementById('profileDropdown')?.classList.remove('open'); });

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  renderTable('all',      filtered.all,      1);
  renderTable('approved', filtered.approved, 1);
  renderTable('rejected', filtered.rejected, 1);
  renderPendingCards();
});

/* ── إضافة أكاديمي جديد ── */
function addAcademic() {
    const name      = document.getElementById('addName').value.trim();
    const email     = document.getElementById('addEmail').value.trim();
    const specialty = document.getElementById('addSpecialty').value.trim();
    const degree    = document.getElementById('addDegree').value;
    const bio       = document.getElementById('addBio').value.trim();

    if (!name || !email) {
        Toast.show('يرجى تعبئة الاسم والبريد الإلكتروني', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('name', name);
    formData.append('email', email);
    formData.append('specialty', specialty);
    formData.append('degree', degree);
    formData.append('bio', bio);

    fetch('../ajax/manage_academic.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(res => {
        if (res.success) {
            Toast.show(res.message, 'success');
            Modal.close('addAcademicModal');
            // تفريغ الحقول (اختياري)
            document.getElementById('addName').value = '';
            document.getElementById('addEmail').value = '';
            document.getElementById('addSpecialty').value = '';
            document.getElementById('addDegree').value = 'بكالوريوس';
            document.getElementById('addBio').value = '';
            // إعادة تحميل الصفحة لعرض الأكاديمي الجديد
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.show(res.message || 'حدث خطأ أثناء الإضافة', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Toast.show('حدث خطأ في الاتصال بالخادم: ' + err.message, 'error');
    });
}
</script>
</body>
</html>