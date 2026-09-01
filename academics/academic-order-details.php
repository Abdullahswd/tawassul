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

      <!-- Accept / Reject Banner (If assigned and pending response) -->
      <?php if ($myAssignmentStatus === 'assigned'): ?>
        <div class="card anim-up" style="padding:22px;background:linear-gradient(135deg, rgba(99,102,241,0.08), rgba(59,130,246,0.08));border:2px solid var(--primary);margin-bottom:24px;border-radius:16px">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
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

      <div style="display:grid;grid-template-columns:1fr 340px;gap:24px">
        
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
              <?php if (empty($messages)): ?>
                <div style="text-align:center;color:var(--text-secondary);margin:auto">لا توجد رسائل سابقة. ابدأ المحادثة الآن.</div>
              <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                  <?php $isMe = ($msg['sender_type'] === 'academic' && $msg['sender_id'] == $academicId); ?>
                  <div class="msg <?= $isMe ? 'msg-academic' : 'msg-student' ?>">
                    <div style="font-size:11px;font-weight:700;margin-bottom:4px;opacity:0.85">
                      <?= $isMe ? 'أنت' : e($msg['sender_name']) ?> (<?= $msg['sender_type'] === 'academic' ? 'أكاديمي' : 'الطالب' ?>)
                    </div>
                    <div><?= nl2br(e($msg['content'])) ?></div>
                    <span class="msg-time" style="text-align: <?= $isMe ? 'left' : 'right' ?>"><?= date('h:i A', strtotime($msg['sent_at'])) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px">
              <textarea id="chatInput" class="form-input" rows="2" placeholder="اكتب رسالتك هنا..." style="resize:none;flex:1"></textarea>
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
                <span style="font-weight:700;color:#ef4444"><?= $order['deadline'] ?></span>
              </div>
            </div>

            <div style="margin-top:20px">
              <label class="form-label">تحديث حالة التنفيذ</label>
              <select class="form-input form-select" id="orderStatus" style="padding-left:36px">
                <option value="accepted" <?= $order['status']=='accepted'?'selected':'' ?>>مقبول وجاري العمل</option>
                <option value="in_progress" <?= $order['status']=='in_progress'?'selected':'' ?>>قيد التنفيذ</option>
                <option value="revision" <?= $order['status']=='revision'?'selected':'' ?>>تحت المراجعة</option>
                <option value="completed" <?= $order['status']=='completed'?'selected':'' ?>>مكتمل</option>
              </select>
              <button class="btn btn-primary btn-block" style="margin-top:14px" onclick="updateOrderStatus()">تحديث الحالة</button>
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

function respondOrder(actionName) {
  const confirmMsg = actionName === 'accept_assignment' ? 'هل أنت متأكد من قبول هذه المهمة الأكاديمية؟' : 'هل أنت متأكد من الاعتذار عن هذه المهمة؟';
  if (!confirm(confirmMsg)) return;

  const formData = new FormData();
  formData.append('order_id', orderId);

  fetch('ajax/handler.php?action=' + actionName, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) {
      Toast.show(data.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      Toast.show(data.message, 'error');
    }
  });
}

function updateOrderStatus() {
  const newStatus = document.getElementById('orderStatus').value;
  const formData = new FormData();
  formData.append('order_id', orderId);
  formData.append('status', newStatus);

  fetch('ajax/handler.php?action=update_order_status', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) Toast.show(data.message, 'success');
    else Toast.show(data.message, 'error');
  });
}

function sendMessage() {
  const input = document.getElementById('chatInput');
  const txt = input.value.trim();
  if(!txt) return;

  const formData = new FormData();
  formData.append('order_id', orderId);
  formData.append('content', txt);

  input.disabled = true;

  fetch('ajax/handler.php?action=send_message', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    input.disabled = false;
    if (data.success) {
      input.value = '';
      
      if (chatBox.innerHTML.includes('لا توجد رسائل سابقة')) {
        chatBox.innerHTML = '';
      }

      const div = document.createElement('div');
      div.className = 'msg msg-academic';
      div.innerHTML = `<div style="font-size:11px;font-weight:700;margin-bottom:4px;opacity:0.85">أنت (أكاديمي)</div><div>${txt.replace(/\n/g, '<br/>')}</div><span class="msg-time" style="text-align:left">الآن</span>`;
      chatBox.appendChild(div);
      chatBox.scrollTop = chatBox.scrollHeight;
    } else {
      Toast.show(data.message, 'error');
    }
  });
}

function uploadFile() {
  const file = document.getElementById('fileInput').files[0];
  if(!file) return;

  Toast.show('جاري الرفع...', 'info');

  const formData = new FormData();
  formData.append('order_id', orderId);
  formData.append('file', file);

  fetch('ajax/handler.php?action=upload_attachment', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) {
      Toast.show('تم رفع الملف بنجاح', 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      Toast.show(data.message || 'حدث خطأ أثناء الرفع', 'error');
    }
  });
}
</script>
</body>
</html>
