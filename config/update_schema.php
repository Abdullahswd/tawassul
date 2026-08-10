<?php
/**
 * Database Schema Update for Tawassul
 */
require_once __DIR__ . '/db.php';

try {
    $db = db();
    
    // 1. Alter orders status column to add pending_assignment and assigned
    $db->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending_assignment','assigned','new','accepted','in_progress','revision','completed','cancelled') NOT NULL DEFAULT 'pending_assignment'");
    echo "Successfully updated orders table status column.\n";
    
    // 2. Create order_assignments table
    $db->exec("CREATE TABLE IF NOT EXISTS `order_assignments` (
        `order_id` INT UNSIGNED NOT NULL,
        `academic_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`order_id`, `academic_id`),
        CONSTRAINT `fk_oa_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_oa_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Successfully created order_assignments table.\n";
    
} catch (Exception $e) {
    echo "Database Update Error: " . $e->getMessage() . "\n";
    exit(1);
}
