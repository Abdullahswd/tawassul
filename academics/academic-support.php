<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/support_functions.php';
requireAcademic();

ensureSupportTables();

$academicId = (int) ($_SESSION['academic_id'] ?? 0);
$db   = db();
$appBase = rtrim(preg_replace('#/academics/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// فتح تذكرة جديدة
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['new_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    if ($subject !== '') {
        $cid = createSupportConversation($academicId, 'academic', mb_substr($subject, 0, 255, 'UTF-8'));
        header('Location: academic-support.php?conv=' . $cid);
        exit;
    }
}

// إرسال رسالة
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['send_support'])) {
    $content = trim($_POST['content'] ?? '');
    $convId  = (int) ($_POST['conv_id'] ?? 0);
    $isAjax  = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    $sent = false;
    if ($convId && ($content !== '' || !empty($_FILES['support_file']['name']))) {
        $chk = $db->prepare("SELECT id FROM support_conversations WHERE id = ? AND user_id = ? AND user_type = 'academic' LIMIT 1");
        $chk->execute([$convId, $academicId]);
        if ($chk->fetchColumn()) {
            $fileInfo = saveChatAttachmentUpload($_FILES['support_file'] ?? null);
            sendSupportMessage($convId, 'user', $academicId, $content, $fileInfo);
            setSupportConversationStatus($convId, 'pending');
            createNotification(1, 'admin', 'رسالة دعم جديدة 💬', 'أرسل أكاديمي رسالة في تذكرة الدعم.', '💬', 'admin/pages/support.php');
            $sent = true;
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $sent], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: academic-support.php?conv=' . $convId);
    exit;
}

$faqs       = getSupportFaqs('academic');
$channels   = getSupportChannels();
$convos     = getSupportConversationsForUser($academicId, 'academic');

$activeConvId = (int) ($_GET['conv'] ?? ($convos[0]['id'] ?? 0));
$activeConv   = null;
$messages     = [];
if ($activeConvId) {
    foreach ($convos as $c) {
        if ((int) $c['id'] === $activeConvId) { $activeConv = $c; break; }
    }
    if ($activeConv) {
        $messages = getSupportConversationMessages($activeConvId);
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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الدعم الفني والشكاوى - لوحة تحكم الأكاديمي</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .sup-box { height: 420px; overflow-y: auto; padding: 20px; background: var(--bg-main); border-radius: 14px; display: flex; flex-direction: column; gap: 16px; }
    .sup-msg { max-width: 75%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.6; }
    .sup-user { background: var(--primary); color: #fff; align-self: flex-end; border-top-left-radius: 4px; }
    .sup-admin { background: var(--bg-card); border: 1px solid var(--border-color); align-self: flex-start; border-top-right-radius: 4px; }
    .sup-time { font-size: 10px; color: var(--text-muted); margin-top: 6px; }
    .sup-user .sup-time { color: rgba(255,255,255,0.7); text-align: left; }
    .sup-input { padding: 16px 0 0; display: flex; align-items: center; gap: 12px; }
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
          <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>الدعم الفني</span></div>
          <h1 class="page-title">الدعم الفني والشكاوى 💬</h1>
          <p style="font-size:14px;color:var(--text-secondary);margin-top:6px">استفسارات، أسئلة شائعة، وقنوات تواصل مع فريق المنصة.</p>
        </div>
      </div>
<?php if (empty($convos)): ?>
        <div class="card anim-up" style="padding:22px">
          <h3 style="font-size:17px;font-weight:800;margin-bottom:14px">📩 فتح تذكرة دعم</h3>
          <form method="POST" action="academic-support.php">
            <input type="hidden" name="new_ticket" value="1">
            <div class="flex gap-3 flex-wrap">
              <input type="text" name="subject" required style="flex:1;min-width:260px" class="form-input" placeholder="موضوع التذكرة، مثال: مشكلة في استلام المرفقات...">
              <button class="btn btn-primary">فتح تذكرة 🚀</button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!empty($channels)): ?>
        <div class="card anim-up" style="padding:22px">
          <h3 style="font-size:17px;font-weight:800;margin-bottom:14px">📞 قنوات التواصل</h3>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
            <?php foreach ($channels as $ch): ?>
              <div style="display:flex;align-items:center;gap:12px;border:1px solid var(--border-color);border-radius:14px;padding:14px;background:var(--bg-main)">
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

      <div style="display:grid;grid-template-columns:320px minmax(0,1fr);gap:20px;align-items:start">

        <!-- قائمة التذاكر -->
        <div class="card" style="padding:0;overflow:hidden">
          <div style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:16px;font-weight:800">🗂️ تذاكري</h3>
            <a href="academic-support.php" class="btn btn-outline btn-sm" style="font-size:12px">➕ جديدة</a>
          </div>
          <?php if (empty($convos)): ?>
            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">لا توجد تذاكر بعد.</div>
          <?php else: ?>
            <?php foreach ($convos as $c):
              $lbl = $statusLabels[$c['status']] ?? $statusLabels['open'];
            ?>
              <a href="academic-support.php?conv=<?= (int) $c['id'] ?>" style="display:block;padding:14px 18px;border-bottom:1px solid var(--border-color);text-decoration:none;<?= (int)$c['id'] === $activeConvId ? 'background:var(--primary-light);' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:8px">
                  <div style="font-weight:700;font-size:13px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['subject']) ?></div>
                  <?php if ($c['unread_admin'] > 0): ?>
                    <span style="background:var(--primary);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= (int)$c['unread_admin'] ?></span>
                  <?php endif; ?>
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
        <div class="card" style="padding:24px">
          <?php if (!$activeConv): ?>
            <div style="text-align:center;padding:48px;color:var(--text-secondary)">
              <div style="font-size:64px">💬</div>
              <div style="font-weight:700;margin:8px 0">ابدأ محادثة مع فريق الدعم</div>
              <div style="font-size:13px">افتح تذكرة جديدة أعلاه وسيرد عليك فريق الإدارة.</div>
            </div>
          <?php else: ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
              <div style="font-weight:800;font-size:15px"><?= e($activeConv['subject']) ?></div>
              <?php $lbl = $statusLabels[$activeConv['status']] ?? $statusLabels['open']; ?>
              <span style="font-size:10px;color:<?= $lbl[1] ?>;background:<?= $lbl[2] ?>;padding:2px 10px;border-radius:20px;font-weight:800"><?= $lbl[0] ?></span>
            </div>

            <div class="sup-box" id="supportMessages">
              <?php if (empty($messages)): ?>
                <div id="supportPlaceholder" style="text-align:center;color:var(--text-muted);font-size:13px;margin:auto">بصدد الرد على تذكرتك...</div>
              <?php else: ?>
                <?php foreach ($messages as $msg):
                  $isUser = ($msg['sender_type'] === 'user'); ?>
                  <div class="sup-msg <?= $isUser ? 'sup-user' : 'sup-admin' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
                    <?php if (!$isUser): ?>
                      <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px">فريق الدعم 💬</div>
                    <?php endif; ?>
                    <?php if (!empty($msg['file_path'])): ?>
                      <div style="margin-bottom:<?= !empty($msg['content']) ? '6px' : '0' ?>;text-align:<?= $isUser ? 'right' : 'left' ?>">
                        <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:<?= $isUser ? 'rgba(255,255,255,0.2)' : 'var(--primary-light)' ?>;color:<?= $isUser ? '#fff' : 'var(--primary)' ?>;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px">
                          <span>📎</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['file_name'] ?: 'ملف') ?></span>
                        </a>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($msg['content'])): ?>
                      <div><?= nl2br(e($msg['content'])) ?></div>
                    <?php endif; ?>
                    <div class="sup-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <form method="POST" action="academic-support.php?conv=<?= $activeConvId ?>" id="supportForm" class="sup-input" enctype="multipart/form-data">
              <input type="hidden" name="send_support" value="1">
              <input type="hidden" name="conv_id" value="<?= $activeConvId ?>">
              <textarea id="supportInput" name="content" rows="2" style="resize:none;flex:1" class="form-input" placeholder="اكتب رسالتك..."></textarea>
              <span style="cursor:pointer;font-size:20px;color:var(--text-secondary);flex-shrink:0" onclick="document.getElementById('supportFileInput').click()" title="إرفاق ملف">📎
                <input type="file" id="supportFileInput" name="support_file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
              </span>
              <span id="supportFileName" style="display:none;font-size:11px;color:var(--text-secondary);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0"></span>
              <button class="btn btn-primary" style="padding:0 24px;height:auto">إرسال ✈️</button>
            </form>
          <?php endif; ?>
        </div>

      </div>

      <?php if (!empty($faqs)): ?>
        <div class="card anim-up" style="padding:24px;margin-top:24px">
          <h3 style="font-size:17px;font-weight:800;margin-bottom:16px">❓ الأسئلة الشائعة</h3>
          <?php foreach ($faqs as $faq): ?>
            <div style="border:1px solid var(--border-color);border-radius:12px;margin-bottom:8px;overflow:hidden">
              <button type="button" onclick="toggleFaq(this)" style="width:100%;display:flex;justify-content:space-between;gap:12px;padding:14px 18px;background:var(--bg-main);border:none;cursor:pointer;font-family:inherit;font-size:14px;font-weight:700;color:var(--text-primary);text-align:right">
                <span><?= e($faq['question']) ?></span>
                <span style="color:var(--primary)">▾</span>
              </button>
              <div class="faq-answer" style="display:none;padding:4px 18px 16px;font-size:13px;color:var(--text-secondary);line-height:1.8;border-top:1px solid var(--border-color)">
                <?= nl2br(e($faq['answer'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div><!-- /.page-body -->
  </div>
</div>

<script src="assets/js/main.js"></script>
<script id="supportAppJs">
  window.CSRF_TOKEN = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';
  window.SUPPORT_APP_BASE = <?= json_encode($appBase) ?>;
  window.SUPPORT_CONV = <?= (int) $activeConvId ?>;
  window.SUPPORT_LASTID = <?= (int) $lastMsgIdPhp ?>;
</script>
<script src="support-app.js?v=<?= time() ?>"></script>
</body>
</html>