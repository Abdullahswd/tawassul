-- ============================================================
--  Migration: إضافة عمود وثيقة المؤهل للأكاديميين
--  Database: acadimic
--  الغرض: إصلاح الخطأ
--    "SQLSTATE[42S22]: Column not found: 1054
--     Unknown column 'document_file' in 'field list'"
--  الذي يظهر عند إضافة/تعديل مؤهل أكاديمي أو أثناء تسجيل أكاديمي
--  بعد الرفع على السيرفر.
--
--  طريقة التشغيل:
--    - عبر phpMyAdmin: افتح قاعدة البيانات `acadimic` ثم تبويب SQL والصق المحتوى كاملاً.
--    - أو عبر CLI:      mysql -u USER -p acadimic < هذا_الملف.sql
--
--  متوافق مع MySQL وMariaDB (آمن لإعادة التشغيل أكثر من مرة).
-- ============================================================

SET @schema := DATABASE();

-- التأكد من وجود عمود `document_file` في جدول `academic_qualifications`
SET @has_doc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME   = 'academic_qualifications'
      AND COLUMN_NAME  = 'document_file'
);

SET @sql := IF(@has_doc = 0,
    'ALTER TABLE `academic_qualifications` ADD COLUMN `document_file` VARCHAR(255) DEFAULT NULL AFTER `graduation_year`',
    'SELECT ''document_file column already exists'' AS document_file_status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;