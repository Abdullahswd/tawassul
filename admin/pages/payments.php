<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Financial stats
$total_revenue  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'")->fetchColumn();
$total_cnt      = (int)   $db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$pending_amount = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'")->fetchColumn();
$failed_amount  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'failed'")->fetchColumn();
$success_cnt    = (int)   $db->query("SELECT COUNT(*) FROM payments WHERE status = 'paid'")->fetchColumn();
$failed_cnt     = (int)   $db->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetchColumn();

// Monthly revenue for chart
$months_ar = ['ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس'];
$rev_by_month = array_fill(0, 12, 0);
$rev_stmt = $db->prepare("SELECT MONTH(paid_at)-1 AS m, SUM(amount) AS rev FROM payments WHERE YEAR(paid_at) = ? AND status = 'paid' GROUP BY MONTH(paid_at)");
$rev_stmt->execute([date('Y')]);
foreach ($rev_stmt->fetchAll() as $r) {
    $rev_by_month[$r['m']] = (float)$r['rev'];
}

// Payments table data
$payments_stmt = $db->query("
    SELECT CONCAT('PAY-', LPAD(p.id,6,'0')) AS id, o.order_number AS `order`,
           u.name AS student, p.amount, p.method, p.status, DATE(p.created_at) AS date
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON p.student_id = u.id
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
  <title>المدفوعات - تواصل Admin</title>
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
        <div style="display:flex;gap:10px">
          <button class="btn btn-outline" onclick="Toast.show('جاري تصدير التقرير المالي...','info')">📤 تصدير</button>
          <select class="form-input form-select" style="width:auto;padding-left:36px">
            <option>هذا الشهر</option>
            <option>الشهر الماضي</option>
            <option>هذا العام</option>
          </select>
        </div>
      </div>

      <!-- Financial Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:24px">
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
  setTimeout(() => {
    Charts.drawLineChart('revenueChart', MOCK_DATA.chartData.revenue, MOCK_DATA.chartData.months, '#10b981');
  }, 200);
});
window.addEventListener('resize', () => {
  Charts.drawLineChart('revenueChart', MOCK_DATA.chartData.revenue, MOCK_DATA.chartData.months, '#10b981');
});
</script>
</body>
</html>

