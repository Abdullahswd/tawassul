<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db   = db();

// Fetch all conversations for this student (orders with an assigned academic)
$conv_stmt = $db->prepare(
    'SELECT c.*, o.order_number, a.name AS academic_name, a.avatar_initials AS academic_avatar,
            (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
            (SELECT sent_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_time,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = ? AND m.is_read = 0) AS unread_count
     FROM conversations c
     JOIN orders o ON c.order_id = o.id
     JOIN academics a ON c.academic_id = a.id
     WHERE c.student_id = ?
     ORDER BY last_time DESC'
);
$conv_stmt->execute(['academic', $user['id']]);
$conversations = $conv_stmt->fetchAll();

// Active conversation
$active_conv_id = (int)($_GET['conv'] ?? ($conversations[0]['id'] ?? 0));
$active_conv    = null;
$messages       = [];

if ($active_conv_id) {
    foreach ($conversations as $c) {
        if ($c['id'] == $active_conv_id) {
            $active_conv = $c;
            break;
        }
    }
    if ($active_conv) {
        $messages = getConversationMessages($active_conv_id);
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
        $check = $db->prepare('SELECT id FROM conversations WHERE id = ? AND student_id = ? LIMIT 1');
        $check->execute([$conv_id, $user['id']]);
        if ($check->fetch()) {
            sendMessage($conv_id, $user['id'], 'student', $content);
        }
    }
    header('Location: chat.php?conv=' . $conv_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>المحادثات - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
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
  </style>
</head>
<body>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo-icon">🎓</div>
        <div class="logo-text">تواصل</div>
      </div>
      <nav class="sidebar-nav">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-bottom:8px;padding:0 8px">القائمة الرئيسية</div>
        <a href="student-dashboard.php" class="nav-item"><span class="icon">📊</span><span>لوحة المعلومات</span></a>
        <a href="services.php" class="nav-item"><span class="icon">📦</span><span>الخدمات الأكاديمية</span></a>
        <a href="orders.php" class="nav-item"><span class="icon">📋</span><span>طلباتي</span></a>
        <a href="chat.php" class="nav-item active"><span class="icon">💬</span><span>المحادثات</span></a>
        <a href="payments.php" class="nav-item"><span class="icon">💳</span><span>المدفوعات</span></a>
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-top:24px;margin-bottom:8px;padding:0 8px">إعدادات الحساب</div>
        <a href="profile.php" class="nav-item"><span class="icon">👤</span><span>الملف الشخصي</span></a>
      </nav>
      <div style="padding:20px;border-top:1px solid var(--border-color)">
        <a href="../logout.php" class="nav-item" style="color:var(--danger)">
          <span class="icon">🚪</span><span>تسجيل الخروج</span>
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-area">
      <header class="top-navbar">
        <div style="display:flex;align-items:center;gap:16px">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <div class="h3">تواصل مع الأكاديميين</div>
        </div>
        <div class="navbar-actions">
          <button class="icon-btn dark-toggle" aria-label="تبديل المظهر">🌙</button>
          <button class="icon-btn" aria-label="الإشعارات">
            🔔<span class="badge-dot"><?= countUnreadNotifications($user['id'], 'student') ?></span>
          </button>
          <div style="width:1px;height:30px;background:var(--border-color);margin:0 8px"></div>
          <div class="user-profile">
            <div class="user-info" style="text-align:left">
              <span class="user-name"><?= e($user['name']) ?></span>
              <span class="user-role">طالب</span>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
          </div>
        </div>
      </header>

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
                    <?= e($active_conv['academic_avatar'] ?: mb_substr($active_conv['academic_name'], 0, 1, 'UTF-8')) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:15px;color:var(--text-primary)"><?= e($active_conv['academic_name']) ?></div>
                    <div style="font-size:12px;color:var(--success)">أكاديمي • الطلب <?= e($active_conv['order_number']) ?></div>
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
      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    // Auto-scroll to bottom of messages
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
  </script>
</body>
</html>
