<?php
/**
 * Migration: Add package_subscriptions table
 * Run once to create the table if it doesn't exist.
 */
require_once __DIR__ . '/db.php';

$db = db();

try {
    $db->exec("DROP TABLE IF EXISTS `package_subscriptions`");
    $db->exec("
        CREATE TABLE `package_subscriptions` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `student_id`  INT UNSIGNED NOT NULL,
          `package_id`  INT UNSIGNED NOT NULL,
          `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `expires_at`  DATETIME NOT NULL,
          `status`      ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
          `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_student`   (`student_id`),
          INDEX `idx_package`   (`package_id`),
          INDEX `idx_status`    (`status`),
          CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_ps_package` FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo '<div style="font-family:sans-serif;padding:20px;color:green;font-size:18px">
        ✅ تم بنجاح: جدول <code>package_subscriptions</code> جاهز في قاعدة البيانات.
        <br><br>
        <a href="../student/packages.php" style="font-size:14px">العودة لصفحة الباقات</a>
    </div>';
} catch (Exception $e) {
    echo '<div style="font-family:sans-serif;padding:20px;color:red;font-size:16px">
        ❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}