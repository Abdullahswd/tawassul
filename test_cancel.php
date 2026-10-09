<?php
require_once __DIR__ . '/config/functions.php';
$db = db();

try {
    $order_id = 1; // Assuming order 1 exists
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    $old_status = $order['status'];
    $reason = "Test reason";
    
    $db->prepare("UPDATE orders SET status = 'cancelled', previous_status = ?, cancellation_reason = ? WHERE id = ?")->execute([$old_status, $reason, $order_id]);
    echo "Update OK\n";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
