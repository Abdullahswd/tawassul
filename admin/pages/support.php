<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/support_functions.php';
requireAdmin();

ensureSupportTables();

$db = db();
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = rtrim(preg_replace('#/admin/.*$#', '', $scriptName), '/');

$tab = $_GET['tab'] ?? 'students';
if (!in_array($tab, ['students', 'academics', 'faqs', 'channels'], true)) $tab = 'students';

$studentConvs  = getSupportConversationsForAdmin('student');
$academicConvs = getSupportConversationsForAdmin('academic');
$faqsAll       = getSupportFaqs();
$channelsAll   = getSupportChannels(false);

$unreadStudents  = countUnreadSupportForAdmin('student');
$unreadAcademics = countUnreadSupportForAdmin('academic');

$activeConvId = (int) ($_GET['conv'] ?? 0);
$activeConv   = null;
$activeMsgs   = [];
if ($activeConvId) {
    foreach (array_merge($studentConvs, $academicConvs) as $c) {
        if ((int) $c['id'] === $activeConvId) { $activeConv = $c; break; }
    }
    if ($activeConv) {
        $activeMsgs = getSupportConversationMessages($activeConvId);
        $db->prepare("UPDATE support_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'user' AND is_read = 0")
           ->execute([$activeConvId]);
    }
}
$lastMsgIdPhp = 0;
foreach ($activeMsgs as $m) { $lastMsgIdPhp = max($lastMsgIdPhp, (int) $m['id']); }

$faqTypes = [
    'whatsapp' => ['واتساب', '🟢'], 'telegram' => ['تيليجرام', '✈️'], 'email' => ['بريد إلكتروني', '📧'],
    'phone' => ['هاتف', '📞'], 'instagram' => ['إنستغرام', '📸'], 'twitter' => ['تويتر', '𝕏'],
    'website' => ['موقع', '🌐'], 'other' => ['أخرى', '📞'],
];
$convStatuses = [
    'open'    => ['مفتوحة', '#16a34a', '#dcfce7'],
    'pending' => ['قيد المراجعة', '#d97706', '#fef3c7'],
    'closed'  => ['مغلقة', '#64748b', '#e2e8f0'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الدعم الفني والشكاوى - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .sup-tab { padding: 12px 20px; font-weight: 700; font-size: 14px; color: var(--text-secondary); border-bottom: 3px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .sup-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
    .sup-chat { height: 440px; overflow-y: auto; padding: 20px; background: var(--bg-main); border-radius: 14px; display: flex; flex-direction: column; gap: 16px; }
    .sup-msg { max-width: 72%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.6; }
    .sup-admin-msg { background: var(--primary); color: #fff; align-self: flex-end; border-top-left-radius: 4px; }
    .sup-user-msg { background: var(--bg-card); border: 1px solid var(--border-color); align-self: flex-start; border-top-right-radius: 4px; }
    .sup-time { font-size: 10px; color: var(--text-muted); margin-top: 6px; }
    .sup-admin-msg .sup-time { color: rgba(255,255,255,0.7); text-align: left; }
    .faq-item { border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-card); }
    .faq-item .faq-q { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 18px; cursor: pointer; }
    .faq-item .faq-a { display: none; padding: 4px 18px 16px; font-size: 13px; color: var(--text-secondary); line-height: 1.8; border-top: 1px dashed var(--border-color); }
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الدعم الفني والشكاوى</span></div>
          <h1 class="page-header-title">الدعم الفني والشكاوى 💬</h1>
          <p class="page-header-subtitle">إدارة تذاكر الدعم، الأسئلة الشائعة، وقنوات التواصل</p>
        </div>
      </div>

      <!-- التبويبات -->
      <div style="border-bottom:1px solid var(--border-color);margin-bottom:24px;display:flex;gap:4px;overflow-x:auto">
        <a class="sup-tab <?= $tab === 'students' ? 'active' : '' ?>" href="support.php?tab=students">👨‍🎓 دعم الطلاب
          <span id="badgeStudents" style="background:#ef4444;color:#fff;border-radius:10px;font-size:10px;padding:1px 8px"><?= $unreadStudents ?></span>
        </a>
        <a class="sup-tab <?= $tab === 'academics' ? 'active' : '' ?>" href="support.php?tab=academics">🎓 دعم الأكاديميين
          <span id="badgeAcademics" style="background:#ef4444;color:#fff;border-radius:10px;font-size:10px;padding:1px 8px"><?= $unreadAcademics ?></span>
        </a>
        <a class="sup-tab <?= $tab === 'faqs' ? 'active' : '' ?>" href="support.php?tab=faqs">❓ الأسئلة الشائعة</a>
        <a class="sup-tab <?= $tab === 'channels' ? 'active' : '' ?>" href="support.php?tab=channels">📞 قنوات التواصل</a>
      </div>
<?php if ($tab === 'students' || $tab === 'academics'): ?>
      <?php
        $convs  = ($tab === 'students') ? $studentConvs : $academicConvs;
        $label  = ($tab === 'students') ? 'الطلاب' : 'الأكاديميين';
      ?>
      <div style="display:grid;grid-template-columns:340px minmax(0,1fr);gap:20px;align-items:start">

        <!-- قائمة التذاكر -->
        <div class="table-container animate-fadeInUp" style="padding:0;overflow:hidden">
          <div style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:15px;font-weight:800">🗂️ تذاكر <?= $label ?></h3>
            <span class="badge" style="background:var(--bg-main);color:var(--text-secondary)"><?= count($convs) ?></span>
          </div>
          <?php if (empty($convs)): ?>
            <div id="convList"><?php /* تُعبَّأ عبر الاستقصاء */ ?>
              <div style="padding:28px;text-align:center;color:var(--text-muted);font-size:13px">لا توجد تذاكر من <?= $label ?> بعد.</div>
            </div>
          <?php else: ?>
            <div id="convList">
            <?php foreach ($convs as $c):
              $st = $convStatuses[$c['status']] ?? $convStatuses['open'];
              $isSel = (int) $c['id'] === $activeConvId;
            ?>
              <a href="support.php?tab=<?= $tab ?>&conv=<?= (int) $c['id'] ?>" style="display:block;padding:14px 18px;border-bottom:1px solid var(--border-color);text-decoration:none;<?= $isSel ? 'background:var(--bg-main);border-right:3px solid var(--primary);' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                  <div style="min-width:0">
                    <div style="font-weight:800;font-size:13px;color:var(--text-primary)"><?= e($c['user_name'] ?? 'مستخدم') ?></div>
                    <div style="font-size:12px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px"><?= e($c['subject']) ?></div>
                  </div>
                  <?php if ($c['unread_user'] > 0): ?>
                    <span style="background:var(--primary);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= (int)$c['unread_user'] ?></span>
                  <?php endif; ?>
                </div>
                <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center">
                  <span style="font-size:10px;color:<?= $st[1] ?>;background:<?= $st[2] ?>;padding:2px 10px;border-radius:20px;font-weight:800"><?= $st[0] ?></span>
                  <span style="font-size:11px;color:var(--text-muted)"><?= $c['last_time'] ? date('d/m H:i', strtotime($c['last_time'])) : date('d/m H:i', strtotime($c['created_at'])) ?></span>
                </div>
              </a>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
<!-- نافذة المحادثة -->
        <div class="table-container animate-fadeInUp" style="padding:24px">
          <?php if (!$activeConv): ?>
            <div style="text-align:center;padding:64px;color:var(--text-muted)">
              <div style="font-size:56px">💬</div>
              <div style="font-weight:700;margin-top:8px">اختر تذكرة من القائمة للرد عليها</div>
            </div>
          <?php else: ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
              <div>
                <div style="font-weight:800;font-size:15px"><?= e($activeConv['user_name'] ?? 'مستخدم') ?></div>
                <div style="font-size:12px;color:var(--text-secondary)"><?= e($activeConv['subject']) ?><?= !empty($activeConv['user_email']) ? ' • ' . e($activeConv['user_email']) : '' ?></div>
              </div>
              <div style="display:flex;gap:8px;align-items:center">
                <?php $st = $convStatuses[$activeConv['status']] ?? $convStatuses['open']; ?>
                <span style="font-size:10px;color:<?= $st[1] ?>;background:<?= $st[2] ?>;padding:2px 10px;border-radius:20px;font-weight:800"><?= $st[0] ?></span>
                <?php if ($activeConv['status'] !== 'closed'): ?>
                  <button class="btn btn-outline btn-sm" onclick="closeConv(<?= (int) $activeConv['id'] ?>)">إغلاق التذكرة 🔒</button>
                <?php else: ?>
                  <button class="btn btn-outline btn-sm" onclick="reopenConv(<?= (int) $activeConv['id'] ?>)">إعادة فتح 🔓</button>
                <?php endif; ?>
              </div>
            </div>

            <div class="sup-chat" id="supChatBox">
              <?php if (empty($activeMsgs)): ?>
                <div id="supPlaceholder" style="text-align:center;color:var(--text-muted);font-size:13px;margin:auto">لا توجد رسائل بعد.</div>
              <?php else: ?>
                <?php foreach ($activeMsgs as $msg):
                  $isAdmin = ($msg['sender_type'] === 'admin'); ?>
                  <div class="sup-msg <?= $isAdmin ? 'sup-admin-msg' : 'sup-user-msg' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
                    <?php if (!$isAdmin): ?>
                      <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px"><?= $tab === 'students' ? 'الطالب' : 'الأكاديمي' ?>: <?= e($activeConv['user_name'] ?? '') ?></div>
                    <?php else: ?>
                      <div style="font-size:11px;font-weight:700;opacity:0.85;margin-bottom:3px">فريق الدعم</div>
                    <?php endif; ?>
                    <?php if (!empty($msg['file_path'])): ?>
                      <div style="margin-bottom:<?= !empty($msg['content']) ? '6px' : '0' ?>;text-align:<?= $isAdmin ? 'right' : 'left' ?>">
                        <a href="<?= e($appBase . '/' . $msg['file_path']) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;background:<?= $isAdmin ? 'rgba(255,255,255,0.2)' : 'var(--primary-light)' ?>;color:<?= $isAdmin ? '#fff' : 'var(--primary)' ?>;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px">
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

            <?php if ($activeConv['status'] !== 'closed'): ?>
              <div style="margin-top:16px;display:flex;gap:10px;align-items:flex-end">
                <input type="file" id="supReplyFile" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
                <span id="supFileName" style="display:none;font-size:11px;color:var(--text-secondary);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
                <textarea id="supReplyInput" rows="2" class="form-input" style="resize:none;flex:1" placeholder="اكتب ردك..."></textarea>
                <span style="cursor:pointer;font-size:20px;padding:8px;color:var(--text-secondary);flex-shrink:0" onclick="document.getElementById('supReplyFile').click()" title="إرفاق ملف">📎</span>
                <button class="btn btn-primary" style="padding:0 22px;height:auto" onclick="sendSupReply()">إرسال ✈️</button>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

      </div>
    <?php endif; ?>
<?php if ($tab === 'faqs'): ?>
      <div class="animate-fadeInUp">
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn btn-primary" onclick="openFaqModal()">+ إضافة سؤال شائع</button>
        </div>
        <?php foreach (['student', 'academic'] as $aud): ?>
          <div class="table-container" style="margin-bottom:20px">
            <div class="table-header"><h3 class="table-title"><?= $aud === 'student' ? '👨‍🎓 أسئلة الطلاب' : '🎓 أسئلة الأكاديميين' ?></h3></div>
            <div style="padding:14px">
              <?php $audFaqs = array_values(array_filter($faqsAll, fn($f) => $f['audience'] === $aud)); ?>
              <?php if (empty($audFaqs)): ?>
                <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px">لا توجد أسئلة لهذا القسم بعد.</div>
              <?php else: ?>
                <?php foreach ($audFaqs as $faq): ?>
                  <div class="faq-item" style="margin-bottom:10px" data-faq-id="<?= (int) $faq['id'] ?>">
                    <div class="faq-q" onclick="this.parentElement.querySelector('.faq-a').style.display = this.parentElement.querySelector('.faq-a').style.display === 'block' ? 'none' : 'block'">
                      <div style="font-weight:700;font-size:14px"><?= e($faq['question']) ?><?= !$faq['is_active'] ? ' <span class="badge" style="background:#e2e8f0;color:#64748b">مخفي</span>' : '' ?></div>
                      <div style="display:flex;gap:6px;flex-shrink:0">
                        <button class="btn btn-outline btn-sm" onclick="event.stopPropagation();openFaqModal(<?= (int) $faq['id'] ?>)">✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="event.stopPropagation();deleteFaq(<?= (int) $faq['id'] ?>)">🗑️</button>
                        <span style="color:var(--text-muted)">▾</span>
                      </div>
                    </div>
                    <div class="faq-a"><?= nl2br(e($faq['answer'])) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
<?php if ($tab === 'channels'): ?>
      <div class="animate-fadeInUp">
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn btn-primary" onclick="openChannelModal()">+ إضافة قناة تواصل</button>
        </div>
        <div class="table-container">
          <div class="table-header"><h3 class="table-title">قنوات التواصل</h3></div>
          <div style="padding:14px">
            <?php if (empty($channelsAll)): ?>
              <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px">لا توجد قنوات تواصل بعد.</div>
            <?php else: ?>
              <?php foreach ($channelsAll as $ch): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;border:1px solid var(--border-color);border-radius:12px;padding:12px 16px;margin-bottom:10px;background:var(--bg-card)">
                  <div style="display:flex;align-items:center;gap:12px;min-width:0">
                    <span style="font-size:24px"><?= e($ch['icon'] ?: '📞') ?></span>
                    <div style="min-width:0">
                      <div style="font-weight:800;font-size:13px"><?= e($ch['label']) ?><?= !$ch['is_active'] ? ' <span class="badge" style="background:#e2e8f0;color:#64748b">مخفي</span>' : '' ?></div>
                      <div style="font-size:12px;color:var(--text-secondary);direction:ltr;text-align:right;word-break:break-all"><?= e($ch['value']) ?></div>
                    </div>
                  </div>
                  <div style="display:flex;gap:6px;flex-shrink:0">
                    <button class="btn btn-outline btn-sm" onclick="openChannelModal(<?= (int) $ch['id'] ?>)">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteChannel(<?= (int) $ch['id'] ?>)">🗑️</button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    </div>
  </div>
</div>

<!-- مودال السؤال الشائع -->
<div class="modal-overlay" id="faqModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3 class="modal-title" id="faqModalTitle">إضافة سؤال شائع</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="faqId" value="0">
      <div class="form-group"><label class="form-label">القسم</label>
        <select class="form-input" id="faqAudience"><option value="student">الطلاب</option><option value="academic">الأكاديميون</option></select>
      </div>
      <div class="form-group"><label class="form-label">السؤال</label><input class="form-input" id="faqQuestion"></div>
      <div class="form-group"><label class="form-label">الإجابة</label><textarea class="form-input" id="faqAnswer" rows="4"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">ترتيب العرض</label><input type="number" class="form-input" id="faqSort" value="0"></div>
        <div class="form-group"><label class="form-label">ظاهر للجميع</label>
          <select class="form-input" id="faqActive"><option value="1">نعم</option><option value="0">مخفي</option></select>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-primary" onclick="saveFaq()">حفظ</button></div>
  </div>
</div>
<!-- مودال قناة التواصل -->
<div class="modal-overlay" id="channelModal">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header"><h3 class="modal-title" id="channelModalTitle">إضافة قناة تواصل</h3><button class="modal-close" data-modal-close>✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="channelId" value="0">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">النوع</label>
          <select class="form-input" id="channelType">
            <?php foreach ($faqTypes as $k => $v): ?><option value="<?= $k ?>"><?= $v[0] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" id="channelIcon" placeholder="📞" value="📞"></div>
      </div>
      <div class="form-group"><label class="form-label">الاسم المعروض</label><input class="form-input" id="channelLabel" placeholder="مثال: واتساب الدعم"></div>
      <div class="form-group"><label class="form-label">القيمة (رقم / بريد / رابط)</label><input class="form-input" id="channelValue" dir="ltr"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">ترتيب العرض</label><input type="number" class="form-input" id="channelSort" value="0"></div>
        <div class="form-group"><label class="form-label">ظاهر للجميع</label>
          <select class="form-input" id="channelActive"><option value="1">نعم</option><option value="0">مخفي</option></select>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-primary" onclick="saveChannel()">حفظ</button></div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
    window.ADMIN_APP_BASE = <?= json_encode($appBase) ?>;
  window.ADMIN_TAB = <?= json_encode($tab) ?>;
  window.ADMIN_CONV = <?= (int) $activeConvId ?>;
  window.ADMIN_LASTID = <?= (int) $lastMsgIdPhp ?>;
  window.ADMIN_POLL_URL = '../ajax/support-poll.php';
  window.ADMIN_AJAX_URL = '../ajax/support.php';
    window.ADMIN_STATUSES = <?= json_encode($convStatuses, JSON_UNESCAPED_UNICODE) ?>;
  window.ADMIN_FAQS = <?= json_encode($faqsAll, JSON_UNESCAPED_UNICODE) ?>;
  window.ADMIN_CHANNELS = <?= json_encode($channelsAll, JSON_UNESCAPED_UNICODE) ?>;
  window.ADMIN_TYPES = <?= json_encode($faqTypes, JSON_UNESCAPED_UNICODE) ?>;
  window.CSRF_TOKEN = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';
</script>
<script src="../assets/js/support-admin.js?v=<?= time() ?>"></script>
</body>
</html>