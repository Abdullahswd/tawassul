<?php
require 'config/functions.php';
$db = db();
echo "Testing Database Connection...\n";
if ($db) {
    echo "OK\n";
} else {
    echo "FAILED\n";
}

echo "\n--- SYSTEM STATS ---\n";
echo "Users: " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
echo "Academics: " . $db->query("SELECT COUNT(*) FROM academics")->fetchColumn() . "\n";
echo "Orders: " . $db->query("SELECT COUNT(*) FROM orders")->fetchColumn() . "\n";
echo "Payments: " . $db->query("SELECT COUNT(*) FROM payments")->fetchColumn() . "\n";

echo "\n--- TESTING CRITICAL QUERIES ---\n";
try {
    $q = $db->query("SELECT id, status FROM orders LIMIT 1");
    echo "Orders Table Accessible. OK\n";
} catch (Exception $e) {
    echo "Error in Orders: " . $e->getMessage() . "\n";
}
