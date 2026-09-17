<?php
/**
 * Lightweight polling endpoint for the academic chat.
 * Returns only NEW messages (id > after) for the order's conversation.
 *
 * GET params: order (order_number), after (last known message id)
 * Output: JSON
 */
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['academic_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

$academicId = (int) $_SESSION['academic_id'];
$db = db();

$orderNumber = trim($_GET['order'] ?? '');
$after       = (int) ($_GET['after'] ?? 0);

$response = ['ok' => true, 'messages' => [], 'last_id' => $after];

if ($orderNumber === '') {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق أن الطلب ملك له أو مسند إليه
$oStmt = $db->prepare(
    "SELECT o.id FROM orders o
     WHERE o.order_number = ?
       AND (o.academic_id = ? OR o.id IN (SELECT order_id FROM order_assignments WHERE academic_id = ?))
     LIMIT 1"
);
$oStmt->execute([$orderNumber, $academicId, $academicId]);
$orderId = (int) $oStmt->fetchColumn();

if ($orderId > 0) {
    $convStmt = $db->prepare('SELECT id FROM conversations WHERE order_id = ? LIMIT 1');
    $convStmt->execute([$orderId]);
    $conversationId = (int) $convStmt->fetchColumn();

    if ($conversationId > 0) {
        $stmt = $db->prepare(
            "SELECT m.id, m.sender_id, m.sender_type, m.content, m.sent_at,
                    m.file_path, m.file_name, m.file_type, m.file_size,
                    CASE
                        WHEN m.sender_type = 'student'  THEN u.name
                        WHEN m.sender_type = 'academic' THEN a.name
                        ELSE 'الإدارة'
                    END AS sender_name
             FROM messages m
             LEFT JOIN users u     ON m.sender_type = 'student'  AND m.sender_id = u.id
             LEFT JOIN academics a ON m.sender_type = 'academic' AND m.sender_id = a.id
             WHERE m.conversation_id = ? AND m.id > ?
             ORDER BY m.id ASC
             LIMIT 100"
        );
        $stmt->execute([$conversationId, $after]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            // تعليم رسائل الطالب كمقروءة (الأكاديمي يرى الصفحة فعلاً)
            $db->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'student' AND is_read = 0")
               ->execute([$conversationId]);
            $response['messages'] = $rows;
            $response['last_id']  = (int) end($rows)['id'];
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);