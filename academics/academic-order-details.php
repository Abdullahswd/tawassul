  <?php
  require_once __DIR__ . '/../config/auth.php';
  require_once __DIR__ . '/../config/functions.php';

  requireAcademic();
  $academicId = $_SESSION['academic_id'];
  $academicData = getAcademicById($academicId);

  $db = db();
  $orderNumber = $_GET['id'] ?? '';
  if (!$orderNumber) {
      header("Location: academic-orders.php");
      exit;
  }

  // 1. Fetch Order Details
  $stmt = $db->prepare("
      SELECT o.*, s.name AS service_name, p.name AS package_name, u.name AS student_name, u.avatar_initials AS student_avatar
      FROM orders o
      LEFT JOIN services s ON o.service_id = s.id
      LEFT JOIN packages p ON o.package_id = p.id
      LEFT JOIN users u ON o.student_id = u.id
      WHERE o.order_number = ? AND (o.academic_id = ? OR (o.id IN (SELECT order_id FROM order_assignments WHERE academic_id = ?)))
  ");
  $stmt->execute([$orderNumber, $academicId, $academicId]);
  $order = $stmt->fetch();

  if (!$order) {
      die("الطلب غير موجود أو لا تملك صلاحية الوصول إليه.");
  }

  $orderId = $order['id'];
  $studentId = $order['student_id'];
  $appBase = rtrim(preg_replace('#/academics/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
  ensureMessageFileColumns();

  // 1.1 Fetch Assigned Team of Academics
  $assignedTeam = getAssignedAcademics($orderId);
  $myAssignmentStatus = 'assigned';
  if ($order['academic_id'] == $academicId && $order['status'] !== 'assigned') {
      $myAssignmentStatus = 'accepted';
  }
  foreach ($assignedTeam as $member) {
      if ($member['id'] == $academicId) {
          $myAssignmentStatus = $member['assignment_status'];
          break;
      }
  }

  // 2. Fetch Conversation & Messages with sender details
  $convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? LIMIT 1");
  $convStmt->execute([$orderId]);
  $conversationId = $convStmt->fetchColumn();

  $messages = [];
  if ($conversationId) {
      $msgStmt = $db->prepare("
          SELECT m.*,
                CASE 
                    WHEN m.sender_type = 'student' THEN u.name
                    WHEN m.sender_type = 'academic' THEN a.name
                    ELSE 'الإدارة'
                END AS sender_name
          FROM messages m
          LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'student'
          LEFT JOIN academics a ON m.sender_id = a.id AND m.sender_type = 'academic'
          WHERE m.conversation_id = ? 
          ORDER BY m.sent_at ASC
      ");
      $msgStmt->execute([$conversationId]);
      $messages = $msgStmt->fetchAll();
      
      // Mark messages as read
      $db->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'student'")->execute([$conversationId]);
  }

  // 3. Fetch Attachments
  $attStmt = $db->prepare("SELECT * FROM order_attachments WHERE order_id = ? ORDER BY uploaded_at DESC");
  $attStmt->execute([$orderId]);
  $attachments = $attStmt->fetchAll();
  ?>
  <!DOCTYPE html>
  <html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>تفاصيل الطلب <?= e($orderNumber) ?> - لوحة تحكم الأكاديمي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/style.css"/>
    <style>
      .chat-box {
        height: 400px;
        overflow-y: auto;
        padding: 20px;
        background: var(--bg-main);
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        gap: 16px;
      }
      .msg {
        max-width: 75%;
        padding: 12px 16px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.6;
        position: relative;
      }
      .msg-academic {
        background: var(--primary);
        color: #fff;
        align-self: flex-start;
        border-top-right-radius: 4px;
      }
      .msg-student {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        align-self: flex-end;
        border-top-left-radius: 4px;
      }
      .msg-time {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 4px;
        display: block;
      }
      .att-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        margin-bottom: 10px;
      }
    </style>
  </head>
  <body>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="page-layout">

    <?php include 'components/academic-sidebar.php'; ?>

    <div class="main-content" id="mainContent">
      <?php include 'components/academic-navbar.php'; ?>

      <div class="page-body">
        <!-- Header -->
        <div class="page-header anim-up">
          <div>
            <div class="breadcrumb">
              <a href="academic-dashboard.php">الرئيسية</a><span>›</span>
              <a href="academic-orders.php">الطلبات</a><span>›</span>
              <span><?= e($orderNumber) ?></span>
            </div>
            <h1 class="page-title">تفاصيل الطلب <?= e($orderNumber) ?></h1>
          </div>
        </div>

        <?php if ($order['status'] === 'cancellation_requested'): ?>
          <div class="card anim-up" style="padding:16px;background:rgba(245,158,11,0.1);border:1.5px solid #f59e0b;margin-bottom:24px;border-radius:14px">
            <div style="color:#b45309;font-weight:700;font-size:14px">⚠️ تنبيه: لقد قام الطالب بتقديم طلب لإلغاء هذا الطلب. نرجو التوقف عن العمل حتى يتم تقييم ومراجعة الطلب من قبل الإدارة.</div>
          </div>
        <?php elseif ($order['status'] === 'cancelled'): ?>
          <div class="card anim-up" style="padding:16px;background:rgba(239,68,68,0.1);border:1.5px solid #ef4444;margin-bottom:24px;border-radius:14px">
            <div style="color:#ef4444;font-weight:700;font-size:14px">🚫 تنبيه: تم إلغاء هذا الطلب، لا يمكنك الاستمرار بالعمل عليه أو تغيير حالته.</div>
          </div>
        <?php endif; ?>

        <!-- Accept / Reject Banner (If assigned and pending response) -->
        <?php if ($myAssignmentStatus === 'assigned'): ?>
          <div class="card anim-up" style="padding:22px;background:linear-gradient(135deg, rgba(99,102,241,0.08), rgba(59,130,246,0.08));border:2px solid var(--primary);margin-bottom:24px;border-radius:16px">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div>
                <h3 style="font-size:17px;font-weight:800;color:var(--text-primary);margin-bottom:6px">📋 طلب مسند إليك من إدارة المنصة</h3>
                <p style="font-size:13px;color:var(--text-secondary);line-height:1.6">
                  تم ترشيحك وإسناد هذه المهمة إليك لمطابقة تخصصك (<strong style="color:var(--primary)"><?= e($order['specialty']) ?></strong>). يرجى مراجعة التفاصيل أدناه وقبول المهمة لبدء التنفيذ أو الاعتذار عنها.
                </p>
              </div>
              <div style="display:flex;gap:12px;flex-shrink:0">
                <button class="btn btn-primary" onclick="respondOrder('accept_assignment')" style="font-weight:700;padding:10px 22px">✅ قبول المهمة</button>
                <button class="btn btn-danger" onclick="respondOrder('reject_assignment')" style="font-weight:700;padding:10px 20px;background:#ef4444">❌ الاعتذار عن المهمة</button>
              </div>
            </div>
          </div>
        <?php elseif ($myAssignmentStatus === 'rejected'): ?>
          <div class="card anim-up" style="padding:16px;background:rgba(239,68,68,0.08);border:1.5px solid #ef4444;margin-bottom:24px;border-radius:14px">
            <div style="color:#ef4444;font-weight:700;font-size:14px">⚠️ لقد اعتذرت عن هذه المهمة مسبقاً.</div>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
          
          <!-- MAIN COL -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <!-- Chat Section -->
            <div class="card anim-up delay-1" style="padding:24px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
                <h3 style="font-size:17px;font-weight:700;color:var(--text-primary)">💬 المحادثة مع الطالب <?= count($assignedTeam) > 1 ? '(محادثة جماعية)' : '' ?></h3>
                <?php if (count($assignedTeam) > 1): ?>
                  <span class="badge badge-primary" style="font-size:12px">👥 فريق من <?= count($assignedTeam) ?> أكاديميين</span>
                <?php endif; ?>
              </div>
              
              <div class="chat-box" id="chatBox">
                <?php $lastMsgIdPhp = 0; ?>
                <?php if (empty($messages)): ?>
                  <div id="chatPlaceholder" style="text-align:center;color:var(--text-secondary);margin:auto">لا توجد رسائل سابقة. ابدأ المحادثة الآن.</div>
                <?php else: ?>
                  <?php foreach ($messages as $msg): ?>
                    <?php 
                    $isMe = ($msg['sender_type'] === 'academic' && $msg['sender_id'] == $academicId); 
                    $lastMsgIdPhp = max($lastMsgIdPhp, (int)$msg['id']);
                    ?>
                    <div class="msg <?= $isMe ? 'msg-academic' : 'msg-student' ?>" data-msg-id="<?= (int)$msg['id'] ?>" data-date="<?= date('Y-m-d', strtotime($msg['sent_at'])) ?>">
                      <div style="font-size:11px;font-weight:700;margin-bottom:4px;opacity:0.85">
                        <?= $isMe ? 'أنت' : e($msg['sender_name']) ?> (<?= $msg['sender_type'] === 'academic' ? 'أكاديمي' : 'الطالب' ?>)
                      </div>
                      <?php if (!empty($msg['file_path'])): ?>
                        <div style="margin-bottom:<?= !empty($msg['content']) ? '6px' : '0' ?>;display:flex;justify-content:<?= $isMe ? 'flex-end' : 'flex-start' ?>">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:<?= $isMe ? 'rgba(255,255,255,0.2)' : 'var(--primary-light)' ?>;color:<?= $isMe ? '#fff' : 'var(--primary)' ?>;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px">
                            <span style="font-size:16px">📎</span>
                            <span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($msg['content'])): ?>
                        <div><?= nl2br(e($msg['content'])) ?></div>
                      <?php endif; ?>
                      <span class="msg-time" style="text-align: <?= $isMe ? 'left' : 'right' ?>"><?= date('h:i A', strtotime($msg['sent_at'])) ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div style="margin-top:16px;display:flex;gap:10px;align-items:flex-end">
                <input type="file" id="chatFileInput" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
                <span id="chatFileName" style="display:none;font-size:11px;color:var(--text-secondary);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
                <textarea id="chatInput" class="form-input" rows="2" placeholder="اكتب رسالتك هنا..." style="resize:none;flex:1"></textarea>
                <span title="إرفاق ملف (حتى 10MB)" style="cursor:pointer;font-size:22px;padding:10px;color:var(--text-secondary);flex-shrink:0" onclick="document.getElementById('chatFileInput').click()">📎</span>
                <button class="btn btn-primary" onclick="sendMessage()" style="padding:0 24px;height:auto">إرسال ✈️</button>
              </div>
            </div>

            <!-- Attachments Section -->
            <div class="card anim-up delay-2" style="padding:24px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
                <h3 style="font-size:17px;font-weight:700;color:var(--text-primary)">📎 المرفقات وملفات العمل</h3>
                <div>
                  <input type="file" id="fileInput" style="display:none" onchange="uploadFile()"/>
                  <button class="btn btn-outline btn-sm" onclick="document.getElementById('fileInput').click()">+ رفع ملف جديد</button>
                </div>
              </div>

              <div id="attachmentsList">
                <?php if (empty($attachments)): ?>
                  <div style="text-align:center;padding:20px;color:var(--text-secondary);border:1px dashed var(--border-color);border-radius:12px">لا توجد مرفقات حتى الآن.</div>
                <?php else: ?>
                  <?php foreach ($attachments as $att): ?>
                    <div class="att-item">
                      <div style="display:flex;align-items:center;gap:14px">
                        <div style="font-size:24px">📄</div>
                        <div>
                          <div style="font-weight:600;font-size:14px;color:var(--text-primary)"><?= e($att['file_name']) ?></div>
                          <div style="font-size:12px;color:var(--text-secondary)">
                            تم الرفع بواسطة: <?= $att['uploaded_by'] === 'academic' ? 'أكاديمي' : 'الطالب' ?> 
                            · <?= date('Y-m-d H:i', strtotime($att['uploaded_at'])) ?>
                          </div>
                        </div>
                      </div>
                      <a href="../<?= e($att['file_path']) ?>" download class="btn btn-outline btn-sm" style="padding:6px 12px;font-size:12px">تحميل ⬇️</a>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

          </div>

          <!-- SIDE COL -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <!-- Assigned Team Card (Co-academics) -->
            <?php if (!empty($assignedTeam)): ?>
              <div class="card anim-up delay-2" style="padding:22px">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">👥 فريق الأكاديميين المكلف (<?= count($assignedTeam) ?>)</h3>
                <div style="display:flex;flex-direction:column;gap:10px">
                  <?php foreach ($assignedTeam as $member): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--bg-main);border-radius:10px;border:1px solid var(--border-color)">
                      <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">
                          <?= e($member['avatar_initials'] ?: mb_substr($member['name'], 0, 2)) ?>
                        </div>
                        <div>
                          <div style="font-size:13px;font-weight:700;color:var(--text-primary)">
                            <?= e($member['name']) ?> <?= $member['id'] == $academicId ? '<span style="font-size:11px;color:var(--primary)">(أنت)</span>' : '' ?>
                          </div>
                          <div style="font-size:11px;color:var(--text-secondary)"><?= e($member['specialty']) ?> · <?= e($member['degree']) ?></div>
                        </div>
                      </div>
                      <span class="badge <?= $member['assignment_status'] === 'accepted' ? 'badge-success' : ($member['assignment_status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>" style="font-size:11px;padding:4px 8px">
                        <?= $member['assignment_status'] === 'accepted' ? '✓ تم القبول' : ($member['assignment_status'] === 'rejected' ? '✕ معتذر' : '⏳ بانتظار الرد') ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="card anim-up delay-3" style="padding:24px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:18px">تفاصيل الطلب</h3>
              
              <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px">
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">رقم الطلب</span>
                  <span style="font-weight:700;color:var(--primary)"><?= e($orderNumber) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">الطالب</span>
                  <span style="font-weight:600;color:var(--text-primary)"><?= e($order['student_name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">الخدمة</span>
                  <span style="font-weight:600;color:var(--text-primary)"><?= e($order['service_name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">التخصص المطلوب</span>
                  <span style="font-weight:600;color:var(--primary)"><?= e($order['specialty']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">الباقة</span>
                  <span style="font-weight:600;color:var(--primary)"><?= e($order['package_name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-color)">
                  <span style="color:var(--text-secondary);font-size:13px">المبلغ المعتمد</span>
                  <span style="font-weight:900;color:var(--success)"><?= floatval($order['amount']) ?> ر.س</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                  <span style="color:var(--text-secondary);font-size:13px">الموعد النهائي</span>
                  <span style="font-weight:700;color:#ef4444"><?= $order['deadline'] ? e($order['deadline']) : '—' ?></span>
                </div>
              </div>

              <div style="margin-top:20px">
                <label class="form-label">تحديث حالة التنفيذ</label>
                <?php if (in_array($order['status'], ['cancellation_requested', 'cancelled'])): ?>
                  <div style="padding:10px;background:#f3f4f6;border-radius:8px;color:#6b7280;font-size:13px;text-align:center;border:1px dashed #d1d5db">
                    لا يمكنك تغيير حالة الطلب حالياً.
                  </div>
                <?php else: ?>
                  <select class="form-input form-select" id="orderStatus" style="padding-left:36px">
                    <option value="accepted" <?= $order['status']=='accepted'?'selected':'' ?>>مقبول وجاري العمل</option>
                    <option value="in_progress" <?= $order['status']=='in_progress'?'selected':'' ?>>قيد التنفيذ</option>
                    <option value="revision" <?= $order['status']=='revision'?'selected':'' ?>>تحت المراجعة</option>
                    <option value="completed" <?= $order['status']=='completed'?'selected':'' ?>>مكتمل</option>
                  </select>
                  <button class="btn btn-primary btn-block" style="margin-top:14px" onclick="updateOrderStatus()">تحديث الحالة</button>
                <?php endif; ?>
              </div>
            </div>

            <div class="card anim-up delay-4" style="padding:24px">
              <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:10px">وصف وملاحظات الطالب</h3>
              <p style="font-size:13px;color:var(--text-secondary);line-height:1.7"><?= nl2br(e($order['description'])) ?></p>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
  // Scroll chat to bottom
  const chatBox = document.getElementById('chatBox');
  if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

  const orderId = <?= $orderId ?>;
  const orderNumberJs = <?= json_encode($orderNumber) ?>;
  const myAcademicId = <?= (int)$academicId ?>;
  const APP_BASE = <?= json_encode($appBase) ?>;
  const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
  const convIdJs = <?= (int)($conversationId ?: 0) ?>;
  let lastMsgId = <?= (int)$lastMsgIdPhp ?>;
  let chatSending = false;

  /* ─────────────────────────────────────────────
    تحديث فوري للمحادثة (AJAX Polling خفيف)
    - كل 5 ثوانٍ: جلب الرسائل الأحدث فقط (id > lastMsgId)
    - يتوقف مؤقتاً عند إخفاء التبويب لتقليل الحمل على السيرفر
  ───────────────────────────────────────────── */
  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatTime12(hhmm) {
    const parts = (hhmm || '').split(':');
    if (parts.length < 2) return hhmm || '';
    let h = parseInt(parts[0], 10);
    const m = parts[1];
    const ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12; if (h === 0) h = 12;
    return String(h).padStart(2, '0') + ':' + m + ' ' + ap;
  }

  function scrollToChatBottom(smooth) {
    if (!chatBox) return;
    chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }

  function appendMessage(m) {
    // إزالة رسالة "لا توجد رسائل سابقة" عند وصول أول رسالة
    const ph = document.getElementById('chatPlaceholder');
    if (ph) ph.remove();

    const mDate = (m.sent_at || '').substring(0, 10);
    const mTime = formatTime12((m.sent_at || '').substring(11, 16));
    const isMe  = (m.sender_type === 'academic' && parseInt(m.sender_id, 10) === myAcademicId);

    const wrap = document.createElement('div');
    wrap.className = 'msg ' + (isMe ? 'msg-academic' : 'msg-student');
    wrap.dataset.msgId = m.id;
    wrap.dataset.date = mDate || '';

    const name = document.createElement('div');
    name.style.cssText = 'font-size:11px;font-weight:700;margin-bottom:4px;opacity:0.85';
    name.textContent = (isMe ? 'أنت' : (m.sender_name || '')) + ' (' + (m.sender_type === 'academic' ? 'أكاديمي' : 'الطالب') + ')';
    wrap.appendChild(name);

    const body = document.createElement('div');
    if (m.file_path) {
      const fileRow = document.createElement('div');
      fileRow.style.marginBottom = m.content ? '6px' : '0';
      fileRow.style.display = 'flex';
      fileRow.style.justifyContent = isMe ? 'flex-end' : 'flex-start';
      const aLink = document.createElement('a');
      aLink.href = APP_BASE + '/' + m.file_path;
      aLink.target = '_blank';
      aLink.download = '';
      aLink.style.cssText = 'display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px';
      if (isMe) { aLink.style.background = 'rgba(255,255,255,0.2)'; aLink.style.color = '#fff'; }
      else { aLink.style.background = 'var(--primary-light)'; aLink.style.color = 'var(--primary)'; }
      const ic = document.createElement('span'); ic.style.fontSize = '16px'; ic.textContent = '📎';
      const nm = document.createElement('span'); nm.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap'; nm.textContent = m.file_name || 'ملف';
      aLink.appendChild(ic); aLink.appendChild(nm);
      fileRow.appendChild(aLink);
      body.appendChild(fileRow);
    }
    if (m.content) {
      const textNode = document.createElement('div');
      textNode.innerHTML = escapeHtml(m.content).replace(/\n/g, '<br>');
      body.appendChild(textNode);
    }
    if (body.childNodes.length) wrap.appendChild(body);

    const time = document.createElement('span');
    time.className = 'msg-time';
    time.style.textAlign = isMe ? 'left' : 'right';
    time.textContent = mTime;
    wrap.appendChild(time);

    chatBox.appendChild(wrap);
  }

  async function pollChat() {
    if (document.hidden || !convIdJs || chatSending) return;
    try {
      const res = await fetch('ajax/chat-poll.php?order=' + encodeURIComponent(orderNumberJs) + '&after=' + lastMsgId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok || !Array.isArray(data.messages) || !data.messages.length) return;
      const nearBottom = chatBox ? (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 150 : false;
      data.messages.forEach(appendMessage);
      lastMsgId = data.last_id || lastMsgId;
      if (nearBottom) scrollToChatBottom(true);
    } catch (e) { /* تجاهل أخطاء الشبكة المؤقتة */ }
  }
  setInterval(pollChat, 5000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) pollChat(); });

  function respondOrder(actionName) {
    const confirmMsg = actionName === 'accept_assignment' ? 'هل أنت متأكد من قبول هذه المهمة الأكاديمية؟' : 'هل أنت متأكد من الاعتذار عن هذه المهمة؟';
    if (!confirm(confirmMsg)) return;

    const formData = new FormData();
    formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', orderId);
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=' + actionName, {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if(data.success) {
          Toast.show(data.message, 'success');
          setTimeout(() => location.reload(), 1200);
        } else {
          Toast.show(data.message, 'error');
        }
      } catch (e) {
        alert("استجابة غير متوقعة من السيرفر (انظر الكونسول):\n" + text.substring(0, 500));
        console.error("RAW RESPONSE:", text);
      }
    })
    .catch(err => {
      alert("فشل الاتصال بالسيرفر: " + err.message);
    });
  }

  function updateOrderStatus() {
    const newStatus = document.getElementById('orderStatus').value;
    const formData = new FormData();
    formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=update_order_status', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if(data.success) Toast.show(data.message, 'success');
        else Toast.show(data.message, 'error');
      } catch (e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0, 500));
        console.error(text);
      }
    })
    .catch(err => alert("فشل الاتصال: " + err.message));
  }

  function sendMessage() {
    const input = document.getElementById('chatInput');
    const fileInput = document.getElementById('chatFileInput');
    const txt = input.value.trim();
    const hasFile = fileInput && fileInput.files.length > 0;
    if((!txt && !hasFile) || chatSending) return;

    const formData = new FormData();
    formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', orderId);
    formData.append('content', txt);
    if (hasFile) formData.append('file', fileInput.files[0]);
    formData.append('csrf_token', csrfTokenJs);

    input.disabled = true;
    chatSending = true;

    fetch('ajax/handler.php?action=send_message', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      input.disabled = false;
      chatSending = false;
      try {
        const data = JSON.parse(text);
        if (data.success) {
          input.value = '';
          if (fileInput) fileInput.value = '';
          const fn = document.getElementById('chatFileName');
          if (fn) fn.style.display = 'none';
          input.focus();
          pollChat();
        } else {
          Toast.show(data.message, 'error');
        }
      } catch (e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0, 500));
        console.error(text);
      }
    })
    .catch(err => {
      input.disabled = false; chatSending = false;
      alert("تعذر إرسال الرسالة: " + err.message);
    });
  }

  // إظهار اسم الملف المحدد للإرسال
  (function () {
    const fi = document.getElementById('chatFileInput');
    if (!fi) return;
    fi.addEventListener('change', function () {
      const fn = document.getElementById('chatFileName');
      if (!fn) return;
      if (fi.files.length) { fn.textContent = '📄 ' + fi.files[0].name; fn.style.display = ''; }
      else fn.style.display = 'none';
    });
  })();

  function uploadFile() {
    const file = document.getElementById('fileInput').files[0];
    if(!file) return;

    Toast.show('جاري الرفع...', 'info');

    const formData = new FormData();
    formData.append('csrf_token', csrfTokenJs);
    formData.append('order_id', orderId);
    formData.append('file', file);
    formData.append('csrf_token', csrfTokenJs);

    fetch('ajax/handler.php?action=upload_attachment', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if(data.success) {
          Toast.show('تم رفع الملف بنجاح', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          Toast.show(data.message || 'حدث خطأ أثناء الرفع', 'error');
        }
      } catch (e) {
        alert("استجابة غير متوقعة:\n" + text.substring(0, 500));
        console.error(text);
      }
    })
    .catch(err => alert("فشل الاتصال: " + err.message));
  }
  </script>
  </body>
  </html>
