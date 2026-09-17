<?php
/**
 * Support admin AJAX handler.
 * Actions: send_reply, set_status, save_faq, delete_faq, save_channel, delete_channel
 */

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/support_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'غير مصرح بالتفاعض'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensureSupportTables();
$action = $_POST['action'] ?? '';

// رد الإدارة على تذكرة (نص + ملف اختياري)
if ($action === 'send_reply') {
    $convId  = (int) ($_POST['conv_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if ($convId <= 0 || ($content === '' && empty($_FILES['file']['name']))) {
        echo json_encode(['ok' => false, 'error' => 'معلومات غير مكتملة'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $chk = db()->prepare('SELECT * FROM support_conversations WHERE id = ? LIMIT 1');
    $chk->execute([$convId]);
    $conv = $chk->fetch();
    if (!$conv) {
        echo json_encode(['ok' => false, 'error' => 'التذكرة غير موجودة'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $fileInfo = saveChatAttachmentUpload($_FILES['file'] ?? null);
    sendSupportMessage($convId, 'admin', (int) $_SESSION['user_id'], $content, $fileInfo);
    setSupportConversationStatus($convId, 'open');

    // إشعار المستخدم
    $uid  = (int) $conv['user_id'];
    $utype = $conv['user_type'];
    createNotification(
        $uid,
        $utype,
        'رد فريق الدعم 🛟',
        'لقد رد فريق الدعم على تذكرتك: ' . mb_substr($conv['subject'], 0, 60, 'UTF-8'),
        '🛟',
        $utype === 'student' ? 'student/support.php?conv=' . $convId : 'academics/academic-support.php?conv=' . $convId
    );

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// تغيير حالة التذكرة
if ($action === 'set_status') {
    $convId = (int) ($_POST['conv_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    setSupportConversationStatus($convId, $status);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// حفظ سؤال شائع
if ($action === 'save_faq') {
    saveSupportFaq([
        'id'         => (int) ($_POST['id'] ?? 0),
        'audience'   => $_POST['audience'] ?? 'student',
        'question'   => trim($_POST['question'] ?? ''),
        'answer'     => trim($_POST['answer'] ?? ''),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active'  => (int) ($_POST['is_active'] ?? 1),
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// حذف سؤال شائع
if ($action === 'delete_faq') {
    deleteSupportFaq((int) ($_POST['id'] ?? 0));
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// حفظ قناة تواصل
if ($action === 'save_channel') {
    saveSupportChannel([
        'id'         => (int) ($_POST['id'] ?? 0),
        'type'       => $_POST['type'] ?? 'other',
        'icon'       => trim($_POST['icon'] ?? '📞'),
        'label'      => trim($_POST['label'] ?? ''),
        'value'      => trim($_POST['value'] ?? ''),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active'  => (int) ($_POST['is_active'] ?? 1),
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// حذف قناة تواصل
if ($action === 'delete_channel') {
    deleteSupportChannel((int) ($_POST['id'] ?? 0));
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'إجراء غير معروف'], JSON_UNESCAPED_UNICODE);