<?php
require_once __DIR__ . '/config/functions.php';
$db = db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $order_id = 1;
    $user_id = 2;
    $order = getOrderById($order_id);
    
    $old_status = $order['status'];
    $reason = "Test cancellation reason";
    
    // Simulate Request Cancel
    $db->prepare("UPDATE orders SET status = 'cancellation_requested', previous_status = ?, cancellation_reason = ? WHERE id = ?")->execute([$old_status, $reason, $order_id]);
    
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
