<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/support_functions.php';
requireStudent();

ensureSupportTables();

$user = currentUser();
$db   = db();
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// معالجة فتح تذكرة جديدة
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['new_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    if ($subject !== '') {
        $convId = createSupportConversation((int) $user['id'], 'student', mb_substr($subject, 0, 255, 'UTF-8'));
        header('Location: support.php?conv=' . $convId);
        exit;
    }
}

// معالجة إرسال رسالة (دعم الإرسال التقليدي + الـ AJAX)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['send_support'])) {
    $content = trim($_POST['content'] ?? '');
    $convId  = (int) ($_POST['conv_id'] ?? 0);
    $isAjax  = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    $sent = false;
    if ($convId && ($content !== '' || !empty($_FILES['support_file']['name']))) {
        $chk = $db->prepare("SELECT id, user_id, user_type FROM support_conversations WHERE id = ? AND user_id = ? AND user_type = 'student' LIMIT 1");
        $chk->execute([$convId, $user['id']]);
        if ($chk->fetch()) {
            $fileInfo = saveChatAttachmentUpload($_FILES['support_file'] ?? null);
            sendSupportMessage($convId, 'user', (int) $user['id'], $content, $fileInfo);
            setSupportConversationStatus($convId, 'pending');
            createNotification(1, 'admin', 'رسالة دعم جديدة 🛟', 'أرسل الطالب ' . $user['name'] . ' رسالة في تذكرة الدعم.', '🛟', 'admin/pages/support.php');
            $sent = true;
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $sent], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: support.php?conv=' . $convId);
    exit;
}

$faqs       = getSupportFaqs('student');
$channels   = getSupportChannels();
$convos     = getSupportConversationsForUser((int) $user['id'], 'student');

$activeConvId = (int) ($_GET['conv'] ?? ($convos[0]['id'] ?? 0));
$activeConv   = null;
$messages     = [];
if ($activeConvId) {
    foreach ($convos as $c) {
        if ((int) $c['id'] === $activeConvId) { $activeConv = $c; break; }
    }
    if ($activeConv) {
        $messages = getSupportConversationMessages($activeConvId);
        // تعليم ردود الإدارة كمقروءة
        $db->prepare("UPDATE support_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'admin' AND is_read = 0")
           ->execute([$activeConvId]);
    }
}
$lastMsgIdPhp = 0;
foreach ($messages as $m) { $lastMsgIdPhp = max($lastMsgIdPhp, (int) $m['id']); }

$statusLabels = [
    'open'    => ['مفتوحة', '#16a34a', '#dcfce7'],
    'pending' => ['قيد المراجعة', '#d97706', '#fef3c7'],
    'closed'  => ['مغلقة', '#64748b', '#e2e8f0'],
];
?>
<?php
$activePage = 'support';
$pageTitle  = 'الدعم الفني والشكاوى';
$extraCss = [
  '<style>
    .chat-messages { flex: 1 1 auto; min-height: 0; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
    .msg { max-width: 75%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.6; }
    .msg-sent { background: var(--primary); color: #fff; align-self: flex-end; border-top-left-radius: 4px; }
    .msg-received { background: var(--bg-card); border: 1px solid var(--border-color); align-self: flex-start; border-top-right-radius: 4px; }
    .msg-time { font-size: 10px; color: var(--text-muted); margin-top: 6px; text-align: right; }
    .msg-sent .msg-time { color: rgba(255,255,255,0.7); text-align: left; }
    .chat-input-area { padding: 14px 18px; background: var(--bg-card); border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
    .chat-contact.active { background: var(--primary-light); }
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 9999px; }
  </style>',
];
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>
      <div class="content-wrap" style="max-width:1200px;margin:0 auto">

        <!-- Hero -->
        <div class="mb-8 bg-gradient-to-l from-indigo-900 to-slate-900 text-white rounded-3xl p-8 shadow-md">
          <div style="display:flex;align-items:center;gap:4px;font-size:20px;font-weight:900;margin-bottom:6px">🛟 الدعم الفني والشكاوى</div>
          <p style="opacity:0.85;font-size:14px">نساعدك في أي استفسار أو مشكلة — اختر السؤال الشائع أو تواصل مع فريق الدعم مباشرة.</p>
        </div>

        <?php if (empty($convos)): ?>
          <div class="card" style="padding:24px;margin-bottom:24px">
            <h3 class="h3" style="margin-bottom:16px">📩 فتح تذكرة دعم</h3>
            <form method="POST" action="support.php">
              <input type="hidden" name="new_ticket" value="1">
              <div style="display:flex;gap:12px;flex-wrap:wrap">
                <input type="text" name="subject" required style="flex:1;min-width:260px" class="form-input"
                       placeholder="اكتب موضوع التذكرة، مثال: مشكلة في رفع إشعار الدفع...">
                <button type="submit" class="btn btn-primary">فتح تذكرة 🚀</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <!-- قنوات التواصل -->
        <?php if (!empty($channels)): ?>
          <div class="card" style="padding:24px;margin-bottom:24px">
            <h3 class="h3" style="margin-bottom:16px">📞 قنوات التواصل</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
              <?php foreach ($channels as $ch): ?>
                <div style="display:flex;align-items:center;gap:12px;border:1px solid var(--border-color);border-radius:14px;padding:14px;background:var(--bg-body)">
                  <span style="font-size:24px"><?= e($ch['icon'] ?: '📞') ?></span>
                  <div style="min-width:0">
                    <div style="font-weight:800;font-size:13px"><?= e($ch['label']) ?></div>
                    <div style="font-size:12px;color:var(--text-secondary);direction:ltr;text-align:right;word-break:break-all"><?= e($ch['value']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
<div style="display:grid;grid-template-columns:340px minmax(0,1fr);gap:20px;align-items:start">

          <!-- قائمة التذاكر -->
          <div class="card" style="padding:0;overflow:hidden">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
              <h3 class="h3" style="font-size:16px">🗂️ تذاكري</h3>
              <a href="support.php" class="btn btn-outline btn-sm" style="font-size:12px">➕ جديدة</a>
            </div>
            <?php if (empty($convos)): ?>
              <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">لا توجد تذاكر بعد.</div>
            <?php else: ?>
              <?php foreach ($convos as $c):
                $lbl = $statusLabels[$c['status']] ?? $statusLabels['open'];
              ?>
                <a href="support.php?conv=<?= (int) $c['id'] ?>" class="chat-contact <?= (int)$c['id'] === $activeConvId ? 'active' : '' ?>" style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:block;text-decoration:none" data-conv="<?= (int) $c['id'] ?>">
                  <div style="display:flex;justify-content:space-between;gap:8px">
                    <div style="font-weight:700;font-size:13px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['subject']) ?></div>
                    <span data-role="badge" style="background:var(--primary);color:#fff;border-radius:50%;width:18px;height:18px;display:<?= $c['unread_admin'] > 0 ? 'flex' : 'none' ?>;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= (int)$c['unread_admin'] ?></span>
                  </div>
                  <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:10px;color:<?= $lbl[1] ?>;background:<?= $lbl[2] ?>;padding:2px 10px;border-radius:20px;font-weight:800"><?= $lbl[0] ?></span>
                    <span style="font-size:11px;color:var(--text-muted)"><?= date('d/m', strtotime($c['updated_at'])) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
<!-- نافذة الدردشة -->
          <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;min-height:480px">
            <?php if (!$activeConv): ?>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px;color:var(--text-secondary)">
                <div style="font-size:64px">💬</div>
                <div style="font-weight:700;margin:8px 0">ابدأ محادثة مع فريق الدعم</div>
                <div style="font-size:13px;text-align:center;max-width:360px">افتح تذكرة جديدة أعلاه وسيرد عليك فريق الإدارة في أقرب وقت.</div>
              </div>
            <?php else: ?>
              <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);background:var(--bg-body)">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div style="font-weight:800;font-size:15px"><?= e($activeConv['subject']) ?></div>
                  <?php $lbl = $statusLabels[$activeConv['status']] ?? $statusLabels['open']; ?>
                  <span style="font-size:10px;color:<?= $lbl[1] ?>;background:<?= $lbl[2] ?>;padding:2px 10px;border-radius:20px;font-weight:800"><?= $lbl[0] ?></span>
                </div>
              </div>

              <div class="chat-messages" id="supportMessages" style="flex:1;min-height:300px;padding:20px">
                <?php if (empty($messages)): ?>
                  <div id="supportPlaceholder" style="text-align:center;color:var(--text-muted);font-size:13px">بصدد الرد على تذكرتك...</div>
                <?php else: ?>
                  <?php foreach ($messages as $msg):
                    $isUser = ($msg['sender_type'] === 'user'); ?>
                    <div class="msg <?= $isUser ? 'msg-sent' : 'msg-received' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
                      <?php if (!$isUser): ?>
                        <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px">فريق الدعم 🛟</div>
                      <?php endif; ?>
                      <?php if (!empty($msg['file_path'])): ?>
                        <div style="display:flex;justify-content:<?= $isUser ? 'flex-end' : 'flex-start' ?>;margin-bottom:<?= !empty($msg['content']) ? '6px' : '0' ?>">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:<?= $isUser ? 'rgba(255,255,255,0.18)' : 'var(--primary-light)' ?>;color:<?= $isUser ? '#fff' : 'var(--primary)' ?>;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px">
                            <span style="font-size:16px">📎</span>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($msg['content'])): ?>
                        <div><?= nl2br(e($msg['content'])) ?></div>
                      <?php endif; ?>
                      <div class="msg-time" style="text-align:<?= $isUser ? 'left' : 'right' ?>"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <form method="POST" action="support.php?conv=<?= $activeConvId ?>" class="chat-input-area" id="supportForm" enctype="multipart/form-data" style="flex-shrink:0">
                <input type="hidden" name="send_support" value="1">
                <input type="hidden" name="conv_id" value="<?= $activeConvId ?>">
                <span style="cursor:pointer;font-size:20px;color:var(--text-secondary);flex-shrink:0" onclick="document.getElementById('supportFileInput').click()" title="إرفاق ملف">📎
                  <input type="file" id="supportFileInput" name="support_file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
                </span>
                <span id="supportFileName" style="display:none;font-size:11px;color:var(--text-secondary);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0"></span>
                <input type="text" name="content" id="supportInput" placeholder="اكتب رسالتك..." class="form-input" style="border-radius:40px;padding:12px 20px;flex:1" autocomplete="off">
                <button type="submit" class="btn btn-primary" style="border-radius:50%;width:48px;height:48px;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0">➔</button>
              </form>
            <?php endif; ?>
          </div>

        </div>
<!-- الأسئلة الشائعة -->
        <?php if (!empty($faqs)): ?>
          <div class="card" style="padding:24px;margin-top:24px">
            <h3 class="h3" style="margin-bottom:20px">❓ الأسئلة الشائعة</h3>
            <div id="faqAccordion">
              <?php foreach ($faqs as $faq): ?>
                <div style="border:1px solid var(--border-color);border-radius:12px;margin-bottom:10px;overflow:hidden">
                  <button type="button" onclick="toggleFaq(this)" style="width:100%;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;background:var(--bg-body);border:none;cursor:pointer;font-family:inherit;font-size:14px;font-weight:700;color:var(--text-primary);text-align:right">
                    <span><?= e($faq['question']) ?></span>
                    <span style="font-size:18px;color:var(--primary);transition:transform .25s">▾</span>
                  </button>
                  <div class="faq-answer" style="display:none;padding:4px 18px 16px;font-size:13px;color:var(--text-secondary);line-height:1.8;border-top:1px solid var(--border-color)">
                    <?= nl2br(e($faq['answer'])) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <script>
        window.SUPPORT_APP_BASE = <?= json_encode($appBase) ?>;
        window.SUPPORT_CONV = <?= (int) $activeConvId ?>;
        window.SUPPORT_LASTID = <?= (int) $lastMsgIdPhp ?>;
        window.SUPPORT_TYPE = 'student';
      </script>
      <script src="support-app.js?v=4"></script>
      <script>
        /* السكربت المضمّن القديم استُبدل بملف support-app.js */
      </script>

<?php require __DIR__ . '/partials/footer.php'; ?>