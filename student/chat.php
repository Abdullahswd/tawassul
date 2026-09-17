<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db   = db();
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
ensureMessageFileColumns();

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
    $isAjax   = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

    $sent = false;
    // السماح بإرسال ملف فقط أو نص فقط أو الاثنين معاً
    if (($content !== '' || !empty($_FILES['chat_file']['name'])) && $conv_id) {
        // Verify this conversation belongs to the student
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
                    ($fileInfo ? 'أرفق ' : 'أرسل ') . 'الطالب رسالة في محادثة الطلب #' . ($active_conv['order_number'] ?? ''),
                    '💬',
                    'academics/academic-order-details.php?id=' . ($active_conv['order_number'] ?? '')
                );
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $sent], JSON_UNESCAPED_UNICODE);
        exit;
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
      grid-template-columns: 320px minmax(0, 1fr);
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
      min-height: 0;
    }
    .chat-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
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
    .chat-main {
      display: flex;
      flex-direction: column;
      background: var(--bg-body);
      min-height: 0;
      min-width: 0;
    }
    .chat-header {
      padding: 16px 24px;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border-color);
      display: flex; align-items: center; justify-content: space-between;
      flex-shrink: 0;
    }
    .chat-messages {
      flex: 1 1 auto;
      min-height: 0;
      padding: 24px; overflow-y: auto; overscroll-behavior: contain;
      display: flex; flex-direction: column; gap: 16px;
    }
    .msg {
      max-width: 70%; padding: 12px 16px; border-radius: 16px;
      font-size: 14px; line-height: 1.6;
      flex-shrink: 0;
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
      flex-shrink: 0;
    }
    .empty-chat {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      color: var(--text-secondary); gap: 12px;
      min-height: 0;
    }
    @media (max-width: 768px) {
      .chat-layout { grid-template-columns: 1fr; height: calc(100dvh - 100px); }
      .chat-sidebar { display: none; }
      .chat-input-area { padding: 12px 16px; }
    }
    /* شريط تمرير رفيع وأنيق داخل الشات */
    .chat-messages::-webkit-scrollbar,
    .chat-list::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-track,
    .chat-list::-webkit-scrollbar-track { background: transparent; }
    .chat-messages::-webkit-scrollbar-thumb,
    .chat-list::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 9999px; }
    .chat-messages::-webkit-scrollbar-thumb:hover,
    .chat-list::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
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
                  <a href="chat.php?conv=<?= $conv['id'] ?>" class="chat-contact <?= $isActive ? 'active' : '' ?>" data-conv="<?= (int)$conv['id'] ?>">
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;flex-shrink:0">
                      <?= e($conv['academic_avatar'] ?: $avatarLetter) ?>
                    </div>
                    <div style="overflow:hidden;flex:1">
                      <div style="display:flex;justify-content:space-between;align-items:baseline">
                        <span style="font-weight:700;font-size:14px;color:var(--text-primary)"><?= e($conv['academic_name']) ?></span>
                        <span style="font-size:11px;color:var(--text-muted)" data-role="time"><?= $conv['last_time'] ? date('H:i', strtotime($conv['last_time'])) : '' ?></span>
                      </div>
                      <div style="display:flex;justify-content:space-between;align-items:center">
                        <div style="font-size:12px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px" data-role="lastmsg">
                          <?= e($conv['last_message'] ?: 'الطلب: ' . $conv['order_number']) ?>
                        </div>
                        <span data-role="badge" style="background:var(--primary);color:white;border-radius:50%;width:18px;height:18px;display:<?= $conv['unread_count'] > 0 ? 'flex' : 'none' ?>;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= (int)$conv['unread_count'] ?></span>
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
                    <div class="chat-date-sep" style="text-align:center;font-size:12px;color:var(--text-muted);margin:8px 0"><?= date('d/m/Y', strtotime($msg['sent_at'])) ?></div>
                  <?php endif; ?>
                    <div class="msg <?= $isSent ? 'msg-sent' : 'msg-received' ?>" data-msg-id="<?= (int)$msg['id'] ?>" data-date="<?= $msgDate ?>">
                      <?php if (!$isSent): ?>
                        <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px">
                          <?= e($msg['sender_name'] ?? 'الأكاديمي') ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($isSent && !empty($msg['file_path'])): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;justify-content:flex-end">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.18);color:#fff;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none">
                            <span style="font-size:16px">📎</span>
                            <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                          </a>
                        </div>
                      <?php elseif (!$isSent && !empty($msg['file_path'])): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;justify-content:flex-start">
                          <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none">
                            <span style="font-size:16px">📎</span>
                            <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
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

              <!-- Input -->
              <form method="POST" action="chat.php?conv=<?= $active_conv_id ?>" class="chat-input-area" id="chatForm" enctype="multipart/form-data">
                <input type="hidden" name="send_message" value="1">
                <input type="hidden" name="conv_id" value="<?= $active_conv_id ?>">
                <label title="إرفاق ملف (حتى 10MB)" style="cursor:pointer;font-size:20px;color:var(--text-secondary);flex-shrink:0">
                  📎
                  <input type="file" name="chat_file" id="chatFileInput" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
                </label>
                <span id="chatFileName" style="display:none;font-size:11px;color:var(--text-secondary);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0"></span>
                <input type="text" name="content" id="chatInput" placeholder="اكتب رسالتك هنا..." class="form-input" style="border-radius:40px;padding:12px 20px;flex:1" autocomplete="off">
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
// آخر معرف رسالة معروض حالياً + آخر تاريخ معروض (لفواصل الأيام)
$lastMsgIdPhp = 0;
foreach ($messages as $m) { $lastMsgIdPhp = max($lastMsgIdPhp, (int)$m['id']); }
?>
  <script>
    // Auto-scroll to bottom of messages
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

    /* ─────────────────────────────────────────────
       تحديث فوري للمحادثة (AJAX Polling خفيف)
       - كل 5 ثوانٍ: جلب الرسائل الأحدث فقط (id > lastMsgId) + ملخص المحادثات
       - يتوقف مؤقتاً عند إخفاء التبويب لتقليل الحمل على السيرفر
    ───────────────────────────────────────────── */
    const convId    = <?= (int)$active_conv_id ?>;
    const APP_BASE  = <?= json_encode($appBase) ?>;
    let   lastMsgId = <?= (int)$lastMsgIdPhp ?>;
    let   lastDate  = (() => {
      const msgs = document.querySelectorAll('#chatMessages .msg[data-date]');
      return msgs.length ? msgs[msgs.length - 1].dataset.date : '';
    })();
    let sending = false;

    function escapeHtml(s) {
      const d = document.createElement('div');
      d.textContent = s == null ? '' : String(s);
      return d.innerHTML;
    }

    function scrollToBottom(smooth) {
      if (!chatMessages) return;
      chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    function appendMessage(m) {
      // إزالة رسالة "ابدأ المحادثة" عند وصول أول رسالة
      const placeholder = chatMessages.querySelector('div[style*="text-align:center"]');
      if (placeholder && !chatMessages.querySelector('.msg')) placeholder.remove();

      const mDate = (m.sent_at || '').substring(0, 10);
      const mTime = (m.sent_at || '').substring(11, 16) || '';
      const isSent = m.sender_type === 'student';

      if (mDate && mDate !== lastDate) {
        lastDate = mDate;
        const sep = document.createElement('div');
        sep.className = 'chat-date-sep';
        sep.style.cssText = 'text-align:center;font-size:12px;color:var(--text-muted);margin:8px 0';
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
        name.style.cssText = 'font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px';
        name.textContent = m.sender_name || 'الأكاديمي';
        wrap.appendChild(name);
      }

      const body = document.createElement('div');
      if (m.file_path) {
        // فقاعة ملف داخل الرسالة (تعرض مكان النص أو قبله)
        const fileRow = document.createElement('div');
        fileRow.style.cssText = 'margin-bottom:' + (m.content ? '6px' : '0') + ';display:flex;align-items:center';
        fileRow.style.justifyContent = isSent ? 'flex-end' : 'flex-start';
        const aLink = document.createElement('a');
        aLink.href = APP_BASE + '/' + m.file_path;
        aLink.target = '_blank';
        aLink.download = '';
        aLink.style.cssText = 'display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px';
        if (isSent) {
          aLink.style.background = 'rgba(255,255,255,0.18)';
          aLink.style.color = '#fff';
        } else {
          aLink.style.background = 'var(--primary-light)';
          aLink.style.color = 'var(--primary)';
        }
        const ic = document.createElement('span');
        ic.style.fontSize = '16px';
        ic.textContent = '📎';
        const nm = document.createElement('span');
        nm.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
        nm.textContent = m.file_name || 'ملف';
        aLink.appendChild(ic);
        aLink.appendChild(nm);
        fileRow.appendChild(aLink);
        body.appendChild(fileRow);
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

    function updateSidebar(convs) {
      if (!Array.isArray(convs)) return;
      let unknownConv = false;
      convs.forEach(function (c) {
        const row = document.querySelector('.chat-contact[data-conv="' + c.id + '"]');
        if (!row) { unknownConv = true; return; }
        const lm = row.querySelector('[data-role="lastmsg"]');
        const tm = row.querySelector('[data-role="time"]');
        const bd = row.querySelector('[data-role="badge"]');
        if (lm && c.last_message) lm.textContent = c.last_message;
        if (tm && c.last_time) tm.textContent = (c.last_time || '').substring(11, 16);
        if (bd) {
          const n = parseInt(c.unread_count, 10) || 0;
          if (String(c.id) === String(convId)) {
            bd.style.display = 'none'; // المحادثة المفتوحة تُقرأ تلقائياً
          } else {
            bd.textContent = n;
            bd.style.display = n > 0 ? 'flex' : 'none';
          }
        }
      });
      // ظهور محادثة جديدة غير موجودة في القائمة → تحديث الصفحة مرة واحدة
      if (unknownConv && !window.__convReloaded) {
        window.__convReloaded = true;
        location.reload();
      }
    }

    async function pollChat() {
      if (document.hidden || !convId || sending) return;
      try {
        const res = await fetch('ajax/chat-poll.php?conv=' + convId + '&after=' + lastMsgId, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        if (Array.isArray(data.messages) && data.messages.length) {
          const nearBottom = chatMessages ? (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < 150 : false;
          data.messages.forEach(appendMessage);
          lastMsgId = data.last_id || lastMsgId;
          if (nearBottom) scrollToBottom(true);
        }
        updateSidebar(data.convs);
      } catch (e) { /* تجاهل أخطاء الشبكة المؤقتة */ }
    }
    setInterval(pollChat, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) pollChat(); });

    /* ── إرسال الرسالة بدون إعادة تحميل الصفحة ── */
    const chatForm = document.getElementById('chatForm');
    const chatFileInput = document.getElementById('chatFileInput');
    const chatFileName = document.getElementById('chatFileName');

    // إظهار اسم الملف المحدد بجانب الحقل
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
        if (!content && !hasFile) return; // لا شيء للإرسال
        sending = true;
        try {
          const fd = new FormData(chatForm);
          const res = await fetch(chatForm.getAttribute('action'), {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          if (res.ok) {
            input.value = '';
            if (chatFileInput) chatFileInput.value = '';
            if (chatFileName) chatFileName.style.display = 'none';
            input.focus();
            await pollChat(); // جلب الرسالة المرسلة فوراً وعرضها
          }
        } catch (err) {
          // في حال فشل الاتصال: إرسال تقليدي (form.submit() يتجاوز معالج الأحداث فلا يحدث تكرار)
          chatForm.submit();
        } finally {
          sending = false;
        }
      });
    }
  </script>
<?php
$extraJs = ob_get_clean();
require __DIR__ . '/partials/footer.php';
?>
