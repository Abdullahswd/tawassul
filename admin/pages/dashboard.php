<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$stats = getAdminDashboardStats();

// Database connection
$db = db();

// Fetch recent orders
$recent_orders_stmt = $db->query("
    SELECT o.order_number AS id, u.name AS student, s.name AS service, o.amount, o.status
    FROM orders o
    JOIN users u ON o.student_id = u.id
    JOIN services s ON o.service_id = s.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recent_orders = $recent_orders_stmt->fetchAll();

// Fetch top academics
$top_academics_stmt = $db->query("
    SELECT avatar_initials AS avatar, name, specialty, rating, total_orders AS orders
    FROM academics
    WHERE status = 'approved'
    ORDER BY rating DESC, total_orders DESC
    LIMIT 5
");
$top_academics = $top_academics_stmt->fetchAll();

// Fetch donut distribution
$donut_completed = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$donut_inprogress = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('accepted', 'in_progress', 'revision')")->fetchColumn();
$donut_new = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'pending_assignment', 'assigned')")->fetchColumn();

// Fetch monthly chart stats (default orders count & revenue by month)
$current_year = date('Y');
$orders_by_month = array_fill(1, 12, 0);
$revenue_by_month = array_fill(1, 12, 0);

$monthly_orders_stmt = $db->prepare("
    SELECT MONTH(created_at) AS m, COUNT(*) AS cnt
    FROM orders
    WHERE YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
");
$monthly_orders_stmt->execute([$current_year]);
foreach ($monthly_orders_stmt->fetchAll() as $row) {
    $orders_by_month[$row['m']] = (int)$row['cnt'];
}

$monthly_rev_stmt = $db->prepare("
    SELECT MONTH(paid_at) AS m, SUM(amount) AS rev
    FROM payments
    WHERE YEAR(paid_at) = ? AND status = 'paid'
    GROUP BY MONTH(paid_at)
");
$monthly_rev_stmt->execute([$current_year]);
foreach ($monthly_rev_stmt->fetchAll() as $row) {
    $revenue_by_month[$row['m']] = (float)$row['rev'];
}

$chart_orders = array_values($orders_by_month);
$chart_revenue = array_values($revenue_by_month);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>لوحة الإدارة - Eduroad</title>
  <meta name="description" content="لوحة إدارة منصة Eduroad - نظرة عامة على الإحصائيات والبيانات" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- Admin Layout -->
<div class="admin-layout">

  <?php include '../components/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">

    <?php include '../components/navbar.php'; ?>

    <!-- Page Content -->
    <div class="page-content">

      <!-- Page Header -->
      <div class="page-header animate-fadeInUp">
        <div>
          <div class="breadcrumb"><a href="#">الرئيسية</a><span>›</span><span>لوحة التحكم</span></div>
          <h1 class="page-header-title">لوحة التحكم</h1>
          <p class="page-header-subtitle">مرحباً بك! إليك نظرة عامة على المنصة</p>
        </div>
        <div class="page-header-actions">
          <select class="form-input form-select" style="width:auto;padding-left:36px" id="periodFilter">
            <option>هذا الشهر</option>
            <option>الشهر الماضي</option>
            <option>آخر 3 أشهر</option>
            <option>هذا العام</option>
          </select>
          <button class="btn btn-primary" onclick="Toast.show('جاري تحديث البيانات...','info')">
            🔄 تحديث
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid-responsive-4">

        <div class="stat-card animate-fadeInUp delay-1">
          <div class="card-icon" style="background:rgba(99,102,241,0.1)">👥</div>
          <div class="card-value" data-counter="<?= $stats['total_students'] ?>"><?= $stats['total_students'] ?></div>
          <div class="card-label">إجمالي الطلاب</div>
          <div class="card-trend up">مجموع المسجلين</div>
          <div class="card-bg-blob" style="background:#6366f1"></div>
        </div>

        <div class="stat-card animate-fadeInUp delay-2">
          <div class="card-icon" style="background:rgba(14,165,233,0.1)">🎓</div>
          <div class="card-value" data-counter="<?= $stats['total_academics'] ?>"><?= $stats['total_academics'] ?></div>
          <div class="card-label">الأكاديميون المسجلون</div>
          <div class="card-trend up">مقبولون ومستعدون</div>
          <div class="card-bg-blob" style="background:#0ea5e9"></div>
        </div>

        <div class="stat-card animate-fadeInUp delay-3">
          <div class="card-icon" style="background:rgba(245,158,11,0.1)">📋</div>
          <div class="card-value" data-counter="<?= $stats['total_orders'] ?>"><?= $stats['total_orders'] ?></div>
          <div class="card-label">إجمالي الطلبات</div>
          <div class="card-trend up">كل الحالات</div>
          <div class="card-bg-blob" style="background:#f59e0b"></div>
        </div>

        <div class="stat-card animate-fadeInUp delay-4">
          <div class="card-icon" style="background:rgba(16,185,129,0.1)">💰</div>
          <div class="card-value" data-counter="<?= (int)$stats['total_revenue'] ?>" data-suffix=" ر.س">0</div>
          <div class="card-label">إجمالي الأرباح</div>
          <div class="card-trend up">المدفوعات الناجحة</div>
          <div class="card-bg-blob" style="background:#10b981"></div>
        </div>

      </div>

      <!-- Charts Row -->
      <div class="grid-2-1 animate-fadeInUp delay-2" style="margin-bottom:28px">

        <!-- Line Chart -->
        <div class="chart-card">
          <div class="chart-header">
            <div>
              <h3 class="chart-title">نمو الطلبات والأرباح</h3>
              <p style="font-size:13px;color:var(--text-secondary);margin-top:2px">السنة الحالية - رسم بياني شهري</p>
            </div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-sm btn-outline active-chart-btn" id="btnOrders" onclick="switchChart('orders')">الطلبات</button>
              <button class="btn btn-sm btn-outline" id="btnRevenue" onclick="switchChart('revenue')">الأرباح</button>
            </div>
          </div>
          <canvas id="mainChart" style="width:100%;height:280px;display:block"></canvas>
        </div>

        <!-- Donut Chart -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">توزيع الطلبات</h3>
          </div>
          <canvas id="donutChart" style="width:100%;height:200px;display:block"></canvas>
          <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#10b981;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">مكتملة</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $donut_completed ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#f59e0b;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جارية</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $donut_inprogress ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#3b82f6;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جديدة</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $donut_new ?></span>
            </div>
          </div>
        </div>

      </div>

      <!-- Bottom Row -->
      <div class="grid-2-1 animate-fadeInUp delay-3">

        <!-- Recent Orders Table -->
        <div class="table-container">
          <div class="table-header">
            <h3 class="table-title">📋 آخر الطلبات</h3>
            <a href="orders.php" class="btn btn-sm btn-outline" style="margin-right:auto">عرض الكل ←</a>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>رقم الطلب</th>
                <th>الطالب</th>
                <th>الخدمة</th>
                <th>المبلغ</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="recentOrdersBody"></tbody>
          </table>
        </div>

        <!-- Top Academics -->
        <div class="table-container">
          <div class="table-header">
            <h3 class="table-title">🏆 أفضل الأكاديميين</h3>
            <a href="academics.php" class="btn btn-sm btn-outline" style="margin-right:auto">عرض الكل ←</a>
          </div>
          <div id="topAcademicsList" style="padding:8px"></div>
        </div>

      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->

<script src="../assets/js/main.js"></script>
<script>
// Live Database MOCK_DATA replacement
MOCK_DATA.orders = <?= json_encode($recent_orders) ?>;
MOCK_DATA.academics = <?= json_encode($top_academics) ?>;
MOCK_DATA.chartData = {
  months: ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
  orders: <?= json_encode($chart_orders) ?>,
  revenue: <?= json_encode($chart_revenue) ?>,
};

// Profile dropdown
document.getElementById('profileDropdown')?.addEventListener('click', function(e){
  e.stopPropagation();
  this.classList.toggle('open');
  document.getElementById('notificationDropdown')?.classList.remove('open');
});
document.addEventListener('click', () => {
  document.getElementById('profileDropdown')?.classList.remove('open');
});

// Render recent orders
function renderRecentOrders() {
  const tbody = document.getElementById('recentOrdersBody');
  if (!tbody) return;
  const orders = MOCK_DATA.orders.slice(0, 5);
  tbody.innerHTML = orders.map((o, i) => `
    <tr>
      <td><a href="order-details.php?id=${o.id}" style="color:var(--primary);font-weight:600;text-decoration:none">${o.id}</a></td>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="table-avatar" style="background:${getAvatarColor(i)}">${o.student.slice(0,2)}</div>
          <span>${o.student}</span>
        </div>
      </td>
      <td style="color:var(--text-secondary);font-size:13px">${o.service.slice(0,12)}...</td>
      <td><strong>${o.amount.toLocaleString('ar')} ر.س</strong></td>
      <td>${getStatusBadge(o.status)}</td>
    </tr>
  `).join('');
}

// Render top academics
function renderTopAcademics() {
  const list = document.getElementById('topAcademicsList');
  if (!list) return;
  list.innerHTML = MOCK_DATA.academics.map((a, i) => `
    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;margin-bottom:4px;transition:background 0.2s" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='transparent'">
      <div style="width:10px;height:10px;border-radius:50%;font-size:18px;font-weight:800;color:var(--text-secondary);text-align:center;min-width:24px">${i+1}</div>
      <div class="table-avatar" style="background:${getAvatarColor(i)}">${a.avatar || 'أك'}</div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.name}</div>
        <div style="font-size:12px;color:var(--text-secondary)">${a.specialty || 'تخصص عام'}</div>
      </div>
      <div style="text-align:left;flex-shrink:0">
        <div style="font-size:13px;font-weight:700;color:var(--text-primary)">${a.orders} طلب</div>
        <div style="font-size:12px;color:#f59e0b">⭐ ${parseFloat(a.rating).toFixed(1)}</div>
      </div>
    </div>
  `).join('');
}

// Charts
let currentChart = 'orders';
function switchChart(type) {
  currentChart = type;
  document.getElementById('btnOrders').classList.toggle('btn-primary', type === 'orders');
  document.getElementById('btnOrders').classList.toggle('btn-outline', type !== 'orders');
  document.getElementById('btnRevenue').classList.toggle('btn-primary', type === 'revenue');
  document.getElementById('btnRevenue').classList.toggle('btn-outline', type !== 'orders');
  drawMainChart();
}

function drawMainChart() {
  const data = currentChart === 'orders' ? MOCK_DATA.chartData.orders : MOCK_DATA.chartData.revenue;
  const color = currentChart === 'orders' ? '#6366f1' : '#10b981';
  Charts.drawLineChart('mainChart', data, MOCK_DATA.chartData.months, color);
}

document.addEventListener('DOMContentLoaded', () => {
  renderRecentOrders();
  renderTopAcademics();
  setTimeout(() => {
    drawMainChart();
    Charts.drawDonutChart('donutChart', [<?= $donut_completed ?>, <?= $donut_inprogress ?>, <?= $donut_new ?>], ['مكتملة', 'جارية', 'جديدة'], ['#10b981', '#f59e0b', '#3b82f6']);
  }, 200);
});

window.addEventListener('resize', () => {
  drawMainChart();
  Charts.drawDonutChart('donutChart', [<?= $donut_completed ?>, <?= $donut_inprogress ?>, <?= $donut_new ?>], ['مكتملة', 'جارية', 'جديدة'], ['#10b981', '#f59e0b', '#3b82f6']);
});
</script>
</body>
</html>

