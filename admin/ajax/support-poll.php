<?php
/**
 * Support polling endpoint for admin.
 * GET: tab (students|academics), conv (conversation id), after (last message id)
 * Returns: new messages of open conversation + conversation-list summaries (both tabs)
 *          + unread counts, so the admin list updates in realtime.
 */
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/support_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensureSupportTables();
$db    = db();
$tab   = ($_GET['tab'] ?? '') === 'academics' ? 'academics' : 'students';
$conv  = (int) ($_GET['conv'] ?? 0);
$after = (int) ($_GET['after'] ?? 0);

$response = [
    'ok' => true,
    'messages' => [],
    'last_id'  => $after,
    'students' => [],
    'academics' => [],
    'unread_students' => 0,
    'unread_academics' => 0,
];

// رسائل جديدة للتذكرة المفتوحة
if ($conv > 0) {
    $stmt = $db->prepare('SELECT id, sender_type, content, file_path, file_name, file_size, created_at
                          FROM support_messages WHERE conversation_id = ? AND id > ?
                          ORDER BY id ASC LIMIT 100');
    $stmt->execute([$conv, $after]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $db->prepare("UPDATE support_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'user' AND is_read = 0")
           ->execute([$conv]);
        $response['messages'] = $rows;
        $response['last_id']  = (int) end($rows)['id'];
    }
}

// ملخص قوائم التذاكر (يُحدَّث دائماً ليعكس أي رسالة جديدة)
$response['students']  = getSupportConversationsForAdmin('student');
$response['academics'] = getSupportConversationsForAdmin('academic');
$response['unread_students']  = countUnreadSupportForAdmin('student');
$response['unread_academics'] = countUnreadSupportForAdmin('academic');

echo json_encode($response, JSON_UNESCAPED_UNICODE);