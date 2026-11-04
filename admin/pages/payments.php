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
  Student Payments & Receipts (سجل مدفوعات وإشعارات الطلاب)
───────────────────────────── */

ensureMissingPaymentReceipts();

/* ── Unified Payment / Receipt Action (اعتماد أو رفض الدفعة والإشعار) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['payment_action']) || isset($_POST['receipt_action']))) {
    $action = ($_POST['payment_action'] ?? $_POST['receipt_action']) === 'approve' ? 'approve' : 'reject';
    $pid = (int)($_POST['payment_id'] ?? 0);
    $rid = (int)($_POST['receipt_id'] ?? 0);
    $oid = (int)($_POST['order_id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');

    $payData = null;
    if ($pid > 0) {
        $stmt = $db->prepare('SELECT p.*, o.order_number FROM payments p LEFT JOIN orders o ON p.order_id = o.id WHERE p.id = ?');
        $stmt->execute([$pid]);
        $payData = $stmt->fetch();
        if ($payData && empty($oid) && !empty($payData['order_id'])) {
            $oid = (int)$payData['order_id'];
        }
    }

    if (empty($oid) && $rid > 0) {
        $rstmt = $db->prepare('SELECT * FROM payment_receipts WHERE id = ?');
        $rstmt->execute([$rid]);
        $rData = $rstmt->fetch();
        if ($rData && !empty($rData['order_id'])) {
            $oid = (int)$rData['order_id'];
        }
    }

    if ($action === 'approve') {
        if ($pid > 0) {
            $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$pid]);
        }
        if ($oid > 0) {
            $db->prepare("UPDATE orders SET status = 'pending_assignment' WHERE id = ? AND status IN ('awaiting_payment', 'new', 'pending')")
              ->execute([$oid]);
            $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND status != 'paid'")
              ->execute([$oid]);
            $db->prepare("UPDATE payment_receipts SET status = 'approved', rejection_reason = NULL WHERE order_id = ?")
              ->execute([$oid]);

            try {
                syncAcademicEarnings($oid);
            } catch (\Throwable $e) {
                error_log("syncAcademicEarnings error: " . $e->getMessage());
            }

            try {
                $ordNum = $payData['order_number'] ?? '';
                if (empty($ordNum)) {
                    $ordNum = $db->query("SELECT order_number FROM orders WHERE id = $oid")->fetchColumn() ?: '';
                }
                $studentId = $payData['student_id'] ?? $db->query("SELECT student_id FROM orders WHERE id = $oid")->fetchColumn();
                if ($studentId) {
                    createNotification(
                        $studentId,
                        'student',
                        'تأكيد استلام الدفعة ✅',
                        'تم اعتماد دفعتك بنجاح للطلب #' . $ordNum . ' وجاري تعيين أكاديمي لطلبك.',
                        '💰',
                        'student/order-details.php?id=' . $oid
                    );
                }
            } catch (\Throwable $e) {
                error_log("createNotification error: " . $e->getMessage());
            }
        }
        if ($rid > 0) {
            updatePaymentReceiptStatus($rid, 'approved');
            $subId = $db->query("SELECT subscription_id FROM payment_receipts WHERE id = $rid")->fetchColumn();
            if ($subId) {
                $db->prepare("UPDATE package_subscriptions SET status = 'active' WHERE id = ?")->execute([$subId]);
                $stdId = $db->query("SELECT student_id FROM payment_receipts WHERE id = $rid")->fetchColumn();
                if ($stdId) {
                    try {
                        createNotification($stdId, 'student', 'اعتماد اشتراك الباقة ✅', 'تم اعتماد إيصال الدفع وتفعيل اشتراكك في الباقة بنجاح.', '📦', 'student/packages.php');
                    } catch (\Throwable $e) {
                        error_log("createNotification error: " . $e->getMessage());
                    }
                }
            }
        }
        header('Location: payments.php?payment_updated=paid#tab-students');
        exit;
    } else {
        $rejMsg = !empty($reason) ? $reason : 'تم رفض الدفعة من قبل الإدارة';
        if ($pid > 0) {
            $db->prepare("UPDATE payments SET status = 'failed' WHERE id = ?")->execute([$pid]);
        }
        if ($oid > 0) {
            $db->prepare("UPDATE payment_receipts SET status = 'rejected', rejection_reason = ? WHERE order_id = ?")
              ->execute([$rejMsg, $oid]);

            try {
                $ordNum = $payData['order_number'] ?? '';
                if (empty($ordNum)) {
                    $ordNum = $db->query("SELECT order_number FROM orders WHERE id = $oid")->fetchColumn() ?: '';
                }
                $studentId = $payData['student_id'] ?? $db->query("SELECT student_id FROM orders WHERE id = $oid")->fetchColumn();
                if ($studentId) {
                    $msg = 'تعذر تأكيد دفعتك للطلب #' . $ordNum . '. السبب: ' . $rejMsg;
                    createNotification($studentId, 'student', 'فشل تأكيد الدفعة ❌', $msg, '❌', 'student/order-details.php?id=' . $oid);
                }
            } catch (\Throwable $e) {
                error_log("createNotification error: " . $e->getMessage());
            }
        }
        if ($rid > 0) {
            updatePaymentReceiptStatus($rid, 'rejected', $rejMsg);
        }
        header('Location: payments.php?payment_updated=failed#tab-students');
        exit;
    }
}

$payments_stmt = $db->query("
    SELECT 
        p.id AS raw_id,
        CONCAT('PAY-', LPAD(p.id,6,'0')) AS payment_id,
        p.payment_number,
        p.order_id,
        p.student_id,
        p.amount,
        p.platform_fee,
        p.academic_net,
        p.method,
        p.status,
        p.transaction_ref,
        p.discount,
        p.coupon_code,
        p.paid_at,
        p.created_at,
        DATE(p.created_at) AS date,
        COALESCE(o.order_number, '—') AS order_number,
        o.status AS order_status,
        o.academic_level,
        o.specialty,
        o.language,
        o.deadline,
        o.description AS order_description,
        COALESCE(s.name, pkg.name, 'طلب خدمة أكاديمية') AS service_title,
        COALESCE(u.name, '—') AS student,
        COALESCE(u.email, '') AS student_email,
        COALESCE(u.phone, '') AS student_phone,
        COALESCE(
            (SELECT pr_sub.id FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.receipt_path IS NOT NULL AND pr_sub.receipt_path != '' ORDER BY pr_sub.id DESC LIMIT 1),
            pr.id,
            0
        ) AS receipt_id,
        COALESCE(
            (SELECT pr_sub.receipt_path FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.receipt_path IS NOT NULL AND pr_sub.receipt_path != '' ORDER BY pr_sub.id DESC LIMIT 1),
            NULLIF(pr.receipt_path, ''),
            NULLIF(p.receipt_path, ''),
            (SELECT oa.file_path FROM order_attachments oa WHERE oa.order_id = p.order_id AND (oa.file_name LIKE '%.jpg' OR oa.file_name LIKE '%.png' OR oa.file_name LIKE '%.jpeg' OR oa.file_name LIKE '%.webp' OR oa.file_name LIKE '%.pdf' OR oa.file_name LIKE '%receipt%' OR oa.file_name LIKE '%إشعار%' OR oa.file_name LIKE '%اشعار%') ORDER BY oa.id DESC LIMIT 1),
            ''
        ) AS receipt_path,
        COALESCE(
            (SELECT pr_sub.method_label FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.method_label IS NOT NULL AND pr_sub.method_label != '' ORDER BY pr_sub.id DESC LIMIT 1),
            pr.method_label,
            ''
        ) AS method_label,
        COALESCE(
            (SELECT pr_sub.student_note FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.student_note IS NOT NULL AND pr_sub.student_note != '' ORDER BY pr_sub.id DESC LIMIT 1),
            pr.student_note,
            ''
        ) AS student_note,
        COALESCE(
            (SELECT pr_sub.rejection_reason FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.rejection_reason IS NOT NULL AND pr_sub.rejection_reason != '' ORDER BY pr_sub.id DESC LIMIT 1),
            pr.rejection_reason,
            ''
        ) AS rejection_reason,
        COALESCE(
            (SELECT pr_sub.status FROM payment_receipts pr_sub WHERE pr_sub.order_id = p.order_id AND pr_sub.receipt_path IS NOT NULL AND pr_sub.receipt_path != '' ORDER BY pr_sub.id DESC LIMIT 1),
            pr.status,
            ''
        ) AS receipt_status
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.id
    LEFT JOIN services s ON o.service_id = s.id
    LEFT JOIN packages pkg ON o.package_id = pkg.id
    LEFT JOIN users u ON p.student_id = u.id
    LEFT JOIN (
        SELECT pr1.*
        FROM payment_receipts pr1
        INNER JOIN (
            SELECT MAX(id) AS max_id
            FROM payment_receipts
            WHERE order_id IS NOT NULL
            GROUP BY order_id
        ) pr2 ON pr1.id = pr2.max_id
    ) pr ON p.order_id = pr.order_id
    ORDER BY p.created_at DESC
    LIMIT 100
");
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

$sub_receipts = $db->query("
    SELECT 
        0 AS raw_id,
        CONCAT('RCPT-', LPAD(pr.id,6,'0')) AS payment_id,
        CONCAT('RCPT-', LPAD(pr.id,6,'0')) AS payment_number,
        0 AS order_id,
        pr.student_id,
        pr.amount,
        0.00 AS platform_fee,
        pr.amount AS academic_net,
        'bank_transfer' AS method,
        CASE WHEN pr.status = 'approved' THEN 'paid' WHEN pr.status = 'rejected' THEN 'failed' ELSE 'pending' END AS status,
        '' AS transaction_ref,
        0.00 AS discount,
        '' AS coupon_code,
        NULL AS paid_at,
        pr.created_at,
        DATE(pr.created_at) AS date,
        CONCAT('اشتراك باقة #', pr.subscription_id) AS order_number,
        pr.status AS order_status,
        '' AS academic_level,
        '' AS specialty,
        '' AS language,
        NULL AS deadline,
        COALESCE(pr.student_note, '') AS order_description,
        COALESCE(p.name, 'اشتراك باقة') AS service_title,
        COALESCE(u.name, '—') AS student,
        COALESCE(u.email, '') AS student_email,
        COALESCE(u.phone, '') AS student_phone,
        pr.id AS receipt_id,
        pr.receipt_path,
        pr.method_label,
        pr.student_note,
        pr.rejection_reason,
        pr.status AS receipt_status
    FROM payment_receipts pr
    LEFT JOIN package_subscriptions ps ON pr.subscription_id = ps.id
    LEFT JOIN packages p ON ps.package_id = p.id
    LEFT JOIN users u ON pr.student_id = u.id
    WHERE pr.order_id IS NULL AND pr.subscription_id IS NOT NULL
    ORDER BY pr.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($sub_receipts)) {
    $payments = array_merge($payments, $sub_receipts);
    usort($payments, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
}

$receipts = $payments;

/* ─────────────────────────────
  Refund Requests (طلبات الاسترجاع)
──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_id'], $_POST['refund_action'])) {
    $rid = (int) $_POST['refund_id'];
    $action = $_POST['refund_action'] === 'process' ? 'processed' : 'rejected';
    $admin_note = trim($_POST['admin_note'] ?? '');
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    
    // Handle transfer receipt file upload
    $receipt_path = null;
    if ($action === 'processed' && !empty($_FILES['receipt_file']['name'])) {
        $receipt_path = saveRefundReceiptUpload($_FILES['receipt_file']);
    }
    
    if ($action === 'processed') {
        $db->prepare("UPDATE refund_requests SET status = ?, admin_note = ?, receipt_path = ?, transaction_ref = ?, processed_at = NOW() WHERE id = ?")
          ->execute([$action, $admin_note, $receipt_path, $transaction_ref, $rid]);
    } else {
        $db->prepare("UPDATE refund_requests SET status = ?, admin_note = ? WHERE id = ?")
          ->execute([$action, $admin_note, $rid]);
    }
      
    // Notify student
    $stmt = $db->prepare('SELECT student_id, order_id FROM refund_requests WHERE id = ?');
    $stmt->execute([$rid]);
    $refundData = $stmt->fetch();
    if ($refundData) {
        if ($action === 'processed') {
            // Update order status if it was cancellation_requested
            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'cancellation_requested'")->execute([$refundData['order_id']]);
            
            // [FINANCIAL FIX]: Update payments to refunded ONLY if not partially paid to academic
            $db->prepare("UPDATE payments SET status = 'refunded' WHERE order_id = ? AND is_transferred_to_academic = 0")->execute([$refundData['order_id']]);
            
            $db->prepare("UPDATE payment_receipts SET status = 'rejected', rejection_reason = 'تم استرجاع المبلغ' WHERE order_id = ?")->execute([$refundData['order_id']]);
        }
        
        if ($action === 'processed') {
            $msg = 'تم تحويل مبلغ الاسترجاع بنجاح لطلبك الملغي 💸.';
            if ($receipt_path) {
                $msg .= ' تم إرفاق إشعار التحويل البنكي في تفاصيل الطلب للاطلاع عليه.';
            }
        } else {
            $msg = 'نعتذر، تم رفض طلب الاسترجاع للسبب التالي: ' . $admin_note;
        }
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
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />
  <title>المدفوعات - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>" />
  <style>
    @media (max-width: 768px) {
      .modal-two-col { grid-template-columns: 1fr !important; }
      .decision-grid { grid-template-columns: 1fr !important; }
    }
  </style>
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

      <?php if (isset($_GET['payment_updated'])): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:12px 18px;border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px" class="animate-fadeInUp">
          <span><?= $_GET['payment_updated'] === 'paid' ? '✅' : '❌' ?></span>
          <span><?= $_GET['payment_updated'] === 'paid' ? 'تم اعتماد الدفعة بنجاح وتحديث حالة الطلب وإشعار الطالب.' : 'تم تسجيل الدفعة كعملية مرفوضة/فاشلة وإشعار الطالب بالسبب.' ?></span>
        </div>
      <?php elseif (isset($_GET['receipt_approved'])): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:12px 18px;border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px" class="animate-fadeInUp">
          <span>✅</span>
          <span>تم اعتماد إشعار الدفع بنجاح وتحديث حالة الطلب والدفعة.</span>
        </div>
      <?php elseif (isset($_GET['receipt_rejected'])): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px 18px;border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px" class="animate-fadeInUp">
          <span>❌</span>
          <span>تم رفض إشعار الدفع وإرسال السبب للطالب لإعادة إرفاقه.</span>
        </div>
      <?php elseif (isset($_GET['refund_updated'])): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:12px 18px;border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px" class="animate-fadeInUp">
          <span>💸</span>
          <span>تم تحديث طلب الاسترجاع وإشعار الطالب بنجاح.</span>
        </div>
      <?php endif; ?>

      <div data-tabs>
        <div class="tabs-container animate-fadeInUp delay-1">
          <div class="tabs-list">
            <button class="tab-btn active" data-tab="tab-general">📊 التفاصيل العامة</button>
            <button class="tab-btn" data-tab="tab-students">🎓 مدفوعات الطلاب</button>
            <button class="tab-btn" data-tab="tab-refunds">💸 طلبات الاسترجاع</button>
            <button class="tab-btn" data-tab="tab-academics">👨🏫 مستحقات الأكاديميين</button>
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
          <div class="card-value" style="font-size:30px;color:white" data-counter="<?= (float)$total_revenue ?>" data-decimals="2" data-suffix=" ر.س"><?= number_format((float)$total_revenue, 2) ?> ر.س</div>
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
            <div><div class="card-value" style="font-size:26px" data-counter="<?= (float)$pending_amount ?>" data-decimals="2" data-suffix=" ر.س"><?= number_format((float)$pending_amount, 2) ?> ر.س</div><div class="card-label">مدفوعات معلقة</div></div>
            <div class="card-icon" style="background:rgba(245,158,11,0.1);margin:0">⏳</div>
          </div>
        </div>
        <div class="stat-card animate-fadeInUp delay-4" style="padding:22px">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div><div class="card-value" style="font-size:26px" data-counter="<?= (float)$failed_amount ?>" data-decimals="2" data-suffix=" ر.س"><?= number_format((float)$failed_amount, 2) ?> ر.س</div><div class="card-label">مدفوعات فاشلة</div></div>
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
            <div style="font-size:28px;font-weight:900;color:var(--success)"><?= number_format((float)$total_revenue, 2, '.', ',') ?> ر.س</div>
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
      <!-- Unified Students Payments & Receipts Table -->
      <div class="table-container animate-fadeInUp delay-3">
        <div class="table-header" style="flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between">
          <div>
            <h3 class="table-title" style="font-size:18px;display:flex;align-items:center;gap:8px">
              <span>💳</span> سجل مدفوعات وإشعارات الطلاب
            </h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:2px">عرض وتدقيق جميع مدفوعات الطلبات والاشتراكات، معاينة إشعارات التحويل، والاعتماد أو الرفض بالسبب من مكان واحد.</p>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <div class="search-box" style="min-width:240px">
              <span class="search-icon">🔍</span>
              <input type="text" id="paySearch" placeholder="بحث بالطلب، العملية، الطالب..." />
            </div>
            <select class="form-input form-select" id="payStatusFilter" style="width:auto;padding-left:32px;font-size:13px">
              <option value="">جميع الحالات</option>
              <option value="pending">⏳ معلق (بانتظار الاعتماد)</option>
              <option value="paid">✅ معتمد ومسدد</option>
              <option value="failed">❌ مرفوض / فاشل</option>
            </select>
            <select class="form-input form-select" id="payMethodFilter" style="width:auto;padding-left:32px;font-size:13px">
              <option value="">جميع طرق الدفع</option>
              <option value="bank_transfer">تحويل بنكي</option>
              <option value="credit_card">بطاقة ائتمانية</option>
              <option value="wallet">محفظة إلكترونية</option>
              <option value="apple_pay">Apple Pay</option>
              <option value="stc_pay">STC Pay</option>
            </select>
            <select class="form-input form-select" id="payReceiptFilter" style="width:auto;padding-left:32px;font-size:13px">
              <option value="">جميع الإشعارات</option>
              <option value="has_receipt">📎 يوجد إشعار مرفق</option>
              <option value="no_receipt">⚠️ بدون إشعار</option>
            </select>
          </div>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>الطلب والعملية</th>
                <th>الطالب</th>
                <th>المبلغ</th>
                <th>طريقة وحساب الدفع</th>
                <th>الإشعار المرفق</th>
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
          <div style="font-size:13px;color:var(--text-secondary)">
            إجمالي المعروض: <strong id="payVisibleCount"><?= count($payments) ?></strong> عملية
          </div>
          <div style="display:flex;gap:24px">
            <div style="text-align:center"><div style="font-size:12px;color:var(--text-secondary)">إجمالي الإيرادات</div><div style="font-size:16px;font-weight:800;color:var(--success)"><?= number_format((float)$total_revenue, 2, '.', ',') ?> ر.س</div></div>
            <div style="text-align:center"><div style="font-size:12px;color:var(--text-secondary)">مدفوعات معلقة</div><div style="font-size:16px;font-weight:800;color:var(--warning)"><?= number_format((float)$pending_amount, 2, '.', ',') ?> ر.س</div></div>
            <div style="text-align:center"><div style="font-size:12px;color:var(--text-secondary)">العمليات الناجحة</div><div style="font-size:16px;font-weight:800;color:var(--primary)"><?= $success_cnt ?></div></div>
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
                      <div style="display:flex;gap:6px;align-items:center">
                        <button type="button" 
                                style="background:#10b981;color:#fff;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px"
                                onclick="openProcessRefundModal(<?= (int)$rf['id'] ?>, '<?= e(addslashes($rf['student_name'])) ?>', '<?= e(addslashes($rf['order_number'])) ?>', '<?= number_format((float)$rf['amount'], 2) ?>')">
                          تم التحويل وإرفاق الإشعار ✓
                        </button>
                        <form method="POST" style="margin:0;display:flex;align-items:center;gap:4px" onsubmit="return confirm('هل أنت متأكد من رفض طلب الاسترجاع؟')">
                          <input type="hidden" name="refund_id" value="<?= (int)$rf['id'] ?>">
                          <input type="hidden" name="refund_action" value="reject">
                          <input type="text" name="admin_note" placeholder="سبب الرفض..." required style="width:110px;font-size:11px;padding:4px;border:1px solid #fca5a5;border-radius:4px;outline:none">
                          <button type="submit" style="background:#ef4444;color:#fff;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">رفض ✕</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <div style="display:flex;flex-direction:column;gap:4px;font-size:12px">
                        <?php if (!empty($rf['receipt_path'])): ?>
                          <a href="<?= e($adminAppBase . '/' . ltrim($rf['receipt_path'], '/')) ?>" target="_blank" class="btn btn-outline btn-xs" style="color:#059669;border-color:#10b981;padding:3px 8px;font-size:11px;display:inline-flex;align-items:center;gap:4px;width:fit-content;font-weight:700">
                            👁️ عرض إشعار التحويل
                          </a>
                        <?php endif; ?>
                        <?php if (!empty($rf['transaction_ref'])): ?>
                          <span style="font-size:11px;color:var(--text-secondary);font-family:monospace">مرجع: <?= e($rf['transaction_ref']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($rf['admin_note'])): ?>
                          <span style="font-size:11px;color:var(--text-secondary)">ملاحظة: <?= e($rf['admin_note']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($rf['processed_at'])): ?>
                          <span style="font-size:10px;color:var(--text-secondary)"><?= formatDate($rf['processed_at']) ?></span>
                        <?php endif; ?>
                      </div>
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

<!-- Master Student Payment & Receipt Modal -->
<div class="modal-overlay" id="studentPaymentModal">
  <div class="modal-box" style="max-width:960px;width:95%;max-height:92vh;display:flex;flex-direction:column;padding:0;overflow:hidden">
    <div class="modal-header" style="padding:16px 24px;border-bottom:1px solid var(--border-color);background:var(--bg-card);display:flex;justify-content:space-between;align-items:center">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <h3 class="modal-title" id="spModalTitle" style="font-size:18px;font-weight:800;display:flex;align-items:center;gap:8px;margin:0">
          <span>💳</span> تفاصيل الدفعة والطلب
        </h3>
        <span id="spModalOrderBadge"></span>
        <span id="spModalStatusBadge"></span>
      </div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body" id="spModalBody" style="padding:22px 24px;overflow-y:auto;flex:1;background:var(--bg-card)">
      <!-- Injected dynamically via JS -->
    </div>
    <div class="modal-footer" style="padding:12px 24px;border-top:1px solid var(--border-color);background:var(--bg-main);display:flex;justify-content:flex-end">
      <button type="button" class="btn btn-outline" data-modal-close>إغلاق النافذة</button>
    </div>
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
const adminAppBase = <?= json_encode($adminAppBase) ?>;
const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
MOCK_DATA.payments = <?= json_encode($payments) ?>;
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
  const countEl = document.getElementById('payVisibleCount');
  if (countEl) countEl.textContent = data.length;
  if (!tbody) return;

  if (data.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:36px;color:var(--text-secondary);font-size:14px">لم يتم العثور على أي عمليات مطابقة لمعايير البحث.</td></tr>`;
    return;
  }

  tbody.innerHTML = data.map((p, i) => {
    const methodAr = METHOD_NAMES[p.method] || p.method || 'تحويل بنكي';
    const methodIcon = METHOD_ICONS[p.method] || '💰';
    const isOrder = p.order_id && p.order_id !== '0' && p.order_id !== 0;
    const orderLink = isOrder ? `order-details.php?id=${p.order_id}` : null;
    const orderNum = p.order_number || '—';
    const payCode = p.payment_id || ('PAY-' + p.raw_id);
    const serviceName = p.service_title || 'خدمة أكاديمية';

    // Receipt column display
    let receiptCell = '';
    if (p.receipt_path) {
      receiptCell = `
        <div style="display:flex;align-items:center;gap:6px">
          <button type="button" class="btn btn-xs btn-outline" style="color:#059669;border-color:#10b981;font-weight:700;display:inline-flex;align-items:center;gap:4px;padding:3px 8px;font-size:11px" onclick="openStudentPaymentModal('${payCode}')">
            <span>👁️</span> الإشعار
          </button>
          ${p.student_note ? `<span title="ملاحظة الطالب: ${escHtml(p.student_note)}" style="cursor:help;font-size:12px">💬</span>` : ''}
        </div>
      `;
    } else {
      receiptCell = `<span style="display:inline-block;padding:2px 8px;background:#fef3c7;color:#92400e;border-radius:6px;font-size:11px;font-weight:600">⚠️ لم يرفق</span>`;
    }

    // Status Badge
    const statusHtml = getStatusBadge(p.status === 'paid' ? 'paid' : p.status === 'pending' ? 'pending_pay' : 'failed');

    // Actions
    let actionsHtml = `<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">`;
    actionsHtml += `<button class="btn btn-sm btn-primary" onclick="openStudentPaymentModal('${payCode}')" style="padding:4px 10px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px">🔍 التفاصيل والاعتماد</button>`;

    if (p.status === 'pending') {
      actionsHtml += `
        <form method="POST" style="margin:0;display:inline" onsubmit="return confirm('هل أنت متأكد من اعتماد واستلام هذه الدفعة وتفعيل الطلب؟')">
          <input type="hidden" name="csrf_token" value="${csrfToken}">
          <input type="hidden" name="payment_id" value="${p.raw_id || 0}">
          <input type="hidden" name="receipt_id" value="${p.receipt_id || 0}">
          <input type="hidden" name="order_id" value="${p.order_id || 0}">
          <input type="hidden" name="payment_action" value="approve">
          <button type="submit" class="btn btn-sm" style="background:#10b981;color:#fff;border:none;font-weight:700;padding:4px 9px;font-size:12px;cursor:pointer" title="اعتماد مباشر">✓</button>
        </form>
      `;
    }
    actionsHtml += `</div>`;

    return `
      <tr>
        <td>
          <div style="display:flex;flex-direction:column;gap:2px">
            <div style="display:flex;align-items:center;gap:6px">
              ${isOrder ? `<a href="${orderLink}" style="color:var(--primary);font-weight:800;text-decoration:none;font-size:13px">#${escHtml(orderNum)}</a>` : `<span style="font-weight:700;font-size:13px">${escHtml(orderNum)}</span>`}
            </div>
            <span style="font-family:monospace;font-size:11px;color:var(--text-secondary)">${escHtml(payCode)}</span>
            <span style="font-size:11px;color:#475569;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(serviceName)}">📋 ${escHtml(serviceName)}</span>
          </div>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="table-avatar" style="background:${getAvatarColor(i)};width:32px;height:32px;border-radius:8px;font-size:11px;flex-shrink:0">${(p.student || '').slice(0,2)}</div>
            <div style="display:flex;flex-direction:column">
              <span style="font-weight:700;font-size:13px">${escHtml(p.student)}</span>
              ${p.student_phone ? `<span style="font-size:11px;color:var(--text-secondary)" dir="ltr">${escHtml(p.student_phone)}</span>` : ''}
            </div>
          </div>
        </td>
        <td>
          <div style="display:flex;flex-direction:column">
            <strong style="font-size:14px;color:var(--text-primary)">${Number(p.amount).toLocaleString('ar', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ر.س</strong>
            ${p.discount && Number(p.discount) > 0 ? `<span style="font-size:10px;color:var(--success)">خصم ${Number(p.discount)} ر.س</span>` : ''}
          </div>
        </td>
        <td>
          <div style="display:flex;flex-direction:column;gap:2px">
            <span style="font-size:12px;font-weight:600">${methodIcon} ${escHtml(methodAr)}</span>
            ${p.method_label ? `<span style="font-size:11px;color:var(--text-secondary)">${escHtml(p.method_label)}</span>` : ''}
          </div>
        </td>
        <td>${receiptCell}</td>
        <td>${statusHtml}</td>
        <td><span style="color:var(--text-secondary);font-size:12px" dir="ltr">${escHtml(p.date)}</span></td>
        <td>${actionsHtml}</td>
      </tr>
    `;
  }).join('');
}

function openStudentPaymentModal(key) {
  const p = MOCK_DATA.payments.find(x => 
    (x.payment_id || x.id) === key || 
    String(x.raw_id) === String(key) || 
    String(x.receipt_id) === String(key) ||
    x.order_number === key
  );
  if (!p) return;

  const titleEl = document.getElementById('spModalTitle');
  const orderBadgeEl = document.getElementById('spModalOrderBadge');
  const statusBadgeEl = document.getElementById('spModalStatusBadge');
  const bodyEl = document.getElementById('spModalBody');

  const orderNum = p.order_number || '—';
  const payCode = p.payment_id || ('PAY-' + p.raw_id);
  const isOrder = p.order_id && p.order_id !== '0' && p.order_id !== 0;
  const orderUrl = isOrder ? `order-details.php?id=${p.order_id}` : null;

  orderBadgeEl.innerHTML = isOrder 
    ? `<a href="${orderUrl}" target="_blank" style="background:rgba(99,102,241,0.12);color:var(--primary);font-weight:700;font-size:12px;padding:3px 8px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px">📋 #${escHtml(orderNum)} <span style="font-size:10px">↗</span></a>`
    : `<span class="badge badge-info" style="font-size:12px">${escHtml(orderNum)}</span>`;

  statusBadgeEl.innerHTML = getStatusBadge(p.status === 'paid' ? 'paid' : p.status === 'pending' ? 'pending_pay' : 'failed');

  const methodAr = METHOD_NAMES[p.method] || p.method || 'تحويل بنكي';
  const methodIcon = METHOD_ICONS[p.method] || '💰';
  const accountLabel = p.method_label ? `<span style="color:var(--text-secondary);font-size:12px;margin-right:6px">(${escHtml(p.method_label)})</span>` : '';

  const cleanReceiptPath = p.receipt_path ? p.receipt_path.replace(/\\/g, '/').replace(/^\/+/, '') : null;
  const receiptFullUrl = cleanReceiptPath ? `${adminAppBase}/${cleanReceiptPath}` : null;

  let receiptHtml = '';
  if (receiptFullUrl) {
    const isPdf = cleanReceiptPath.toLowerCase().endsWith('.pdf');
    if (isPdf) {
      receiptHtml = `
        <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:24px;text-align:center">
          <div style="font-size:44px;margin-bottom:10px">📄</div>
          <div style="font-weight:700;font-size:14px;margin-bottom:6px">إشعار دفع بصيغة PDF</div>
          <p style="font-size:12px;color:var(--text-secondary);margin-bottom:16px">قام الطالب بإرفاق مستند PDF كإشعار تحويل بنكي</p>
          <a href="${receiptFullUrl}" target="_blank" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <span>👁️</span> فتح وقراءة مستند الإشعار (PDF) ↗
          </a>
        </div>
      `;
    } else {
      receiptHtml = `
        <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:12px;display:flex;flex-direction:column;align-items:center">
          <a href="${receiptFullUrl}" target="_blank" title="انقر لفتح الصورة بالحجم الكامل" style="display:block;width:100%;max-height:360px;overflow:hidden;border-radius:8px;background:#000;text-align:center">
            <img src="${receiptFullUrl}" alt="إشعار الدفع" style="max-height:360px;width:auto;max-width:100%;margin:0 auto;display:block;object-fit:contain;transition:transform 0.2s" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
          </a>
          <div style="width:100%;display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:10px;border-top:1px solid var(--border-color);flex-wrap:wrap;gap:8px">
            <span style="font-size:11px;color:var(--text-secondary)">🔎 انقر على الصورة لمعاينتها بحجمها الكامل</span>
            <a href="${receiptFullUrl}" target="_blank" class="btn btn-outline btn-sm" style="font-size:11px;display:inline-flex;align-items:center;gap:4px">
              <span>↗</span> فتح في نافذة مستقلة
            </a>
          </div>
        </div>
      `;
    }
  } else {
    receiptHtml = `
      <div style="background:#fffbeb;border:1px dashed #f59e0b;border-radius:12px;padding:24px 16px;text-align:center;color:#92400e">
        <div style="font-size:36px;margin-bottom:8px">⚠️</div>
        <div style="font-weight:800;font-size:14px;margin-bottom:4px">لم يتم إرفاق إشعار تحويل</div>
        <p style="font-size:12px;line-height:1.6;margin:0">
          لم يقم الطالب برفع صورة أو مستند إشعار التحويل البنكي لهذا الطلب حتى الآن.
          يمكنك الانتظار لحين قيامه بالإرفاق، أو رفض الدفعة مع كتابة ملاحظة توجيهية للطالب لرفع الإشعار من حسابه.
        </p>
      </div>
    `;
  }

  // Student note callout
  let studentNoteHtml = '';
  if (p.student_note && p.student_note.trim() !== '') {
    studentNoteHtml = `
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;margin-top:14px;font-size:12px;color:#1e40af;line-height:1.5">
        <strong style="display:flex;align-items:center;gap:6px;margin-bottom:4px">💬 ملاحظة الطالب المرفقة مع الإشعار:</strong>
        <span style="color:#1e3a8a">${escHtml(p.student_note)}</span>
      </div>
    `;
  }

  // Previous rejection reason
  let rejectionReasonHtml = '';
  if (p.rejection_reason && p.rejection_reason.trim() !== '') {
    rejectionReasonHtml = `
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;margin-top:14px;font-size:12px;color:#991b1b;line-height:1.5">
        <strong style="display:flex;align-items:center;gap:6px;margin-bottom:4px">❌ سبب الرفض المسجل مسبقاً:</strong>
        <span>${escHtml(p.rejection_reason)}</span>
      </div>
    `;
  }

  // Action Panel at bottom
  let actionPanelHtml = '';
  if (p.status === 'pending') {
    actionPanelHtml = `
      <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:14px;padding:18px 20px;margin-top:22px">
        <div style="font-size:14px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px">
          <span>⚙️</span> اتخاذ القرار الإداري بشأن الدفعة
        </div>

        <div style="display:grid;grid-template-columns:1fr 1.25fr;gap:18px;align-items:stretch" class="decision-grid">
          <!-- Approval Option -->
          <div style="background:var(--bg-card);border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:16px;display:flex;flex-direction:column;justify-content:space-between">
            <div>
              <div style="font-weight:800;color:var(--success);margin-bottom:6px;display:flex;align-items:center;gap:6px">
                <span>✅</span> اعتماد واستلام المبلغ
              </div>
              <p style="font-size:12px;color:var(--text-secondary);line-height:1.5;margin-bottom:14px">
                عند التأكد من صحة التحويل، سيتم تأكيد الدفعة فوراً، وتحديث حالة الطلب إلى (بانتظار التعيين) لإسناده لأكاديمي، وإرسال إشعار فوري للطالب.
              </p>
            </div>
            <form method="POST" style="margin:0" onsubmit="return confirm('هل أنت متأكد من اعتماد واستلام دفعة الطلب وتفعيله للأكاديميين؟')">
              <input type="hidden" name="csrf_token" value="${csrfToken}">
              <input type="hidden" name="payment_id" value="${p.raw_id || 0}">
              <input type="hidden" name="receipt_id" value="${p.receipt_id || 0}">
              <input type="hidden" name="order_id" value="${p.order_id || 0}">
              <input type="hidden" name="payment_action" value="approve">
              <button type="submit" class="btn btn-success" style="width:100%;background:#10b981;color:#fff;border:none;padding:10px;font-weight:800;border-radius:8px;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px">
                ✓ اعتماد الدفعة وتفعيل الطلب
              </button>
            </form>
          </div>

          <!-- Rejection Option -->
          <div style="background:var(--bg-card);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:16px">
            <div style="font-weight:800;color:var(--danger);margin-bottom:6px;display:flex;align-items:center;gap:6px">
              <span>❌</span> رفض الدفعة مع إرسال السبب
            </div>
            <p style="font-size:11px;color:var(--text-secondary);line-height:1.4;margin-bottom:8px">
              اختر أو اكتب سبب الرفض؛ وسيتم إرسال إشعار فوري للطالب مع السبب المرفق ليقوم بإعادة الرفع.
            </p>
            <form method="POST" style="margin:0" onsubmit="return validateRejectionForm(this)">
              <input type="hidden" name="csrf_token" value="${csrfToken}">
              <input type="hidden" name="payment_id" value="${p.raw_id || 0}">
              <input type="hidden" name="receipt_id" value="${p.receipt_id || 0}">
              <input type="hidden" name="order_id" value="${p.order_id || 0}">
              <input type="hidden" name="payment_action" value="reject">

              <!-- Quick chips -->
              <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px">
                <button type="button" class="btn btn-xs btn-outline" style="font-size:10px;padding:2px 6px;border-radius:10px" onclick="setRejectionReason(this, 'الإشعار المرفق غير واضح أو مقطوع')">الإشعار غير واضح</button>
                <button type="button" class="btn btn-xs btn-outline" style="font-size:10px;padding:2px 6px;border-radius:10px" onclick="setRejectionReason(this, 'المبلغ المحول غير مطابق لقيمة الطلب')">المبلغ غير مطابق</button>
                <button type="button" class="btn btn-xs btn-outline" style="font-size:10px;padding:2px 6px;border-radius:10px" onclick="setRejectionReason(this, 'لم يصل مبلغ الحوالة إلى الحساب البنكي حتى الآن')">الحوالة لم تصل</button>
                <button type="button" class="btn btn-xs btn-outline" style="font-size:10px;padding:2px 6px;border-radius:10px" onclick="setRejectionReason(this, 'يرجى إرفاق إشعار رسمي من تطبيق البنك يتضمن رقم العملية وتاريخها')">طلب إشعار رسمي</button>
              </div>

              <textarea name="rejection_reason" id="modalRejectionReason" rows="2" required placeholder="اكتب سبب الرفض هنا..." class="form-control" style="width:100%;padding:8px 10px;font-size:12px;border:1px solid #fca5a5;border-radius:8px;background:var(--bg-main);margin-bottom:8px;resize:vertical"></textarea>
              <button type="submit" class="btn btn-danger" style="width:100%;background:#ef4444;color:#fff;border:none;padding:9px;font-weight:800;border-radius:8px;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px">
                ✕ رفض الدفعة وإرسال السبب للطالب
              </button>
            </form>
          </div>
        </div>
      </div>
    `;
  } else if (p.status === 'paid') {
    actionPanelHtml = `
      <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:12px;padding:14px 18px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:22px">✅</span>
          <div>
            <div style="font-weight:800;font-size:14px">هذه الدفعة معتمدة ومستلمة بنجاح</div>
            <div style="font-size:12px;opacity:0.9">تم تأكيد استلام المبلغ بالكامل وتفعيل الطلب. ${p.paid_at ? 'تاريخ السداد: ' + p.paid_at : ''}</div>
          </div>
        </div>
        ${isOrder ? `<a href="${orderUrl}" target="_blank" class="btn btn-outline btn-sm" style="background:#fff;border-color:#166534;color:#166534;font-weight:700">متابعة إنجاز الطلب ↗</a>` : ''}
      </div>
    `;
  } else {
    actionPanelHtml = `
      <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:14px 18px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:22px">❌</span>
          <div>
            <div style="font-weight:800;font-size:14px">هذه الدفعة مسجلة كعملية مرفوضة / فاشلة</div>
            <div style="font-size:12px;opacity:0.9">${p.rejection_reason ? 'السبب: ' + escHtml(p.rejection_reason) : 'تم رفض العملية من قبل الإدارة.'}</div>
          </div>
        </div>
        <form method="POST" style="margin:0" onsubmit="return confirm('هل تريد التراجع وإعادة اعتماد هذه الدفعة وتفعيل الطلب؟')">
          <input type="hidden" name="csrf_token" value="${csrfToken}">
          <input type="hidden" name="payment_id" value="${p.raw_id || 0}">
          <input type="hidden" name="receipt_id" value="${p.receipt_id || 0}">
          <input type="hidden" name="order_id" value="${p.order_id || 0}">
          <input type="hidden" name="payment_action" value="approve">
          <button type="submit" class="btn btn-sm" style="background:#10b981;color:#fff;border:none;font-weight:700;padding:6px 14px;border-radius:8px">
            🔄 إعادة الاعتماد وتفعيل الطلب
          </button>
        </form>
      </div>
    `;
  }

  bodyEl.innerHTML = `
    <div style="display:grid;grid-template-columns:1.15fr 0.85fr;gap:20px" class="modal-two-col">
      <!-- Right Side: Details & Info -->
      <div>
        <!-- Order & Item Card -->
        <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px;margin-bottom:16px">
          <div style="font-size:13px;font-weight:800;color:var(--text-secondary);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center">
            <span>📋 بيانات الطلب والخدمة</span>
            ${isOrder ? `<a href="${orderUrl}" target="_blank" style="color:var(--primary);font-size:12px;font-weight:700;text-decoration:none">صفحة الطلب ↗</a>` : ''}
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary);width:35%">الخدمة / الباقة</td><td style="padding:6px 0;font-weight:700;color:var(--text-primary)">${escHtml(p.service_title || 'خدمة أكاديمية')}</td></tr>
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">رقم الطلب</td><td style="padding:6px 0;font-weight:700;color:var(--primary)">#${escHtml(orderNum)}</td></tr>
            ${p.specialty ? `<tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">التخصص والمستوى</td><td style="padding:6px 0">${escHtml(p.specialty)} ${p.academic_level ? ' • ' + escHtml(p.academic_level) : ''}</td></tr>` : ''}
            ${p.deadline ? `<tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">الموعد النهائي</td><td style="padding:6px 0;color:var(--danger);font-weight:600">${escHtml(p.deadline)}</td></tr>` : ''}
            ${p.order_description ? `<tr><td style="padding:6px 0;color:var(--text-secondary);vertical-align:top">متطلبات الطلب</td><td style="padding:6px 0;line-height:1.5;max-height:80px;overflow-y:auto;display:block">${escHtml(p.order_description)}</td></tr>` : ''}
          </table>
        </div>

        <!-- Student & Financial Card -->
        <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px">
          <div style="font-size:13px;font-weight:800;color:var(--text-secondary);margin-bottom:10px">
            👤 بيانات الطالب والعملية المالية
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary);width:35%">اسم الطالب</td><td style="padding:6px 0;font-weight:700;color:var(--text-primary)">${escHtml(p.student)}</td></tr>
            ${p.student_email ? `<tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">البريد الإلكتروني</td><td style="padding:6px 0" dir="ltr">${escHtml(p.student_email)}</td></tr>` : ''}
            ${p.student_phone ? `<tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">رقم الهاتف</td><td style="padding:6px 0" dir="ltr">${escHtml(p.student_phone)}</td></tr>` : ''}
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">المبلغ الإجمالي</td><td style="padding:6px 0;font-weight:900;font-size:16px;color:var(--success)">${Number(p.amount).toLocaleString('ar', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ر.س</td></tr>
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">طريقة السداد والحساب</td><td style="padding:6px 0;font-weight:600">${methodIcon} ${escHtml(methodAr)} ${accountLabel}</td></tr>
            <tr style="border-bottom:1px solid var(--border-color)"><td style="padding:6px 0;color:var(--text-secondary)">رقم العملية المنظومي</td><td style="padding:6px 0;font-family:monospace;font-size:12px">${escHtml(payCode)}</td></tr>
            <tr><td style="padding:6px 0;color:var(--text-secondary)">تاريخ إنشاء العملية</td><td style="padding:6px 0" dir="ltr">${escHtml(p.created_at || p.date)}</td></tr>
          </table>

          ${studentNoteHtml}
          ${rejectionReasonHtml}
        </div>
      </div>

      <!-- Left Side: Receipt Proof -->
      <div>
        <div style="font-size:13px;font-weight:800;color:var(--text-secondary);margin-bottom:8px;display:flex;align-items:center;gap:6px">
          <span>🧾</span> صورة ومستند إشعار التحويل البنكي
        </div>
        ${receiptHtml}
      </div>
    </div>

    <!-- Actions & Decisions Section -->
    ${actionPanelHtml}
  `;

  Modal.open('studentPaymentModal');
}

function viewPaymentDetails(paymentId) {
  openStudentPaymentModal(paymentId);
}

function setRejectionReason(btn, text) {
  const textarea = document.getElementById('modalRejectionReason');
  if (textarea) {
    textarea.value = text;
    textarea.focus();
  }
}

function validateRejectionForm(form) {
  const reason = form.querySelector('[name="rejection_reason"]').value.trim();
  if (!reason) {
    Toast.show('يرجى إدخال سبب الرفض لتوضيحه للطالب', 'error');
    return false;
  }
  return confirm('هل أنت متأكد من رفض هذه الدفعة وإرسال السبب للطالب؟');
}

function applyPayFilters() {
  const q = document.getElementById('paySearch').value.toLowerCase().trim();
  const st = document.getElementById('payStatusFilter').value;
  const mt = document.getElementById('payMethodFilter').value;
  const rc = document.getElementById('payReceiptFilter') ? document.getElementById('payReceiptFilter').value : '';

  const filtered = MOCK_DATA.payments.filter(p => {
    const pId = String(p.payment_id || p.id || '').toLowerCase();
    const pOrder = String(p.order_number || '').toLowerCase();
    const pStd = String(p.student || '').toLowerCase();
    const pPhone = String(p.student_phone || '').toLowerCase();
    const pSrv = String(p.service_title || '').toLowerCase();

    const matchQ = !q || pId.includes(q) || pOrder.includes(q) || pStd.includes(q) || pPhone.includes(q) || pSrv.includes(q);
    const matchSt = !st || p.status === st;
    const matchMt = !mt || p.method === mt;
    const matchRc = !rc || (rc === 'has_receipt' ? !!p.receipt_path : !p.receipt_path);

    return matchQ && matchSt && matchMt && matchRc;
  });
  renderPayments(filtered);
}

document.getElementById('paySearch')?.addEventListener('input', applyPayFilters);
document.getElementById('payStatusFilter')?.addEventListener('change', applyPayFilters);
document.getElementById('payMethodFilter')?.addEventListener('change', applyPayFilters);
document.getElementById('payReceiptFilter')?.addEventListener('change', applyPayFilters);

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

function fmtMoney(n) { return Number(n || 0).toLocaleString('ar', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
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
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  fd.append('csrf_token', csrf);
  fetch('../ajax/manage_payouts.php', { 
    method: 'POST', 
    body: fd,
    headers: { 
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': csrf 
    }
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
          ${a.status === 'suspended' ? '<span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;font-size:10px;padding:2px 6px">موقوف 🚫</span>' : ''}
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
  if (a && amount > parseFloat(a.balance || 0)) { Toast.show('المبلغ أكبر من الرصيد المستحق (' + fmtMoney(a.balance) + ' ر.س)', 'error'); return; }

  const fd = new FormData();
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
      headers: { 
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrf
      }
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
    fd.append('action', 'update_payout_status');
    fd.append('payout_id', payoutId);
    fd.append('status', status);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.append('csrf_token', csrf);
    fetch('../ajax/manage_payouts.php', { 
        method: 'POST', 
        body: fd,
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrf
        }
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
    fd.append('action', 'send_message');
    fd.append('payout_id', payoutId);
    fd.append('message', msgText);
    
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.append('csrf_token', csrf);
    fetch('../ajax/manage_payouts.php', { 
        method: 'POST', 
        body: fd,
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrf
        }
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

<!-- Modal for processing refund & uploading transfer receipt -->
<div id="processRefundModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)">
  <div style="width:100%;max-width:500px;padding:24px;border-radius:16px;background:var(--bg-card);box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);border:1px solid var(--border-color);position:relative">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border-color);padding-bottom:12px">
      <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:0;display:flex;align-items:center;gap:8px">
        <span>💸</span> تأكيد تحويل الاسترجاع وإرفاق الإشعار
      </h3>
      <button type="button" onclick="closeProcessRefundModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-secondary);line-height:1">&times;</button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="processRefundForm">
      <input type="hidden" name="refund_id" id="modal_refund_id" value="">
      <input type="hidden" name="refund_action" value="process">

      <!-- Refund Info Summary Box -->
      <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
          <span style="color:var(--text-secondary)">الطالب:</span>
          <span id="modal_student_name" style="font-weight:700;color:var(--text-primary)">-</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
          <span style="color:var(--text-secondary)">رقم الطلب:</span>
          <span id="modal_order_number" style="font-weight:700;color:var(--primary)">-</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px">
          <span style="color:var(--text-secondary)">المبلغ المسترد:</span>
          <span id="modal_refund_amount" style="font-weight:900;color:#ef4444">- ر.س</span>
        </div>
      </div>

      <!-- Receipt File Upload -->
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">
          📎 إشعار التحويل البنكي (صورة أو PDF) <span style="font-size:11px;color:var(--text-secondary);font-weight:normal">(يُفضل إرفاقه لتوثيق الحوالة)</span>
        </label>
        <input type="file" name="receipt_file" id="modal_receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px">
        <small style="color:var(--text-secondary);font-size:11px;margin-top:4px;display:block">
          الصيغ المدعومة: JPG, PNG, WEBP, PDF (الحد الأقصى: 10 ميجابايت).
        </small>
      </div>

      <!-- Transaction Reference -->
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">
          رقم الحوالة أو المرجع البنكي (إن وجد)
        </label>
        <input type="text" name="transaction_ref" id="modal_transaction_ref" class="form-control" placeholder="مثال: REF-987654321..." style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px">
      </div>

      <!-- Admin Note -->
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">
          ملاحظات حول التحويل
        </label>
        <textarea name="admin_note" id="modal_admin_note" rows="2" class="form-control" placeholder="أي تفاصيل أو ملاحظات إضافية حول العملية..." style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px;resize:vertical"></textarea>
      </div>

      <div style="background:rgba(16,185,129,0.08);border:1px dashed #10b981;padding:10px 12px;border-radius:8px;margin-bottom:16px;font-size:12px;color:#065f46;line-height:1.5">
        ℹ️ <strong>تنبيه:</strong> سيتم تأكيد استرجاع المبلغ للطالب، وتحديث حالة الطلب والدفع، وإرسال إشعار فوري للطالب متضمناً إشعار التحويل المرفوع.
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeProcessRefundModal()">إلغاء</button>
        <button type="submit" class="btn btn-success btn-sm" style="background:#10b981;color:#fff;border:none;font-weight:700;padding:8px 16px">✓ تأكيد التحويل وحفظ الإشعار</button>
      </div>
    </form>
  </div>
</div>

<script>
function openProcessRefundModal(id, student, orderNo, amount) {
  document.getElementById('modal_refund_id').value = id;
  document.getElementById('modal_student_name').textContent = student;
  document.getElementById('modal_order_number').textContent = '#' + orderNo;
  document.getElementById('modal_refund_amount').textContent = amount + ' ر.س';
  document.getElementById('modal_receipt_file').value = '';
  document.getElementById('modal_transaction_ref').value = '';
  document.getElementById('modal_admin_note').value = '';
  document.getElementById('processRefundModal').style.display = 'flex';
}

function closeProcessRefundModal() {
  document.getElementById('processRefundModal').style.display = 'none';
}

document.getElementById('processRefundModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeProcessRefundModal();
});
</script>
</body>
</html>
