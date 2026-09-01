<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db   = db();

// Handle order_id query param to find or open conversation
$orderParamId = (int)($_GET['order_id'] ?? 0);
if ($orderParamId > 0) {
    // Check if conversation exists
    $cStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? AND student_id = ? LIMIT 1");
    $cStmt->execute([$orderParamId, $user['id']]);
    $foundConvId = $cStmt->fetchColumn();
    if ($foundConvId) {
        header('Location: chat.php?conv=' . $foundConvId);
        exit;
    } else {
        // If order has an assigned academic, create the conversation
        $oStmt = $db->prepare("SELECT id, academic_id FROM orders WHERE id = ? AND student_id = ? LIMIT 1");
        $oStmt->execute([$orderParamId, $user['id']]);
        $oData = $oStmt->fetch();
        if ($oData && $oData['academic_id']) {
            $convId = getOrCreateConversation($orderParamId, $user['id'], $oData['academic_id']);
            header('Location: chat.php?conv=' . $convId);
            exit;
        }
    }
}

// Fetch all conversations for this student
$conv_stmt = $db->prepare(
    'SELECT c.*, o.order_number, o.specialty, a.name AS academic_name, a.avatar_initials AS academic_avatar,
            (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
            (SELECT sent_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_time,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = ? AND m.is_read = 0) AS unread_count
     FROM conversations c
     JOIN orders o ON c.order_id = o.id
     LEFT JOIN academics a ON c.academic_id = a.id
     WHERE c.student_id = ?
     ORDER BY last_time DESC'
);
$conv_stmt->execute(['academic', $user['id']]);
$conversations = $conv_stmt->fetchAll();

// Active conversation
$active_conv_id = (int)($_GET['conv'] ?? ($conversations[0]['id'] ?? 0));
$active_conv    = null;
$active_team    = [];
$messages       = [];

if ($active_conv_id) {
    foreach ($conversations as $c) {
        if ($c['id'] == $active_conv_id) {
            $active_conv = $c;
            break;
        }
    }
    if ($active_conv) {
        $active_team = getAssignedAcademics($active_conv['order_id']);
        $messages    = getConversationMessages($active_conv_id);
        // Mark messages from academic as read
        $db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ?')
           ->execute([$active_conv_id, 'academic']);
    }
}

// Handle AJAX message send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $content  = trim($_POST['content'] ?? '');
    $conv_id  = (int)($_POST['conv_id'] ?? 0);
    if ($content && $conv_id) {
        // Verify this conversation belongs to the student
        $check = $db->prepare('SELECT id, order_id FROM conversations WHERE id = ? AND student_id = ? LIMIT 1');
        $check->execute([$conv_id, $user['id']]);
        $cRow = $check->fetch();
        if ($cRow) {
            sendMessage($conv_id, $user['id'], 'student', $content);
            
            // Notify assigned academics
            $team = getAssignedAcademics($cRow['order_id']);
            foreach ($team as $member) {
                createNotification(
                    $member['id'],
                    'academic',
                    'رسالة جديدة من الطالب ' . $user['name'],
                    'أرسل الطالب رسالة في محادثة الطلب #' . ($active_conv['order_number'] ?? ''),
                    '💬',
                    'academics/academic-order-details.php?id=' . ($active_conv['order_number'] ?? '')
                );
            }
        }
    }
    header('Location: chat.php?conv=' . $conv_id);
    exit;
}
?>
<?php
$extraCss = [
  '<style>
    .chat-layout {
      display: grid;
      grid-template-columns: 320px 1fr;
      height: calc(100vh - 134px);
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    .chat-sidebar {
      border-left: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
      background: var(--bg-card);
    }
    .chat-list { flex-grow: 1; overflow-y: auto; }
    .chat-contact {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      border-bottom: 1px solid var(--border-color);
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
    }
    .chat-contact:hover { background: var(--bg-hover); }
    .chat-contact.active { background: var(--primary-light); }
    .chat-main { display: flex; flex-direction: column; background: var(--bg-body); }
    .chat-header {
      padding: 16px 24px;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border-color);
      display: flex; align-items: center; justify-content: space-between;
    }
    .chat-messages {
      flex-grow: 1; padding: 24px; overflow-y: auto;
      display: flex; flex-direction: column; gap: 16px;
    }
    .msg {
      max-width: 70%; padding: 12px 16px; border-radius: 16px;
      font-size: 14px; line-height: 1.6;
    }
    .msg-received {
      background: var(--bg-card); border: 1px solid var(--border-color);
      align-self: flex-start; border-top-right-radius: 4px;
    }
    .msg-sent {
      background: var(--primary); color: white;
      align-self: flex-end; border-top-left-radius: 4px;
    }
    .msg-time { font-size: 10px; color: var(--text-muted); margin-top: 6px; text-align: right; }
    .msg-sent .msg-time { color: rgba(255,255,255,0.7); text-align: left; }
    .chat-input-area {
      padding: 16px 24px; background: var(--bg-card);
      border-top: 1px solid var(--border-color);
      display: flex; align-items: center; gap: 12px;
    }
    .empty-chat {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      color: var(--text-secondary); gap: 12px;
    }
    @media (max-width: 768px) {
      .chat-layout { grid-template-columns: 1fr; height: calc(100vh - 100px); }
      .chat-sidebar { display: none; }
    }
  </style>',
];
$pageTitle  = 'المحادثات والتواصل الأكاديمي';
$activePage = 'chat';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <!-- Page Content -->
      <div class="content-wrap" style="padding:20px;display:flex;flex-direction:column">
        <div class="chat-layout shadow-sm">
          
          <!-- Contacts Sidebar -->
          <div class="chat-sidebar">
            <div style="padding:16px;border-bottom:1px solid var(--border-color)">
              <div style="font-weight:700;font-size:15px">محادثاتي</div>
            </div>
            <div class="chat-list">
              <?php if (empty($conversations)): ?>
                <div style="padding:32px;text-align:center;color:var(--text-secondary);font-size:14px">
                  <div style="font-size:32px;margin-bottom:12px">💬</div>
                  لا توجد محادثات بعد.<br>ستظهر هنا عند تعيين أكاديمي لطلبك.
                </div>
              <?php else: ?>
                <?php foreach ($conversations as $conv): 
                  $isActive = $conv['id'] == $active_conv_id;
                  $avatarLetter = mb_substr($conv['academic_name'], 0, 1, 'UTF-8');
                ?>
                  <a href="chat.php?conv=<?= $conv['id'] ?>" class="chat-contact <?= $isActive ? 'active' : '' ?>">
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;flex-shrink:0">
                      <?= e($conv['academic_avatar'] ?: $avatarLetter) ?>
                    </div>
                    <div style="overflow:hidden;flex:1">
                      <div style="display:flex;justify-content:space-between;align-items:baseline">
                        <span style="font-weight:700;font-size:14px;color:var(--text-primary)"><?= e($conv['academic_name']) ?></span>
                        <?php if ($conv['last_time']): ?>
                          <span style="font-size:11px;color:var(--text-muted)"><?= date('H:i', strtotime($conv['last_time'])) ?></span>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;justify-content:space-between;align-items:center">
                        <div style="font-size:12px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">
                          <?= e($conv['last_message'] ?: 'الطلب: ' . $conv['order_number']) ?>
                        </div>
                        <?php if ($conv['unread_count'] > 0): ?>
                          <span style="background:var(--primary);color:white;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Chat Area -->
          <div class="chat-main">
            <?php if ($active_conv): ?>
              <!-- Header -->
              <div class="chat-header">
                <div style="display:flex;align-items:center;gap:12px">
                  <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:800">
                    <?= count($active_team) > 1 ? '👥' : e($active_conv['academic_avatar'] ?: mb_substr($active_conv['academic_name'] ?? 'أ', 0, 1, 'UTF-8')) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:15px;color:var(--text-primary)">
                      <?= count($active_team) > 1 ? 'مجموعة العمل الأكاديمي (' . count($active_team) . ' أكاديميين)' : e($active_conv['academic_name']) ?>
                    </div>
                    <div style="font-size:12px;color:var(--success)">
                      <?= count($active_team) > 1 ? 'فريق متخصص' : 'أكاديمي متخصص' ?> • الطلب <?= e($active_conv['order_number']) ?>
                    </div>
                  </div>
                </div>
                <a href="order-details.php?id=<?= $active_conv['order_id'] ?>" class="btn btn-outline" style="padding:6px 16px;font-size:13px">تفاصيل الطلب 📋</a>
              </div>

              <!-- Messages -->
              <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                  <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:32px">
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
                    <div style="text-align:center;font-size:12px;color:var(--text-muted);margin:8px 0"><?= date('d/m/Y', strtotime($msg['sent_at'])) ?></div>
                  <?php endif; ?>
                    <div class="msg <?= $isSent ? 'msg-sent' : 'msg-received' ?>">
                      <?php if (!$isSent): ?>
                        <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px">
                          <?= e($msg['sender_name'] ?? 'الأكاديمي') ?>
                        </div>
                      <?php endif; ?>
                      <?= nl2br(e($msg['content'])) ?>
                      <div class="msg-time"><?= date('H:i', strtotime($msg['sent_at'])) ?> <?= $isSent ? '✓✓' : '' ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Input -->
              <form method="POST" action="chat.php?conv=<?= $active_conv_id ?>" class="chat-input-area" id="chatForm">
                <input type="hidden" name="send_message" value="1">
                <input type="hidden" name="conv_id" value="<?= $active_conv_id ?>">
                <input type="text" name="content" id="chatInput" placeholder="اكتب رسالتك هنا..." class="form-input" style="border-radius:40px;padding:12px 20px;flex:1" required autocomplete="off">
                <button type="submit" class="btn btn-primary" style="border-radius:50%;width:48px;height:48px;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0" aria-label="إرسال">
                  <span style="transform:rotate(180deg)">➔</span>
                </button>
              </form>

            <?php else: ?>
              <div class="empty-chat">
                <div style="font-size:64px">💬</div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary)">اختر محادثة</div>
                <div style="font-size:14px;text-align:center;max-width:300px">اختر محادثة من القائمة على اليمين للبدء في التواصل مع الأكاديميين</div>
                <?php if (empty($conversations)): ?>
                  <a href="orders.php" class="btn btn-primary" style="margin-top:16px">📋 طلباتي</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
<?php
$extraJs = ob_start() ? '' : '';
ob_start();
?>
  <script>
    // Auto-scroll to bottom of messages
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
  </script>
<?php
$extraJs = ob_get_clean();
require __DIR__ . '/partials/footer.php';
?>
