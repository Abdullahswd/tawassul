<?php
/**
 * ============================================================
 *  Tawassul - Create "featured_academics" table
 *  Run this file ONCE in the browser to create the table:
 *  -> http://localhost/tawassul/config/update_featured_academics.php
 * ============================================================
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = db();

    // 1) Create the featured academics table
    $db->exec("CREATE TABLE IF NOT EXISTS `featured_academics` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(150) NOT NULL COMMENT 'اسم الأكاديمي',
        `specialty`   VARCHAR(150) DEFAULT NULL COMMENT 'التخصص',
        `bio`         TEXT DEFAULT NULL COMMENT 'نبذة تعريفية',
        `image`       VARCHAR(255) DEFAULT NULL COMMENT 'مسار الصورة',
        `sort_order`  INT NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
        `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ تم إنشاء جدول featured_academics بنجاح.<br>\n";

    // 2) Make sure the uploads/featured folder exists
    $dir = dirname(__DIR__) . '/uploads/featured';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    echo "✅ مجلد رفع الصور جاهز (uploads/featured).<br>\n";

    // 3) Insert a welcome sample row so the section is not empty
    $count = (int) $db->query("SELECT COUNT(*) FROM featured_academics")->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare(
            "INSERT INTO featured_academics (name, specialty, bio, image, sort_order, is_active)
             VALUES (?, ?, ?, ?, 1, 1)"
        );
        $stmt->execute([
            'البروفيسور خليل سعيد الوجيه',
            'دكتوراه في النمذجة والمحاكاة',
            'أكاديمي يمني بارز حاصل على الدكتوراه في النمذجة والمحاكاة، شغل مناصب قيادية رفيعة في مجال التعليم العالي.',
            null,
        ]);
        echo "✅ تم إدراج بطاقة تعريفية افتراضية.<br>\n";
    } else {
        echo "ℹ️ الجدول يحتوي بالفعل على بيانات، لم يتم إدراج بطاقة افتراضية.<br>\n";
    }

    echo "<br>تم بنجاح. يمكنك الآن الذهاب إلى <a href='../admin/pages/featured-academics.php'>صفحة الأكاديميين المتميزين</a>.";

} catch (Exception $e) {
    echo "❌ خطأ: " . htmlspecialchars($e->getMessage());
    exit(1);
}