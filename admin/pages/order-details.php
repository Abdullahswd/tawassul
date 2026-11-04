<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/mailer.php';
requireAdmin();

$order_no = $_GET['id'] ?? '';
if (!$order_no) {
  header('Location: orders.php');
  exit;
}

$db = db();

// Fetch order
$stmt = $db->prepare("
        SELECT o.*, s.name AS service_name, s.icon AS service_icon, s.platform_percent AS service_platform_percent,
              p.name AS package_name, p.price AS package_price,
              u.name AS student_name, u.email AS student_email, u.phone AS student_phone,
              a.name AS academic_name, a.email AS academic_email, a.specialty AS academic_specialty, a.rating AS academic_rating
        FROM orders o
        JOIN users u ON o.student_id = u.id
        JOIN services s ON o.service_id = s.id
        LEFT JOIN academics a ON o.academic_id = a.id
        LEFT JOIN packages p ON o.package_id = p.id
        WHERE o.order_number = ? LIMIT 1
    ");
$stmt->execute([$order_no]);
$order = $stmt->fetch();

if (!$order) {
  header('Location: orders.php');
  exit;
}

// مرفقات الطلب المرفوعة من الطالب
$attStmt = $db->prepare("SELECT * FROM order_attachments WHERE order_id = ? ORDER BY id ASC");
$attStmt->execute([(int) $order['id']]);
$orderAttachments = $attStmt->fetchAll();

// مسار التطبيق الأساسي لبناء روابط الملفات
$adminAppBase = rtrim(preg_replace('#/admin/pages/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// جلب المحادثة بين الطالب والأكاديمي إن وجدت
$chatMessages = [];
$convStmt = $db->prepare("SELECT * FROM conversations WHERE order_id = ? LIMIT 1");
$convStmt->execute([(int) $order['id']]);
$conversation = $convStmt->fetch();

if ($conversation) {
  $msgStmt = $db->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY sent_at ASC");
  $msgStmt->execute([(int) $conversation['id']]);
  $chatMessages = $msgStmt->fetchAll();
}

$teamChatMessages = getTeamMessages((int) $order['id']);
$assignedTeam = getAssignedAcademics((int) $order['id']);
$assignedAcademics = $assignedTeam;
$isMultiAcademics = count($assignedAcademics) > 1 || (int)($order['academics_count'] ?? 1) > 1;

// Fetch payment & receipt details for this order
$payStmt = $db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$payStmt->execute([(int) $order['id']]);
$orderPayment = $payStmt->fetch() ?: null;
$orderReceipt = getReceiptForOrder((int) $order['id']);

// Handle saving admin internal notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_admin_notes') {
  $admin_notes = trim($_POST['admin_notes'] ?? '');
  $db->prepare("UPDATE orders SET admin_notes = ? WHERE id = ?")->execute([$admin_notes, $order['id']]);
  header('Location: order-details.php?id=' . $order['order_number'] . '&notes_saved=1');
  exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $new_status = $_POST['status'] ?? '';
  if (in_array($new_status, ['pending_assignment', 'assigned', 'new', 'accepted', 'in_progress', 'delivered', 'revision', 'completed', 'cancelled', 'cancellation_requested'])) {
    updateOrderStatus($order['id'], $new_status);

    if ($new_status === 'cancelled') {
      $deducted = isset($_POST['deducted_amount']) ? (float) $_POST['deducted_amount'] : 0.0;
      $total_paid = (float) ($order['price'] ?? $order['amount']);
      if ($deducted < 0)
        $deducted = 0.0;
      if ($deducted > $total_paid)
        $deducted = $total_paid;

      $refund_amount = $total_paid - $deducted;

      // Check if there was any payment
      $rcStmt = $db->prepare("SELECT id FROM payment_receipts WHERE order_id = ? AND status IN ('pending', 'approved') LIMIT 1");
      $rcStmt->execute([$order['id']]);
      if ($rcStmt->fetchColumn()) {
        $chkRef = $db->prepare("SELECT id FROM refund_requests WHERE order_id = ? LIMIT 1");
        $chkRef->execute([$order['id']]);
        if (!$chkRef->fetchColumn() && $refund_amount > 0) {
          $db->prepare("INSERT INTO refund_requests (order_id, student_id, amount, status) VALUES (?, ?, ?, 'pending')")
            ->execute([$order['id'], $order['student_id'], $refund_amount]);
        }

        if (!empty($order['academic_id'])) {
          $payStmt = $db->prepare("SELECT id, academic_net, is_transferred_to_academic FROM payments WHERE order_id = ? LIMIT 1");
          $payStmt->execute([$order['id']]);
          $payment = $payStmt->fetch();

          if ($payment) {
            // Revert old balance if it was already transferred
            if ($payment['is_transferred_to_academic']) {
              $db->prepare("UPDATE academics SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?")
                ->execute([$payment['academic_net'], $payment['academic_net'], $order['academic_id']]);
            }

            if ($deducted > 0) {
              $platform_fee = $deducted * 0.15;
              $academic_net = $deducted - $platform_fee;

              // Update payments table to pay academic
              $db->prepare("UPDATE payments SET amount = ?, platform_fee = ?, academic_net = ?, status = 'paid', paid_at = NOW(), is_transferred_to_academic = 1 WHERE id = ?")
                ->execute([$deducted, $platform_fee, $academic_net, $payment['id']]);

              // Add new balance
              $db->prepare("UPDATE academics SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
                ->execute([$academic_net, $academic_net, $order['academic_id']]);
            } else {
              // Deducted is 0, just mark as not transferred
              $db->prepare("UPDATE payments SET is_transferred_to_academic = 0 WHERE id = ?")
                ->execute([$payment['id']]);
            }
          }
        }
      }
    }

    // Notify Academics if order is cancelled
    if ($new_status === 'cancelled') {
      $academics = $db->prepare("SELECT DISTINCT academic_id FROM order_assignments WHERE order_id = ? AND status NOT IN ('rejected', 'replaced') UNION SELECT academic_id FROM orders WHERE id = ? AND academic_id IS NOT NULL AND id NOT IN (SELECT order_id FROM order_assignments WHERE academic_id = orders.academic_id AND status = 'replaced')");
      $academics->execute([$order['id'], $order['id']]);
      foreach ($academics->fetchAll(PDO::FETCH_COLUMN) as $acid) {
        createNotification($acid, 'academic', 'إلغاء الطلب نهائياً 🚫', 'قامت الإدارة بإلغاء الطلب #' . $order['order_number'] . ' بشكل نهائي.', '🚫', 'academics/academic-order-details.php?id=' . $order['order_number']);
      }
    }
    // Add a notification for the student
    createNotification(
      $order['student_id'],
      'student',
      'تحديث حالة الطلب 🔔',
      'تم تحديث حالة طلبك ' . $order['order_number'] . ' لتصبح: ' . orderStatusLabel($new_status)['label'],
      '🔔',
      'student/order-details.php?id=' . $order['id']
    );

    header('Location: order-details.php?id=' . $order['order_number'] . '&updated=1');
    exit;
  }
}

// Handle academic replacement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'replace_academic') {
  $target_academic_id = (int)($_POST['target_academic_id'] ?? 0);
  $new_academic_id    = (int)($_POST['new_academic_id'] ?? 0);
  $reason             = trim($_POST['replacement_reason'] ?? 'other');
  $note               = trim($_POST['replacement_note'] ?? '');

  if ($target_academic_id <= 0 || $new_academic_id <= 0) {
    $error = "بيانات الاستبدال غير مكتملة.";
  } elseif ($target_academic_id === $new_academic_id) {
    $error = "لا يمكن استبدال الأكاديمي بنفسه.";
  } else {
    // Check new academic is approved
    $chkNew = $db->prepare("SELECT id, name, email FROM academics WHERE id = ? AND status = 'approved'");
    $chkNew->execute([$new_academic_id]);
    $newAcademic = $chkNew->fetch();

    $chkOld = $db->prepare("SELECT id, name, email FROM academics WHERE id = ?");
    $chkOld->execute([$target_academic_id]);
    $oldAcademic = $chkOld->fetch();

    if (!$newAcademic) {
      $error = "الأكاديمي البديل المحدد غير صالح أو غير معتمد.";
    } elseif (!$oldAcademic) {
      $error = "الأكاديمي المراد استبداله غير موجود.";
    } else {
      $db->beginTransaction();
      try {
        // 1. Mark existing assignment as replaced
        $oaStmt = $db->prepare("SELECT id FROM order_assignments WHERE order_id = ? AND academic_id = ? AND status != 'replaced' LIMIT 1");
        $oaStmt->execute([$order['id'], $target_academic_id]);
        $oldOaId = $oaStmt->fetchColumn();

        if ($oldOaId) {
          $updOa = $db->prepare("
            UPDATE order_assignments 
            SET status = 'replaced', 
                replaced_at = NOW(), 
                replacement_reason = ?, 
                replacement_note = ?, 
                replaced_by_academic_id = ? 
            WHERE id = ?
          ");
          $updOa->execute([$reason, $note, $new_academic_id, $oldOaId]);
        } else {
          // If was assigned only in orders.academic_id
          $insOa = $db->prepare("
            INSERT INTO order_assignments 
            (order_id, academic_id, status, assigned_at, replaced_at, replacement_reason, replacement_note, replaced_by_academic_id) 
            VALUES (?, ?, 'replaced', NOW(), NOW(), ?, ?, ?)
          ");
          $insOa->execute([$order['id'], $target_academic_id, $reason, $note, $new_academic_id]);
        }

        // 2. Insert new assignment for replacement academic
        $insNew = $db->prepare("
          INSERT INTO order_assignments (order_id, academic_id, status, assigned_at) 
          VALUES (?, ?, 'assigned', NOW())
        ");
        $insNew->execute([$order['id'], $new_academic_id]);

        // 3. Update orders table if target was primary academic_id
        if ((int)$order['academic_id'] === $target_academic_id) {
          $otherAccepted = $db->prepare("SELECT academic_id FROM order_assignments WHERE order_id = ? AND status = 'accepted' AND academic_id != ? LIMIT 1");
          $otherAccepted->execute([$order['id'], $target_academic_id]);
          $otherAcid = $otherAccepted->fetchColumn();

          if ($otherAcid) {
            $db->prepare("UPDATE orders SET academic_id = ? WHERE id = ?")->execute([$otherAcid, $order['id']]);
          } else {
            $db->prepare("UPDATE orders SET academic_id = NULL, status = 'assigned' WHERE id = ?")->execute([$order['id']]);
          }
        }

        // 4. Update conversation if exists
        $db->prepare("UPDATE conversations SET academic_id = ? WHERE order_id = ? AND academic_id = ?")
           ->execute([$new_academic_id, $order['id'], $target_academic_id]);

        // 5. Notifications
        $reasonLabel = replacementReasonLabel($reason);

        // Notify old academic
        createNotification(
          $target_academic_id,
          'academic',
          'إلغاء تكليفك بالطلب ⚠️',
          'تم إلغاء تكليفك بالطلب #' . $order['order_number'] . ' وإسناده لأكاديمي آخر (السبب: ' . $reasonLabel . ').',
          '⚠️',
          'academics/academic-orders.php'
        );

        // Notify new academic
        createNotification(
          $new_academic_id,
          'academic',
          'إسناد طلب جديد إليك كبديل 📋',
          'قامت الإدارة بإسناد الطلب #' . $order['order_number'] . ' إليك كبديل. يرجى مراجعته وقبوله للبدء بالعمل.',
          '📋',
          'academics/academic-orders.php'
        );
        sendOrderAssignedToAcademicEmail((int)$new_academic_id, $order);

        // Notify student
        createNotification(
          $order['student_id'],
          'student',
          'تحديث الأكاديمي المكلّف بالطلب 🔄',
          'تم تحديث الأكاديمي المكلّف بطلبك #' . $order['order_number'] . ' لضمان جودة وسرعة الإنجاز.',
          '🔄',
          'student/order-details.php?id=' . $order['id']
        );

        $db->commit();
        header('Location: order-details.php?id=' . $order['order_number'] . '&replaced=1');
        exit;
      } catch (Exception $e) {
        $db->rollBack();
        $error = "حدث خطأ أثناء استبدال الأكاديمي: " . $e->getMessage();
      }
    }
  }
}

// Handle assignment updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_academics') {
  $academic_ids = $_POST['academic_ids'] ?? [];

  $db->beginTransaction();
  try {
    // Clear previous assignments
    $del = $db->prepare("DELETE FROM order_assignments WHERE order_id = ? AND status != 'replaced'");
    $del->execute([$order['id']]);

    if (!empty($academic_ids)) {
      // Filter out any suspended or non-approved academics
      $placeholders = implode(',', array_fill(0, count($academic_ids), '?'));
      $vStmt = $db->prepare("SELECT id FROM academics WHERE id IN ($placeholders) AND status = 'approved'");
      $vStmt->execute(array_map('intval', $academic_ids));
      $valid_ids = $vStmt->fetchAll(PDO::FETCH_COLUMN);

      if (!empty($valid_ids)) {
        $ins = $db->prepare("INSERT INTO order_assignments (order_id, academic_id) VALUES (?, ?)");
        foreach ($valid_ids as $ac_id) {
          $ins->execute([$order['id'], $ac_id]);

          // Notify academic (In-app)
          createNotification(
            $ac_id,
            'academic',
            'تم إسناد طلب جديد إليك 📋',
            'قامت الإدارة بإسناد الطلب #' . $order['order_number'] . ' إليك. يرجى مراجعته وقبوله لبدء العمل.',
            '📋',
            'academics/academic-orders.php'
          );

          // Send Email to Academic (SMTP)
          sendOrderAssignedToAcademicEmail((int)$ac_id, $order);
        }

        // Set status to assigned
        $upd = $db->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
        $upd->execute([$order['id']]);

        // Notify student
        createNotification(
          $order['student_id'],
          'student',
          'تحديث حالة الطلب 🔔',
          'تم تحويل طلبك #' . $order['order_number'] . ' إلى الأكاديميين المتخصصين للمراجعة والقبول.',
          '🔔',
          'student/order-details.php?id=' . $order['id']
        );
      } else {
        // Revert status to pending_assignment
        $upd = $db->prepare("UPDATE orders SET status = 'pending_assignment' WHERE id = ?");
        $upd->execute([$order['id']]);
      }
    } else {
      // Revert status to pending_assignment
      $upd = $db->prepare("UPDATE orders SET status = 'pending_assignment' WHERE id = ?");
      $upd->execute([$order['id']]);
    }

    $db->commit();
    header('Location: order-details.php?id=' . $order['order_number'] . '&updated=1');
    exit;
  } catch (Exception $e) {
    $db->rollBack();
    $error = "حدث خطأ أثناء حفظ الإسناد: " . $e->getMessage();
  }
}

// Fetch approved academics who have the order's service
$academics_stmt = $db->prepare("
        SELECT DISTINCT a.*
        FROM academics a
        INNER JOIN academic_services acs ON a.id = acs.academic_id
        WHERE a.status = 'approved'
          AND acs.service_id = ?
        ORDER BY a.rating DESC
    ");
$academics_stmt->execute([$order['service_id']]);
$matching_academics = $academics_stmt->fetchAll();

// جلب جميع الأكاديميين المعتمدين (للإسناد اليدوي حتى لمن لا يقدم هذه الخدمة)
$all_academics = $db->query("SELECT * FROM academics WHERE status = 'approved' ORDER BY rating DESC, name ASC")->fetchAll();
$matching_ids = array_map('intval', array_column($matching_academics, 'id'));

// Fetch currently assigned academics for this order (excluding replaced)
$assigned_stmt = $db->prepare("SELECT academic_id FROM order_assignments WHERE order_id = ? AND status != 'replaced'");
$assigned_stmt->execute([$order['id']]);
$assigned_academic_ids = $assigned_stmt->fetchAll(PDO::FETCH_COLUMN);
$assignedTeam = getAssignedAcademics($order['id']);
$replacementHistory = getReplacementHistory($order['id']);

$badge = orderStatusLabel($order['status']);

// Platform fees — نسبة العمولة تُجلب من عمود platform_percent الخاص بالخدمة
$commission_pct = isset($order['service_platform_percent']) ? (float) $order['service_platform_percent'] : 0.00;
$platform_fee = round($order['amount'] * $commission_pct / 100, 2);
$academic_net = round($order['amount'] - $platform_fee, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تفاصيل الطلب - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
    rel="stylesheet" />
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
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><a
                href="orders.php">الطلبات</a><span>›</span><span>تفاصيل الطلب</span></div>
            <h1 class="page-header-title">تفاصيل الطلب <?= e($order['order_number']) ?></h1>
            <p class="page-header-subtitle">تاريخ الطلب: <?= formatDate($order['created_at']) ?></p>
          </div>
          <div class="page-header-actions">
            <a href="orders.php" class="btn btn-outline">← العودة للطلبات</a>
            <button class="btn btn-primary" onclick="window.print()">🖨 طباعة</button>
          </div>
        </div>

        <?php if (isset($_GET['notes_saved'])): ?>
          <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">✅</span>
            <span style="font-weight:600">تم حفظ وتحديث ملاحظات الإدارة السرية بنجاح.</span>
          </div>
        <?php elseif (isset($_GET['replaced'])): ?>
          <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">✅</span>
            <span style="font-weight:600">تم استبدال الأكاديمي بنجاح وإسناد الطلب للأكاديمي البديل وإشعار الأطراف المعنية.</span>
          </div>
        <?php elseif (isset($_GET['updated'])): ?>
          <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">✅</span>
            <span style="font-weight:600">تم تحديث بيانات الطلب بنجاح.</span>
          </div>
        <?php elseif (!empty($error)): ?>
          <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">⚠️</span>
            <span style="font-weight:600"><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <div class="grid-2-1">

          <!-- Left Column -->
          <div style="display:flex;flex-direction:column;gap:20px">

            <!-- Order Summary -->
            <div class="stat-card animate-fadeInUp delay-1" style="padding:0;overflow:hidden">
              <div
                style="padding:20px 24px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:white;display:flex;align-items:center;justify-content:space-between">
                <div>
                  <h3 style="font-size:18px;font-weight:800"><?= e($order['order_number']) ?></h3>
                  <p style="font-size:13px;opacity:0.8;margin-top:2px"><?= e($order['service_icon']) ?>
                    <?= e($order['service_name']) ?> · <?= e($order['package_name'] ?: 'طلب خدمة مخصصة') ?>
                  </p>
                </div>
                <div style="text-align:left">
                  <div style="font-size:28px;font-weight:900"><?= formatMoney($order['amount']) ?></div>
                  <span
                    style="background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600"><?= $badge['label'] ?></span>
                </div>
              </div>

              <!-- Progress Steps -->
              <div style="padding:24px">
                <div
                  style="display:flex;align-items:center;justify-content:space-between;position:relative;margin-bottom:24px">
                  <div
                    style="position:absolute;top:16px;right:40px;left:40px;height:2px;background:var(--border-color);z-index:0">
                  </div>
                  <div
                    style="position:absolute;top:16px;right:40px;width:<?= $order['status'] === 'completed' ? '100%' : ($order['status'] === 'in_progress' ? '50%' : '25%') ?>;height:2px;background:var(--primary);z-index:1">
                  </div>
                  <div
                    style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                    <div
                      style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">
                      ✓</div>
                    <span style="font-size:12px;font-weight:600;color:var(--primary)">جديد</span>
                  </div>
                  <div
                    style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                    <div
                      style="width:32px;height:32px;border-radius:50%;background:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'var(--primary)' : 'var(--border-color)' ?>;color:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">
                      2</div>
                    <span
                      style="font-size:12px;color:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'var(--primary)' : 'var(--text-secondary)' ?>">قيد
                      التنفيذ</span>
                  </div>
                  <div
                    style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                    <div
                      style="width:32px;height:32px;border-radius:50%;background:<?= in_array($order['status'], ['revision', 'completed']) ? 'var(--primary)' : 'var(--border-color)' ?>;color:<?= in_array($order['status'], ['revision', 'completed']) ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px">
                      3</div>
                    <span
                      style="font-size:12px;color:<?= in_array($order['status'], ['revision', 'completed']) ? 'var(--primary)' : 'var(--text-secondary)' ?>">مراجعة</span>
                  </div>
                  <div
                    style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                    <div
                      style="width:32px;height:32px;border-radius:50%;background:<?= $order['status'] === 'completed' ? 'var(--success)' : 'var(--border-color)' ?>;color:<?= $order['status'] === 'completed' ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px">
                      4</div>
                    <span
                      style="font-size:12px;color:<?= $order['status'] === 'completed' ? 'var(--success)' : 'var(--text-secondary)' ?>">مكتمل</span>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                  <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                    <div class="form-label">تاريخ الإنشاء</div>
                    <div style="font-weight:600;color:var(--text-primary);margin-top:4px">
                      <?= formatDate($order['created_at']) ?>
                    </div>
                  </div>
                  <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                    <div class="form-label">الموعد النهائي</div>
                    <div style="font-weight:600;color:#ef4444;margin-top:4px"><?= formatDate($order['deadline']) ?>
                    </div>
                  </div>
                  <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                    <div class="form-label">المبلغ الإجمالي</div>
                    <div style="font-weight:700;font-size:18px;color:var(--success);margin-top:4px">
                      <?= formatMoney($order['amount']) ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Order Specifications & Student Requirements -->
            <div class="stat-card animate-fadeInUp delay-2" style="padding:22px">
              <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px;display:flex;align-items:center;gap:8px">
                <span>📋</span> مواصفات ومتطلبات الطلب
              </h3>

              <!-- Specification Badges Grid -->
              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:12px;margin-bottom:18px">
                <div style="background:var(--bg-main);padding:12px 14px;border-radius:10px;border:1px solid var(--border-color)">
                  <div style="font-size:11px;color:var(--text-secondary);font-weight:600">🎯 التخصص المطلوب</div>
                  <div style="font-size:13.5px;font-weight:700;color:var(--text-primary);margin-top:4px">
                    <?= e($order['specialty'] ?: 'عام / غير محدد') ?>
                  </div>
                </div>

                <div style="background:var(--bg-main);padding:12px 14px;border-radius:10px;border:1px solid var(--border-color)">
                  <div style="font-size:11px;color:var(--text-secondary);font-weight:600">🎓 المستوى الدراسي</div>
                  <div style="font-size:13.5px;font-weight:700;color:var(--text-primary);margin-top:4px">
                    <?= e($order['academic_level'] ?: 'غير محدد') ?>
                  </div>
                </div>

                <div style="background:var(--bg-main);padding:12px 14px;border-radius:10px;border:1px solid var(--border-color)">
                  <div style="font-size:11px;color:var(--text-secondary);font-weight:600">🌐 لغة العمل</div>
                  <div style="font-size:13.5px;font-weight:700;color:var(--text-primary);margin-top:4px">
                    <?= e($order['language'] ?: 'العربية') ?>
                  </div>
                </div>

                <div style="background:var(--bg-main);padding:12px 14px;border-radius:10px;border:1px solid var(--border-color)">
                  <div style="font-size:11px;color:var(--text-secondary);font-weight:600">👥 طبيعة الإسناد الأكاديمي</div>
                  <div style="font-size:13.5px;font-weight:700;color:var(--text-primary);margin-top:4px">
                    <?= ($order['academics_count'] ?? 1) > 1 ? 'فريق جماعي (' . (int)$order['academics_count'] . ' أكاديميين)' : 'أكاديمي فردي (1)' ?>
                  </div>
                </div>
              </div>

              <!-- Order Description / Student Text -->
              <div>
                <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px">📝 تفاصيل وشرح المطلوب من قِبل الطالب:</div>
                <?php if (!empty($order['description'])): ?>
                  <div style="background:var(--bg-main);padding:16px;border-radius:10px;font-size:13.5px;color:var(--text-primary);line-height:1.75;white-space:pre-wrap;border:1px solid var(--border-color);border-right:4px solid var(--primary)">
                    <?= e($order['description']) ?>
                  </div>
                <?php else: ?>
                  <div style="background:var(--bg-main);padding:14px;border-radius:10px;font-size:13px;color:var(--text-secondary);font-style:italic;border:1px dashed var(--border-color)">
                    لم يقم الطالب بإدخال تفاصيل أو شروط إضافية عند إنشاء الطلب.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Files -->
            <div class="stat-card animate-fadeInUp delay-2" style="padding:20px">
              <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📎 الملفات
                المرفوعة</h3>
              <?php if (empty($orderAttachments)): ?>
                <p style="font-size:13px;color:var(--text-secondary)">لا يوجد أي ملفات مرفوعة حالياً للطلب.</p>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                  <?php foreach ($orderAttachments as $att):
                    $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                    $iconMap = [
                      'pdf' => '📕',
                      'doc' => '📄',
                      'docx' => '📄',
                      'xls' => '📊',
                      'xlsx' => '📊',
                      'ppt' => '📽️',
                      'pptx' => '📽️',
                      'zip' => '🗜️',
                      'rar' => '🗜️',
                      'jpg' => '🖼️',
                      'jpeg' => '🖼️',
                      'png' => '🖼️',
                      'gif' => '🖼️',
                      'webp' => '🖼️',
                      'txt' => '📝',
                    ];
                    $attIcon = $iconMap[$ext] ?? '📎';
                    $attSize = (float) $att['file_size'];
                    $attSizeTxt = $attSize >= 1048576
                      ? number_format($attSize / 1048576, 2) . ' MB'
                      : number_format($attSize / 1024, 1) . ' KB';
                    $attPath = $adminAppBase . '/' . $att['file_path'];
                    $attDate = !empty($att['created_at']) ? formatDate($att['created_at']) : '';
                    ?>
                    <div
                      style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg-main);border-radius:12px;border:1px solid var(--border-color)">
                      <div style="font-size:26px;flex-shrink:0"><?= $attIcon ?></div>
                      <div style="flex:1;min-width:0">
                        <div
                          style="font-weight:700;font-size:13px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                          title="<?= e($att['file_name']) ?>"><?= e($att['file_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-secondary);margin-top:2px">
                          <?= e($attSizeTxt) ?>     <?= $attDate ? ' · ' . e($attDate) : '' ?>
                        </div>
                      </div>
                      <a href="<?= e($attPath) ?>" target="_blank" class="btn btn-outline"
                        style="padding:6px 14px;font-size:12px;flex-shrink:0">عرض 👁️</a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Notes -->
            <div class="stat-card animate-fadeInUp delay-3" style="padding:20px">
              <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📝 ملاحظات الطلب
              </h3>
              <div
                style="background:var(--bg-main);padding:16px;border-radius:12px;color:var(--text-secondary);font-size:14px;line-height:1.8;margin-bottom:16px">
                <?= nl2br(e($order['description'])) ?>
              </div>
            </div>

            <!-- Chat Viewer -->
            <div class="stat-card animate-fadeInUp delay-4"
              style="padding:20px; display:flex; flex-direction:column; max-height:550px">
              
              <?php if ($isMultiAcademics): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border-color); padding-bottom:12px; flex-wrap:wrap; gap:10px">
                  <div style="display:flex; gap:10px; align-items:center">
                    <button type="button" id="adminTabBtnStudent" onclick="adminSwitchChat('student')" class="btn btn-sm" style="font-weight:700; border-radius:10px; background:var(--primary); color:#fff; border:none; padding:7px 14px; cursor:pointer">
                      💬 المحادثة مع الطالب
                    </button>
                    <button type="button" id="adminTabBtnTeam" onclick="adminSwitchChat('team')" class="btn btn-sm btn-outline" style="font-weight:700; border-radius:10px; padding:7px 14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px">
                      👥 نقاش فريق العمل (داخلي)
                      <span style="font-size:10px; background:rgba(239,68,68,0.15); color:#ef4444; padding:2px 6px; border-radius:6px; font-weight:800">خاص 🔒</span>
                    </button>
                  </div>
                  <span class="badge badge-primary" style="font-size:12px">فريق من <?= count($assignedAcademics) ?> أكاديميين</span>
                </div>
              <?php else: ?>
                <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">💬 المحادثة المرفقة (طالب ↔ أكاديمي)</h3>
              <?php endif; ?>

              <!-- TAB 1: STUDENT CHAT PANE -->
              <div id="adminPaneStudent" style="flex:1; display:flex; flex-direction:column; min-height:0">
                <div style="flex:1; overflow-y:auto; background:var(--bg-main); border-radius:12px; padding:16px; border:1px solid var(--border-color); display:flex; flex-direction:column; gap:16px; max-height:420px">
                  <?php if (empty($chatMessages)): ?>
                    <div style="text-align:center; padding:30px 0; color:var(--text-secondary); font-size:13px">
                      لا توجد رسائل أو ملفات متبادلة بعد في محادثة الطالب.
                    </div>
                  <?php else: ?>
                    <?php foreach ($chatMessages as $msg):
                      $isStudent = $msg['sender_type'] === 'student';
                      $msgColor = $isStudent ? 'var(--primary)' : '#10b981';
                      $bgColor = $isStudent ? 'rgba(99,102,241,0.1)' : 'rgba(16,185,129,0.1)';
                      $senderName = $isStudent ? $order['student_name'] : $order['academic_name'];
                      $senderAvatar = $isStudent ? '👨‍🎓' : '👨‍🏫';

                      // Format file if attached
                      $msgHasFile = !empty($msg['file_path']);
                      $msgExt = $msgHasFile ? strtolower(pathinfo($msg['file_name'], PATHINFO_EXTENSION)) : '';
                      $msgIconMap = [
                        'pdf' => '📕', 'doc' => '📄', 'docx' => '📄', 'xls' => '📊', 'xlsx' => '📊',
                        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'webp' => '🖼️',
                      ];
                      $msgFileIcon = $msgIconMap[$msgExt] ?? '📎';
                      $msgFilePath = $msgHasFile ? $adminAppBase . '/' . $msg['file_path'] : '#';
                      ?>
                      <div style="display:flex; gap:12px; <?= $isStudent ? 'flex-direction:row;' : 'flex-direction:row-reverse;' ?>">
                        <div style="width:36px; height:36px; border-radius:10px; background:<?= $bgColor ?>; color:<?= $msgColor ?>; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                          <?= $senderAvatar ?>
                        </div>
                        <div style="max-width:85%; display:flex; flex-direction:column; gap:4px; <?= $isStudent ? 'align-items:flex-start;' : 'align-items:flex-end;' ?>">
                          <div style="font-size:11px; color:var(--text-secondary); font-weight:600">
                            <?= e($senderName) ?> · <?= formatDate($msg['sent_at']) ?>
                          </div>
                          <div style="background:<?= $isStudent ? 'white' : '#f0fdf4' ?>; border:1px solid <?= $isStudent ? 'var(--border-color)' : '#bbf7d0' ?>; padding:10px 14px; border-radius:12px; <?= $isStudent ? 'border-top-right-radius:2px;' : 'border-top-left-radius:2px;' ?> font-size:13.5px; color:#111827; line-height:1.6">
                            <?php if (!empty($msg['content'])): ?>
                              <div style="white-space:pre-wrap"><?= e($msg['content']) ?></div>
                            <?php endif; ?>

                            <?php if ($msgHasFile): ?>
                              <a href="<?= e($msgFilePath) ?>" target="_blank"
                                style="display:flex; align-items:center; gap:8px; margin-top:8px; padding:8px 12px; background:<?= $isStudent ? 'var(--bg-main)' : '#dcfce7' ?>; border:1px solid <?= $isStudent ? 'var(--border-color)' : '#86efac' ?>; border-radius:8px; text-decoration:none; color:#111827; transition:border-color 0.2s">
                                <span style="font-size:18px"><?= $msgFileIcon ?></span>
                                <span style="font-size:12px; font-weight:600; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px" title="<?= e($msg['file_name']) ?>"><?= e($msg['file_name']) ?></span>
                                <span style="font-size:11px; color:<?= $msgColor ?>; font-weight:700">تنزيل</span>
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($isMultiAcademics): ?>
                <!-- TAB 2: TEAM CHAT PANE (Internal - Academics & Admin) -->
                <div id="adminPaneTeam" style="flex:1; display:none; flex-direction:column; min-height:0">
                  <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:10px; padding:10px 14px; margin-bottom:12px; font-size:12px; color:var(--text-primary)">
                    🔒 <strong>مساحة نقاش خاصة بالأكاديميين المكلفين والإدارة:</strong> هذه الرسائل والملفات سرية ولا تظهر للطالب إطلاقاً.
                  </div>
                  <div style="flex:1; overflow-y:auto; background:var(--bg-main); border-radius:12px; padding:16px; border:1px solid var(--border-color); display:flex; flex-direction:column; gap:16px; max-height:360px">
                    <?php if (empty($teamChatMessages)): ?>
                      <div style="text-align:center; padding:30px 0; color:var(--text-secondary); font-size:13px">
                        لا توجد رسائل داخلية مسجلة بين أعضاء فريق العمل حتى الآن.
                      </div>
                    <?php else: ?>
                      <?php foreach ($teamChatMessages as $tMsg):
                        $isAdminMsg = $tMsg['sender_type'] === 'admin';
                        $tMsgColor = $isAdminMsg ? '#dc2626' : '#059669';
                        $tBgColor = $isAdminMsg ? 'rgba(220,38,38,0.1)' : 'rgba(5,150,105,0.1)';
                        $tAvatar = $isAdminMsg ? '🛡️' : '👨‍🏫';
                        $tHasFile = !empty($tMsg['file_path']);
                        $tFilePath = $tHasFile ? $adminAppBase . '/' . $tMsg['file_path'] : '#';
                        ?>
                        <div style="display:flex; gap:12px; <?= $isAdminMsg ? 'flex-direction:row-reverse;' : 'flex-direction:row;' ?>">
                          <div style="width:36px; height:36px; border-radius:10px; background:<?= $tBgColor ?>; color:<?= $tMsgColor ?>; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                            <?= $tAvatar ?>
                          </div>
                          <div style="max-width:85%; display:flex; flex-direction:column; gap:4px; <?= $isAdminMsg ? 'align-items:flex-end;' : 'align-items:flex-start;' ?>">
                            <div style="font-size:11px; color:var(--text-secondary); font-weight:600">
                              <?= e($tMsg['sender_name']) ?> <?= $isAdminMsg ? '(الإدارة)' : '(أكاديمي)' ?> · <?= formatDate($tMsg['sent_at']) ?>
                            </div>
                            <div style="background:white; border:1px solid <?= $isAdminMsg ? '#fca5a5' : '#a7f3d0' ?>; padding:10px 14px; border-radius:12px; font-size:13.5px; color:#111827; line-height:1.6">
                              <?php if (!empty($tMsg['content'])): ?>
                                <div style="white-space:pre-wrap"><?= e($tMsg['content']) ?></div>
                              <?php endif; ?>

                              <?php if ($tHasFile): ?>
                                <a href="<?= e($tFilePath) ?>" target="_blank"
                                  style="display:flex; align-items:center; gap:8px; margin-top:8px; padding:8px 12px; background:#f0fdf4; border:1px solid #86efac; border-radius:8px; text-decoration:none; color:#111827">
                                  <span style="font-size:18px">📎</span>
                                  <span style="font-size:12px; font-weight:600; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px"><?= e($tMsg['file_name'] ?: 'ملف') ?></span>
                                  <span style="font-size:11px; color:#059669; font-weight:700">تنزيل</span>
                                </a>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>

                <script>
                function adminSwitchChat(tab) {
                  const pStudent = document.getElementById('adminPaneStudent');
                  const pTeam = document.getElementById('adminPaneTeam');
                  const bStudent = document.getElementById('adminTabBtnStudent');
                  const bTeam = document.getElementById('adminTabBtnTeam');
                  if (tab === 'team') {
                    if (pStudent) pStudent.style.display = 'none';
                    if (pTeam) pTeam.style.display = 'flex';
                    if (bStudent) { bStudent.style.background = 'transparent'; bStudent.style.color = 'var(--text-secondary)'; bStudent.style.border = '1px solid var(--border-color)'; }
                    if (bTeam) { bTeam.style.background = '#059669'; bTeam.style.color = '#fff'; bTeam.style.border = 'none'; }
                  } else {
                    if (pStudent) pStudent.style.display = 'flex';
                    if (pTeam) pTeam.style.display = 'none';
                    if (bStudent) { bStudent.style.background = 'var(--primary)'; bStudent.style.color = '#fff'; bStudent.style.border = 'none'; }
                    if (bTeam) { bTeam.style.background = 'transparent'; bTeam.style.color = 'var(--text-primary)'; bTeam.style.border = '1px solid var(--border-color)'; }
                  }
                }
                </script>
              <?php endif; ?>

            </div>

          </div>

            <!-- Replacement Audit History -->
            <?php if (!empty($replacementHistory)): ?>
              <div class="stat-card animate-fadeInUp delay-3" style="padding:20px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                  <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin:0;display:flex;align-items:center;gap:8px">
                    <span>🔄</span> سجل استبدال الأكاديميين (<?= count($replacementHistory) ?>)
                  </h3>
                  <span class="badge" style="background:rgba(245,158,11,0.1);color:#d97706;padding:4px 10px;font-size:11px;font-weight:700">سجل إداري موثق</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px">
                  <?php foreach ($replacementHistory as $hist): ?>
                    <div style="background:var(--bg-main);border-radius:12px;padding:14px;border:1px solid var(--border-color);border-right:4px solid #f59e0b">
                      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
                        <div style="display:flex;align-items:center;gap:8px;font-size:13px">
                          <span style="font-weight:700;color:#991b1b;text-decoration:line-through"><?= e($hist['old_academic_name']) ?></span>
                          <span style="color:var(--text-secondary)">➔</span>
                          <span style="font-weight:700;color:#166534"><?= e($hist['new_academic_name'] ?? 'لم يحدد') ?></span>
                        </div>
                        <span style="font-size:11px;color:var(--text-secondary);direction:ltr"><?= formatDate($hist['replaced_at']) ?></span>
                      </div>
                      <div style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:6px">
                        <span style="color:var(--text-secondary)">سبب الاستبدال:</span>
                        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px;padding:2px 8px;font-weight:600">
                          <?= replacementReasonLabel($hist['replacement_reason']) ?>
                        </span>
                      </div>
                      <?php if (!empty($hist['replacement_note'])): ?>
                        <div style="background:var(--bg-card);padding:8px 10px;border-radius:6px;font-size:12px;color:var(--text-primary);margin-top:6px;border:1px dashed var(--border-color)">
                          <strong>ملاحظة الإدارة:</strong> <?= nl2br(e($hist['replacement_note'])) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

          <!-- Right Column -->
          <div style="display:flex;flex-direction:column;gap:20px">

            <!-- Student Info -->
            <div class="stat-card animate-fadeInUp delay-1" style="padding:20px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">👤 بيانات الطالب
              </h3>
              <div
                style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--bg-main);border-radius:12px;margin-bottom:12px">
                <div class="table-avatar"
                  style="width:52px;height:52px;border-radius:14px;font-size:20px;background:#6366f1">👨‍🎓</div>
                <div>
                  <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= e($order['student_name']) ?>
                  </div>
                  <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['student_email']) ?></div>
                  <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['student_phone']) ?></div>
                </div>
              </div>
            </div>

            <!-- Academic Info -->
            <div class="stat-card animate-fadeInUp delay-2" style="padding:20px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">🎓 بيانات
                الأكاديمي</h3>
              <?php if ($order['academic_id']): ?>
                <div
                  style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--bg-main);border-radius:12px;margin-bottom:12px">
                  <div class="table-avatar"
                    style="width:52px;height:52px;border-radius:14px;font-size:20px;background:#10b981">👨‍🏫</div>
                  <div>
                    <div style="font-size:15px;font-weight:700;color:var(--text-primary)">
                      <?= e($order['academic_name']) ?>
                    </div>
                    <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['academic_email']) ?></div>
                    <div style="font-size:12px;color:#f59e0b;font-weight:600">⭐
                      <?= number_format((float) $order['academic_rating'], 1) ?> تقييم
                    </div>
                  </div>
                </div>
                <div style="background:var(--bg-main);padding:10px;border-radius:10px;margin-bottom:8px">
                  <div style="font-size:12px;color:var(--text-secondary)">التخصص</div>
                  <div style="font-size:13px;font-weight:600;color:var(--text-primary)">
                    <?= e($order['academic_specialty']) ?>
                  </div>
                </div>

                <?php if (!in_array($order['status'], ['completed', 'cancelled'])): ?>
                  <button type="button" class="btn btn-warning btn-sm" style="width:100%;justify-content:center;margin-top:8px;background:#f59e0b;color:#fff;border:none;font-weight:700;gap:6px" onclick="openReplaceAcademicModal(<?= (int)$order['academic_id'] ?>, '<?= e(addslashes($order['academic_name'])) ?>')">
                    🔄 استبدال / تغيير الأكاديمي
                  </button>
                <?php endif; ?>

                <?php if (count($assignedTeam) > 1): ?>
                  <div style="margin-top:16px;border-top:1px dashed var(--border-color);padding-top:12px">
                    <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px">👥 أعضاء الفريق الأكاديمي المكلف:</div>
                    <div style="display:flex;flex-direction:column;gap:6px">
                      <?php foreach ($assignedTeam as $tm): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;background:var(--bg-main);padding:8px 10px;border-radius:8px;font-size:12px">
                          <div>
                            <span style="font-weight:600;color:var(--text-primary)"><?= e($tm['name']) ?></span>
                            <?php if ((int)$tm['id'] === (int)$order['academic_id']): ?>
                              <span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;font-size:10px;padding:1px 5px">الرئيسي</span>
                            <?php endif; ?>
                          </div>
                          <div style="display:flex;align-items:center;gap:6px">
                            <?php if ($tm['assignment_status'] === 'completed'): ?>
                              <span class="badge" style="background:#dcfce7;color:#166534;font-size:10px;padding:2px 6px">✅ أنجز</span>
                            <?php elseif ($tm['assignment_status'] === 'accepted'): ?>
                              <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:10px;padding:2px 6px">⏳ قيد التنفيذ</span>
                            <?php elseif ($tm['assignment_status'] === 'rejected'): ?>
                              <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:10px;padding:2px 6px">❌ اعتذر</span>
                            <?php else: ?>
                              <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;padding:2px 6px">📨 بانتظار الرد</span>
                            <?php endif; ?>

                            <?php if (!in_array($order['status'], ['completed', 'cancelled']) && $tm['assignment_status'] !== 'completed'): ?>
                              <button type="button" class="btn btn-outline btn-xs" style="padding:2px 6px;font-size:10px;color:#d97706;border-color:#f59e0b" onclick="openReplaceAcademicModal(<?= (int)$tm['id'] ?>, '<?= e(addslashes($tm['name'])) ?>')">
                                🔄 استبدال
                              </button>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">لم يتم قبول الطلب من أكاديمي بعد.
                </p>

                <?php if ($order['status'] === 'awaiting_payment'): ?>
                  <div style="border-top:1px dashed var(--border-color);padding-top:16px;text-align:center">
                    <div
                      style="background:#fffbeb;color:#b45309;padding:12px;border-radius:8px;font-size:13px;border:1px solid #fde68a">
                      ⚠️ يجب اعتماد إيصال الدفع من قسم المدفوعات أولاً قبل إمكانية تعيين الأكاديميين.
                    </div>
                  </div>
                <?php else: ?>
                  <!-- إسناد الطلب للأكاديميين المرشحين -->
                  <div style="border-top:1px dashed var(--border-color);padding-top:16px">
                    <?php if (($order['academics_count'] ?? 1) > 1): ?>
                      <div style="background:rgba(245,158,11,0.08);border:1.5px dashed #f59e0b;padding:12px;border-radius:10px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:22px">👥</span>
                        <div style="font-size:12px;color:#92400e;line-height:1.5">
                          <strong>خدمة جماعية:</strong> طلب الطالب إسناد العمل إلى <strong><?= (int)$order['academics_count'] ?> أكاديميين</strong>. لن يصبح الطلب مكتملاً إلا بعد إكماله من قبل جميع الأكاديميين المعتمدين.
                        </div>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($assignedTeam)): ?>
                      <div style="margin-bottom:16px;background:var(--bg-main);padding:12px;border-radius:10px">
                        <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px">حالة إنجاز الفريق الأكاديمي المكلف:</div>
                        <div style="display:flex;flex-direction:column;gap:6px">
                          <?php foreach ($assignedTeam as $tm): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;background:var(--bg-card);padding:8px 12px;border-radius:8px;border:1px solid var(--border-color);font-size:13px">
                              <span style="font-weight:600;color:var(--text-primary)"><?= e($tm['name']) ?></span>
                              <div style="display:flex;align-items:center;gap:6px">
                                <?php if ($tm['assignment_status'] === 'completed'): ?>
                                  <span class="badge" style="background:#dcfce7;color:#166534;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700">✅ أنجز مهمته</span>
                                <?php elseif ($tm['assignment_status'] === 'accepted'): ?>
                                  <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700">⏳ قيد التنفيذ</span>
                                <?php elseif ($tm['assignment_status'] === 'rejected'): ?>
                                  <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700">❌ اعتذر</span>
                                <?php else: ?>
                                  <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700">📨 بانتظار الرد</span>
                                <?php endif; ?>
                                <?php if (!in_array($order['status'], ['completed', 'cancelled']) && $tm['assignment_status'] !== 'completed'): ?>
                                  <button type="button" class="btn btn-outline btn-xs" style="padding:2px 6px;font-size:11px;color:#d97706;border-color:#f59e0b" title="استبدال هذا الأكاديمي" onclick="openReplaceAcademicModal(<?= (int)$tm['id'] ?>, '<?= e(addslashes($tm['name'])) ?>')">
                                    🔄 استبدال
                                  </button>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>

                    <h4 style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:8px">📋 إسناد الطلب
                      لأكاديمي أو أكثر</h4>
                    <div
                      style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                      <p style="font-size:11px;color:var(--text-secondary);margin:0">الخدمة: <strong
                          style="color:var(--primary)"><?= e($order['service_icon']) ?>
                          <?= e($order['service_name']) ?></strong></p>
                      <button type="button" id="toggleAllBtn" class="btn btn-outline btn-sm"
                        onclick="toggleAllAcademics()">👥 عرض كل الأكاديميين (<?= count($all_academics) ?>)</button>
                    </div>
                    <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>">
                      <input type="hidden" name="action" value="assign_academics">

                      <!-- القائمة الافتراضية: الأكاديميون المتخصصون فقط -->
                      <div id="matchingList"
                        style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow-y:auto;margin-bottom:12px;padding-left:4px">
                        <?php if (empty($matching_academics)): ?>
                          <p style="font-size:12px;color:var(--text-secondary)">لا يوجد أكاديميون معتمدون يقدّمون هذه الخدمة
                            حالياً — استخدم زر «عرض كل الأكاديميين» للإسناد اليدوي.</p>
                        <?php else: ?>
                          <?php foreach ($matching_academics as $ac): ?>
                            <?php $checked = in_array($ac['id'], $assigned_academic_ids) ? 'checked' : ''; ?>
                            <label
                              style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-main);border-radius:8px;cursor:pointer;font-size:13px">
                              <input type="checkbox" name="academic_ids[]" value="<?= $ac['id'] ?>" <?= $checked ?>
                                style="width:16px;height:16px;accent-color:var(--primary)" />
                              <div style="flex:1">
                                <div style="display:flex;align-items:center;gap:6px">
                                  <span style="font-weight:600;color:var(--text-primary)"><?= e($ac['name']) ?></span>
                                  <?php if (($ac['availability'] ?? 'available') === 'available'): ?>
                                    <span class="badge"
                                      style="background:rgba(16,185,129,.1);color:#059669;font-size:10px;padding:2px 6px">متاح</span>
                                  <?php else: ?>
                                    <span class="badge"
                                      style="background:rgba(239,68,68,.1);color:#ef4444;font-size:10px;padding:2px 6px">غير
                                      متاح</span>
                                  <?php endif; ?>
                                </div>
                                <span
                                  style="font-size:10px;color:var(--text-secondary);display:block;margin-top:4px"><?= e($ac['specialty']) ?>
                                  · <?= e($ac['degree']) ?> · ⭐ <?= number_format((float) $ac['rating'], 1) ?></span>
                              </div>
                            </label>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </div>

                      <!-- القائمة البديلة: جميع الأكاديميين المعتمدين -->
                      <div id="allAcademicsList"
                        style="display:none;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto;margin-bottom:12px;padding-left:4px">
                        <?php if (empty($all_academics)): ?>
                          <p style="font-size:12px;color:var(--text-secondary)">لا يوجد أكاديميون معتمدون في المنصة حالياً.
                          </p>
                        <?php else: ?>
                          <?php
                          // ترتيب: من يقدم الخدمة أولاً ثم حسب التقييم
                          $sorted_all = $all_academics;
                          usort($sorted_all, function ($a, $b) use ($matching_ids) {
                            $aMatch = in_array((int) $a['id'], $matching_ids) ? 1 : 0;
                            $bMatch = in_array((int) $b['id'], $matching_ids) ? 1 : 0;
                            if ($aMatch !== $bMatch)
                              return $bMatch - $aMatch;
                            return (float) $b['rating'] <=> (float) $a['rating'];
                          });
                          foreach ($sorted_all as $ac):
                            $isMatch = in_array((int) $ac['id'], $matching_ids);
                            $checked = in_array($ac['id'], $assigned_academic_ids) ? 'checked' : '';
                            ?>
                            <label
                              style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-main);border-radius:8px;cursor:pointer;font-size:13px;<?= $isMatch ? 'border:1px solid var(--primary);' : '' ?>">
                              <input type="checkbox" name="academic_ids[]" value="<?= $ac['id'] ?>" <?= $checked ?>
                                style="width:16px;height:16px;accent-color:var(--primary)" />
                              <div style="flex:1">
                                <div style="display:flex;align-items:center;gap:6px">
                                  <span style="font-weight:600;color:var(--text-primary)"><?= e($ac['name']) ?></span>
                                  <?php if (($ac['availability'] ?? 'available') === 'available'): ?>
                                    <span class="badge"
                                      style="background:rgba(16,185,129,.1);color:#059669;font-size:10px;padding:2px 6px">متاح</span>
                                  <?php else: ?>
                                    <span class="badge"
                                      style="background:rgba(239,68,68,.1);color:#ef4444;font-size:10px;padding:2px 6px">غير
                                      متاح</span>
                                  <?php endif; ?>
                                  <?php if ($isMatch): ?><span style="font-size:10px;color:var(--primary);font-weight:700"> ✓
                                      يقدم هذه الخدمة</span><?php endif; ?>
                                </div>
                                <span
                                  style="font-size:10px;color:var(--text-secondary);display:block;margin-top:4px"><?= e($ac['specialty']) ?>
                                  · <?= e($ac['degree']) ?> · ⭐ <?= number_format((float) $ac['rating'], 1) ?></span>
                              </div>
                            </label>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </div>

                      <button type="submit" class="btn btn-primary btn-block btn-sm"
                        style="justify-content:center;width:100%">💾 حفظ وإرسال الإسناد</button>
                    </form>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <!-- Change Status -->
            <div class="stat-card animate-fadeInUp delay-3" style="padding:20px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">🔄 تغيير حالة
                الطلب</h3>
              <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>">
                <input type="hidden" name="action" value="update_status">
                <div style="display:flex;flex-direction:column;gap:8px">
                  <button type="submit" name="status" value="pending_assignment" class="btn btn-info"
                    style="justify-content:center;background:#6366f1">⏳ بانتظار التعيين</button>
                  <button type="submit" name="status" value="new" class="btn btn-info" style="justify-content:center">⭐
                    طلب جديد</button>
                  <button type="submit" name="status" value="in_progress" class="btn btn-warning"
                    style="justify-content:center">⟳ قيد التنفيذ</button>
                  <button type="submit" name="status" value="delivered" class="btn btn-info"
                    style="justify-content:center;background:#8b5cf6">📦 تم التسليم للعميل</button>
                  <button type="submit" name="status" value="revision" class="btn btn-warning"
                    style="justify-content:center;background:#f59e0b">✏️ طلب تعديلات</button>
                  <button type="submit" name="status" value="completed" class="btn btn-success"
                    style="justify-content:center">✓ اعتماد واكتمال الطلب</button>
                  <hr class="divider" />
                  <?php if ($order['status'] === 'cancellation_requested'): ?>
                    <div
                      style="background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin-bottom:12px">
                      <div style="font-size:12px;color:#991b1b;font-weight:700;margin-bottom:4px">⚠️ طلب إلغاء من الطالب
                      </div>
                      <div style="font-size:13px;color:#7f1d1d;margin-bottom:6px"><strong>السبب:</strong>
                        <?= e($order['cancellation_reason'] ?? 'غير محدد') ?>
                      </div>
                      <div style="font-size:13px;color:#7f1d1d"><strong>حالة الطلب وقت الإلغاء (عند الأكاديمي):</strong>
                        <?= e(orderStatusLabel($order['previous_status'] ?? '')['label'] ?? 'غير معروف') ?>
                      </div>
                    </div>
                  <?php elseif ($order['status'] === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                    <div
                      style="background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin-bottom:12px">
                      <div style="font-size:12px;color:#991b1b;font-weight:700;margin-bottom:4px">🚫 تم إلغاء هذا الطلب
                      </div>
                      <div style="font-size:13px;color:#7f1d1d;margin-bottom:6px"><strong>سبب الإلغاء:</strong>
                        <?= e($order['cancellation_reason']) ?>
                      </div>
                      <?php if (!empty($order['previous_status'])): ?>
                        <div style="font-size:13px;color:#7f1d1d"><strong>الحالة قبل الإلغاء:</strong>
                          <?= e(orderStatusLabel($order['previous_status'])['label'] ?? 'غير معروف') ?>
                        </div>
                      <?php endif; ?>
                      <?php 
                      $refundForOrder = getRefundForOrder((int)$order['id']);
                      if ($refundForOrder): ?>
                        <div style="border-top:1px dashed #fca5a5;margin-top:8px;padding-top:8px">
                          <div style="font-size:12px;font-weight:700;color:#991b1b;margin-bottom:4px">
                            💸 حالة استرجاع المبلغ (<?= formatMoney($refundForOrder['amount']) ?>):
                          </div>
                          <?php if ($refundForOrder['status'] === 'processed'): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
                              <span style="font-size:12px;color:#166534;font-weight:700">✅ تم تحويل المبلغ للطالب</span>
                              <?php if (!empty($refundForOrder['receipt_path'])): ?>
                                <a href="<?= e($adminAppBase . '/' . ltrim($refundForOrder['receipt_path'], '/')) ?>" target="_blank" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;color:#166534;border-color:#166534;font-weight:700">
                                  👁️ إشعار التحويل
                                </a>
                              <?php endif; ?>
                            </div>
                          <?php elseif ($refundForOrder['status'] === 'pending'): ?>
                            <span style="font-size:12px;color:#92400e;font-weight:600">⏳ قيد الانتظار في قسم المدفوعات</span>
                          <?php elseif ($refundForOrder['status'] === 'rejected'): ?>
                            <span style="font-size:12px;color:#991b1b;font-weight:600">❌ تم رفض الاسترجاع</span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($order['status'] === 'cancellation_requested'): ?>
                    <script>
                      function handlePartialRefund(form) {
                        var maxAmount = <?= (float) $order['amount'] ?>;
                        var val = prompt("الرجاء إدخال المبلغ المراد خصمه كتعويض للأكاديمي (باقي المبلغ سيتم استرداده للطالب):\nأدخل 0 لاسترداد كامل المبلغ للطالب.", "0");
                        if (val === null) return false;
                        var deducted = parseFloat(val);
                        if (isNaN(deducted) || deducted < 0 || deducted > maxAmount) {
                          alert("قيمة غير صحيحة. يجب أن يكون الخصم بين 0 و " + maxAmount);
                          return false;
                        }
                        var rInput = document.createElement("input");
                        rInput.type = "hidden";
                        rInput.name = "deducted_amount";
                        rInput.value = deducted;
                        form.appendChild(rInput);
                        return true;
                      }
                    </script>
                    <button type="submit" name="status" value="cancelled" onclick="return handlePartialRefund(this.form);"
                      class="btn btn-danger" style="justify-content:center;background:#991b1b">⚠️ قبول الإلغاء وتحديد
                      الاسترجاع</button>
                    <button type="submit" name="status" value="<?= e($order['previous_status'] ?? 'in_progress') ?>"
                      class="btn btn-outline" style="justify-content:center;color:#991b1b;border-color:#991b1b">✕ رفض
                      وإعادة للحالة السابقة</button>
                  <?php else: ?>
                    <button type="submit" name="status" value="cancelled" class="btn btn-danger"
                      style="justify-content:center">✕ إلغاء الطلب مباشرة</button>
                  <?php endif; ?>
                </div>
              </form>
            </div>

            <!-- Payment & Financial Summary -->
            <div class="stat-card animate-fadeInUp delay-4" style="padding:20px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px;display:flex;align-items:center;gap:8px">
                <span>💰</span> بيانات الدفع والملخص المالي
              </h3>

              <!-- Payment Details Box -->
              <div style="background:var(--bg-main);border-radius:12px;padding:14px;border:1px solid var(--border-color);margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                  <span style="font-size:12px;color:var(--text-secondary)">حالة الدفع:</span>
                  <?php if (!empty($orderPayment) && $orderPayment['status'] === 'paid'): ?>
                    <span class="badge" style="background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 8px">✅ مدفوع ومعتمد</span>
                  <?php elseif ($order['status'] === 'awaiting_payment'): ?>
                    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 8px">⏳ بانتظار السداد / الاعتماد</span>
                  <?php elseif (!empty($orderPayment) && $orderPayment['status'] === 'refunded'): ?>
                    <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:11px;font-weight:700;padding:2px 8px">↩️ تم الاسترداد</span>
                  <?php else: ?>
                    <span class="badge" style="background:#f3f4f6;color:#374151;font-size:11px;font-weight:700;padding:2px 8px">ℹ️ <?= e($orderPayment['status'] ?? 'غير مسدد') ?></span>
                  <?php endif; ?>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:12px">
                  <span style="color:var(--text-secondary)">طريقة الدفع:</span>
                  <span style="font-weight:600;color:var(--text-primary)">
                    <?php
                    $m = $orderPayment['method'] ?? ($orderReceipt ? 'bank_transfer' : null);
                    $mMap = [
                      'credit_card'   => 'بطاقة ائتمان / مدى 💳',
                      'bank_transfer' => 'تحويل بنكي 🏦',
                      'apple_pay'     => 'Apple Pay 🍏',
                      'wallet'        => 'المحفظة الإلكترونية 👛',
                    ];
                    echo e($mMap[$m] ?? ($orderReceipt['method_label'] ?? 'غير محدد'));
                    ?>
                  </span>
                </div>

                <?php if (!empty($orderPayment['payment_number'])): ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:12px">
                    <span style="color:var(--text-secondary)">رقم المعاملة:</span>
                    <span style="font-weight:600;font-family:monospace;color:var(--primary)"><?= e($orderPayment['payment_number']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($orderPayment['paid_at'])): ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:12px">
                    <span style="color:var(--text-secondary)">تاريخ السداد:</span>
                    <span style="font-weight:600;color:var(--text-primary)"><?= formatDate($orderPayment['paid_at']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($orderReceipt)): ?>
                  <div style="border-top:1px dashed var(--border-color);margin-top:10px;padding-top:10px">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                      <span style="font-size:12px;color:var(--text-secondary)">إيصال التحويل:</span>
                      <a href="<?= e($adminAppBase . '/' . ltrim($orderReceipt['receipt_path'], '/')) ?>" target="_blank" class="btn btn-outline btn-xs" style="font-size:11px;padding:3px 8px;gap:4px">
                        👁️ عرض الإيصال
                      </a>
                    </div>
                    <?php if (!empty($orderReceipt['student_note'])): ?>
                      <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">
                        <strong>ملاحظة الطالب:</strong> <?= e($orderReceipt['student_note']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Amounts Breakdown -->
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
                <span style="font-size:13px;color:var(--text-secondary)">سعر الباقة</span>
                <span style="font-weight:600"><?= formatMoney($order['amount']) ?></span>
              </div>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
                <span style="font-size:13px;color:var(--text-secondary)">العمولة (<?= number_format($commission_pct, 2) ?>%)</span>
                <span style="font-weight:600;color:var(--primary)"><?= formatMoney($platform_fee) ?></span>
              </div>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
                <span style="font-size:13px;color:var(--text-secondary)">نصيب الأكاديمي</span>
                <span style="font-weight:600;color:var(--success)"><?= formatMoney($academic_net) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0 0">
                <span style="font-size:15px;font-weight:700;color:var(--text-primary)">إجمالي الإيراد</span>
                <span style="font-size:20px;font-weight:800;color:var(--primary)"><?= formatMoney($order['amount']) ?></span>
              </div>
            </div>

            <!-- Admin Internal Notes -->
            <div class="stat-card animate-fadeInUp delay-5" style="padding:20px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:8px;display:flex;align-items:center;gap:8px">
                <span>📝</span> ملاحظات الإدارة السرية
              </h3>
              <p style="font-size:11px;color:var(--text-secondary);margin-bottom:12px">
                🔒 هذه الملاحظات خاصة بالمشرفين والإدارة فقط، ولا تظهر للطالب أو الأكاديميين.
              </p>
              <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>">
                <input type="hidden" name="action" value="save_admin_notes">
                <textarea name="admin_notes" rows="4" class="form-control" placeholder="اكتب ملاحظات داخلية حول متابعة الطلب أو الطالب أو الأكاديمي..." style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px;resize:vertical;line-height:1.6"><?= e($order['admin_notes'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-top:10px;font-weight:700">
                  💾 حفظ الملاحظات
                </button>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Replace Academic Modal -->
  <div id="replaceAcademicModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)">
    <div style="width:100%;max-width:520px;padding:24px;border-radius:16px;background:var(--bg-card);box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);position:relative;border:1px solid var(--border-color)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border-color);padding-bottom:12px">
        <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:0;display:flex;align-items:center;gap:8px">
          <span>🔄</span> استبدال الأكاديمي المكلف
        </h3>
        <button type="button" onclick="closeReplaceAcademicModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-secondary);line-height:1">&times;</button>
      </div>

      <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>" id="replaceAcademicForm">
        <input type="hidden" name="action" value="replace_academic">
        <input type="hidden" name="target_academic_id" id="replace_target_academic_id" value="">

        <!-- Current Academic Info -->
        <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:12px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
          <span style="font-size:24px">👨‍🏫</span>
          <div style="flex:1">
            <div style="font-size:11px;color:#991b1b;font-weight:700">الأكاديمي المراد استبداله:</div>
            <div id="replace_target_academic_name" style="font-size:15px;font-weight:800;color:var(--text-primary);margin-top:2px">-</div>
          </div>
        </div>

        <!-- Select New Academic -->
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">الأكاديمي البديل <span style="color:#ef4444">*</span></label>
          <select name="new_academic_id" id="replace_new_academic_id" class="form-control" required style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px">
            <option value="">-- اختر الأكاديمي البديل --</option>
            <?php
            foreach ($all_academics as $ac):
              $isSpecialist = in_array((int)$ac['id'], $matching_ids);
              $specialistBadge = $isSpecialist ? ' (★ متخصص بنفس الخدمة)' : '';
            ?>
              <option value="<?= $ac['id'] ?>" data-specialist="<?= $isSpecialist ? '1' : '0' ?>">
                <?= e($ac['name']) ?> — <?= e($ac['specialty'] ?? 'عام') ?> · ⭐ <?= number_format((float)$ac['rating'], 1) ?><?= $specialistBadge ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color:var(--text-secondary);font-size:11px;margin-top:4px;display:block">
            الأكاديميون الموسومون بـ (★ متخصص بنفس الخدمة) يقدّمون نفس نوع خدمة هذا الطلب.
          </small>
        </div>

        <!-- Select Reason -->
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">سبب الاستبدال <span style="color:#ef4444">*</span></label>
          <select name="replacement_reason" id="replace_reason" class="form-control" required style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px">
            <option value="no_response">عدم الرد أو التجاوب بعد الإسناد</option>
            <option value="apology">اعتذار لظرف طارئ أو شخصي</option>
            <option value="delay">تأخر غير مبرر في تسليم العمل</option>
            <option value="quality">عدم مطابقة معايير الجودة المطلوبة</option>
            <option value="student_request">بناءً على طلب الطالب</option>
            <option value="other">سبب إداري آخر</option>
          </select>
        </div>

        <!-- Replacement Note -->
        <div class="form-group" style="margin-bottom:16px">
          <label class="form-label" style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">ملاحظات إضافية (تظهر في السجل الإداري)</label>
          <textarea name="replacement_note" id="replace_note" rows="3" class="form-control" placeholder="اكتب تفاصيل أو أسباب إضافية لتوثيقها بالسجل..." style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-main);color:var(--text-primary);font-size:13px;resize:vertical"></textarea>
        </div>

        <div style="background:rgba(245,158,11,0.08);border:1px dashed #f59e0b;padding:10px 12px;border-radius:8px;margin-bottom:16px;font-size:12px;color:#92400e;line-height:1.5">
          ℹ️ <strong>تنبيه:</strong> سيتم إلغاء تكليف الأكاديمي السابق وإسناد المهمة للأكاديمي البديل، وتحديث صلاحيات المحادثة، وإرسال إشعارات فورية وبريدية للأطراف المعنية.
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeReplaceAcademicModal()">إلغاء</button>
          <button type="submit" class="btn btn-warning btn-sm" style="background:#f59e0b;color:#fff;border:none;font-weight:700">✓ تأكيد الاستبدال والإسناد</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/main.js"></script>
  <script>
    // التبديل بين قائمة المتخصصين وقائمة جميع الأكاديميين عند الإسناد
    function toggleAllAcademics() {
      const matchList = document.getElementById('matchingList');
      const allList = document.getElementById('allAcademicsList');
      const btn = document.getElementById('toggleAllBtn');
      if (!matchList || !allList || !btn) return;

      const showAll = (allList.style.display === 'none' || allList.style.display === '');
      allList.style.display = showAll ? 'flex' : 'none';
      matchList.style.display = showAll ? 'none' : 'flex';
      btn.textContent = showAll ? '🎯 الأكاديميون المتخصصون فقط' : '👥 عرض كل الأكاديميين';
      btn.classList.toggle('btn-primary', showAll);
      btn.classList.toggle('btn-outline', !showAll);
    }

    function openReplaceAcademicModal(targetId, targetName) {
      document.getElementById('replace_target_academic_id').value = targetId;
      document.getElementById('replace_target_academic_name').textContent = targetName;

      const select = document.getElementById('replace_new_academic_id');
      const activeIds = <?= json_encode(array_map('intval', $assigned_academic_ids)) ?>;

      for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (!opt.value) continue;
        const val = parseInt(opt.value, 10);
        const cleanText = opt.textContent.replace(' (مكلّف حالياً)', '');
        if (val === parseInt(targetId, 10) || activeIds.includes(val)) {
          opt.disabled = true;
          opt.textContent = cleanText + ' (مكلّف حالياً)';
        } else {
          opt.disabled = false;
          opt.textContent = cleanText;
        }
      }
      select.value = '';
      document.getElementById('replace_note').value = '';
      document.getElementById('replace_reason').value = 'no_response';
      document.getElementById('replaceAcademicModal').style.display = 'flex';
    }

    function closeReplaceAcademicModal() {
      document.getElementById('replaceAcademicModal').style.display = 'none';
    }

    document.getElementById('replaceAcademicModal')?.addEventListener('click', function(e) {
      if (e.target === this) closeReplaceAcademicModal();
    });
  </script>
</body>

</html>