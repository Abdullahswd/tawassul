<?php
require_once __DIR__ . '/config/functions.php';
$db = db();
$output = [];

try {
    $db->exec("ALTER TABLE orders ADD COLUMN cancellation_reason TEXT NULL DEFAULT NULL");
    $output[] = "Added cancellation_reason to orders table.";
} catch (Exception $e) {
    $output[] = "cancellation_reason already exists or error: " . $e->getMessage();
}

try {
    $db->exec("ALTER TABLE orders ADD COLUMN previous_status VARCHAR(50) NULL DEFAULT NULL");
    $output[] = "Added previous_status to orders table.";
} catch (Exception $e) {
    $output[] = "previous_status already exists or error: " . $e->getMessage();
}

foreach ($output as $msg) {
    echo $msg . "<br>\n";
}
echo "<b>All done. Please delete this file now.</b>";
