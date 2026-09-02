<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

try {
$db = db();

// General KPIs
$total_orders = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn();
$total_users = (int) $db->query("SELECT (SELECT COUNT(*) FROM users WHERE role = 'student') + (SELECT COUNT(*) FROM academics)")->fetchColumn();
$avg_rating = (float) $db->query("SELECT COALESCE(AVG(rating), 0.0) FROM reviews")->fetchColumn();

// Target calculation (mock goals dynamically compared to)
$orders_target_percentage = $total_orders > 0 ? min(100, round(($total_orders / 500) * 100)) : 0;
$rev_target_percentage = $total_revenue > 0 ? min(100, round(($total_revenue / 100000) * 100)) : 0;
$users_target_percentage = $total_users > 0 ? min(100, round(($total_users / 2000) * 100)) : 0;
$rating_target_percentage = $avg_rating > 0 ? min(100, round(($avg_rating / 5) * 100)) : 0;

// Months Arabic labels
$months_ar = ['ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس'];

// 1. Orders growth chart
$orders_by_month = array_fill(0, 12, 0);
$orders_stmt = $db->prepare("SELECT MONTH(created_at) AS m, COUNT(*) AS cnt FROM orders WHERE YEAR(created_at) = ? GROUP BY m");
$orders_stmt->execute([date('Y')]);
foreach ($orders_stmt->fetchAll() as $row) {
    $orders_by_month[$row['m'] - 1] = (int)$row['cnt'];
}

// 2. Orders breakdown
$cnt_completed = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$cnt_progress  = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('accepted', 'in_progress', 'revision')")->fetchColumn();
$cnt_new       = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'pending_assignment', 'assigned')")->fetchColumn();
$cnt_all       = max(1, $cnt_completed + $cnt_progress + $cnt_new);

$pct_completed = round(($cnt_completed / $cnt_all) * 100);
$pct_progress  = round(($cnt_progress / $cnt_all) * 100);
$pct_new       = round(($cnt_new / $cnt_all) * 100);

// 3. Top Services
$top_services = $db->query("
    SELECT s.id, s.name, s.icon, COUNT(o.id) AS order_cnt, COALESCE(SUM(o.amount), 0) AS total_amount
    FROM services s
    LEFT JOIN orders o ON o.service_id = s.id
    GROUP BY s.id, s.name, s.icon
    ORDER BY order_cnt DESC
    LIMIT 5
")->fetchAll();

$max_service_orders = 1;
foreach ($top_services as $ts) {
    if ($ts['order_cnt'] > $max_service_orders) {
        $max_service_orders = $ts['order_cnt'];
    }
}

// 4. Revenue monthly
$rev_by_month = array_fill(0, 12, 0);
$rev_stmt = $db->prepare("SELECT MONTH(paid_at) AS m, SUM(amount) AS rev FROM payments WHERE YEAR(paid_at) = ? AND status = 'paid' GROUP BY m");
$rev_stmt->execute([date('Y')]);
foreach ($rev_stmt->fetchAll() as $row) {
    $rev_by_month[$row['m'] - 1] = (float)$row['rev'];
}

$non_zero_revs = array_filter($rev_by_month);
$highest_revenue_month = count($non_zero_revs) > 0 ? max($rev_by_month) : 0;
$average_revenue_month = array_sum($rev_by_month) / 12;
$lowest_revenue_month = count($non_zero_revs) > 0 ? min($non_zero_revs) : 0;

// 5. Users growth cumulative
$users_by_month = array_fill(0, 12, 0);
$u_stmt = $db->prepare("
    SELECT MONTH(created_at) AS m, COUNT(*) AS cnt
    FROM (
        SELECT created_at FROM users WHERE YEAR(created_at) = ? AND role = 'student'
        UNION ALL
        SELECT created_at FROM academics WHERE YEAR(created_at) = ?
    ) combined
    GROUP BY m
");
$u_stmt->execute([date('Y'), date('Y')]);
foreach ($u_stmt->fetchAll() as $row) {
    $users_by_month[$row['m'] - 1] = (int)$row['cnt'];
}
$cumulative_users = [];
$prev_users = (int) $db->query("SELECT (SELECT COUNT(*) FROM users WHERE YEAR(created_at) < " . date('Y') . " AND role = 'student') + (SELECT COUNT(*) FROM academics WHERE YEAR(created_at) < " . date('Y') . ")")->fetchColumn();
$current_sum = $prev_users;
for ($i = 0; $i < 12; $i++) {
    $current_sum += $users_by_month[$i];
    $cumulative_users[$i] = $current_sum;
}

// 6. Users breakdown
$u_active = (int) $db->query("SELECT (SELECT COUNT(*) FROM users WHERE status='active' AND role='student') + (SELECT COUNT(*) FROM academics WHERE status='approved')")->fetchColumn();
$u_suspended = (int) $db->query("SELECT (SELECT COUNT(*) FROM users WHERE status='suspended' AND role='student') + (SELECT COUNT(*) FROM academics WHERE status='rejected')")->fetchColumn();
$u_inactive = (int) $db->query("SELECT (SELECT COUNT(*) FROM users WHERE status='inactive' AND role='student') + (SELECT COUNT(*) FROM academics WHERE status='pending')")->fetchColumn();
$u_total = max(1, $u_active + $u_suspended + $u_inactive);

$pct_u_active = round(($u_active / $u_total) * 100);
$pct_u_suspended = round(($u_suspended / $u_total) * 100);
$pct_u_inactive = round(($u_inactive / $u_total) * 100);

// 7. Services report
$services_report_stmt = $db->query("
    SELECT name, (SELECT COUNT(*) FROM orders o WHERE o.service_id = s.id) AS orders 
    FROM services s 
    ORDER BY orders DESC
");
$services_report = $services_report_stmt->fetchAll();
$services_report = array_map(function($s) {
    $s['orders'] = (int)$s['orders'];
    return $s;
}, $services_report);
} catch (Throwable $e) {
    $log = __DIR__ . '/../../reports_error.log';
    @file_put_contents($log, date('Y-m-d H:i:s') . ' | ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>خطأ</title></head>';
    echo '<body style="font-family:sans-serif;padding:40px;direction:rtl">';
    echo '<h2>حدث خطأ في صفحة التقارير</h2>';
    echo '<p>تم تسجيل تفاصيل الخطأ في ملف <code>reports_error.log</code> في جذر المشروع.</p>';
    echo '<pre style="background:#f5f5f5;padding:12px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>التقارير - Eduroad Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>التقارير</span></div>
          <h1 class="page-header-title">التقارير والإحصائيات</h1>
          <p class="page-header-subtitle">تحليل شامل لأداء المنصة</p>
        </div>
        <div class="page-header-actions">
          <select class="form-input form-select" style="width:auto;padding-left:36px" id="reportPeriod">
            <option>هذا الشهر</option>
            <option>الربع الأول</option>
            <option>النصف الأول</option>
            <option>هذه السنة</option>
          </select>
          <button class="btn btn-primary" onclick="Toast.show('جاري تصدير التقرير الشامل...','info')">📤 تصدير PDF</button>
        </div>
      </div>

      <!-- KPIs Row -->
      <div class="grid-responsive-4">
        <div class="stat-card animate-fadeInUp delay-1" style="padding:20px;border-top:3px solid #6366f1">
          <div style="font-size:30px;margin-bottom:8px">📊</div>
          <div class="card-value" style="font-size:28px" data-counter="<?= $total_orders ?>">0</div>
          <div class="card-label">إجمالي الطلبات</div>
          <div class="progress-bar" style="margin-top:12px"><div class="progress-fill" style="width:<?= $orders_target_percentage ?>%;background:#6366f1"></div></div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:6px"><?= $orders_target_percentage ?>% من الهدف الشهري</div>
        </div>
        <div class="stat-card animate-fadeInUp delay-2" style="padding:20px;border-top:3px solid #10b981">
          <div style="font-size:30px;margin-bottom:8px">💰</div>
          <div class="card-value" style="font-size:28px" data-counter="<?= $total_revenue ?>" data-suffix=" ر.س">0</div>
          <div class="card-label">الإيرادات الكلية</div>
          <div class="progress-bar" style="margin-top:12px"><div class="progress-fill" style="width:<?= $rev_target_percentage ?>%;background:#10b981"></div></div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:6px"><?= $rev_target_percentage ?>% من الهدف السنوي</div>
        </div>
        <div class="stat-card animate-fadeInUp delay-3" style="padding:20px;border-top:3px solid #f59e0b">
          <div style="font-size:30px;margin-bottom:8px">👥</div>
          <div class="card-value" style="font-size:28px" data-counter="<?= $total_users ?>">0</div>
          <div class="card-label">المستخدمون</div>
          <div class="progress-bar" style="margin-top:12px"><div class="progress-fill" style="width:<?= $users_target_percentage ?>%;background:#f59e0b"></div></div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:6px"><?= $users_target_percentage ?>% نمو من عام ماضٍ</div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:20px;border-top:3px solid #0ea5e9">
          <div style="font-size:30px;margin-bottom:8px">⭐</div>
          <div class="card-value" style="font-size:28px"><?= number_format($avg_rating, 1) ?></div>
          <div class="card-label">متوسط التقييم</div>
          <div class="progress-bar" style="margin-top:12px"><div class="progress-fill" style="width:<?= $rating_target_percentage ?>%;background:#0ea5e9"></div></div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:6px"><?= $rating_target_percentage ?>% رضا العملاء</div>
        </div>
      </div>

      <!-- Tabs for different report types -->
      <div data-tabs class="animate-fadeInUp delay-2">
        <div class="tabs-container">
          <div class="tabs-list">
            <button class="tab-btn active" data-tab="tab-orders-report">📋 تقرير الطلبات</button>
            <button class="tab-btn" data-tab="tab-revenue-report">💰 تقرير الإيرادات</button>
            <button class="tab-btn" data-tab="tab-users-report">👥 تقرير المستخدمين</button>
            <button class="tab-btn" data-tab="tab-services-report">⚙️ تقرير الخدمات</button>
          </div>
        </div>

        <!-- Orders Report -->
        <div id="tab-orders-report" class="tab-panel active">
          <div class="grid-2-1" style="margin-bottom:20px">
            <div class="chart-card">
              <div class="chart-header"><h3 class="chart-title">نمو الطلبات الشهري</h3></div>
              <canvas id="ordersChart" style="width:100%;height:260px;display:block"></canvas>
            </div>
            <div class="chart-card">
              <div class="chart-header"><h3 class="chart-title">توزيع حسب الحالة</h3></div>
              <canvas id="ordersDonut" style="width:100%;height:200px;display:block"></canvas>
              <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#10b981;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">مكتملة (<?= $pct_completed ?>%)</span></div>
                  <span style="font-weight:700;color:var(--text-primary)"><?= $cnt_completed ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#f59e0b;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جارية (<?= $pct_progress ?>%)</span></div>
                  <span style="font-weight:700;color:var(--text-primary)"><?= $cnt_progress ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#3b82f6;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جديدة (<?= $pct_new ?>%)</span></div>
                  <span style="font-weight:700;color:var(--text-primary)"><?= $cnt_new ?></span>
                </div>
              </div>
            </div>
          </div>
          <!-- Top services table -->
          <div class="table-container">
            <div class="table-header"><h3 class="table-title">أعلى الخدمات طلباً</h3></div>
            <div style="overflow-x:auto">
              <table class="data-table">
                <thead><tr><th>الخدمة</th><th>عدد الطلبات</th><th>الإيراد</th><th>النسبة</th></tr></thead>
                <tbody>
                  <?php foreach ($top_services as $s): 
                    $pct = round(($s['order_cnt'] / $max_service_orders) * 100);
                  ?>
                    <tr>
                      <td><?= $s['name'] ?> <?= $s['icon'] ?></td>
                      <td><strong><?= $s['order_cnt'] ?></strong></td>
                      <td><?= number_format($s['total_amount'], 0) ?> ر.س</td>
                      <td><div class="progress-bar" style="min-width:120px"><div class="progress-fill" style="width:<?= $pct ?>%;background:#6366f1"></div></div></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Revenue Report -->
        <div id="tab-revenue-report" class="tab-panel">
          <div class="chart-card" style="margin-bottom:20px">
            <div class="chart-header"><h3 class="chart-title">الإيرادات الشهرية</h3></div>
            <canvas id="revenueLineChart" style="width:100%;height:280px;display:block"></canvas>
          </div>
          <div class="grid-responsive-4">
            <div class="stat-card" style="padding:20px;text-align:center">
              <div style="font-size:14px;color:var(--text-secondary);margin-bottom:8px">أعلى شهر</div>
              <div style="font-size:26px;font-weight:900;color:var(--success)"><?= number_format($highest_revenue_month, 0) ?> ر.س</div>
              <div style="font-size:13px;color:var(--text-secondary)">هذه السنة</div>
            </div>
            <div class="stat-card" style="padding:20px;text-align:center">
              <div style="font-size:14px;color:var(--text-secondary);margin-bottom:8px">متوسط الشهر</div>
              <div style="font-size:26px;font-weight:900;color:var(--primary)"><?= number_format($average_revenue_month, 0) ?> ر.س</div>
              <div style="font-size:13px;color:var(--text-secondary)">هذه السنة</div>
            </div>
            <div class="stat-card" style="padding:20px;text-align:center">
              <div style="font-size:14px;color:var(--text-secondary);margin-bottom:8px">أدنى شهر</div>
              <div style="font-size:26px;font-weight:900;color:var(--warning)"><?= number_format($lowest_revenue_month, 0) ?> ر.س</div>
              <div style="font-size:13px;color:var(--text-secondary)">هذه السنة</div>
            </div>
          </div>
        </div>

        <!-- Users Report -->
        <div id="tab-users-report" class="tab-panel">
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
            <div class="chart-card">
              <div class="chart-header"><h3 class="chart-title">نمو قاعدة المستخدمين</h3></div>
              <canvas id="usersGrowthChart" style="width:100%;height:260px;display:block"></canvas>
            </div>
            <div class="chart-card">
              <div class="chart-header"><h3 class="chart-title">توزيع الحالات</h3></div>
              <canvas id="usersDonut" style="width:100%;height:200px;display:block"></canvas>
              <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#10b981;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">نشطون (<?= $pct_u_active ?>%)</span></div><span style="font-weight:700;color:var(--text-primary)"><?= $u_active ?></span></div>
                <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#ef4444;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">معلقون (<?= $pct_u_suspended ?>%)</span></div><span style="font-weight:700;color:var(--text-primary)"><?= $u_suspended ?></span></div>
                <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:50%;background:#94a3b8;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">غير نشطين (<?= $pct_u_inactive ?>%)</span></div><span style="font-weight:700;color:var(--text-primary)"><?= $u_inactive ?></span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Services Report -->
        <div id="tab-services-report" class="tab-panel">
          <div class="chart-card">
            <div class="chart-header"><h3 class="chart-title">أداء الخدمات (عدد الطلبات)</h3></div>
            <canvas id="servicesBarChart" style="width:100%;height:280px;display:block"></canvas>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
MOCK_DATA.chartData = {
  orders: <?= json_encode($orders_by_month) ?>,
  revenue: <?= json_encode($rev_by_month) ?>,
  usersGrowth: <?= json_encode($cumulative_users) ?>,
  months: <?= json_encode($months_ar) ?>
};
MOCK_DATA.ordersBreakdown = [<?= $cnt_completed ?>, <?= $cnt_progress ?>, <?= $cnt_new ?>];
MOCK_DATA.usersBreakdown = [<?= $u_active ?>, <?= $u_suspended ?>, <?= $u_inactive ?>];
MOCK_DATA.services = <?= json_encode($services_report) ?>;

document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

function drawAllCharts() {
  Charts.drawLineChart('ordersChart', MOCK_DATA.chartData.orders, MOCK_DATA.chartData.months, '#6366f1');
  Charts.drawDonutChart('ordersDonut', MOCK_DATA.ordersBreakdown, ['مكتملة','جارية','جديدة'], ['#10b981','#f59e0b','#3b82f6']);
  Charts.drawLineChart('revenueLineChart', MOCK_DATA.chartData.revenue, MOCK_DATA.chartData.months, '#10b981');
  Charts.drawLineChart('usersGrowthChart', MOCK_DATA.chartData.usersGrowth, MOCK_DATA.chartData.months, '#0ea5e9');
  Charts.drawDonutChart('usersDonut', MOCK_DATA.usersBreakdown, ['نشطون','معلقون','غير نشطين'], ['#10b981','#ef4444','#94a3b8']);
  Charts.drawBarChart('servicesBarChart', MOCK_DATA.services.map(s=>s.orders), MOCK_DATA.services.map(s=>s.name.slice(0,8)), '#8b5cf6');
}

document.addEventListener('DOMContentLoaded', () => setTimeout(drawAllCharts, 200));
window.addEventListener('resize', drawAllCharts);

// Re-draw when switching tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => setTimeout(drawAllCharts, 100));
});
</script>
</body>
</html>
