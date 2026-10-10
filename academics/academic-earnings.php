<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAcademic();

$academicId = $_SESSION['academic_id'];
$academicData = getAcademicById($academicId);

$db = db();

// --- TEMPORARY DB MODIFICATIONS ---
try {
    $db->exec("CREATE TABLE IF NOT EXISTS payout_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payout_id INT NOT NULL,
        sender_type ENUM('admin', 'academic') NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {}
// --- END TEMPORARY DB MODIFICATIONS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payout_message') {
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($payoutId > 0 && !empty($message)) {
        // Verify payout belongs to academic
        $verify = $db->prepare("SELECT id FROM payouts WHERE id = ? AND academic_id = ?");
        $verify->execute([$payoutId, $academicId]);
        if ($verify->fetch()) {
            $stmt = $db->prepare("INSERT INTO payout_messages (payout_id, sender_type, sender_id, message) VALUES (?, 'academic', ?, ?)");
            $stmt->execute([$payoutId, $academicId, $message]);
        }
    }
    header("Location: academic-earnings.php");
    exit;
}
// Real financial figures
$balance = (float)$academicData['balance'];
$totalEarned = (float)$academicData['total_earned'];

// Fetch payout policy settings
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$min_withdrawal = (float)($settings['min_withdrawal'] ?? 200);
$withdrawal_eligibility_days = (int)($settings['withdrawal_eligibility_days'] ?? 14);
$payout_processing_time = (int)($settings['payout_processing_time'] ?? 3);

// The `total_earned` is now robustly managed by the system upon order completion.
// Please run fix_financial_data.php if there are any historical discrepancies.

$totalWithdrawn = $totalEarned - $balance;
if ($totalWithdrawn < 0) $totalWithdrawn = 0;

// Calculate eligible balance (only from payments where order is completed older than withdrawal_eligibility_days)
$totalEligibleEarned = (float)$db->query("
    SELECT SUM(p.academic_net)
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = $academicId AND p.status = 'paid' AND p.is_transferred_to_academic = 1 AND DATEDIFF(NOW(), o.updated_at) >= $withdrawal_eligibility_days
")->fetchColumn();
$eligibleBalance = $totalEligibleEarned - $totalWithdrawn;
if ($eligibleBalance < 0) $eligibleBalance = 0;
// Make sure it doesn't exceed total balance
if ($eligibleBalance > $balance) $eligibleBalance = $balance;
$pendingEligibility = $balance - $eligibleBalance;
if ($pendingEligibility < 0) $pendingEligibility = 0;

// Earnings this month (based on when the order was completed)
$thisMonthEarnings = (float)$db->query("
    SELECT COALESCE(SUM(p.academic_net), 0)
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.academic_id = $academicId AND p.status = 'paid' AND p.is_transferred_to_academic = 1 AND MONTH(o.updated_at) = MONTH(NOW()) AND YEAR(o.updated_at) = YEAR(NOW())
")->fetchColumn();

// Fetch academic's saved bank accounts
$stmt = $db->prepare("SELECT * FROM academic_bank_accounts WHERE academic_id = ? ORDER BY id ASC");
$stmt->execute([$academicId]);
$bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    WHERE o.academic_id = ? AND p.status = 'paid' AND p.is_transferred_to_academic = 1 AND YEAR(p.paid_at) = YEAR(NOW())
    GROUP BY MONTH(p.paid_at)
");
$earningsStmt->execute([$academicId]);
while ($row = $earningsStmt->fetch()) {
    $mIndex = (int)$row['m'] - 1;
    if ($mIndex >= 0 && $mIndex < 12) {
        $monthlyEarnings[$mIndex] = (float)$row['total'];
    }
}

// Fetch Payout Requests
$payoutsStmt = $db->prepare("SELECT * FROM payouts WHERE academic_id = ? ORDER BY created_at DESC");
$payoutsStmt->execute([$academicId]);
$payoutRequests = $payoutsStmt->fetchAll(PDO::FETCH_ASSOC);

$payoutMessages = [];
if (!empty($payoutRequests)) {
    $payoutIds = array_column($payoutRequests, 'id');
    $inQuery = implode(',', array_fill(0, count($payoutIds), '?'));
    $msgStmt = $db->prepare("SELECT * FROM payout_messages WHERE payout_id IN ($inQuery) ORDER BY created_at ASC");
    $msgStmt->execute($payoutIds);
    $msgs = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($msgs as $msg) {
        $payoutMessages[$msg['payout_id']][] = $msg;
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
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
          <div class="stat-value" data-counter="<?= round($eligibleBalance) ?>" data-suffix=" ر.س">0</div>
          <div class="stat-label">المتاح للسحب (مؤهل)</div>
          <div class="stat-trend" style="color:var(--text-secondary)">
            <?php if ($pendingEligibility > 0): ?>
              +<?= round($pendingEligibility) ?> ر.س معلق لفترة <?= $withdrawal_eligibility_days ?> يوم
            <?php else: ?>
              كل الرصيد قابل للصرف
            <?php endif; ?>
          </div>
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

      <!-- Withdrawal Requests -->
      <div class="tbl-container anim-up delay-2" style="margin-bottom:24px">
        <div class="tbl-header">
          <h3 class="tbl-title">💸 طلبات السحب</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>رقم الطلب</th>
                <th>المبلغ</th>
                <th>تاريخ الطلب</th>
                <th>الحالة</th>
                <th>الإجراء</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($payoutRequests)): ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary);">لا توجد طلبات سحب</td></tr>
              <?php else: ?>
                <?php foreach($payoutRequests as $pr): ?>
                <tr>
                  <td><span style="font-family:monospace;font-size:12px;background:var(--bg-main);padding:3px 8px;border-radius:6px"><?= e($pr['payout_number']) ?></span></td>
                  <td><strong><?= formatMoney($pr['amount']) ?> ر.س</strong></td>
                  <td style="font-size:13px;color:var(--text-secondary)"><?= date('Y-m-d H:i', strtotime($pr['created_at'])) ?></td>
                  <td>
                    <?php if($pr['status'] == 'pending'): ?>
                      <span class="badge badge-warning">قيد المراجعة</span>
                    <?php elseif($pr['status'] == 'approved'): ?>
                      <span class="badge badge-success">تمت الموافقة</span>
                    <?php elseif($pr['status'] == 'rejected'): ?>
                      <span class="badge badge-danger">مرفوض</span>
                    <?php else: ?>
                      <span class="badge badge-info"><?= e($pr['status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-outline" style="padding:4px 12px;font-size:13px;" onclick="openPayoutMessages(<?= $pr['id'] ?>)">المراسلات 
                    <?php if(isset($payoutMessages[$pr['id']])): ?>
                      (<span style="color:var(--primary);font-weight:bold;"><?= count($payoutMessages[$pr['id']]) ?></span>)
                    <?php endif; ?>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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
      <div class="alert alert-info"><span>ℹ️</span><span>رصيدك المؤهل للسحب: <strong><?= formatMoney($eligibleBalance) ?></strong></span></div>
      <div class="form-group">
        <label class="form-label">مبلغ السحب (ر.س)</label>
        <input class="form-input" id="withdrawAmount" type="number" placeholder="الحد الأدنى <?= $min_withdrawal ?> ر.س" min="<?= $min_withdrawal ?>" max="<?= intval($eligibleBalance) ?>"/>
      </div>
      <div class="form-group">
        <label class="form-label">اختر حساب الاستلام</label>
        <select class="form-input form-select" id="withdrawBankId" style="padding-left:36px">
          <?php if (empty($bankAccounts)): ?>
            <option value="">لا توجد حسابات مضافة (يرجى إضافتها من الملف الشخصي)</option>
          <?php else: ?>
            <?php foreach ($bankAccounts as $acc): ?>
              <option value="<?= (int)$acc['id'] ?>">
                <?= ($acc['account_type'] === 'wallet' ? '📱 محفظة ' : '🏦 بنك ') . e($acc['account_name']) ?> - <?= e($acc['account_number']) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="alert alert-warning"><span>⚠️</span><span>تُعالج طلبات السحب خلال <?= $payout_processing_time ?> أيام عمل كحد أقصى. الحد الأدنى للسحب هو <?= $min_withdrawal ?> ر.س.</span></div>
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

<!-- Payout Messages Modal -->
<div class="modal-overlay" id="payoutMessagesModal">
  <div class="modal-box" style="max-width:600px; padding:0;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-color);"><h3 class="modal-title">مراسلات طلب السحب</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body" style="background:#f9fafb; padding:20px; height:350px; overflow-y:auto;" id="payoutMessagesContainer">
      <!-- Messages injected here via JS -->
    </div>
    <div class="modal-footer" style="background:#fff; border-top:1px solid var(--border-color); padding:15px 20px; justify-content:flex-start;">
      <form method="POST" action="academic-earnings.php" style="width:100%; display:flex; gap:10px; margin:0;">
        <input type="hidden" name="action" value="send_payout_message">
        <input type="hidden" name="payout_id" id="replyPayoutId" value="">
        <input type="text" name="message" class="form-input" placeholder="اكتب ردك هنا واستفسر من الإدارة..." required style="flex:1;">
        <button type="submit" class="btn btn-primary">إرسال</button>
      </form>
    </div>
  </div>
</div>

<script src="assets/js/main.js"></script>
<script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

const payoutMessagesData = <?= json_encode($payoutMessages) ?>;
function openPayoutMessages(payoutId) {
    document.getElementById('replyPayoutId').value = payoutId;
    const container = document.getElementById('payoutMessagesContainer');
    container.innerHTML = '';
    const msgs = payoutMessagesData[payoutId] || [];
    
    if(msgs.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">لا توجد رسائل سابقة. يمكنك إرسال استفسارك للإدارة من هنا.</div>';
    } else {
        msgs.forEach(msg => {
            const isAdmin = msg.sender_type === 'admin';
            const align = isAdmin ? 'left' : 'right';
            const bg = isAdmin ? '#e0e7ff' : '#dcfce7';
            const title = isAdmin ? 'الإدارة' : 'أنت';
            container.innerHTML += `
              <div style="margin-bottom:12px; text-align:${align}">
                <div style="display:inline-block; max-width:85%; background:${bg}; padding:10px 14px; border-radius:12px; text-align:right; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                  <div style="font-size:11px; color:#666; margin-bottom:4px; font-weight:bold;">${title} <span style="font-weight:normal;font-size:10px;margin-right:5px;">${msg.created_at}</span></div>
                  <div style="font-size:14px; color:#111; line-height:1.5;">${msg.message.replace(/\\n/g, '<br>')}</div>
                </div>
              </div>
            `;
        });
        // Scroll to bottom
        setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);
    }
    Modal.open('payoutMessagesModal');
}

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
  const bankId = document.getElementById('withdrawBankId').value;

  if (!amount || +amount < <?= $min_withdrawal ?>) { Toast.show('المبلغ يجب أن يكون <?= $min_withdrawal ?> ر.س على الأقل', 'error'); return; }
  if (+amount > <?= $eligibleBalance ?>) { Toast.show('المبلغ يتجاوز الرصيد المؤهل للسحب', 'error'); return; }
  if (!bankId) { Toast.show('الرجاء اختيار أو إضافة حساب استلام من ملفك الشخصي', 'error'); return; }

  const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
  formData.append('amount', amount);
  formData.append('bank_account_id', bankId);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  formData.append('csrf_token', csrf);

  fetch('ajax/handler.php?action=submit_withdraw', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Modal.close('withdrawModal');
      Toast.show(data.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      Toast.show(data.message || data.error, 'error');
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
