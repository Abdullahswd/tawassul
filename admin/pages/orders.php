<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Fetch orders
$orders_stmt = $db->query("
    SELECT o.order_number AS id, u.name AS student, COALESCE(a.name, 'غير معين') AS academic, s.name AS service, COALESCE(p.name, 'مخصصة') AS package, o.amount, o.status, DATE(o.created_at) AS date
    FROM orders o
    JOIN users u ON o.student_id = u.id
    JOIN services s ON o.service_id = s.id
    LEFT JOIN academics a ON o.academic_id = a.id
    LEFT JOIN packages p ON o.package_id = p.id
    ORDER BY o.created_at DESC
");
$orders = $orders_stmt->fetchAll();

// Count stats
$cnt_all = count($orders);
$cnt_new = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'pending_assignment', 'assigned')")->fetchColumn();
$cnt_progress = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('accepted', 'in_progress', 'revision')")->fetchColumn();
$cnt_completed = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الطلبات - تواصل Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الطلبات</span></div>
          <h1 class="page-header-title">إدارة الطلبات</h1>
          <p class="page-header-subtitle">عرض ومتابعة جميع طلبات المنصة</p>
        </div>
        <div class="page-header-actions">
          <button class="btn btn-outline" onclick="Toast.show('جاري تصدير الطلبات...','info')">📤 تصدير</button>
        </div>
      </div>

      <!-- Status Cards -->
      <div class="grid-responsive-4">
        <div class="stat-card animate-fadeInUp delay-1" style="padding:18px;cursor:pointer" onclick="filterByStatus('')">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:28px" data-counter="<?= $cnt_all ?>"><?= $cnt_all ?></div><div class="card-label">جميع الطلبات</div></div><div class="card-icon" style="background:rgba(99,102,241,0.1);margin:0;font-size:22px">📋</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-2" style="padding:18px;cursor:pointer;border-bottom:3px solid #3b82f6" onclick="filterByStatus('new')">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:28px;color:#3b82f6" data-counter="<?= $cnt_new ?>"><?= $cnt_new ?></div><div class="card-label">طلبات جديدة</div></div><div class="card-icon" style="background:rgba(59,130,246,0.1);margin:0;font-size:22px">⭐</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-3" style="padding:18px;cursor:pointer;border-bottom:3px solid #f59e0b" onclick="filterByStatus('in_progress')">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:28px;color:#f59e0b" data-counter="<?= $cnt_progress ?>"><?= $cnt_progress ?></div><div class="card-label">قيد التنفيذ</div></div><div class="card-icon" style="background:rgba(245,158,11,0.1);margin:0;font-size:22px">⟳</div></div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:18px;cursor:pointer;border-bottom:3px solid #10b981" onclick="filterByStatus('completed')">
          <div style="display:flex;align-items:center;justify-content:space-between"><div><div class="card-value" style="font-size:28px;color:#10b981" data-counter="<?= $cnt_completed ?>"><?= $cnt_completed ?></div><div class="card-label">مكتملة</div></div><div class="card-icon" style="background:rgba(16,185,129,0.1);margin:0;font-size:22px">✅</div></div>
        </div>
      </div>

      <!-- Table -->
      <div class="table-container animate-fadeInUp delay-2">
        <div class="table-header" style="flex-wrap:wrap;gap:12px">
          <h3 class="table-title">قائمة الطلبات</h3>
          <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="orderSearch" placeholder="بحث برقم أو اسم..." /></div>
          <select class="form-input form-select" id="orderStatusFilter" style="width:auto;padding-left:32px;font-size:14px">
            <option value="">جميع الحالات</option>
            <option value="pending_assignment">بانتظار تعيين الإدارة</option>
            <option value="assigned">أُرسل إلى الأكاديمي</option>
            <option value="new">جديد (قيد المراجعة)</option>
            <option value="in_progress">قيد التنفيذ</option>
            <option value="completed">مكتمل</option>
          </select>
          <select class="form-input form-select" id="orderServiceFilter" style="width:auto;padding-left:32px;font-size:14px">
            <option value="">جميع الخدمات</option>
            <option>الأبحاث والدراسات</option>
            <option>الرسائل الجامعية</option>
            <option>برمجة المشاريع</option>
            <option>التحليل الإحصائي</option>
            <option>الترجمة الأكاديمية</option>
          </select>
          <input type="date" class="form-input" id="dateFilter" style="width:auto;font-size:14px" />
        </div>

        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>رقم الطلب</th>
                <th>الطالب</th>
                <th>الأكاديمي</th>
                <th>الخدمة</th>
                <th>الباقة</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th>التاريخ</th>
                <th>الإجراءات</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody"></tbody>
          </table>
        </div>

        <div class="pagination">
          <span class="pagination-info">عرض 1-<?= count($orders) ?> من <strong><?= $cnt_all ?></strong> طلب</span>
          <div class="pagination-pages">
            <button class="page-btn">‹</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">...</button>
            <button class="page-btn">57</button>
            <button class="page-btn">›</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px"><div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div><div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:64px;margin-bottom:16px">⚠️</div><p id="confirmMessage" style="color:var(--text-secondary);font-size:15px"></p></div><div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmBtn">تأكيد</button></div></div>
</div>

<script src="../assets/js/main.js"></script>
<script>
document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

MOCK_DATA.orders = <?= json_encode($orders) ?>;
let currentOrders = [...MOCK_DATA.orders];

function renderOrders(data) {
  const tbody = document.getElementById('ordersTableBody');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-secondary)">لا توجد طلبات</td></tr>';
    return;
  }
  tbody.innerHTML = data.map((o, i) => `
    <tr>
      <td><a href="order-details.php?id=${o.id}" style="color:var(--primary);font-weight:700;text-decoration:none">${o.id}</a></td>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="table-avatar" style="background:${getAvatarColor(i)};width:34px;height:34px;border-radius:9px;font-size:13px">${o.student.slice(0,2)}</div>
          <span style="font-size:14px">${o.student}</span>
        </div>
      </td>
      <td style="font-size:13px;color:var(--text-secondary)">${o.academic}</td>
      <td style="font-size:13px">${o.service}</td>
      <td><span class="badge badge-primary">${o.package}</span></td>
      <td><strong>${o.amount.toLocaleString('ar')} ر.س</strong></td>
      <td>${getStatusBadge(o.status)}</td>
      <td style="color:var(--text-secondary);font-size:13px">${o.date}</td>
      <td>
        <div style="display:flex;gap:5px">
          <a href="order-details.php?id=${o.id}" class="btn btn-sm btn-outline btn-icon" title="التفاصيل">👁</a>
        </div>
      </td>
    </tr>
  `).join('');
}

function filterByStatus(status) {
  document.getElementById('orderStatusFilter').value = status;
  applyFilters();
}

function applyFilters() {
  const q = document.getElementById('orderSearch').value.toLowerCase();
  const st = document.getElementById('orderStatusFilter').value;
  const sv = document.getElementById('orderServiceFilter').value;
  const dt = document.getElementById('dateFilter').value;
  const filtered = MOCK_DATA.orders.filter(o => {
    const matchQ = !q || o.id.toLowerCase().includes(q) || o.student.toLowerCase().includes(q) || o.academic.toLowerCase().includes(q);
    let matchSt = !st || o.status === st;
    if (st === 'new') {
       matchSt = ['new', 'pending_assignment', 'assigned'].includes(o.status);
    } else if (st === 'in_progress') {
       matchSt = ['accepted', 'in_progress', 'revision'].includes(o.status);
    }
    const matchSv = !sv || o.service.includes(sv);
    const matchDt = !dt || o.date === dt;
    return matchQ && matchSt && matchSv && matchDt;
  });
  renderOrders(filtered);
}

function changeStatus(id) {
  Toast.show(`تم تحديث حالة الطلب ${id}`, 'success');
}

function deleteOrder(id) {
  Modal.confirm('حذف الطلب', `هل تريد حذف الطلب ${id}؟ لا يمكن التراجع.`, () => {
    Toast.show(`تم حذف الطلب ${id}`, 'success');
    const idx = MOCK_DATA.orders.findIndex(o => o.id === id);
    if (idx > -1) MOCK_DATA.orders.splice(idx, 1);
    renderOrders(MOCK_DATA.orders);
  });
}

document.getElementById('orderSearch')?.addEventListener('input', applyFilters);
document.getElementById('orderStatusFilter')?.addEventListener('change', applyFilters);
document.getElementById('orderServiceFilter')?.addEventListener('change', applyFilters);
document.getElementById('dateFilter')?.addEventListener('change', applyFilters);

document.addEventListener('DOMContentLoaded', () => renderOrders(MOCK_DATA.orders));
</script>
</body>
</html>

