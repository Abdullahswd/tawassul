<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$order_id = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$order = getOrderById($order_id);

if (!$order || $order['student_id'] !== $user['id']) {
    header('Location: orders.php');
    exit;
}

// إشعار الدفع المرفق مع الطلب (إن وُجد)
$orderReceipt = (float)($order['amount'] ?? 0) > 0 ? getReceiptForOrder($order_id) : null;
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// Fetch assigned team of academics
$assignedTeam = getAssignedAcademics($order_id);
$db = db();

// Handle AJAX message send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $content  = trim($_POST['content'] ?? '');
    $conv_id  = (int)($_POST['conv_id'] ?? 0);
    $isAjax   = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

    $sent = false;
    if (($content !== '' || !empty($_FILES['chat_file']['name'])) && $conv_id) {
        $check = $db->prepare('SELECT id, order_id FROM conversations WHERE id = ? AND student_id = ? LIMIT 1');
        $check->execute([$conv_id, $user['id']]);
        $cRow = $check->fetch();
        if ($cRow) {
            $fileInfo = saveChatAttachmentUpload($_FILES['chat_file'] ?? null);
            sendMessage($conv_id, $user['id'], 'student', $content, $fileInfo);
            $sent = true;

            // Notify assigned academics
            $team = getAssignedAcademics($cRow['order_id']);
            foreach ($team as $member) {
                createNotification(
                    $member['id'],
                    'academic',
                    'رسالة جديدة من الطالب ' . $user['name'],
                    ($fileInfo ? 'أرفق ' : 'أرسل ') . 'الطالب رسالة في محادثة الطلب #' . $order['order_number'],
                    '💬',
                    'academics/order-details.php?id=' . $order_id
                );
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $sent], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: order-details.php?id=' . $order_id . '#orderChat');
    exit;
}

// Handle Receipt Re-upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reupload_receipt'])) {
    $pay_method = (int)($_POST['pay_method'] ?? 0);
    $msg = trim($_POST['reupload_message'] ?? '');
    
    // Fetch old receipt to fallback
    $rcStmt = $db->prepare("SELECT id, receipt_path, method_id, method_label FROM payment_receipts WHERE order_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    $rcStmt->execute([$order_id, $user['id']]);
    $oldRc = $rcStmt->fetch();
    
    if ($oldRc) {
        $fileName = $oldRc['receipt_path'];
        $finalMethodId = $oldRc['method_id'] ?? 0;
        $finalMethodLabel = $oldRc['method_label'] ?? '';
        
        if ($pay_method > 0) {
            $finalMethodId = $pay_method;
            $finalMethodLabel = getPaymentMethodLabel($pay_method);
        }

        if (isset($_FILES['new_receipt']) && $_FILES['new_receipt']['error'] === UPLOAD_ERR_OK) {
            $fileExt = strtolower(pathinfo($_FILES['new_receipt']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg','jpeg','png','pdf'])) {
                $newFile = uniqid('receipt_', true) . '.' . $fileExt;
                if (move_uploaded_file($_FILES['new_receipt']['tmp_name'], __DIR__ . '/../uploads/receipts/' . $newFile)) {
                    $fileName = 'uploads/receipts/' . $newFile;
                }
            }
        }
        
        $db->prepare("UPDATE payment_receipts SET receipt_path = ?, method_id = ?, method_label = ?, status = 'pending', rejection_reason = NULL, student_note = ? WHERE id = ?")
           ->execute([$fileName, $finalMethodId, $finalMethodLabel, $msg, $oldRc['id']]);
           
        $convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? AND student_id = ? LIMIT 1");
        $convStmt->execute([$order_id, $user['id']]);
        $cId = (int)$convStmt->fetchColumn();
        if ($msg !== '' && $cId) {
            sendMessage($cId, $user['id'], 'student', $msg, null);
        }
        
        createNotification(1, 'admin', 'إعادة رفع أو تصحيح إيصال دفع 🧾', 'قام الطالب ' . $user['name'] . ' بتصحيح إيصال دفع للطلب #' . $order['order_number'], '🧾', 'admin/pages/order-details.php?id=' . $order['order_number']);
        header("Location: order-details.php?id=$order_id");
        exit;
    }
}

// Handle Order Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $reason = trim($_POST['cancellation_reason'] ?? '');
    $old_status = $order['status'];
    
    if (in_array($order['status'], ['new', 'pending_assignment', 'assigned', 'accepted'])) {
        // Direct cancel (100% refund)
        $db->prepare("UPDATE orders SET status = 'cancelled', previous_status = ?, cancellation_reason = ? WHERE id = ?")->execute([$old_status, $reason, $order_id]);
        
        // Check if there was any money paid (from receipts)
        $rcStmt = $db->prepare("SELECT id FROM payment_receipts WHERE order_id = ? AND status IN ('pending', 'approved') LIMIT 1");
        $rcStmt->execute([$order_id]);
        if ($rcStmt->fetchColumn()) {
            $db->prepare("INSERT INTO refund_requests (order_id, student_id, amount, status) VALUES (?, ?, ?, 'pending')")
               ->execute([$order_id, $user['id'], $order['amount']]);
        }
        
        createNotification(1, 'admin', 'تم إلغاء طلب ❌', 'قام الطالب بإلغاء الطلب #' . $order['order_number'] . ' (استرداد 100%)', '❌', 'admin/pages/order-details.php?id=' . $order['order_number']);
        
        // Notify Academics
        $academics = $db->prepare("SELECT DISTINCT academic_id FROM order_assignments WHERE order_id = ? UNION SELECT academic_id FROM orders WHERE id = ? AND academic_id IS NOT NULL");
        $academics->execute([$order_id, $order_id]);
        foreach ($academics->fetchAll(PDO::FETCH_COLUMN) as $acid) {
            createNotification($acid, 'academic', 'إلغاء الطلب 🚫', 'قام الطالب بإلغاء الطلب #' . $order['order_number'] . '، نرجو التوقف عن العمل عليه.', '🚫', 'academics/academic-order-details.php?id=' . $order['order_number']);
        }
        
        header("Location: order-details.php?id=$order_id");
        exit;
    } elseif (in_array($order['status'], ['in_progress', 'revision'])) {
        // Request cancel (Partial refund, admin review)
        $db->prepare("UPDATE orders SET status = 'cancellation_requested', previous_status = ?, cancellation_reason = ? WHERE id = ?")->execute([$old_status, $reason, $order_id]);
        createNotification(1, 'admin', 'طلب إلغاء لطلب قيد التنفيذ ⚠️', 'طلب الطالب إلغاء الطلب #' . $order['order_number'] . ' الذي بدأ العمل عليه.', '⚠️', 'admin/pages/order-details.php?id=' . $order['order_number']);
        
        // Notify Academics
        $academics = $db->prepare("SELECT DISTINCT academic_id FROM order_assignments WHERE order_id = ? UNION SELECT academic_id FROM orders WHERE id = ? AND academic_id IS NOT NULL");
        $academics->execute([$order_id, $order_id]);
        foreach ($academics->fetchAll(PDO::FETCH_COLUMN) as $acid) {
            createNotification($acid, 'academic', 'طلب إلغاء من الطالب ⚠️', 'طلب الطالب إلغاء الطلب #' . $order['order_number'] . '، نرجو التوقف عن العمل حتى يتم تقييم الطلب من الإدارة.', '⚠️', 'academics/academic-order-details.php?id=' . $order['order_number']);
        }

        header("Location: order-details.php?id=$order_id");
        exit;
    }
}

// Find or Create active conversation
$convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? AND student_id = ? LIMIT 1");
$convStmt->execute([$order_id, $user['id']]);
$convId = (int)$convStmt->fetchColumn();

if (!$convId && $order['academic_id']) {
    $convId = getOrCreateConversation($order_id, $user['id'], $order['academic_id']);
}

// Fetch messages if conv exists
$messages = [];
if ($convId) {
    $messages = getConversationMessages($convId);
    $db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ?')->execute([$convId, 'academic']);
}

// مرفقات الطلب
$attStmt = $db->prepare("SELECT * FROM order_attachments WHERE order_id = ? ORDER BY id ASC");
$attStmt->execute([$order_id]);
$orderAttachments = $attStmt->fetchAll();

$badge = orderStatusLabel($order['status']);
?>
<?php
$extraCss = [
  '<style>
    /* Timeline styles */
    .timeline {
      display: flex;
      flex-direction: column;
      position: relative;
      padding-right: 20px;
    }
    .timeline::before {
      content: "";
      position: absolute;
      top: 0; bottom: 0; right: 0;
      width: 2px;
      background: var(--border-color);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 32px;
      padding-right: 24px;
    }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-item::before {
      content: "";
      position: absolute;
      right: -25px; top: 0px;
      width: 12px; height: 12px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      z-index: 1;
    }
    .timeline-item.active::before {
      border-color: var(--primary);
      background: var(--primary);
      box-shadow: 0 0 0 4px var(--primary-light);
    }
    .timeline-item.done::before {
      border-color: var(--success);
      background: var(--success);
    }
    .ti-title { font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
    .ti-desc { font-size: 13px; color: var(--text-secondary); }
    .ti-date { display: inline-block; font-size: 11px; padding: 2px 8px; background: var(--bg-hover); border-radius: 4px; margin-top: 8px; font-weight: 700; }
    /* Chat CSS */
    .chat-main { display: flex; flex-direction: column; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: var(--radius-md); height: 500px; margin-top: 16px; }
    .chat-messages { flex: 1 1 auto; min-height: 0; padding: 16px; overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 12px; }
    .msg { max-width: 85%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; flex-shrink: 0; }
    .msg-received { background: var(--bg-card); border: 1px solid var(--border-color); align-self: flex-start; border-top-right-radius: 4px; }
    .msg-sent { background: var(--primary); color: white; align-self: flex-end; border-top-left-radius: 4px; }
    .msg-time { font-size: 10px; color: var(--text-muted); margin-top: 4px; text-align: right; }
    .msg-sent .msg-time { color: rgba(255,255,255,0.7); text-align: left; }
    .chat-input-area { padding: 12px 16px; background: var(--bg-card); border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; border-bottom-left-radius: var(--radius-md); border-bottom-right-radius: var(--radius-md); }
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
    .chat-messages::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 9999px; }
    .chat-messages::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
  </style>',
];
$pageTitle  = 'تفاصيل الطلب ' . $order['order_number'];
$activePage = 'orders';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <!-- Page Content -->
      <div class="content-wrap" style="max-width:1200px;margin:0 auto">
        
        <div class="mb-6">
          <a href="orders.php" class="text-sm font-bold transition hover:opacity-80" style="color:var(--text-secondary)">← العودة لقائمة الطلبات</a>
        </div>

        <?php if ($orderReceipt): ?>
          <?php if ($orderReceipt['status'] === 'rejected'): ?>
            <div class="flex flex-col gap-4 rounded-xl p-6 mb-6 border bg-red-50 border-red-300 shadow-sm">
              <div class="flex flex-wrap items-start gap-4">
                <span style="font-size:32px">❌</span>
                <div class="flex-1 min-w-[200px]">
                  <strong class="block text-lg text-red-800 mb-1">تم رفض إشعار الدفع</strong>
                  <p class="text-sm text-red-600 mb-2">السبب: <?= e($orderReceipt['rejection_reason'] ?: 'لم يتم توضيح السبب. يرجى التأكد من الإيصال.') ?></p>
                  <span class="text-xs text-red-500">
                    المبلغ المطلوب: <?= number_format((float)$orderReceipt['amount'], 2) ?> ر.س
                  </span>
                </div>
                <a href="<?= e($appBase . '/' . $orderReceipt['receipt_path']) ?>" target="_blank" class="btn btn-outline text-sm border-red-300 text-red-700 hover:bg-red-100">عرض الإشعار المرفوض 👁️</a>
              </div>
              
              <hr class="border-red-200 my-2">
              
              <form method="POST" action="order-details.php?id=<?= $order_id ?>" enctype="multipart/form-data" class="bg-white p-5 rounded-xl border border-red-100">
                <h4 class="font-bold text-slate-800 mb-4">إعادة رفع الإيصال</h4>
                <input type="hidden" name="reupload_receipt" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">طريقة الدفع (إذا أردت تغييرها)</label>
                    <select name="pay_method" class="form-input w-full text-sm">
                      <option value="">-- الإبقاء على الحساب السابق --</option>
                      <?php
                      $platform_methods = $db->query("SELECT * FROM platform_payment_methods WHERE is_active = 1")->fetchAll();
                      foreach ($platform_methods as $m):
                          $icon = $m['account_type'] === 'wallet' ? '👛' : '🏦';
                      ?>
                        <option value="<?= $m['id'] ?>"><?= $icon . ' ' . e($m['account_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">الإيصال الجديد (اختياري)</label>
                    <input type="file" name="new_receipt" accept=".jpg,.jpeg,.png,.pdf" class="form-input w-full text-sm">
                  </div>
                </div>
                
                <div class="mb-4">
                  <label class="block text-xs font-bold text-slate-600 mb-2">رسالة توضيحية للإدارة (اختياري)</label>
                  <textarea name="reupload_message" class="form-input w-full text-sm" rows="2" placeholder="اكتب ملاحظاتك بخصوص الدفع..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary w-full shadow-sm text-sm py-2">رفع الإيصال وإرسال للتدقيق 🚀</button>
              </form>
            </div>
          <?php else: ?>
            <div class="flex flex-wrap items-center gap-4 rounded-xl p-4 mb-6 border <?= $orderReceipt['status'] === 'approved' ? 'bg-green-100 border-green-300' : 'bg-yellow-100 border-yellow-300' ?>">
              <span style="font-size:26px">🧾</span>
              <div class="flex-1 min-w-[200px]">
                <strong class="block text-sm">
                  إشعار الدفع <?= $orderReceipt['status'] === 'approved' ? 'مُعتمد ✅' : 'قيد المراجعة ⏳' ?>
                </strong>
                <span class="text-xs" style="color:var(--text-secondary)">
                  المبلغ: <?= number_format((float)$orderReceipt['amount'], 2) ?> ر.س
                  <?= $orderReceipt['method'] ? ' • ' . e($orderReceipt['method']) : '' ?>
                  • <?= formatDate($orderReceipt['created_at']) ?>
                </span>
              </div>
              <a href="<?= e($appBase . '/' . $orderReceipt['receipt_path']) ?>" target="_blank" class="btn btn-outline text-sm">عرض الإشعار 👁️</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Left Column -->
          <div class="lg:col-span-2 flex flex-col gap-6">
            
            <!-- Order Header Card -->
            <div class="card">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                <div>
                  <span style="font-size:12px;color:var(--text-secondary);font-weight:700">رقم الطلب: <?= e($order['order_number']) ?></span>
                  <h1 class="h2" style="margin-top:4px"><?= e($order['service_icon']) ?> <?= e($order['service_name']) ?></h1>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                  <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                  <?php if (in_array($order['status'], ['new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision'])): ?>
                    <script>
                    function confirmCancelOrder(form) {
                        var warnMsg = <?= in_array($order['status'], ['in_progress', 'revision']) ? "'الطلب قيد التنفيذ حالياً. هل أنت متأكد من تقديم طلب إلغاء؟ (سيتم تقييم الجهد المبذول وخصمه من المبلغ)'" : "'هل أنت متأكد أنك تريد إلغاء هذا الطلب واسترداد المبلغ 100%؟'" ?>;
                        if (!confirm(warnMsg)) return false;
                        var reason = prompt("الرجاء كتابة سبب الإلغاء (مطلوب):");
                        if (reason === null || reason.trim() === "") {
                            alert("يجب كتابة سبب الإلغاء للمتابعة.");
                            return false;
                        }
                        var rInput = document.createElement("input");
                        rInput.type = "hidden";
                        rInput.name = "cancellation_reason";
                        rInput.value = reason;
                        form.appendChild(rInput);
                        return true;
                    }
                    </script>
                    <form method="POST" style="margin:0" onsubmit="return confirmCancelOrder(this);">
                      <input type="hidden" name="action" value="cancel_order">
                      <button type="submit" class="text-[11px] bg-red-50 text-red-600 border border-red-200 px-3 py-1 rounded-full font-bold hover:bg-red-100 transition">
                        <?= in_array($order['status'], ['in_progress', 'revision']) ? 'تقديم طلب إلغاء ⚠️' : 'إلغاء الطلب ❌' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl border border-[var(--border-color)]" style="background:var(--bg-body)">
                <div>
                  <div style="font-size:12px;color:var(--text-secondary)">التخصص المطلوب:</div>
                  <div style="font-size:14px;font-weight:700;color:var(--text-primary);margin-top:2px"><?= e($order['specialty']) ?></div>
                </div>
                <div>
                  <div style="font-size:12px;color:var(--text-secondary)">المبلغ الإجمالي:</div>
                  <div style="font-size:16px;font-weight:900;color:var(--primary);margin-top:2px"><?= formatMoney($order['amount']) ?></div>
                </div>
              </div>
            </div>

            <!-- Description Card -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:16px">تفاصيل ومتطلبات الخدمة</h2>
              <div style="font-size:14px;color:var(--text-secondary);line-height:1.7;white-space:pre-line">
                <?= e($order['description'] ?: 'لا توجد تفاصيل إضافية مكتوبة.') ?>
              </div>
            </div>

            <!-- Attachments -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:20px">الملفات المرفوعة</h2>
              <?php if (empty($orderAttachments)): ?>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">لا توجد ملفات مرفوعة حالياً للطلب.</p>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                  <?php foreach ($orderAttachments as $att): ?>
                    <div style="display:flex;align-items:center;gap:12px;background:var(--bg-body);border:1px solid var(--border-color);border-radius:12px;padding:12px 14px">
                      <span style="font-size:22px">📎</span>
                      <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($att['file_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-secondary)">
                          <?= number_format((float)$att['file_size'] / 1024 / 1024, 2) ?> MB
                          • <?= $att['uploaded_by'] === 'student' ? 'من الطالب' : ($att['uploaded_by'] === 'academic' ? 'من الأكاديمي' : 'من الإدارة') ?>
                          <?= !empty($att['uploaded_at']) ? '• ' . formatDate($att['uploaded_at']) : '' ?>
                        </div>
                      </div>
                      <a href="<?= e($appBase . '/' . $att['file_path']) ?>" target="_blank" class="btn btn-outline" style="font-size:12px;flex-shrink:0">عرض 👁️</a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Draggable file upload UI -->
              <div style="border:2px dashed var(--border-color);border-radius:var(--radius-sm);padding:24px;text-align:center;background:var(--bg-body);margin-top:20px;cursor:pointer">
                <h4 style="font-weight:700;margin-bottom:8px;color:var(--primary)">إضافة ملفات جديدة ➕</h4>
                <p style="font-size:12px;color:var(--text-secondary)">اسحب وأفلت الملفات هنا أو اضغط للاختيار</p>
              </div>
            </div>

          </div>

          <!-- Right Column -->
          <div class="lg:col-span-1 flex flex-col gap-6">

          <!-- Academic Profile / Team -->
            <div class="card" style="padding:24px">
              <?php 
              $acceptedTeam = array_filter($assignedTeam, fn($m) => $m['assignment_status'] === 'accepted');
              $pendingTeam  = array_filter($assignedTeam, fn($m) => $m['assignment_status'] === 'assigned');
              ?>
              <?php if (!empty($acceptedTeam)): ?>
                <h3 class="h3" style="margin-bottom:16px">
                  🎓 <?= count($acceptedTeam) > 1 ? 'فريق الأكاديميين المُعيَّن' : 'الأكاديمي المُعيَّن' ?>
                </h3>
                <?php foreach ($acceptedTeam as $member): ?>
                  <div style="display:flex;align-items:center;gap:14px;background:var(--bg-body);border-radius:12px;padding:14px;margin-bottom:10px;border:1px solid var(--border-color)">
                    <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--secondary),var(--primary));color:white;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;flex-shrink:0">
                      <?= e($member['avatar_initials'] ?: mb_substr($member['name'], 0, 1, 'UTF-8')) ?>
                    </div>
                    <div style="flex:1">
                      <div style="font-weight:700;font-size:14px;color:var(--text-primary)"><?= e($member['name']) ?></div>
                      <div style="font-size:12px;color:var(--text-secondary)"><?= e($member['specialty']) ?> · <?= e($member['degree']) ?></div>
                      <div style="font-size:12px;color:#f59e0b;font-weight:600">⭐ <?= number_format((float)$member['rating'], 1) ?> تقييم</div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if ($convId): ?>
                  <a href="#orderChat" class="btn btn-outline" style="width:100%;margin-top:8px;text-align:center;font-weight:bold;">
                    ↓ الانتقال للمحادثة المباشرة ↓
                  </a>
                <?php else: ?>
                  <a href="#orderChat" class="btn btn-outline" style="width:100%;margin-top:8px;text-align:center;font-weight:bold;">
                    ↓ المحادثة غير متاحة بعد ↓
                  </a>
                <?php endif; ?>

              <?php elseif (!empty($pendingTeam)): ?>
                <div style="text-align:center;padding:8px 0 16px">
                  <div style="width:64px;height:64px;border-radius:50%;background:rgba(245,158,11,0.1);border:2px solid #f59e0b;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px">⏳</div>
                  <h3 class="h3" style="margin-bottom:6px;color:var(--text-primary)">بانتظار رد الأكاديمي</h3>
                  <p style="font-size:13px;color:var(--text-secondary);line-height:1.5">
                    تم إرسال طلبك إلى <?= count($pendingTeam) ?> أكاديمي/أكاديميين. بانتظار قبول أحدهم للعمل عليه.
                  </p>
                </div>
              <?php else: ?>
                <div style="text-align:center;padding:8px 0 16px">
                  <div style="width:64px;height:64px;border-radius:50%;background:var(--bg-body);border:1.5px dashed var(--border-color);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px">⏳</div>
                  <h3 class="h3" style="margin-bottom:6px;color:var(--text-secondary)">قيد التعيين</h3>
                  <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;line-height:1.5">يقوم فريق الدعم بمراجعة الطلب لربطك بأفضل أكاديمي متخصص.</p>
                  <button class="btn btn-outline" style="width:100%" disabled>بانتظار الأكاديمي...</button>
                </div>
              <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="card" style="padding:24px">
              <h3 class="h3" style="margin-bottom:24px">متابعة سير الطلب</h3>
              
              <div class="timeline">
                <div class="timeline-item done">
                  <div class="ti-title">تم تقديم الطلب</div>
                  <div class="ti-desc">تم تسجيل طلبك بنجاح على المنصة.</div>
                  <div class="ti-date"><?= formatDate($order['created_at']) ?></div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['assigned', 'accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'pending_assignment') ? 'active' : '') ?>">
                  <div class="ti-title">مراجعة الإدارة</div>
                  <div class="ti-desc">
                    <?= ($order['status'] === 'pending_assignment') ? 'جاري مراجعة الطلب وتعيين الأكاديمي المناسب.' : 'تمت مراجعة الطلب من قِبل الإدارة.' ?>
                  </div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'assigned') ? 'active' : '') ?>">
                  <div class="ti-title">الربط والتعيين</div>
                  <div class="ti-desc">
                    <?= $order['academic_id'] ? 'تم تعيين الأكاديمي وقبول العمل.' : (($order['status'] === 'assigned') ? 'تم إرسال الطلب للأكاديمي، بانتظار القبول.' : 'جاري البحث عن أكاديمي متخصص.') ?>
                  </div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : (in_array($order['status'], ['in_progress', 'revision']) ? 'active' : '') ?>">
                  <div class="ti-title">قيد التنفيذ</div>
                  <div class="ti-desc">يقوم الأكاديمي حالياً بالعمل على الملفات.</div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : '' ?>">
                  <div class="ti-title">اكتمال وتسليم</div>
                  <div class="ti-desc">مراجعة الملفات النهائية وتنزيلها.</div>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Chat Section (Moved to Bottom for larger width) -->
        <div id="orderChat" class="mt-8 mb-8" style="max-width: 900px; margin-left: auto; margin-right: auto;">
          <?php if ($convId): ?>
            <!-- Embedded Chat -->
            <div class="chat-main shadow-lg" style="height: 600px; border-radius: 20px; overflow: hidden; border: 1px solid var(--border-color);">
              <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);font-weight:900;font-size:16px;background:var(--bg-card);display:flex;align-items:center;gap:12px;">
                <span style="font-size:24px">💬</span>
                <?= count($acceptedTeam) > 1 ? 'محادثة فريق العمل' : 'التواصل المباشر مع الأكاديمي' ?>
              </div>
              <div class="chat-messages" id="chatMessages" style="padding: 24px;">
                <?php if (empty($messages)): ?>
                  <div style="text-align:center;color:var(--text-muted);font-size:14px;padding:48px">
                    ابدأ المحادثة مع الأكاديمي الآن 👋
                  </div>
                <?php else: ?>
                  <?php 
                  $lastDate = '';
                  foreach ($messages as $msg): 
                    $msgDate = date('Y-m-d', strtotime($msg['sent_at']));
                    $isSent  = ($msg['sender_type'] === 'student');
                    if ($msgDate !== $lastDate):
                      $lastDate = $msgDate;
                  ?>
                    <div class="chat-date-sep" style="text-align:center;font-size:12px;color:var(--text-muted);margin:8px 0;font-weight:700"><?= date('d/m/Y', strtotime($msg['sent_at'])) ?></div>
                  <?php endif; ?>
                    <div class="msg <?= $isSent ? 'msg-sent' : 'msg-received' ?>" data-msg-id="<?= (int)$msg['id'] ?>" data-date="<?= $msgDate ?>" style="font-size:14px;padding:12px 16px;max-width:75%;">
                      <?php if (!$isSent): ?>
                        <div style="font-size:11px;font-weight:800;color:var(--primary);margin-bottom:4px">
                          <?= e($msg['sender_name'] ?? 'الأكاديمي') ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($isSent && !empty($msg['file_path'])): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;justify-content:flex-end">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.18);color:#fff;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none">
                            <span style="font-size:16px">📎</span>
                            <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                          </a>
                        </div>
                      <?php elseif (!$isSent && !empty($msg['file_path'])): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;justify-content:flex-start">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none">
                            <span style="font-size:16px">📎</span>
                            <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($msg['content'])): ?>
                        <?= nl2br(e($msg['content'])) ?>
                      <?php endif; ?>
                      <div class="msg-time"><?= date('H:i', strtotime($msg['sent_at'])) ?> <?= $isSent ? '✓✓' : '' ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <form method="POST" action="order-details.php?id=<?= $order_id ?>" class="chat-input-area" id="chatForm" enctype="multipart/form-data" style="padding:16px 20px;">
                <input type="hidden" name="send_message" value="1">
                <input type="hidden" name="conv_id" value="<?= $convId ?>">
                <label title="إرفاق ملف" style="cursor:pointer;font-size:24px;color:var(--text-secondary);flex-shrink:0;transition:color 0.2s" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-secondary)'">
                  📎
                  <input type="file" name="chat_file" id="chatFileInput" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
                </label>
                <span id="chatFileName" style="display:none;font-size:12px;color:var(--text-secondary);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0"></span>
                <input type="text" name="content" id="chatInput" placeholder="اكتب رسالتك للأكاديمي هنا..." class="form-input" style="border-radius:30px;padding:12px 20px;flex:1;font-size:14px;border:1px solid var(--border-color);" autocomplete="off">
                <button type="submit" class="btn btn-primary" style="border-radius:50%;width:44px;height:44px;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <span style="transform:rotate(180deg);font-size:18px">➔</span>
                </button>
              </form>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:32px;background:var(--bg-body);border-radius:var(--radius-md);border:1px solid var(--border-color);">
              <div style="font-size:48px;margin-bottom:16px">💬</div>
              <h3 style="font-size:18px;font-weight:800;margin-bottom:8px">المحادثة غير متاحة بعد</h3>
              <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px">بمجرد تعيين الأكاديمي للعمل على طلبك، ستفتح المحادثة تلقائياً هنا.</p>
              <a href="order-details.php?id=<?= $order_id ?>" class="btn btn-primary">تحديث الصفحة</a>
            </div>
          <?php endif; ?>
        </div>

      </div>
<?php
$extraJs = ob_start() ? '' : '';
ob_start();
$lastMsgIdPhp = 0;
if (isset($messages)) {
    foreach ($messages as $m) { $lastMsgIdPhp = max($lastMsgIdPhp, (int)$m['id']); }
}
?>
  <script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

    const convId = <?= (int)($convId ?? 0) ?>;
    const APP_BASE = <?= json_encode($appBase ?? '') ?>;
    let lastMsgId = <?= (int)$lastMsgIdPhp ?>;
    let lastDate = (() => {
      if(!chatMessages) return '';
      const msgs = chatMessages.querySelectorAll('.msg[data-date]');
      return msgs.length ? msgs[msgs.length - 1].dataset.date : '';
    })();
    let sending = false;

    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function scrollToBottom(smooth) { if (chatMessages) chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); }

    function appendMessage(m) {
      if(!chatMessages) return;
      const placeholder = chatMessages.querySelector('div[style*="text-align:center"]');
      if (placeholder && !chatMessages.querySelector('.msg')) placeholder.remove();

      const mDate = (m.sent_at || '').substring(0, 10);
      const mTime = (m.sent_at || '').substring(11, 16) || '';
      const isSent = m.sender_type === 'student';

      if (mDate && mDate !== lastDate) {
        lastDate = mDate;
        const sep = document.createElement('div');
        sep.className = 'chat-date-sep';
        sep.style.cssText = 'text-align:center;font-size:11px;color:var(--text-muted);margin:6px 0';
        const parts = mDate.split('-');
        sep.textContent = parts[2] + '/' + parts[1] + '/' + parts[0];
        chatMessages.appendChild(sep);
      }

      const wrap = document.createElement('div');
      wrap.className = 'msg ' + (isSent ? 'msg-sent' : 'msg-received');
      wrap.dataset.msgId = m.id;
      wrap.dataset.date = mDate || '';

      if (!isSent) {
        const name = document.createElement('div');
        name.style.cssText = 'font-size:10px;font-weight:700;color:var(--primary);margin-bottom:2px';
        name.textContent = m.sender_name || 'الأكاديمي';
        wrap.appendChild(name);
      }

      const body = document.createElement('div');
      if (m.file_path) {
        const fileRow = document.createElement('div');
        fileRow.style.cssText = 'margin-bottom:' + (m.content ? '4px' : '0') + ';display:flex;align-items:center';
        fileRow.style.justifyContent = isSent ? 'flex-end' : 'flex-start';
        const aLink = document.createElement('a');
        aLink.href = APP_BASE + '/' + m.file_path;
        aLink.target = '_blank';
        aLink.download = '';
        aLink.style.cssText = 'display:inline-flex;align-items:center;gap:6px;border-radius:8px;padding:6px 10px;font-size:11px;font-weight:700;text-decoration:none;max-width:140px';
        if (isSent) { aLink.style.background = 'rgba(255,255,255,0.18)'; aLink.style.color = '#fff'; }
        else { aLink.style.background = 'var(--primary-light)'; aLink.style.color = 'var(--primary)'; }
        const ic = document.createElement('span'); ic.textContent = '📎';
        const nm = document.createElement('span'); nm.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
        nm.textContent = m.file_name || 'ملف';
        aLink.appendChild(ic); aLink.appendChild(nm); fileRow.appendChild(aLink); body.appendChild(fileRow);
      }
      if (m.content) {
        const textNode = document.createElement('div');
        textNode.innerHTML = escapeHtml(m.content).replace(/\n/g, '<br>');
        body.appendChild(textNode);
      }
      if (body.childNodes.length) wrap.appendChild(body);

      const time = document.createElement('div');
      time.className = 'msg-time';
      time.textContent = mTime + (isSent ? ' ✓✓' : '');
      wrap.appendChild(time);
      chatMessages.appendChild(wrap);
    }

    async function pollChat() {
      if (document.hidden || !convId || sending) return;
      try {
        const res = await fetch('ajax/chat-poll.php?conv=' + convId + '&after=' + lastMsgId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        if (Array.isArray(data.messages) && data.messages.length) {
          const nearBottom = chatMessages ? (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < 100 : false;
          data.messages.forEach(appendMessage);
          lastMsgId = data.last_id || lastMsgId;
          if (nearBottom) scrollToBottom(true);
        }
      } catch (e) {}
    }
    if (convId) {
      setInterval(pollChat, 5000);
      document.addEventListener('visibilitychange', function () { if (!document.hidden) pollChat(); });
    }

    const chatForm = document.getElementById('chatForm');
    const chatFileInput = document.getElementById('chatFileInput');
    const chatFileName = document.getElementById('chatFileName');

    if (chatFileInput) {
      chatFileInput.addEventListener('change', function () {
        if (chatFileName) {
          if (chatFileInput.files.length) {
            chatFileName.textContent = '📄 ' + chatFileInput.files[0].name;
            chatFileName.style.display = '';
          } else {
            chatFileName.style.display = 'none';
          }
        }
      });
    }

    if (chatForm) {
      chatForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (sending) return;
        const input = document.getElementById('chatInput');
        const content = (input.value || '').trim();
        const hasFile = chatFileInput && chatFileInput.files.length > 0;
        if (!content && !hasFile) return;
        sending = true;
        try {
          const fd = new FormData(chatForm);
  fd.append('csrf_token', csrfTokenJs);
          const res = await fetch(chatForm.getAttribute('action'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          if (res.ok) {
            input.value = '';
            if (chatFileInput) chatFileInput.value = '';
            if (chatFileName) chatFileName.style.display = 'none';
            input.focus();
            sending = false; // Allow pollChat to run
            await pollChat();
          }
        } catch (err) { chatForm.submit(); } finally { sending = false; }
      });
    }
  </script>
<?php
$extraJs = ob_get_clean();
require __DIR__ . '/partials/footer.php';
?>
