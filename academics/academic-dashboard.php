<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAcademic();

$academicId = $_SESSION['academic_id'];
$academicData = getAcademicById($academicId);

// Calculate real stats
$db = db();

// 1. Active orders count (new, accepted, in_progress, revision)
$activeOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE academic_id = $academicId AND status IN ('new', 'accepted', 'in_progress', 'revision')")->fetchColumn();

// 2. Completed orders count
$completedOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE academic_id = $academicId AND status = 'completed'")->fetchColumn();

// 3. Earnings this month (payments net)
$thisMonthEarnings = (float)$db->query("
    SELECT COALESCE(SUM(p.academic_net), 0)
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = $academicId AND p.status = 'paid' AND MONTH(p.paid_at) = MONTH(NOW()) AND YEAR(p.paid_at) = YEAR(NOW())
")->fetchColumn();

// 4. Rating and reviews
$rating = (float)$academicData['rating'];
$reviewsCount = (int)$academicData['total_reviews'];

// Get last 5 orders for latest orders table
$ordersStmt = $db->prepare("
    SELECT o.*, s.name AS service_name, s.icon AS service_icon, u.name AS student_name
    FROM orders o
    LEFT JOIN services s ON o.service_id = s.id
    LEFT JOIN users u ON o.student_id = u.id
    WHERE o.academic_id = ? OR (o.academic_id IS NULL AND o.status = 'new')
    ORDER BY o.created_at DESC
    LIMIT 5
");
$ordersStmt->execute([$academicId]);
$latestOrders = $ordersStmt->fetchAll();

$latestOrdersJson = [];
foreach ($latestOrders as $o) {
    $latestOrdersJson[] = [
        'id' => $o['order_number'],
        'student' => $o['student_name'] ?? 'طالب غير معروف',
        'service' => $o['service_name'] ?? 'خدمة عامة',
        'package' => 'الباقة',
        'amount' => (float)$o['amount'],
        'status' => $o['status'],
        'deadline' => $o['deadline'] ? date('Y/m/d', strtotime($o['deadline'])) : '-'
    ];
}

// Monthly earnings for the line chart (12 months of current year)
$monthlyEarnings = array_fill(0, 12, 0.0);
$earningsStmt = $db->prepare("
    SELECT MONTH(p.paid_at) as m, SUM(p.academic_net) as total
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = ? AND p.status = 'paid' AND YEAR(p.paid_at) = YEAR(NOW())
    GROUP BY MONTH(p.paid_at)
");
$earningsStmt->execute([$academicId]);
while ($row = $earningsStmt->fetch()) {
    $mIndex = (int)$row['m'] - 1;
    if ($mIndex >= 0 && $mIndex < 12) {
        $monthlyEarnings[$mIndex] = (float)$row['total'];
    }
}

// Donut chart distribution (Completed, In Progress, New)
$newCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE academic_id = $academicId AND status = 'new'")->fetchColumn();
$inProgressCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE academic_id = $academicId AND status IN ('accepted', 'in_progress', 'revision')")->fetchColumn();
$completedCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE academic_id = $academicId AND status = 'completed'")->fetchColumn();

// Format date to Arabic readable
$todayDateArabic = date('Y/m/d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>لوحة تحكم الأكاديمي - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="page-layout">

  <?php include 'components/academic-sidebar.php'; ?>

  <!-- ===== MAIN ===== -->
  <div class="main-content" id="mainContent">

    <?php include 'components/academic-navbar.php'; ?>

    <!-- ===== PAGE BODY ===== -->
    <div class="page-body">

      <!-- Header -->
      <div class="page-header anim-up">
        <div>
          <h1 class="page-title">مرحباً، <?= e($academicData['name']) ?> 👋</h1>
          <p class="page-subtitle">إليك ملخص نشاطك اليوم — <?= $todayDateArabic ?></p>
        </div>
        <div style="display:flex;gap:10px">
          <a href="academic-orders.php" class="btn btn-primary">📋 الطلبات الجديدة</a>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px">
        <div class="stat-card anim-up delay-1" style="border-top:3px solid #6366f1">
          <div class="stat-icon" style="background:rgba(99,102,241,.1)">📋</div>
          <div class="stat-value" data-counter="<?= $activeOrdersCount ?>">0</div>
          <div class="stat-label">طلبات جارية</div>
          <div class="stat-trend trend-up">▲ نشط ومتابع</div>
        </div>
        <div class="stat-card anim-up delay-2" style="border-top:3px solid #10b981">
          <div class="stat-icon" style="background:rgba(16,185,129,.1)">✅</div>
          <div class="stat-value" data-counter="<?= $completedOrdersCount ?>">0</div>
          <div class="stat-label">طلبات مكتملة</div>
          <div class="stat-trend trend-up">▲ في المنصة</div>
        </div>
        <div class="stat-card anim-up delay-3" style="border-top:3px solid #f59e0b">
          <div class="stat-icon" style="background:rgba(245,158,11,.1)">💰</div>
          <div class="stat-value" data-counter="<?= round($thisMonthEarnings) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label">أرباح هذا الشهر</div>
          <div class="stat-trend trend-up">▲ صافي أرباحك</div>
        </div>
        <div class="stat-card anim-up delay-4" style="border-top:3px solid #f59e0b">
          <div class="stat-icon" style="background:rgba(245,158,11,.1)">⭐</div>
          <div class="stat-value"><?= number_format($rating, 1) ?></div>
          <div class="stat-label">التقييم</div>
          <div class="stat-trend trend-up">▲ من <?= $reviewsCount ?> تقييم</div>
        </div>
      </div>

      <!-- Charts row -->
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:22px;margin-bottom:24px">
        <div class="chart-card anim-up delay-2">
          <div class="chart-header">
            <div>
              <h3 class="chart-title">📈 الأرباح الشهرية</h3>
              <p style="font-size:13px;color:var(--text-secondary)">أرباح السنة الحالية</p>
            </div>
          </div>
          <canvas id="earningsChart" style="width:100%;height:220px"></canvas>
        </div>
        <div class="chart-card anim-up delay-3">
          <div class="chart-header">
            <h3 class="chart-title">📊 توزيع الطلبات</h3>
          </div>
          <canvas id="ordersDonut" style="width:100%;height:180px"></canvas>
          <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">مكتملة</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $completedCount ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جارية</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $inProgressCount ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#3b82f6;display:inline-block"></span><span style="font-size:13px;color:var(--text-secondary)">جديدة</span></div>
              <span style="font-weight:700;color:var(--text-primary)"><?= $newCount ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Latest Orders + Quick Actions -->
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:22px">

        <!-- Latest orders table -->
        <div class="tbl-container anim-up delay-3">
          <div class="tbl-header" style="justify-content:space-between">
            <h3 class="tbl-title">📋 آخر الطلبات</h3>
            <a href="academic-orders.php" class="btn btn-ghost btn-sm">عرض الكل ←</a>
          </div>
          <div style="overflow-x:auto">
            <table class="tbl">
              <thead>
                <tr>
                  <th>رقم الطلب</th>
                  <th>الطالب</th>
                  <th>الخدمة</th>
                  <th>المبلغ</th>
                  <th>الحالة</th>
                  <th>الموعد</th>
                </tr>
              </thead>
              <tbody id="latestOrdersBody"></tbody>
            </table>
          </div>
        </div>

        <!-- Quick actions + profile summary -->
        <div style="display:flex;flex-direction:column;gap:16px">
          <!-- Profile completion -->
          <div class="card" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">✅ اكتمال الملف الشخصي</h3>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
              <span style="font-size:13px;color:var(--text-secondary)">85% مكتمل</span>
              <span style="font-size:13px;font-weight:700;color:var(--primary)">85%</span>
            </div>
            <div class="progress-bar" style="height:8px;margin-bottom:14px">
              <div class="progress-fill" style="width:85%;background:linear-gradient(90deg,#6366f1,#818cf8)"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#10b981">✓ البيانات الشخصية</div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#10b981">✓ المؤهلات الأكاديمية</div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#10b981">✓ الخدمات</div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary)">○ صورة الواجهة</div>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary)">○ شهادات إضافية</div>
            </div>
            <a href="academic-settings.php" class="btn btn-primary btn-sm btn-block" style="margin-top:16px">إتمام الملف ←</a>
          </div>

          <!-- Quick actions -->
          <div class="card" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">⚡ إجراءات سريعة</h3>
            <div style="display:flex;flex-direction:column;gap:8px">
              <a href="academic-orders.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px">📋 عرض الطلبات الجديدة</a>
              <a href="academic-earnings.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px">💰 طلب سحب أرباح</a>
              <a href="academic-settings.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px">👤 تعديل الملف</a>
            </div>
          </div>
        </div>

      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:56px;margin-bottom:12px">⚠️</div><p id="confirmMsg" style="color:var(--text-secondary)"></p></div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmOkBtn">تأكيد</button></div>
  </div>
</div>

<script src="assets/js/main.js"></script>
<script>
// Load dynamic dashboard values
window.ACADEMICS_DATA.orders = <?= json_encode($latestOrdersJson, JSON_UNESCAPED_UNICODE) ?>;
window.ACADEMICS_DATA.earnings.monthly = <?= json_encode($monthlyEarnings) ?>;

function renderLatestOrders() {
  const tbody = document.getElementById('latestOrdersBody');
  if (!tbody) return;
  if (ACADEMICS_DATA.orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-secondary)">📭 لا توجد طلبات حديثة</td></tr>';
    return;
  }
  tbody.innerHTML = ACADEMICS_DATA.orders.map((o, i) => `
    <tr>
      <td><a href="academic-orders.php" style="color:var(--primary);font-weight:700">${o.id}</a></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="tbl-avatar" style="background:${getAvatarColor(i)};width:30px;height:30px;border-radius:8px;font-size:12px">${o.student.slice(0,2)}</div>
          <span style="font-size:13px">${o.student}</span>
        </div>
      </td>
      <td style="font-size:12px;color:var(--text-secondary)">${o.service.slice(0,14)}...</td>
      <td><strong style="color:var(--primary)">${o.amount} ر.س</strong></td>
      <td>${getStatusBadge(o.status)}</td>
      <td style="font-size:12px;color:${o.status!=='completed'?'#ef4444':'var(--text-secondary)'}">📅 ${o.deadline}</td>
    </tr>
  `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  renderLatestOrders();
  setTimeout(() => {
    Charts.drawLine('earningsChart', ACADEMICS_DATA.earnings.monthly, ACADEMICS_DATA.earnings.months, '#6366f1');
    Charts.drawDonut('ordersDonut', [<?= $completedCount ?>, <?= $inProgressCount ?>, <?= $newCount ?>], ['مكتملة','جارية','جديدة'], ['#10b981','#f59e0b','#3b82f6']);
  }, 200);
});
window.addEventListener('resize', () => {
  Charts.drawLine('earningsChart', ACADEMICS_DATA.earnings.monthly, ACADEMICS_DATA.earnings.months, '#6366f1');
});
</script>
</body>
</html>
