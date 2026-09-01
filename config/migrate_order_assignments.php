<?php
/**
 * Migration: Add order_assignments table
 * Run once to create the table if it doesn't exist.
 */
require_once __DIR__ . '/db.php';

$db = db();

try {
    $db->exec("DROP TABLE IF EXISTS `order_assignments`");// Drop table first to ensure correct schema
    $db->exec("
        CREATE TABLE `order_assignments` (
          `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `order_id`       INT UNSIGNED NOT NULL,
          `academic_id`    INT UNSIGNED NOT NULL,
          `status`         ENUM('assigned','accepted','rejected') NOT NULL DEFAULT 'assigned',
          `assigned_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `response_at`    DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_order_academic` (`order_id`, `academic_id`),
          INDEX `idx_order`    (`order_id`),
          INDEX `idx_academic` (`academic_id`),
          CONSTRAINT `fk_oa_order`    FOREIGN KEY (`order_id`)    REFERENCES `orders`(`id`)    ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_oa_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo '<div style="font-family:sans-serif;padding:20px;color:green;font-size:18px">
        ✅ تم بنجاح: جدول <code>order_assignments</code> موجود ومجهّز في قاعدة البيانات.
        <br><br>
        <a href="../admin/index.php" style="font-size:14px">العودة للإدارة</a>
    </div>';
} catch (Exception $e) {
    echo '<div style="font-family:sans-serif;padding:20px;color:red;font-size:16px">
        ❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}
