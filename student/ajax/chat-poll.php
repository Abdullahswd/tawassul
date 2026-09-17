<?php
/**
 * Lightweight polling endpoint for student chat.
 * Returns only NEW messages (id > after) for the active conversation
 * plus a light summary of all conversations (for sidebar badges).
 *
 * GET params: conv (int, optional), after (int, last known message id)
 * Output: JSON
 */
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = (int) $_SESSION['user_id'];
$db  = db();

$convId = (int) ($_GET['conv'] ?? 0);
$after  = (int) ($_GET['after'] ?? 0);

$response = ['ok' => true, 'messages' => [], 'last_id' => $after, 'convs' => []];

// ── رسائل جديدة للمحادثة النشطة (مع التحقق من الملكية) ──
if ($convId > 0) {
    $chk = $db->prepare('SELECT id FROM conversations WHERE id = ? AND student_id = ? LIMIT 1');
    $chk->execute([$convId, $uid]);
    if ($chk->fetch()) {
        $stmt = $db->prepare(
            'SELECT m.id, m.sender_type, m.content, m.sent_at,
                    m.file_path, m.file_name, m.file_type, m.file_size,
                    CASE WHEN m.sender_type = "academic" THEN a.name ELSE u.name END AS sender_name
             FROM messages m
             LEFT JOIN academics a ON m.sender_type = "academic" AND m.sender_id = a.id
             LEFT JOIN users u     ON m.sender_type = "student"  AND m.sender_id = u.id
             WHERE m.conversation_id = ? AND m.id > ?
             ORDER BY m.id ASC
             LIMIT 100'
        );
        $stmt->execute([$convId, $after]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            // تعليم رسائل الأكاديمي كمقروءة (الطالب يرى الصفحة فعلاً)
            $db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = "academic" AND is_read = 0')
               ->execute([$convId]);
            $response['messages'] = $rows;
            $response['last_id']  = (int) end($rows)['id'];
        }
    }
}

// ── ملخص خفيف لقائمة المحادثات (آخر رسالة + غير المقروء) ──
$stmt2 = $db->prepare(
    'SELECT c.id,
            (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
            (SELECT sent_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_time,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = "academic" AND m.is_read = 0) AS unread_count
     FROM conversations c
     WHERE c.student_id = ?'
);
$stmt2->execute([$uid]);
$response['convs'] = $stmt2->fetchAll();

echo json_encode($response, JSON_UNESCAPED_UNICODE);