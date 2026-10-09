<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAcademic();

$currentAcademicId = $_SESSION['academic_id'];
$db = db();

// Load orders for this academic
$ordersStmt = $db->prepare("
    SELECT o.*, s.name AS service_name, s.icon AS service_icon, s.platform_percent, p.name AS package_name, u.name AS student_name
    FROM orders o
    LEFT JOIN services s ON o.service_id = s.id
    LEFT JOIN packages p ON o.package_id = p.id
    LEFT JOIN users u ON o.student_id = u.id
    WHERE o.academic_id = ? OR (o.academic_id IS NULL AND o.status = 'assigned' AND o.id IN (SELECT order_id FROM order_assignments WHERE academic_id = ?))
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([$currentAcademicId, $currentAcademicId]);
$orders = $ordersStmt->fetchAll();

// Map orders to JS array
$ordersJson = [];
foreach ($orders as $o) {
    $platform_pct = isset($o['platform_percent']) ? (float)$o['platform_percent'] : 0.00;
    $amount = (float)$o['amount'];
    $platform_fee = round($amount * $platform_pct / 100, 2);
    $academic_net = round($amount - $platform_fee, 2);

    $ordersJson[] = [
        'id' => $o['order_number'],
        'db_id' => (int)$o['id'],
        'student' => $o['student_name'] ?? 'طالب غير معروف',
        'service' => $o['service_name'] ?? 'خدمة عامة',
        'package' => $o['package_name'] ?? 'باقة فردية',
        'amount' => $amount,
        'academic_net' => $academic_net,
        'platform_pct' => $platform_pct,
        'status' => $o['status'],
        'date' => date('Y-m-d', strtotime($o['created_at'])),
        'deadline' => $o['deadline'] ? date('Y-m-d', strtotime($o['deadline'])) : '-'
    ];
}

// Count by status
$allCount = count($ordersJson);
$newCount = 0;
$inProgressCount = 0;
$completedCount = 0;
foreach ($ordersJson as $o) {
    if (in_array($o['status'], ['new', 'assigned'])) $newCount++;
    elseif (in_array($o['status'], ['accepted', 'in_progress', 'revision'])) $inProgressCount++;
    elseif ($o['status'] === 'completed') $completedCount++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الطلبات - لوحة تحكم الأكاديمي</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="page-layout">

  <!-- Sidebar -->
  <?php include 'components/academic-sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <?php include 'components/academic-navbar.php'; ?>

    <div class="page-body">
      <!-- Header -->
      <div class="page-header anim-up">
        <div>
          <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>الطلبات</span></div>
          <h1 class="page-title">إدارة الطلبات</h1>
          <p class="page-subtitle">متابعة وإدارة جميع طلبات الطلاب</p>
        </div>
      </div>

      <!-- Status tabs & stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card anim-up delay-1" style="padding:16px;cursor:pointer;border-bottom:3px solid #6366f1" onclick="filterOrders('')">
          <div style="font-size:24px;font-weight:900;color:var(--primary)" data-counter="<?= $allCount ?>">0</div>
          <div style="font-size:13px;color:var(--text-secondary)">جميع الطلبات</div>
        </div>
        <div class="stat-card anim-up delay-2" style="padding:16px;cursor:pointer;border-bottom:3px solid #3b82f6" onclick="filterOrders('new')">
          <div style="font-size:24px;font-weight:900;color:#3b82f6" data-counter="<?= $newCount ?>">0</div>
          <div style="font-size:13px;color:var(--text-secondary)">جديدة</div>
        </div>
        <div class="stat-card anim-up delay-3" style="padding:16px;cursor:pointer;border-bottom:3px solid #f59e0b" onclick="filterOrders('in_progress')">
          <div style="font-size:24px;font-weight:900;color:#f59e0b" data-counter="<?= $inProgressCount ?>">0</div>
          <div style="font-size:13px;color:var(--text-secondary)">قيد التنفيذ</div>
        </div>
        <div class="stat-card anim-up delay-4" style="padding:16px;cursor:pointer;border-bottom:3px solid #10b981" onclick="filterOrders('completed')">
          <div style="font-size:24px;font-weight:900;color:#10b981" data-counter="<?= $completedCount ?>">0</div>
          <div style="font-size:13px;color:var(--text-secondary)">مكتملة</div>
        </div>
      </div>

      <!-- Filters + Table -->
      <div class="tbl-container anim-up delay-2">
        <div class="tbl-header flex flex-col sm:flex-row flex-wrap gap-4 items-start sm:items-center justify-between">
          <h3 class="tbl-title">📋 قائمة الطلبات</h3>
          <div class="flex flex-col sm:flex-row flex-wrap gap-3 w-full sm:w-auto">
            <div class="search-box w-full sm:w-auto">
              <span class="s-icon">🔍</span>
              <input type="text" id="orderSearch" placeholder="بحث برقم أو اسم..." class="w-full sm:w-[220px]" oninput="filterOrders()"/>
            </div>
            <select class="form-input form-select w-full sm:w-auto" id="statusFilter" style="padding-left:36px;font-size:14px" onchange="filterOrders()">
              <option value="">جميع الحالات</option>
              <option value="new">جديد</option>
              <option value="accepted">مقبول</option>
              <option value="in_progress">قيد التنفيذ</option>
              <option value="revision">تحت المراجعة</option>
              <option value="completed">مكتمل</option>
              <option value="cancelled">ملغي</option>
            </select>
          </div>
        </div>

        <div style="overflow-x:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>رقم الطلب</th>
                <th>الطالب</th>
                <th>الخدمة</th>
                <th>الباقة</th>
                <th>سعر الخدمة</th>
                <th>أرباحك (الصافي)</th>
                <th>الحالة</th>
                <th>الموعد النهائي</th>
                <th>الإجراءات</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody"></tbody>
          </table>
        </div>

        <div class="pagination">
          <span class="pagination-info" id="paginationInfo">عرض 1-<?= count($ordersJson) ?> من <strong><?= count($ordersJson) ?></strong> طلب</span>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Order Detail Modal -->
<div class="modal-overlay" id="orderDetailModal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header">
      <h3 class="modal-title" id="orderDetailTitle">تفاصيل الطلب</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body" id="orderDetailBody"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" id="orderUpdateBtn">🔄 تحديث الحالة</button>
    </div>
  </div>
</div>

<!-- Confirm -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px"><div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div><div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:56px;margin-bottom:12px">⚠️</div><p id="confirmMsg" style="color:var(--text-secondary)"></p></div><div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmOkBtn">تأكيد</button></div></div>
</div>

<script src="assets/js/main.js"></script>
<script>
// Feed database orders into ACADEMICS_DATA
window.ACADEMICS_DATA.orders = <?= json_encode($ordersJson, JSON_UNESCAPED_UNICODE) ?>;
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

function renderOrders(data) {
  const tbody = document.getElementById('ordersTableBody');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary)">📭 لا توجد طلبات</td></tr>';
    document.getElementById('paginationInfo').textContent = 'عرض 0-0 من 0 طلب';
    return;
  }
  tbody.innerHTML = data.map((o, i) => `
    <tr>
      <td><span style="font-weight:700;color:var(--primary)">${o.id}</span></td>
      <td>
        <div class="flex items-center gap-2">
          <div class="tbl-avatar" style="background:${getAvatarColor(i)};width:34px;height:34px;border-radius:9px;font-size:12px">${o.student.slice(0,2)}</div>
          <span>${o.student}</span>
        </div>
      </td>
      <td style="font-size:13px;color:var(--text-secondary)">${o.service}</td>
      <td><span class="badge badge-primary">${o.package}</span></td>
      <td><div style="font-size:13px;text-decoration:line-through;color:var(--text-secondary)">${o.amount} ر.س</div></td>
        <td>
          <div style="font-size:15px;font-weight:700;color:#10b981">${o.academic_net} ر.س</div>
          <div style="font-size:11px;color:var(--text-secondary)">عمولة المنصة ${o.platform_pct}%</div>
        </td>
      <td>${getStatusBadge(o.status)}</td>
      <td style="color:${o.status !== 'completed' ? '#ef4444' : 'var(--text-secondary)'};font-size:13px">📅 ${o.deadline}</td>
      <td>
        <div class="flex flex-wrap gap-1">
          <a href="academic-order-details.php?id=${o.id}" class="btn btn-sm btn-icon" style="background:rgba(99,102,241,.1);color:#6366f1;border:none;text-decoration:none;display:flex;align-items:center;justify-content:center" title="عرض التفاصيل">👁</a>
          ${o.status === 'assigned' || o.status === 'new' ? `<button class="btn btn-sm btn-icon" style="background:rgba(16,185,129,.1);color:#10b981;border:none" title="قبول الطلب" onclick="acceptOrder('${o.id}')">✓</button>` : ''}
          ${o.status === 'in_progress' || o.status === 'accepted' ? `<button class="btn btn-sm btn-icon" style="background:rgba(245,158,11,.1);color:#f59e0b;border:none" title="إتمام الطلب" onclick="completeOrder('${o.id}')">🏁</button>` : ''}
          ${o.status !== 'completed' && o.status !== 'cancelled' ? `<button class="btn btn-sm btn-icon" style="background:rgba(239,68,68,.1);color:#ef4444;border:none" title="إلغاء" onclick="cancelOrder('${o.id}')">✕</button>` : ''}
        </div>
      </td>
    </tr>
  `).join('');
  document.getElementById('paginationInfo').innerHTML = `عرض 1-${data.length} من <strong>${data.length}</strong> طلب`;
}

function filterOrders(statusOverride) {
  if (statusOverride !== undefined) {
    document.getElementById('statusFilter').value = statusOverride;
  }
  const q = document.getElementById('orderSearch').value.toLowerCase();
  const st = document.getElementById('statusFilter').value;
  const filtered = ACADEMICS_DATA.orders.filter(o => {
    const matchQ = !q || o.id.toLowerCase().includes(q) || o.student.toLowerCase().includes(q);
    const matchSt = !st || o.status === st || (st === 'in_progress' && o.status === 'accepted') || (st === 'new' && o.status === 'assigned');
    return matchQ && matchSt;
  });
  renderOrders(filtered);
}

function viewOrder(id) {
  const o = ACADEMICS_DATA.orders.find(x => x.id === id);
  if (!o) return;
  document.getElementById('orderDetailTitle').textContent = `تفاصيل الطلب ${o.id}`;
  document.getElementById('orderDetailBody').innerHTML = `
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">الطالب</div><div style="font-weight:700;color:var(--text-primary);margin-top:4px">${o.student}</div></div>
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">الخدمة</div><div style="font-weight:700;color:var(--text-primary);margin-top:4px">${o.service}</div></div>
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">الباقة</div><div style="font-weight:700;color:var(--primary);margin-top:4px">${o.package}</div></div>
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">المبلغ</div><div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:4px">${o.amount} ر.س</div></div>
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">تاريخ الطلب</div><div style="font-weight:700;margin-top:4px">${o.date}</div></div>
      <div style="padding:14px;background:var(--bg-main);border-radius:12px"><div style="font-size:12px;color:var(--text-secondary)">الموعد النهائي</div><div style="font-weight:700;color:#ef4444;margin-top:4px">${o.deadline}</div></div>
    </div>
    <div style="margin-top:14px;padding:14px;background:var(--bg-main);border-radius:12px;display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:14px;color:var(--text-secondary)">الحالة الحالية</span>
      <span>${getStatusBadge(o.status)}</span>
    </div>
    <div style="margin-top:14px">
      <label class="form-label">تحديث الحالة</label>
      <select class="form-input form-select" id="newStatusSelect" style="padding-left:36px">
        <option value="new" ${o.status==='new'?'selected':''}>جديد</option>
        <option value="accepted" ${o.status==='accepted'?'selected':''}>مقبول</option>
        <option value="in_progress" ${o.status==='in_progress'?'selected':''}>قيد التنفيذ</option>
        <option value="revision" ${o.status==='revision'?'selected':''}>تحت المراجعة</option>
        <option value="completed" ${o.status==='completed'?'selected':''}>مكتمل</option>
        <option value="cancelled" ${o.status==='cancelled'?'selected':''}>ملغي</option>
      </select>
    </div>
  `;

  document.getElementById('orderUpdateBtn').onclick = () => {
    const newStatus = document.getElementById('newStatusSelect').value;
    const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', o.db_id);
    formData.append('status', newStatus);
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=update_order_status', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success) {
          o.status = newStatus;
          Modal.close('orderDetailModal');
          Toast.show(data.message, 'success');
          filterOrders();
        } else {
          Toast.show(data.message, 'error');
        }
      } catch(e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0,200));
      }
    });
  };
  Modal.open('orderDetailModal');
}

function acceptOrder(id) {
  const o = ACADEMICS_DATA.orders.find(x => x.id === id);
  if (!o) return;
  Modal.confirm('قبول الطلب', `هل تريد قبول الطلب ${id} والبدء في التنفيذ؟`, () => {
    const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', o.db_id);
    formData.append('status', 'accepted');
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=update_order_status', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success) {
          o.status = 'accepted';
          Toast.show(data.message, 'success');
          filterOrders();
        } else {
          Toast.show(data.message, 'error');
        }
      } catch(e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0,200));
      }
    });
  });
}

function completeOrder(id) {
  const o = ACADEMICS_DATA.orders.find(x => x.id === id);
  if (!o) return;
  Modal.confirm('إتمام الطلب', `هل تريد تعليم الطلب ${id} كمكتمل؟`, () => {
    const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', o.db_id);
    formData.append('status', 'completed');
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=update_order_status', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success) {
          o.status = 'completed';
          Toast.show(data.message, 'success');
          filterOrders();
        } else {
          Toast.show(data.message, 'error');
        }
      } catch(e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0,200));
      }
    });
  });
}

function cancelOrder(id) {
  const o = ACADEMICS_DATA.orders.find(x => x.id === id);
  if (!o) return;
  Modal.confirm('إلغاء الطلب', `هل تريد إلغاء الطلب ${id}؟ لا يمكن التراجع عن هذا الإجراء.`, () => {
    const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', o.db_id);
    formData.append('status', 'cancelled');
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=update_order_status', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success) {
          o.status = 'cancelled';
          Toast.show(data.message, 'error'); // using error color per original code
          filterOrders();
        } else {
          Toast.show(data.message, 'error');
        }
      } catch(e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0,200));
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', () => renderOrders(ACADEMICS_DATA.orders));
</script>
</body>
</html>
