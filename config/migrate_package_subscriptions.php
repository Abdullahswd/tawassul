<?php
/**
 * Migration: Add package_subscriptions + package_subscription_uses tables
 * Run once to create tables / adjust columns if they don't exist.
 */
require_once __DIR__ . '/db.php';

$db = db();

try {
    // الاشتراك غير محدد بتاريخ: نجعل expires_at قابلة لأن تكون NULL
    try {
        $db->exec("ALTER TABLE `package_subscriptions` MODIFY `expires_at` DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
        // قد لا يوجد الجدول بعد أو تعديل مسبق — تجاهل
    }

    $db->exec("CREATE TABLE IF NOT EXISTS `package_subscriptions` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `student_id`  INT UNSIGNED NOT NULL,
          `package_id`  INT UNSIGNED NOT NULL,
          `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `expires_at`  DATETIME NULL DEFAULT NULL COMMENT 'NULL = lifetime (غير محدد بتاريخ)',
          `status`      ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
          `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_student` (`student_id`),
          INDEX `idx_package` (`package_id`),
          INDEX `idx_status`  (`status`),
          CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_ps_package` FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `package_subscription_uses` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `subscription_id` INT UNSIGNED NOT NULL,
          `service_id`      INT UNSIGNED NOT NULL,
          `used_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_use_sub_service` (`subscription_id`, `service_id`),
          INDEX `idx_use_sub`  (`subscription_id`),
          INDEX `idx_use_svc`  (`service_id`),
          CONSTRAINT `fk_use_sub` FOREIGN KEY (`subscription_id`) REFERENCES `package_subscriptions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_use_svc` FOREIGN KEY (`service_id`)      REFERENCES `services`(`id`)            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo '<div style="font-family:sans-serif;padding:20px;color:green;font-size:18px">
        ✅ تم بنجاح: جداول <code>package_subscriptions</code> و <code>package_subscription_uses</code> جاهزة.
        <br>الأشتراكات الآن غير محددة بتاريخ (lifetime).
        <br><br>
        <a href="../student/packages.php" style="font-size:14px">العودة لصفحة الباقات</a>
    </div>';
} catch (Exception $e) {
    echo '<div style="font-family:sans-serif;padding:20px;color:red;font-size:16px">
        ❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}