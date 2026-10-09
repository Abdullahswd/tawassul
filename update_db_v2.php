<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * سكربت لتحديث قاعدة البيانات للمشروع
 * يرجى رفع هذا الملف إلى السيرفر وفتحه مرة واحدة من المتصفح، ثم حذفه لأسباب أمنية.
 */

// السماح بتشغيل الملف سواء كان في المجلد الرئيسي أو داخل مجلد config/
$functionsPath1 = __DIR__ . '/config/functions.php';
$functionsPath2 = __DIR__ . '/functions.php';

if (file_exists($functionsPath1)) {
    require_once $functionsPath1;
} elseif (file_exists($functionsPath2)) {
    require_once $functionsPath2;
} else {
    die("<h3 style='color:red'>❌ Error: Cannot find functions.php. Please make sure the script is uploaded to the correct location.</h3>");
}
$db = db();
$output = [];
    
    try {
        // 1. إضافة حقل student_note إلى جدول payment_receipts إذا لم يكن موجوداً
        $checkCol = $db->query("SHOW COLUMNS FROM `payment_receipts` LIKE 'student_note'");
        if ($checkCol->rowCount() == 0) {
            $db->exec("ALTER TABLE `payment_receipts` ADD COLUMN `student_note` TEXT NULL DEFAULT NULL");
            $output[] = "✅ تم إضافة حقل student_note بنجاح.";
        } else {
            $output[] = "ℹ️ حقل student_note موجود مسبقاً.";
        }

        // 2. إنشاء جدول refund_requests الخاص بطلبات الاسترجاع
        $db->exec("CREATE TABLE IF NOT EXISTS `refund_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `status` ENUM('pending', 'processed', 'rejected') DEFAULT 'pending',
        `admin_note` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $output[] = "✅ تم التحقق من إنشاء جدول refund_requests بنجاح.";

        // 3. إضافة حقل method_label إلى جدول payment_receipts إذا لم يكن موجوداً
        $checkLabel = $db->query("SHOW COLUMNS FROM `payment_receipts` LIKE 'method_label'");
        if ($checkLabel->rowCount() == 0) {
            $db->exec("ALTER TABLE `payment_receipts` ADD COLUMN `method_label` VARCHAR(255) NULL DEFAULT NULL");
            $output[] = "✅ تم إضافة حقل method_label بنجاح.";
        } else {
            $output[] = "ℹ️ حقل method_label موجود مسبقاً.";
        }

        // 4. إضافة حقل method_id إلى جدول payment_receipts إذا لم يكن موجوداً
        $checkId = $db->query("SHOW COLUMNS FROM `payment_receipts` LIKE 'method_id'");
        if ($checkId->rowCount() == 0) {
            $db->exec("ALTER TABLE `payment_receipts` ADD COLUMN `method_id` INT NULL DEFAULT NULL");
            $output[] = "✅ تم إضافة حقل method_id بنجاح.";
        } else {
            $output[] = "ℹ️ حقل method_id موجود مسبقاً.";
        }

        // 4.5. إضافة حقل rejection_reason إلى جدول payment_receipts إذا لم يكن موجوداً
        $checkReason = $db->query("SHOW COLUMNS FROM `payment_receipts` LIKE 'rejection_reason'");
        if ($checkReason->rowCount() == 0) {
            $db->exec("ALTER TABLE `payment_receipts` ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL");
            $output[] = "✅ تم إضافة حقل rejection_reason بنجاح.";
        } else {
            $output[] = "ℹ️ حقل rejection_reason موجود مسبقاً.";
        }

        // 5. تحديث قيم status في جدول orders
        $db->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
            'awaiting_payment',
            'pending_assignment',
            'assigned',
            'new',
            'accepted',
            'in_progress',
            'revision',
            'completed',
            'cancelled',
            'cancellation_requested'
        ) NOT NULL DEFAULT 'awaiting_payment'");

        $output[] = "✅ تم تحديث حقل status في جدول orders بنجاح.";

        echo "<h3>نتيجة تحديث قاعدة البيانات:</h3><ul>";

        foreach ($output as $msg) {
            echo "<li>{$msg}</li>";
        }

        echo "</ul><br>";
        echo "<b style='color:red'>الرجاء حذف هذا الملف (update_db_v2.php) بعد الانتهاء لأسباب أمنية.</b>";

    } catch (Exception $e) {
        echo "<h3 style='color:red'>❌ حدث خطأ أثناء التحديث:</h3>";
        echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    }
