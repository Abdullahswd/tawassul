<?php
/**
 * Support polling endpoint for students.
 * GET: conv (conversation id), after (last message id)
 */
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/support_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensureSupportTables();
$uid   = (int) $_SESSION['user_id'];
$db    = db();
$conv  = (int) ($_GET['conv'] ?? 0);
$after = (int) ($_GET['after'] ?? 0);

$response = ['ok' => true, 'messages' => [], 'last_id' => $after];

if ($conv > 0) {
    $chk = $db->prepare("SELECT id FROM support_conversations WHERE id = ? AND user_id = ? AND user_type = 'student' LIMIT 1");
    $chk->execute([$conv, $uid]);
    if ($chk->fetchColumn()) {
        $stmt = $db->prepare('SELECT id, sender_type, content, file_path, file_name, file_size, created_at
                              FROM support_messages WHERE conversation_id = ? AND id > ?
                              ORDER BY id ASC LIMIT 100');
        $stmt->execute([$conv, $after]);
        $rows = $stmt->fetchAll();
        if ($rows) {
            $db->prepare("UPDATE support_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'admin' AND is_read = 0")
               ->execute([$conv]);
            $response['messages'] = $rows;
            $response['last_id']  = (int) end($rows)['id'];
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);