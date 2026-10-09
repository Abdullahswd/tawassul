  <?php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
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
    Unified Payments & Receipts (دمج سجل المدفوعات والإشعارات)
  ───────────────────────────── */

  // 1. Fetch all standard payments
  $payments_stmt = $db->query("
      SELECT 
          p.id AS db_payment_id,
          CONCAT('PAY-', LPAD(p.id,6,'0')) AS payment_id,
          o.order_number AS target_name,
          u.name AS student,
          p.amount,
          p.method,
          p.status,
          DATE(p.created_at) AS date,
          p.order_id
      FROM payments p
      LEFT JOIN orders o ON p.order_id = o.id
      LEFT JOIN users u ON p.student_id = u.id
      ORDER BY p.created_at DESC
      LIMIT 150
  ");
  $all_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

  // 2. Fetch all receipts
  $receipts = getAllPaymentReceipts();

  // 3. Combine them
  $combined_payments = [];
  $processed_receipt_ids = [];

  foreach ($all_payments as $p) {
      $item = $p;
      $item['receipt_id'] = null;
      $item['receipt_status'] = null;
      $item['receipt_path'] = null;
      $item['student_note'] = null;

      // Find if there's a receipt for this order
      if (!empty($p['order_id'])) {
          foreach ($receipts as $rc) {
              if ($rc['order_id'] == $p['order_id']) {
                  $item['receipt_id'] = $rc['id'];
                  $item['receipt_status'] = $rc['status'];
                  $item['receipt_path'] = $rc['receipt_path'];
                  $item['student_note'] = $rc['student_note'];
                  $processed_receipt_ids[] = $rc['id'];
                  break;
              }
          }
      }
      $combined_payments[] = $item;
  }

  // 4. Add remaining standalone receipts (e.g. Packages that don't have a payments row yet)
  foreach ($receipts as $rc) {
      if (!in_array($rc['id'], $processed_receipt_ids)) {
          $target = $rc['order_id'] ? 'طلب ' . $rc['order_number'] : 'باقة ' . $rc['package_name'];
          $combined_payments[] = [
              'db_payment_id' => 'RC-' . $rc['id'],
              'payment_id' => 'RC-' . $rc['id'],
              'target_name' => $target,
              'student' => $rc['student_name'] ?? 'طالب غير معروف',
              'amount' => $rc['amount'],
              'method' => $rc['method_label'] ?? 'bank_transfer',
              'status' => $rc['status'] === 'approved' ? 'paid' : ($rc['status'] === 'rejected' ? 'failed' : 'pending'),
              'date' => date('Y-m-d', strtotime($rc['created_at'])),
              'receipt_id' => $rc['id'],
              'receipt_status' => $rc['status'],
              'receipt_path' => $rc['receipt_path'],
              'student_note' => $rc['student_note']
          ];
      }
  }

  // Sort unified array by date descending
  usort($combined_payments, function($a, $b) {
      return strtotime($b['date']) - strtotime($a['date']);
  });

  // Handle receipt approve/reject form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'], $_POST['receipt_action'])) {
      $rid = (int) $_POST['receipt_id'];
      $action = $_POST['receipt_action'] === 'approve' ? 'approved' : 'rejected';
      $reason = $_POST['rejection_reason'] ?? null;
      updatePaymentReceiptStatus($rid, $action, $reason);
      
      // Add a notification to the student about the receipt status
      $stmt = $db->prepare('SELECT student_id, order_id FROM payment_receipts WHERE id = ?');
      $stmt->execute([$rid]);
      $receiptData = $stmt->fetch();
      if ($receiptData) {
          $msg = $action === 'approved' ? 'تم اعتماد إيصال الدفع بنجاح ✅ وجاري تعيين أكاديمي لطلبك.' : 'تم رفض إيصال الدفع. يرجى مراجعة السبب المرفق ❌.';
          $link = $receiptData['order_id'] ? 'student/order-details.php?id=' . $receiptData['order_id'] : 'student/packages.php';
          createNotification($receiptData['student_id'], 'student', 'تحديث حالة إيصال الدفع 🧾', $msg, '🧾', $link);
          
          // Auto-update order to pending_assignment if approved
          if ($action === 'approved' && $receiptData['order_id']) {
              $db->prepare("UPDATE orders SET status = 'pending_assignment' WHERE id = ? AND status = 'awaiting_payment'")
                ->execute([$receiptData['order_id']]);
                
              // Mark payment as paid
              $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND status != 'paid'")
                ->execute([$receiptData['order_id']]);
          }
      }
      
      header('Location: payments.php?receipt_' . ($action === 'approved' ? 'approved' : 'rejected') . '=1');
      exit;
  }

  /* ─────────────────────────────
    Refund Requests (طلبات الاسترجاع)
  ──────────────────────────── */
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_id'], $_POST['refund_action'])) {
      $rid = (int) $_POST['refund_id'];
      $action = $_POST['refund_action'] === 'process' ? 'processed' : 'rejected';
      $admin_note = $_POST['admin_note'] ?? '';
      
      $db->prepare("UPDATE refund_requests SET status = ?, admin_note = ? WHERE id = ?")
        ->execute([$action, $admin_note, $rid]);
        
      // Notify student
      $stmt = $db->prepare('SELECT student_id, order_id FROM refund_requests WHERE id = ?');
      $stmt->execute([$rid]);
      $refundData = $stmt->fetch();
      if ($refundData) {
          if ($action === 'processed') {
              // Update order status if it was cancellation_requested
              $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'cancellation_requested'")->execute([$refundData['order_id']]);
              // Update payments to refunded
              $db->prepare("UPDATE payments SET status = 'refunded' WHERE order_id = ?")->execute([$refundData['order_id']]);
              $db->prepare("UPDATE payment_receipts SET status = 'rejected', rejection_reason = 'تم استرجاع المبلغ' WHERE order_id = ?")->execute([$refundData['order_id']]);
          }
          
          $msg = $action === 'processed' ? 'تم تحويل مبلغ الاسترجاع بنجاح لطلبك الملغي 💸.' : 'نعتذر، تم رفض طلب الاسترجاع للسبب التالي: ' . $admin_note;
          $link = 'student/order-details.php?id=' . $refundData['order_id'];
          createNotification($refundData['student_id'], 'student', 'تحديث حالة طلب الاسترجاع 💸', $msg, '💸', $link);
      }
      
      header('Location: payments.php?refund_updated=1');
      exit;
  }

  $refunds_stmt = $db->query("
      SELECT r.*, u.name AS student_name, o.order_number 
      FROM refund_requests r
      JOIN users u ON r.student_id = u.id
      JOIN orders o ON r.order_id = o.id
      ORDER BY r.created_at DESC
  ");
  $refunds = $refunds_stmt->fetchAll();
  $adminAppBase = rtrim(preg_replace('#/admin/pages/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
  $receiptStatusLabels = [
      'pending'  => ['قيد المراجعة ⏳', '#fef9c3', '#854d0e', '#fde047'],
      'approved' => ['مُعتمد ✅', '#dcfce7', '#166534', '#86efac'],
      'rejected' => ['مرفوض ❌', '#fee2e2', '#991b1b', '#fca5a5'],
  ];
  ?>
  <!DOCTYPE html>
  <html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>المدفوعات - Eduroad Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>" />
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
            <h1 class="page-header-title">إدارة المدفوعات والشؤون المالية</h1>
            <p class="page-header-subtitle">متابعة مدفوعات الطلاب، طلبات الاسترجاع، ومستحقات الأكاديميين</p>
          </div>
        </div>

        <div data-tabs>
          <div class="tabs-container animate-fadeInUp delay-1">
            <div class="tabs-list">
              <button class="tab-btn active" data-tab="tab-general">📊 التفاصيل العامة</button>
              <button class="tab-btn" data-tab="tab-students">🎓 مدفوعات الطلاب</button>
              <button class="tab-btn" data-tab="tab-refunds">💸 طلبات الاسترجاع</button>
              <button class="tab-btn" data-tab="tab-academics">👨‍🏫 مستحقات الأكاديميين</button>
            </div>
          </div>

          <!-- ==============================
              TAB 0: GENERAL DETAILS
              ============================== -->
          <div id="tab-general" class="tab-panel active">
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

        </div> <!-- END TAB 0 -->

          <!-- ==============================
              TAB 1: STUDENTS PAYMENTS
              ============================== -->
          <div id="tab-students" class="tab-panel">
        <!-- Payments Table -->
        <div class="table-container animate-fadeInUp delay-3">
          <div class="table-header">
            <h3 class="table-title">سجل المدفوعات والإشعارات</h3>
            <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="paySearch" placeholder="بحث..." /></div>
            <select class="form-input form-select" id="payStatusFilter" style="width:auto;padding-left:32px;font-size:14px">
              <option value="">جميع الحالات</option>
              <option value="paid">مدفوع / معتمد</option>
              <option value="pending">معلق / قيد المراجعة</option>
              <option value="failed">فاشل / مرفوض</option>
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
                  <th>الطلب / الباقة</th>
                  <th>الطالب</th>
                  <th>المبلغ</th>
                  <th>طريقة الدفع</th>
                  <th>حالة الدفع</th>
                  <th>الإشعار المرفق</th>
                  <th>التاريخ</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody id="paymentsTableBody"></tbody>
            </table>
          </div>

          <!-- Totals Row -->
          <div style="padding:16px 24px;background:var(--bg-main);border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div style="font-size:13px;color:var(--text-secondary)">عرض 1-<?= count($combined_payments) ?> عملية</div>
            <div style="display:flex;gap:24px">
              <div style="text-align:center"><div style="font-size:13px;color:var(--text-secondary)">إجمالي المبالغ</div><div style="font-size:18px;font-weight:800;color:var(--success)"><?= number_format($total_revenue, 0, '.', ',') ?> ر.س</div></div>
            </div>
            <div class="pagination-pages">
              <button class="page-btn">‹</button>
              <button class="page-btn active">1</button>
              <button class="page-btn">2</button>
              <button class="page-btn">›</button>
            </div>
          </div>

        </div>
      </div> <!-- END TAB 1 -->

      <!-- ==============================
          TAB 2: REFUND REQUESTS
          ============================== -->
      <div id="tab-refunds" class="tab-panel">
        <!-- ===================== Refund Requests (طلبات الاسترجاع) ===================== -->
        <div class="table-container animate-fadeInUp delay-2">
          <div class="table-header">
            <h3 class="table-title">💸 طلبات الاسترجاع للطلاب</h3>
            <p style="font-size:13px;color:var(--text-secondary)">الطلبات الملغاة التي تتطلب تحويل المبلغ للطالب</p>
          </div>
          <div style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>الطالب</th>
                  <th>رقم الطلب</th>
                  <th>المبلغ المطلوب</th>
                  <th>تاريخ الطلب</th>
                  <th>الحالة</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($refunds)): ?>
                  <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-secondary)">لا توجد طلبات استرجاع</td></tr>
                <?php endif; ?>
                <?php foreach ($refunds as $rf): 
                    $rfStatus = match($rf['status']) {
                        'pending' => ['قيد الانتظار ⏳', '#fef9c3', '#854d0e', '#fde047'],
                        'processed' => ['تم التحويل ✅', '#dcfce7', '#166534', '#bbf7d0'],
                        'rejected' => ['مرفوض ❌', '#fee2e2', '#991b1b', '#fecaca'],
                        default => ['غير معروف', '#f1f5f9', '#475569', '#cbd5e1']
                    };
                ?>
                  <tr>
                    <td style="font-weight:700"><?= e($rf['student_name']) ?></td>
                    <td><a href="order-details.php?id=<?= $rf['order_number'] ?>" style="color:var(--primary);font-weight:700;text-decoration:none">#<?= e($rf['order_number']) ?></a></td>
                    <td style="font-weight:800;color:var(--danger)"><?= number_format((float)$rf['amount'], 2) ?> ر.س</td>
                    <td style="font-size:13px"><?= formatDate($rf['created_at']) ?></td>
                    <td><span style="background:<?= $rfStatus[1] ?>;color:<?= $rfStatus[2] ?>;border:1px solid <?= $rfStatus[3] ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:800"><?= $rfStatus[0] ?></span></td>
                    <td>
                      <?php if ($rf['status'] === 'pending'): ?>
                        <div style="display:flex;gap:6px">
                          <form method="POST" style="margin:0" onsubmit="return confirm('هل قمت بتحويل المبلغ لحساب الطالب بالفعل؟')">
                            <input type="hidden" name="refund_id" value="<?= (int)$rf['id'] ?>">
                            <input type="hidden" name="refund_action" value="process">
                            <button type="submit" style="background:#10b981;color:#fff;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">تم التحويل ✓</button>
                          </form>
                          <form method="POST" style="margin:0;display:flex;align-items:center;gap:4px">
                            <input type="hidden" name="refund_id" value="<?= (int)$rf['id'] ?>">
                            <input type="hidden" name="refund_action" value="reject">
                            <input type="text" name="admin_note" placeholder="سبب الرفض..." required style="width:110px;font-size:11px;padding:4px;border:1px solid #fca5a5;border-radius:4px;outline:none">
                            <button type="submit" style="background:#ef4444;color:#fff;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">رفض ✕</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span style="font-size:12px;color:var(--text-secondary)"><?= $rf['admin_note'] ? 'ملاحظة: ' . e($rf['admin_note']) : '—' ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div> <!-- END TAB 2 -->

      <!-- ==============================
          TAB 3: ACADEMIC PAYOUTS
          ============================== -->
      <div id="tab-academics" class="tab-panel">
        <!-- ===================== Academic Payouts ===================== -->
        <div class="chart-card animate-fadeInUp delay-2">
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
                  <th>الحالة</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody id="payoutsTableBody"></tbody>
            </table>
          </div>
        </div>
        </div> <!-- END TAB 3 -->
      </div> <!-- END data-tabs -->
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

  <!-- Payment Details Modal -->
  <div class="modal-overlay" id="paymentDetailsModal">
    <div class="modal-box" style="max-width:500px">
      <div class="modal-header">
        <h3 class="modal-title">تفاصيل الدفعة</h3>
        <button class="modal-close" data-modal-close>✕</button>
      </div>
      <div class="modal-body" id="paymentDetailsBody" style="line-height:1.8">
        <!-- Injected via JS -->
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" data-modal-close>إغلاق</button>
      </div>
    </div>
  </div>

  <!-- Admin Payout Messages Modal -->
  <div class="modal-overlay" id="adminPayoutMessagesModal">
    <div class="modal-box" style="max-width:600px; padding:0;">
      <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-color);"><h3 class="modal-title">مراسلات طلب السحب</h3><button class="modal-close" data-modal-close>✕</button></div>
      <div class="modal-body" style="background:#f9fafb; padding:20px; height:350px; overflow-y:auto;" id="adminPayoutMessagesContainer">
        <!-- Messages injected here via JS -->
      </div>
      <div class="modal-footer" style="background:#fff; border-top:1px solid var(--border-color); padding:15px 20px; justify-content:flex-start;">
        <div style="width:100%; display:flex; gap:10px; margin:0;">
          <input type="hidden" id="adminReplyPayoutId" value="">
          <input type="text" id="adminPayoutMessageText" class="form-input" placeholder="اكتب ردك للأكاديمي..." required style="flex:1;">
          <button type="button" class="btn btn-primary" onclick="sendAdminPayoutMessage()">إرسال</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/js/main.js?v=<?= time() ?>"></script>
  <script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

  MOCK_DATA.payments = <?= json_encode($combined_payments) ?>;
  MOCK_DATA.chartData = {
    revenue: <?= json_encode(array_values($rev_by_month)) ?>,
    months: <?= json_encode($months_ar) ?>
  };

  document.getElementById('profileDropdown')?.addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('open');});
  document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

  const METHOD_NAMES = {
    'credit_card': 'بطاقة ائتمانية',
    'bank_transfer': 'تحويل بنكي',
    'wallet': 'محفظة إلكترونية',
    'apple_pay': 'Apple Pay',
    'stc_pay': 'STC Pay'
  };
  const METHOD_ICONS = { 
    'credit_card': '💳', 
    'bank_transfer': '🏦', 
    'wallet': '📱',
    'apple_pay': '🍏',
    'stc_pay': '📱'
  };

  function renderPayments(data) {
    const tbody = document.getElementById('paymentsTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.map((p, i) => {
      const methodAr = METHOD_NAMES[p.method] || p.method || 'تحويل';
      const methodIcon = METHOD_ICONS[p.method] || '💰';
      
      let receiptHtml = '<span style="color:#94a3b8;font-size:12px">بدون إشعار</span>';
      let actionsHtml = '';

      if (p.receipt_id) {
         const path = p.receipt_path || '';
         receiptHtml = `<a href="${path.startsWith('http') ? path : '../' + path}" target="_blank" class="btn btn-outline" style="padding:4px 8px;font-size:11px;display:inline-block">👁 عرض الإشعار</a>`;
         if (p.student_note) {
            receiptHtml += \`<div style="margin-top:6px;font-size:10px;color:#475569;background:#f1f5f9;padding:6px;border-radius:4px;border:1px solid #cbd5e1;line-height:1.4">💬 \${p.student_note}</div>\`;
         }
         
         if (p.receipt_status === 'pending') {
             actionsHtml = \`
               <div style="display:flex;gap:4px;flex-direction:column;min-width:140px">
                 <form method="POST" style="margin:0">
                   <input type="hidden" name="receipt_id" value="\${p.receipt_id}">
                   <input type="hidden" name="receipt_action" value="approve">
                   <button type="submit" style="background:#10b981;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer;width:100%">اعتماد ✓</button>
                 </form>
                 <form method="POST" style="margin:0;display:flex;align-items:center;gap:4px">
                   <input type="hidden" name="receipt_id" value="\${p.receipt_id}">
                   <input type="hidden" name="receipt_action" value="reject">
                   <input type="text" name="rejection_reason" placeholder="سبب الرفض..." required style="flex:1;min-width:60px;font-size:10px;padding:4px;border:1px solid #fca5a5;border-radius:4px;outline:none">
                   <button type="submit" style="background:#ef4444;color:#fff;border:none;padding:6px 8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer">رفض</button>
                 </form>
               </div>
             \`;
         } else if (p.receipt_status === 'approved') {
             actionsHtml = '<span style="color:#10b981;font-size:12px;font-weight:bold;background:#dcfce7;padding:4px 8px;border-radius:12px">الإشعار مُعتمد ✅</span>';
         } else if (p.receipt_status === 'rejected') {
             actionsHtml = '<span style="color:#ef4444;font-size:12px;font-weight:bold;background:#fee2e2;padding:4px 8px;border-radius:12px">الإشعار مرفوض ❌</span>';
         }
      } else {
         actionsHtml = \`<button class="btn btn-sm btn-outline" onclick="viewPaymentDetails('\${p.payment_id || p.id}')">تفاصيل أكثر</button>\`;
      }

      return \`
      <tr>
        <td><span style="font-family:monospace;font-size:13px;background:var(--bg-main);padding:4px 8px;border-radius:6px">\${p.payment_id || p.id}</span></td>
        <td><a href="order-details.php" style="color:var(--primary);font-weight:700;text-decoration:none">\${p.target_name || ''}</a></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="table-avatar" style="background:\${getAvatarColor(i)};width:30px;height:30px;border-radius:8px;font-size:11px;color:white">\${p.student ? p.student.slice(0,2) : ''}</div>
            <span style="font-weight:600;font-size:13px">\${p.student}</span>
          </div>
        </td>
        <td><strong style="font-size:15px;color:var(--text-primary)">\${Number(p.amount).toLocaleString('ar')} ر.س</strong></td>
        <td><div style="display:flex;align-items:center;gap:4px"><span style="font-size:16px">\${methodIcon}</span> <span style="font-size:12px;font-weight:500">\${methodAr}</span></div></td>
        <td>\${getStatusBadge(p.status === 'paid' ? 'paid' : p.status === 'pending' ? 'pending_pay' : 'failed')}</td>
        <td>\${receiptHtml}</td>
        <td style="color:var(--text-secondary);font-size:12px" dir="ltr">\${p.date}</td>
        <td>\${actionsHtml}</td>
      </tr>
    \`}).join('');
  }

  document.addEventListener('click',()=>{document.getElementById('profileDropdown')?.classList.remove('open');});

  function viewPaymentDetails(paymentId) {
    const p = MOCK_DATA.payments.find(x => (x.payment_id || x.id) === paymentId);
    if (!p) return;
    const body = document.getElementById('paymentDetailsBody');
    
    const statusHtml = getStatusBadge(p.status === 'paid' ? 'paid' : p.status === 'pending' ? 'pending_pay' : 'failed');
    const methodAr = METHOD_NAMES[p.method] || p.method || 'تحويل';
    const methodIcon = METHOD_ICONS[p.method] || '💰';
    
    body.innerHTML = `
      <div style="background:var(--bg-main);padding:16px;border-radius:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:12px;color:var(--text-secondary)">رقم الدفعة</div>
          <div style="font-size:16px;font-family:monospace;font-weight:700">${p.payment_id || p.id}</div>
        </div>
        <div>${statusHtml}</div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:10px 0;color:var(--text-secondary)">الطلب/الباقة</td><td style="padding:10px 0;text-align:left;font-weight:600"><a href="order-details.php" style="color:var(--primary);text-decoration:none">${p.target_name || ''}</a></td></tr>
        <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:10px 0;color:var(--text-secondary)">الطالب</td><td style="padding:10px 0;text-align:left;font-weight:600">${p.student}</td></tr>
        <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:10px 0;color:var(--text-secondary)">طريقة الدفع</td><td style="padding:10px 0;text-align:left;font-weight:600">${methodIcon} ${methodAr}</td></tr>
        <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:10px 0;color:var(--text-secondary)">المبلغ الإجمالي</td><td style="padding:10px 0;text-align:left;font-weight:700;color:var(--text-primary)">${Number(p.amount).toLocaleString('ar')} ر.س</td></tr>
        <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:10px 0;color:var(--text-secondary)">التاريخ</td><td style="padding:10px 0;text-align:left" dir="ltr">${p.date}</td></tr>
      </table>
    `;
    Modal.open('paymentDetailsModal');
  }

  function applyPayFilters() {
    const q = document.getElementById('paySearch').value.toLowerCase();
    const st = document.getElementById('payStatusFilter').value;
    const mt = document.getElementById('payMethodFilter').value;
    const filtered = MOCK_DATA.payments.filter(p => {
      const pId = p.payment_id || p.id || '';
      const pOrder = p.order_number || p.order || '';
      const matchQ = !q || pId.toLowerCase().includes(q) || p.student.includes(q) || pOrder.toLowerCase().includes(q);
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
  let PAYOUT_DATA = { academics: [], payouts: [], stats: {}, messages: {} };

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
  fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'get_data');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.append('csrf_token', csrf);
    fetch('../ajax/manage_payouts.php', { 
      method: 'POST', 
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) { Toast.show(res.message || 'تعذر جلب البيانات', 'error'); return; }
        PAYOUT_DATA = { academics: res.academics, payouts: res.payouts, stats: res.stats, messages: res.messages };
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
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-secondary);padding:24px">لا توجد دفعات مسجلة بعد.</td></tr>';
      return;
    }
    tbody.innerHTML = PAYOUT_DATA.payouts.map(p => {
      const receiver = p.bank_name
        ? `${p.bank_type === 'wallet' ? '👛' : '🏦'} ${escHtml(p.bank_name)} <span style="color:var(--text-secondary)" dir="ltr">${escHtml(p.bank_number)}</span>`
        : '<span style="color:var(--text-secondary)">—</span>';
        
      let statusBadge = `<span class="badge badge-info">${escHtml(p.status)}</span>`;
      if(p.status === 'paid') statusBadge = '<span style="color:var(--success);font-weight:bold;">مدفوع</span>';
      if(p.status === 'pending') statusBadge = '<span style="color:var(--warning);font-weight:bold;">قيد المراجعة</span>';
      if(p.status === 'approved') statusBadge = '<span style="color:var(--success);font-weight:bold;">تمت الموافقة</span>';
      if(p.status === 'rejected') statusBadge = '<span style="color:var(--danger);font-weight:bold;">مرفوض</span>';

      let actions = '';
      if(p.status === 'pending') {
          actions += `<button class="btn btn-sm btn-success" onclick="updatePayoutStatus(${p.id}, 'approved')" style="margin-left:4px;">موافقة</button>`;
          actions += `<button class="btn btn-sm btn-danger" onclick="updatePayoutStatus(${p.id}, 'rejected')" style="margin-left:4px;">رفض</button>`;
      }
      const msgsCount = PAYOUT_DATA.messages && PAYOUT_DATA.messages[p.id] ? PAYOUT_DATA.messages[p.id].length : 0;
      actions += `<button class="btn btn-sm btn-outline" onclick="openAdminPayoutMessages(${p.id})">المراسلات (${msgsCount})</button>`;

      return `<tr>
        <td><span style="font-family:monospace;font-size:13px;background:var(--bg-main);padding:3px 8px;border-radius:6px">${escHtml(p.payout_number)}</span></td>
        <td style="font-weight:600">${escHtml(p.academic_name || '-')}</td>
        <td>${receiver}</td>
        <td><strong style="color:var(--success)">${fmtMoney(p.amount)} ر.س</strong></td>
        <td style="color:var(--text-secondary);font-size:13px">${escHtml(p.paid_at || p.created_at || '')}</td>
        <td>${statusBadge}</td>
        <td>${actions}</td>
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
  fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'add_payout');
    fd.append('academic_id', academicId);
    fd.append('bank_account_id', bankId || 0);
    fd.append('amount', amount);
    fd.append('paid_at', date);
    fd.append('note', note);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.append('csrf_token', csrf);
    fetch('../ajax/manage_payouts.php', { 
        method: 'POST', 
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
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
  function updatePayoutStatus(payoutId, status) {
      if (!confirm('هل أنت متأكد من تحديث حالة الطلب؟')) return;
      const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
      fd.append('action', 'update_payout_status');
      fd.append('payout_id', payoutId);
      fd.append('status', status);
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      fd.append('csrf_token', csrf);
      fetch('../ajax/manage_payouts.php', { 
          method: 'POST', 
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show(res.message, 'success');
                loadPayoutData();
            } else {
                Toast.show(res.message, 'error');
            }
        });
  }

  function openAdminPayoutMessages(payoutId) {
      document.getElementById('adminReplyPayoutId').value = payoutId;
      document.getElementById('adminPayoutMessageText').value = '';
      const container = document.getElementById('adminPayoutMessagesContainer');
      container.innerHTML = '';
      const msgs = PAYOUT_DATA.messages[payoutId] || [];
      
      if(msgs.length === 0) {
          container.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">لا توجد رسائل سابقة.</div>';
      } else {
          msgs.forEach(msg => {
              const isAdmin = msg.sender_type === 'admin';
              const align = isAdmin ? 'left' : 'right';
              const bg = isAdmin ? '#e0e7ff' : '#dcfce7';
              const title = isAdmin ? 'أنت (الإدارة)' : 'الأكاديمي';
              container.innerHTML += `
                <div style="margin-bottom:12px; text-align:${align}">
                  <div style="display:inline-block; max-width:85%; background:${bg}; padding:10px 14px; border-radius:12px; text-align:right; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <div style="font-size:11px; color:#666; margin-bottom:4px; font-weight:bold;">${title} <span style="font-weight:normal;font-size:10px;margin-right:5px;">${msg.created_at}</span></div>
                    <div style="font-size:14px; color:#111; line-height:1.5;">${escHtml(msg.message).replace(/\\n/g, '<br>')}</div>
                  </div>
                </div>
              `;
          });
          setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);
      }
      Modal.open('adminPayoutMessagesModal');
  }

  function sendAdminPayoutMessage() {
      const payoutId = document.getElementById('adminReplyPayoutId').value;
      const msgText = document.getElementById('adminPayoutMessageText').value.trim();
      if (!payoutId || !msgText) return;
      
      const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
      fd.append('action', 'send_message');
      fd.append('payout_id', payoutId);
      fd.append('message', msgText);
      
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      fd.append('csrf_token', csrf);
      fetch('../ajax/manage_payouts.php', { 
          method: 'POST', 
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('adminPayoutMessageText').value = '';
                loadPayoutData();
                setTimeout(() => openAdminPayoutMessages(payoutId), 300); // refresh messages
            } else {
                Toast.show(res.message || 'حدث خطأ', 'error');
            }
        });
  }
  </script>
  </body>
  </html>

