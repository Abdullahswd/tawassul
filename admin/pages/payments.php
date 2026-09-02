<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

/* ─────────────────────────────
   Financial stats (safe)
───────────────────────────── */

$total_revenue = (float) $db->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
    WHERE status = 'paid'
")->fetchColumn();

$total_cnt = (int) $db->query("
    SELECT COUNT(*) FROM payments
")->fetchColumn();

$pending_amount = (float) $db->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
    WHERE status = 'pending'
")->fetchColumn();

$failed_amount = (float) $db->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
    WHERE status = 'failed'
")->fetchColumn();

$success_cnt = (int) $db->query("
    SELECT COUNT(*) FROM payments WHERE status = 'paid'
")->fetchColumn();

$failed_cnt = (int) $db->query("
    SELECT COUNT(*) FROM payments WHERE status = 'failed'
")->fetchColumn();


/* ─────────────────────────────
   Monthly revenue (FIXED for MySQL 8)
───────────────────────────── */

$months_ar = ['ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس'];

$rev_by_month = array_fill(0, 12, 0);

$rev_stmt = $db->prepare("
    SELECT 
        MONTH(paid_at) AS m,
        SUM(amount) AS rev
    FROM payments
    WHERE YEAR(paid_at) = ?
      AND status = 'paid'
    GROUP BY MONTH(paid_at)
    ORDER BY m
");

$rev_stmt->execute([date('Y')]);

foreach ($rev_stmt->fetchAll() as $r) {
    $rev_by_month[((int)$r['m']) - 1] = (float)$r['rev'];
}


/* ─────────────────────────────
   Payments table (FIXED safer JOINs)
───────────────────────────── */

$payments_stmt = $db->query("
    SELECT 
        CONCAT('PAY-', LPAD(p.id,6,'0')) AS payment_id,
        o.order_number AS order_number,
        u.name AS student,
        p.amount,
        p.method,
        p.status,
        DATE(p.created_at) AS date
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN users u ON p.student_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 50
");

$payments = $payments_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>المدفوعات - Eduroad Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>المدفوعات</span></div>
          <h1 class="page-header-title">إدارة المدفوعات</h1>
          <p class="page-header-subtitle">عرض ومتابعة جميع العمليات المالية</p>
        </div>
        <div class="page-header-actions">
          <button class="btn btn-outline" onclick="Toast.show('جاري تصدير التقرير المالي...','info')">📤 تصدير</button>
          <select class="form-input form-select" style="width:auto;padding-left:36px">
            <option>هذا الشهر</option>
            <option>الشهر الماضي</option>
            <option>هذا العام</option>
          </select>
        </div>
      </div>

      <!-- Financial Stats -->
      <div class="grid-responsive-4">
        <div class="stat-card animate-fadeInUp delay-1" style="background:linear-gradient(135deg,#10b981,#059669);color:white;border:none">
          <div class="card-icon" style="background:rgba(255,255,255,0.2);margin-bottom:12px">💰</div>
          <div class="card-value" style="font-size:30px;color:white" data-counter="<?= $total_revenue ?>" data-suffix=" ر.س">0</div>
          <div class="card-label" style="color:rgba(255,255,255,0.8)">إجمالي الأرباح</div>
          <div class="card-trend up" style="color:rgba(255,255,255,0.9)">من العمليات الناجحة المكتملة</div>
        </div>
        <div class="stat-card animate-fadeInUp delay-2" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px" data-counter="<?= $total_cnt ?>">0</div><div class="card-label">عدد العمليات</div></div>
            <div class="card-icon" style="background:rgba(99,102,241,0.1);margin:0">🔢</div>
          </div>
        </div>
        <div class="stat-card animate-fadeInUp delay-3" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px" data-counter="<?= $pending_amount ?>" data-suffix=" ر.س">0</div><div class="card-label">مدفوعات معلقة</div></div>
            <div class="card-icon" style="background:rgba(245,158,11,0.1);margin:0">⏳</div>
          </div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px" data-counter="<?= $failed_amount ?>" data-suffix=" ر.س">0</div><div class="card-label">مدفوعات فاشلة</div></div>
            <div class="card-icon" style="background:rgba(239,68,68,0.1);margin:0">❌</div>
          </div>
        </div>
      </div>

      <!-- Revenue Chart -->
      <div class="chart-card animate-fadeInUp delay-2" style="margin-bottom:24px">
        <div class="chart-header">
          <div>
            <h3 class="chart-title">📈 منحنى الإيرادات الشهري</h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:2px">الإيرادات الإجمالية خلال السنة الحالية</p>
          </div>
          <div style="text-align:left">
            <div style="font-size:28px;font-weight:900;color:var(--success)"><?= number_format($total_revenue, 0, '.', ',') ?> ر.س</div>
            <div style="font-size:13px;color:var(--text-secondary)">إجمالي هذه السنة</div>
          </div>
        </div>
        <canvas id="revenueChart" style="width:100%;height:260px;display:block"></canvas>
      </div>

      <!-- Payments Table -->
      <div class="table-container animate-fadeInUp delay-3">
        <div class="table-header">
          <h3 class="table-title">سجل المدفوعات</h3>
          <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="paySearch" placeholder="بحث..." /></div>
          <select class="form-input form-select" id="payStatusFilter" style="width:auto;padding-left:32px;font-size:14px">
            <option value="">جميع الحالات</option>
            <option value="paid">مدفوع</option>
            <option value="pending">معلق</option>
            <option value="failed">فاشل</option>
          </select>
          <select class="form-input form-select" id="payMethodFilter" style="width:auto;padding-left:32px;font-size:14px">
            <option value="">جميع الطرق</option>
            <option>بطاقة ائتمانية</option>
            <option>تحويل بنكي</option>
            <option>محفظة إلكترونية</option>
          </select>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>رقم العملية</th>
                <th>الطلب</th>
                <th>الطالب</th>
                <th>المبلغ</th>
                <th>طريقة الدفع</th>
                <th>الحالة</th>
                <th>التاريخ</th>
                <th>الإجراءات</th>
              </tr>
            </thead>
            <tbody id="paymentsTableBody"></tbody>
          </table>
        </div>

        <!-- Totals Row -->
        <div style="padding:16px 24px;background:var(--bg-main);border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <div style="font-size:13px;color:var(--text-secondary)">عرض 1-<?= count($payments) ?> من <strong><?= $total_cnt ?></strong> عملية</div>
          <div style="display:flex;gap:24px">
            <div style="text-align:center"><div style="font-size:13px;color:var(--text-secondary)">إجمالي المبالغ</div><div style="font-size:18px;font-weight:800;color:var(--success)"><?= number_format($total_revenue, 0, '.', ',') ?> ر.س</div></div>
            <div style="text-align:center"><div style="font-size:13px;color:var(--text-secondary)">العمليات الناجحة</div><div style="font-size:18px;font-weight:800;color:var(--primary)"><?= $success_cnt ?></div></div>
            <div style="text-align:center"><div style="font-size:13px;color:var(--text-secondary)">العمليات الفاشلة</div><div style="font-size:18px;font-weight:800;color:var(--danger)"><?= $failed_cnt ?></div></div>
          </div>
          <div class="pagination-pages">
            <button class="page-btn">‹</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">›</button>
          </div>
        </div>

      </div>

      <!-- ===================== Academic Payouts ===================== -->
      <div class="chart-card animate-fadeInUp delay-2" style="margin-top:28px">
        <div class="chart-header">
          <div>
            <h3 class="chart-title">💸 دفعات الأكاديميين</h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:2px">الحسابات البنكية للأكاديميين وتسجيل المدفوعات المدفوعة لهم</p>
          </div>
          <button class="btn btn-primary" onclick="openPayoutForm()">➕ دفع لأكاديمي</button>
        </div>
      </div>

      <!-- Payout stats -->
      <div class="grid-responsive-4" style="margin:20px 0">
        <div class="stat-card animate-fadeInUp delay-1" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none">
          <div class="card-icon" style="background:rgba(255,255,255,0.2);margin-bottom:12px">💸</div>
          <div class="card-value" style="font-size:26px;color:white" id="payTotal">0</div>
          <div class="card-label" style="color:rgba(255,255,255,0.85)">إجمالي المدفوع للأكاديميين</div>
        </div>
        <div class="stat-card animate-fadeInUp delay-2" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px;color:var(--success)" id="payThisMonth">0</div><div class="card-label">مدفوع هذا الشهر</div></div>
            <div class="card-icon" style="background:rgba(16,185,129,0.1);margin:0">📅</div>
          </div>
        </div>
        <div class="stat-card animate-fadeInUp delay-3" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px;color:var(--warning)" id="payPending">0</div><div class="card-label">أرباح مستحقة (رصيد)</div></div>
            <div class="card-icon" style="background:rgba(245,158,11,0.1);margin:0">⏳</div>
          </div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px;color:var(--primary)" id="payCount">0</div><div class="card-label">عدد الدفعات</div></div>
            <div class="card-icon" style="background:rgba(99,102,241,0.1);margin:0">🧾</div>
          </div>
        </div>
      </div>

      <!-- Academics & bank accounts table -->
      <div class="table-container animate-fadeInUp delay-3">
        <div class="table-header">
          <h3 class="table-title">أكاديميون وحساباتهم البنكية</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>الأكاديمي</th>
                <th>الحسابات البنكية / المحافظ</th>
                <th>الرصيد المستحق</th>
                <th>إجمالي المكاسب</th>
                <th>سبق دفعه</th>
                <th>الإجراءات</th>
              </tr>
            </thead>
            <tbody id="academicsTableBody"></tbody>
          </table>
        </div>
      </div>

      <!-- Payout history table -->
      <div class="table-container animate-fadeInUp delay-3" style="margin-top:20px">
        <div class="table-header">
          <h3 class="table-title">سجل دفعات الأكاديميين</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>رقم الدفعة</th>
                <th>الأكاديمي</th>
                <th>الحساب المستلم</th>
                <th>المبلغ</th>
                <th>التاريخ</th>
                <th>ملاحظة</th>
              </tr>
            </thead>
            <tbody id="payoutsTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Payout modal -->
<div class="modal-overlay" id="payoutModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title">تسجيل دفعة لأكاديمي</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">الأكاديمي</label>
        <select class="form-input form-select" id="payoutAcademic" onchange="onPayoutAcademicChange()" style="padding-left:36px">
          <option value="">اختر الأكاديمي</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">الحساب البنكي / المحفظة المستلمة</label>
        <select class="form-input form-select" id="payoutBank" style="padding-left:36px">
          <option value="0">بدون تحديد</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">المبلغ (ر.س)</label>
        <input type="number" class="form-input" id="payoutAmount" min="0" step="0.01" placeholder="0.00" />
      </div>
      <div class="form-group">
        <label class="form-label">تاريخ الدفعة</label>
        <input type="date" class="form-input" id="payoutDate" value="<?= date('Y-m-d') ?>" />
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">ملاحظة (اختياري)</label>
        <input class="form-input" id="payoutNote" placeholder="ملاحظات حول الدفعة" />
      </div>
      <div id="payoutBalanceHint" style="font-size:12px;color:var(--text-secondary);margin-top:12px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="savePayout()">💸 تأكيد الدفع</button>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
MOCK_DATA.payments = <?= json_encode($payments) ?>;
MOCK_DATA.chartData = {
  revenue: <?= json_encode(array_values($rev_by_month)) ?>,
  months: <?= json_encode($months_ar) ?>
};

document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

const METHOD_ICONS = { 'بطاقة ائتمانية': '💳', 'تحويل بنكي': '🏦', 'محفظة إلكترونية': '📱' };

function renderPayments(data) {
  const tbody = document.getElementById('paymentsTableBody');
  if (!tbody) return;
  tbody.innerHTML = data.map((p, i) => `
    <tr>
      <td><span style="font-family:monospace;font-size:13px;background:var(--bg-main);padding:3px 8px;border-radius:6px">${p.id}</span></td>
      <td><a href="order-details.php" style="color:var(--primary);font-weight:600;text-decoration:none">${p.order}</a></td>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="table-avatar" style="background:${getAvatarColor(i)};width:32px;height:32px;border-radius:8px;font-size:12px">${p.student.slice(0,2)}</div>
          <span>${p.student}</span>
        </div>
      </td>
      <td><strong style="font-size:15px">${p.amount.toLocaleString('ar')} ر.س</strong></td>
      <td><span style="font-size:16px">${METHOD_ICONS[p.method] || '💰'}</span> ${p.method}</td>
      <td>${getStatusBadge(p.status === 'paid' ? 'paid' : p.status === 'pending' ? 'pending_pay' : 'failed')}</td>
      <td style="color:var(--text-secondary);font-size:13px">${p.date}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-sm btn-outline" onclick="Toast.show('عرض تفاصيل الدفعة ${p.id}','info')">👁 عرض</button>
          ${p.status === 'failed' ? `<button class="btn btn-sm btn-warning" onclick="Toast.show('جاري إعادة محاولة الدفع...','warning')">🔄 إعادة</button>` : ''}
        </div>
      </td>
    </tr>
  `).join('');
}

function applyPayFilters() {
  const q = document.getElementById('paySearch').value.toLowerCase();
  const st = document.getElementById('payStatusFilter').value;
  const mt = document.getElementById('payMethodFilter').value;
  const filtered = MOCK_DATA.payments.filter(p => {
    const matchQ = !q || p.id.toLowerCase().includes(q) || p.student.includes(q) || p.order.toLowerCase().includes(q);
    const matchSt = !st || p.status === st;
    const matchMt = !mt || p.method === mt;
    return matchQ && matchSt && matchMt;
  });
  renderPayments(filtered);
}

document.getElementById('paySearch')?.addEventListener('input', applyPayFilters);
document.getElementById('payStatusFilter')?.addEventListener('change', applyPayFilters);
document.getElementById('payMethodFilter')?.addEventListener('change', applyPayFilters);

document.addEventListener('DOMContentLoaded', () => {
  renderPayments(MOCK_DATA.payments);
  loadPayoutData();
  setTimeout(() => {
    Charts.drawLineChart('revenueChart', MOCK_DATA.chartData.revenue, MOCK_DATA.chartData.months, '#10b981');
  }, 200);
});
window.addEventListener('resize', () => {
  Charts.drawLineChart('revenueChart', MOCK_DATA.chartData.revenue, MOCK_DATA.chartData.months, '#10b981');
});

/* =============================================
   ACADEMIC PAYOUTS (دفعات الأكاديميين)
   ============================================= */
let PAYOUT_DATA = { academics: [], payouts: [], stats: {} };

function fmtMoney(n) { return Number(n || 0).toLocaleString('ar'); }
function escHtml(v) {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function bankBadge(acc) {
  const isWallet = acc.account_type === 'wallet';
  const icon = isWallet ? '👛' : '🏦';
  return `<span style="display:inline-flex;align-items:center;gap:6px;background:var(--bg-main);border:1px solid var(--border-color);border-radius:8px;padding:4px 8px;font-size:12px;margin:2px 4px 2px 0"><span>${icon}</span><span>${escHtml(acc.account_name)}</span><span style="color:var(--text-secondary)" dir="ltr">${escHtml(acc.account_number)}</span></span>`;
}

function loadPayoutData() {
  const fd = new FormData();
  fd.append('action', 'get_data');
  fetch('../ajax/manage_payouts.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (!res.success) { Toast.show(res.message || 'تعذر جلب البيانات', 'error'); return; }
      PAYOUT_DATA = { academics: res.academics, payouts: res.payouts, stats: res.stats };
      renderPayoutStats();
      renderAcademicsTable();
      renderPayoutsTable();
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}

function renderPayoutStats() {
  const s = PAYOUT_DATA.stats || {};
  const set = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
  set('payTotal', fmtMoney(s.total_paid) + ' ر.س');
  set('payThisMonth', fmtMoney(s.this_month) + ' ر.س');
  set('payPending', fmtMoney(s.total_pending) + ' ر.س');
  set('payCount', fmtMoney(s.payouts_count));
}

function renderAcademicsTable() {
  const tbody = document.getElementById('academicsTableBody');
  if (!tbody) return;
  if (!PAYOUT_DATA.academics.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:24px">لم يتم العثور على أكاديميين معتمدين.</td></tr>';
    return;
  }
  tbody.innerHTML = PAYOUT_DATA.academics.map((a, i) => {
    const banksHtml = a.bank_accounts.length
      ? a.bank_accounts.map(bankBadge).join('')
      : '<span style="color:var(--text-secondary);font-size:12px">لا توجد حسابات مسجلة</span>';
    const initials = escHtml(a.avatar_initials || (a.name || '').slice(0, 2));
    return `<tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="table-avatar" style="background:${getAvatarColor(i)};width:34px;height:34px;border-radius:8px;font-size:12px;color:white">${initials}</div>
          <span style="font-weight:600">${escHtml(a.name)}</span>
        </div>
      </td>
      <td style="min-width:220px">${banksHtml}</td>
      <td><strong style="color:var(--warning)">${fmtMoney(a.balance)} ر.س</strong></td>
      <td style="color:var(--text-secondary)">${fmtMoney(a.total_earned)} ر.س</td>
      <td style="color:var(--success)">${fmtMoney(a.total_paid)} ر.س</td>
      <td>
        <button class="btn btn-sm btn-primary" onclick="openPayoutForm(${a.id})">💸 دفع</button>
      </td>
    </tr>`;
  }).join('');
}

function renderPayoutsTable() {
  const tbody = document.getElementById('payoutsTableBody');
  if (!tbody) return;
  if (!PAYOUT_DATA.payouts.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:24px">لا توجد دفعات مسجلة بعد.</td></tr>';
    return;
  }
  tbody.innerHTML = PAYOUT_DATA.payouts.map(p => {
    const receiver = p.bank_name
      ? `${p.bank_type === 'wallet' ? '👛' : '🏦'} ${escHtml(p.bank_name)} <span style="color:var(--text-secondary)" dir="ltr">${escHtml(p.bank_number)}</span>`
      : '<span style="color:var(--text-secondary)">—</span>';
    return `<tr>
      <td><span style="font-family:monospace;font-size:13px;background:var(--bg-main);padding:3px 8px;border-radius:6px">${escHtml(p.payout_number)}</span></td>
      <td style="font-weight:600">${escHtml(p.academic_name || '-')}</td>
      <td>${receiver}</td>
      <td><strong style="color:var(--success)">${fmtMoney(p.amount)} ر.س</strong></td>
      <td style="color:var(--text-secondary);font-size:13px">${escHtml(p.paid_at)}</td>
      <td style="color:var(--text-secondary);font-size:13px">${escHtml(p.note || '—')}</td>
    </tr>`;
  }).join('');
}

/* ---- Payout modal handlers ---- */
function openPayoutForm(academicId) {
  const sel = document.getElementById('payoutAcademic');
  sel.innerHTML = '<option value="">اختر الأكاديمي</option>'
    + PAYOUT_DATA.academics.map(a => `<option value="${a.id}">${escHtml(a.name)}</option>`).join('');
  document.getElementById('payoutBank').innerHTML = '<option value="0">بدون تحديد</option>';
  document.getElementById('payoutAmount').value = '';
  document.getElementById('payoutNote').value = '';
  document.getElementById('payoutDate').value = new Date().toISOString().slice(0, 10);
  document.getElementById('payoutBalanceHint').textContent = '';
  if (academicId) { sel.value = String(academicId); onPayoutAcademicChange(); }
  Modal.open('payoutModal');
}

function onPayoutAcademicChange() {
  const sel = document.getElementById('payoutAcademic');
  const bankSel = document.getElementById('payoutBank');
  const hint = document.getElementById('payoutBalanceHint');
  const id = sel.value;
  if (!id) { bankSel.innerHTML = '<option value="0">بدون تحديد</option>'; hint.textContent = ''; return; }
  const a = PAYOUT_DATA.academics.find(x => String(x.id) === String(id));
  bankSel.innerHTML = '<option value="0">بدون تحديد</option>';
  if (a && a.bank_accounts.length) {
    bankSel.innerHTML += a.bank_accounts.map(b => `<option value="${b.id}">${b.account_type === 'wallet' ? '👛 ' : '🏦 '}${escHtml(b.account_name)}</option>`).join('');
  }
  if (a) hint.textContent = 'الرصيد المستحق لهذا الأكاديمي: ' + fmtMoney(a.balance) + ' ر.س';
}

function savePayout() {
  const academicId = document.getElementById('payoutAcademic').value;
  const bankId = document.getElementById('payoutBank').value;
  const amount = parseFloat(document.getElementById('payoutAmount').value);
  const date = document.getElementById('payoutDate').value;
  const note = document.getElementById('payoutNote').value.trim();

  if (!academicId) { Toast.show('يرجى اختيار الأكاديمي', 'error'); return; }
  if (!amount || amount <= 0) { Toast.show('يرجى إدخال مبلغ صحيح', 'error'); return; }

  const a = PAYOUT_DATA.academics.find(x => String(x.id) === String(academicId));
  if (a && amount > a.balance) { Toast.show('المبلغ أكبر من الرصيد المستحق (' + fmtMoney(a.balance) + ' ر.س)', 'error'); return; }

  const fd = new FormData();
  fd.append('action', 'add_payout');
  fd.append('academic_id', academicId);
  fd.append('bank_account_id', bankId || 0);
  fd.append('amount', amount);
  fd.append('paid_at', date);
  fd.append('note', note);

  fetch('../ajax/manage_payouts.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(res.message, 'success');
        Modal.close('payoutModal');
        loadPayoutData();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}
</script>
</body>
</html>

