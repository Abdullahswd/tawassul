<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAcademic();

$academicId = $_SESSION['academic_id'];
$academicData = getAcademicById($academicId);

$db = db();

// Real financial figures
$balance = (float)$academicData['balance'];
$totalEarned = (float)$academicData['total_earned'];

// Let's check total earned from payments paid to make it consistent if total_earned column is 0
$checkEarned = (float)$db->query("
    SELECT SUM(p.academic_net)
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = $academicId AND p.status = 'paid'
")->fetchColumn();
if ($checkEarned > $totalEarned) {
    $totalEarned = $checkEarned;
    // Update academic total_earned
    $db->prepare("UPDATE academics SET total_earned = ? WHERE id = ?")->execute([$totalEarned, $academicId]);
}

$totalWithdrawn = $totalEarned - $balance;
if ($totalWithdrawn < 0) $totalWithdrawn = 0;

// Earnings this month
$thisMonthEarnings = (float)$db->query("
    SELECT COALESCE(SUM(p.academic_net), 0)
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = $academicId AND p.status = 'paid' AND MONTH(p.paid_at) = MONTH(NOW()) AND YEAR(p.paid_at) = YEAR(NOW())
")->fetchColumn();

// Load financial transactions (payments)
$payStmt = $db->prepare("
    SELECT p.*, o.order_number, u.name as student_name
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON p.student_id = u.id
    WHERE o.academic_id = ?
    ORDER BY p.created_at DESC
");
$payStmt->execute([$academicId]);
$payments = $payStmt->fetchAll();

$txnList = [];
$totalComm = 0;
$totalAmt = 0;
foreach ($payments as $p) {
    $txnList[] = [
        'id' => $p['payment_number'],
        'order' => '#' . $p['order_number'],
        'student' => $p['student_name'] ?? 'طالب غير معروف',
        'amount' => (float)$p['amount'],
        'commission' => (float)$p['platform_fee'],
        'net' => (float)$p['academic_net'],
        'date' => date('Y-m-d', strtotime($p['created_at'])),
        'status' => $p['status']
    ];
    if ($p['status'] === 'paid') {
        $totalComm += (float)$p['platform_fee'];
        $totalAmt += (float)$p['amount'];
    }
}

// Monthly earnings for line chart
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الأرباح - لوحة تحكم الأكاديمي</title>
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
          <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>الأرباح</span></div>
          <h1 class="page-title">الأرباح والمدفوعات</h1>
          <p class="page-subtitle">إدارة ومتابعة أرباحك من المنصة</p>
        </div>
        <div style="display:flex;gap:10px">
          <button class="btn btn-primary" onclick="Modal.open('withdrawModal')">💸 طلب سحب</button>
        </div>
      </div>

      <!-- Finance cards -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px">
        <div class="stat-card anim-up delay-1" style="background:linear-gradient(135deg,#10b981,#059669);border:none;color:white">
          <div class="stat-icon" style="background:rgba(255,255,255,.2);margin-bottom:14px">💰</div>
          <div class="stat-value" style="color:white;font-size:26px" data-counter="<?= round($totalEarned) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label" style="color:rgba(255,255,255,.8)">إجمالي الأرباح</div>
          <div class="stat-trend" style="color:rgba(255,255,255,.9)">▲ منذ الانضمام</div>
        </div>
        <div class="stat-card anim-up delay-2" style="border-top:3px solid #6366f1;padding:22px">
          <div class="stat-icon" style="background:rgba(99,102,241,.1)">📅</div>
          <div class="stat-value" data-counter="<?= round($thisMonthEarnings) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label">هذا الشهر</div>
          <div class="stat-trend trend-up">▲ صافي أرباحك</div>
        </div>
        <div class="stat-card anim-up delay-3" style="border-top:3px solid #f59e0b;padding:22px">
          <div class="stat-icon" style="background:rgba(245,158,11,.1)">⏳</div>
          <div class="stat-value" data-counter="<?= round($balance) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label">المتاح للسحب</div>
          <div class="stat-trend" style="color:var(--text-secondary)">قابل للصرف حالياً</div>
        </div>
        <div class="stat-card anim-up delay-4" style="border-top:3px solid var(--primary);padding:22px">
          <div class="stat-icon" style="background:rgba(99,102,241,.1)">🏦</div>
          <div class="stat-value" data-counter="<?= round($totalWithdrawn) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label">إجمالي المسحوبات</div>
          <div class="stat-trend trend-up">▲ متراكم</div>
        </div>
      </div>

      <!-- Revenue Chart -->
      <div class="chart-card anim-up delay-2" style="margin-bottom:24px">
        <div class="chart-header">
          <div>
            <h3 class="chart-title">📈 منحنى الأرباح الشهرية</h3>
            <p style="font-size:13px;color:var(--text-secondary)">السنة الحالية - الأرباح بعد خصم العمولة</p>
          </div>
        </div>
        <canvas id="revenueChart" style="width:100%;height:240px"></canvas>
      </div>

      <!-- Transactions -->
      <div class="tbl-container anim-up delay-3">
        <div class="tbl-header">
          <h3 class="tbl-title">💳 سجل العمليات المالية</h3>
        </div>

        <div style="overflow-x:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>رقم العملية</th>
                <th>الطلب</th>
                <th>الطالب</th>
                <th>المبلغ الإجمالي</th>
                <th>العمولة (15%)</th>
                <th>صافي الأرباح</th>
                <th>الحالة</th>
                <th>التاريخ</th>
              </tr>
            </thead>
            <tbody id="transactionsBody"></tbody>
          </table>
        </div>

        <!-- Summary footer -->
        <div style="padding:16px 22px;background:var(--bg-main);border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:32px;flex-wrap:wrap">
          <div style="text-align:left">
            <div style="font-size:12px;color:var(--text-secondary)">إجمالي العمليات المدفوعة</div>
            <div style="font-size:20px;font-weight:900;color:var(--text-primary)"><?= count($txnList) ?></div>
          </div>
          <div style="text-align:left">
            <div style="font-size:12px;color:var(--text-secondary)">إجمالي المبالغ</div>
            <div style="font-size:20px;font-weight:900;color:var(--text-primary)"><?= formatMoney($totalAmt) ?></div>
          </div>
          <div style="text-align:left">
            <div style="font-size:12px;color:var(--text-secondary)">عمولة المنصة</div>
            <div style="font-size:20px;font-weight:900;color:var(--danger)">- <?= formatMoney($totalComm) ?></div>
          </div>
          <div style="text-align:left">
            <div style="font-size:12px;color:var(--text-secondary)">صافي أرباحك</div>
            <div style="font-size:22px;font-weight:900;color:var(--success)"><?= formatMoney($totalEarned) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Withdraw Modal -->
<div class="modal-overlay" id="withdrawModal">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header"><h3 class="modal-title">💸 طلب سحب الأرباح</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <div class="alert alert-info"><span>ℹ️</span><span>رصيدك المتاح للسحب: <strong><?= formatMoney($balance) ?></strong></span></div>
      <div class="form-group">
        <label class="form-label">مبلغ السحب (ر.س)</label>
        <input class="form-input" id="withdrawAmount" type="number" placeholder="الحد الأدنى 200 ر.س" min="200" max="<?= intval($balance) ?>"/>
      </div>
      <div class="form-group">
        <label class="form-label">طريقة الاستلام</label>
        <select class="form-input form-select" id="withdrawMethod" style="padding-left:36px">
          <option value="تحويل بنكي">تحويل بنكي</option>
          <option value="محفظة STC Pay">محفظة STC Pay</option>
          <option value="Apple Pay">Apple Pay</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">رقم الحساب البنكي / IBAN أو رقم الجوال للمحفظة</label>
        <input class="form-input" id="withdrawIban" placeholder="SA00 0000 0000 0000 0000 0000" dir="ltr"/>
      </div>
      <div class="form-group">
        <label class="form-label">اسم صاحب الحساب</label>
        <input class="form-input" id="withdrawName" value="<?= e($academicName) ?>" placeholder="الاسم الكامل"/>
      </div>
      <div class="alert alert-warning"><span>⚠️</span><span>تُعالج طلبات السحب خلال 3-5 أيام عمل</span></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-success" onclick="submitWithdraw()">✅ تأكيد السحب</button>
    </div>
  </div>
</div>

<!-- Confirm -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px"><div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div><div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:56px;margin-bottom:12px">⚠️</div><p id="confirmMsg"></p></div><div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmOkBtn">تأكيد</button></div></div>
</div>

<script src="assets/js/main.js"></script>
<script>
// Feed database earnings data
window.ACADEMICS_DATA.earnings.transactions = <?= json_encode($txnList, JSON_UNESCAPED_UNICODE) ?>;
window.ACADEMICS_DATA.earnings.monthly = <?= json_encode($monthlyEarnings) ?>;

function renderTransactions() {
  const tbody = document.getElementById('transactionsBody');
  if (!tbody) return;
  if (ACADEMICS_DATA.earnings.transactions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary)">📭 لا توجد عمليات مالية مسجلة</td></tr>';
    return;
  }
  tbody.innerHTML = ACADEMICS_DATA.earnings.transactions.map((t, i) => `
    <tr>
      <td><span style="font-family:monospace;font-size:12px;background:var(--bg-main);padding:3px 8px;border-radius:6px">${t.id}</span></td>
      <td><span style="color:var(--primary);font-weight:600">${t.order}</span></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="tbl-avatar" style="background:${getAvatarColor(i)};width:30px;height:30px;border-radius:8px;font-size:11px">${t.student.slice(0,2)}</div>
          <span style="font-size:13px">${t.student}</span>
        </div>
      </td>
      <td><strong>${t.amount} ر.س</strong></td>
      <td style="color:var(--danger)">- ${t.commission} ر.س</td>
      <td><strong style="font-size:16px;color:var(--success)">${t.net} ر.س</strong></td>
      <td>${t.status === 'paid' ? '<span class="badge badge-success">✓ مدفوع</span>' : '<span class="badge badge-warning">⏳ معلق</span>'}</td>
      <td style="font-size:13px;color:var(--text-secondary)">${t.date}</td>
    </tr>
  `).join('');
}

function submitWithdraw() {
  const amount = document.getElementById('withdrawAmount').value;
  const method = document.getElementById('withdrawMethod').value;
  const iban = document.getElementById('withdrawIban').value;
  const name = document.getElementById('withdrawName').value;

  if (!amount || +amount < 200) { Toast.show('المبلغ يجب أن يكون 200 ر.س على الأقل', 'error'); return; }
  if (+amount > <?= $balance ?>) { Toast.show('المبلغ يتجاوز الرصيد المتاح', 'error'); return; }
  if (!iban) { Toast.show('يرجى إدخال رقم الحساب البنكي أو المحفظة', 'error'); return; }

  const formData = new FormData();
  formData.append('amount', amount);
  formData.append('method', method);
  formData.append('iban', iban);
  formData.append('name', name);

  fetch('ajax/handler.php?action=submit_withdraw', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Modal.close('withdrawModal');
      Toast.show(data.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      Toast.show(data.message, 'error');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  renderTransactions();
  setTimeout(() => {
    Charts.drawLine('revenueChart', ACADEMICS_DATA.earnings.monthly, ACADEMICS_DATA.earnings.months, '#6366f1');
  }, 200);
});
window.addEventListener('resize', () => {
  Charts.drawLine('revenueChart', ACADEMICS_DATA.earnings.monthly, ACADEMICS_DATA.earnings.months, '#6366f1');
});
</script>
</body>
</html>
