-- ============================================================
--  Migration: إضافة عمود الصورة الشخصية للأكاديميين
--  Database: acadimic
--  الغرض: إصلاح الخطأ
--    "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'avatar' in 'field list'"
--  الذي يظهر بعد الرفع على السيرفر عند حفظ الصورة الشخصية.
--
--  طريقة التشغيل:
--    - عبر phpMyAdmin: افتح قاعدة البيانات `acadimic` ثم تبويب SQL والصق المحتوى كاملاً.
--    - أو عبر CLI:      mysql -u USER -p acadimic < هذا_الملف.sql
--
--  متوافق مع MySQL وMariaDB (آمن لإعادة التشغيل أكثر من مرة).
-- ============================================================

-- 1) التأكد من وجود عمود `avatar` في جدول `academics`
SET @schema := DATABASE();
SET @has_avatar := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME   = 'academics'
      AND COLUMN_NAME  = 'avatar'
);

SET @sql := IF(@has_avatar = 0,
    'ALTER TABLE `academics` ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL COMMENT ''Uploaded profile picture path'' AFTER `bio`',
    'SELECT ''avatar column already exists'' AS avatars_status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) (اختياري) التأكد من وجود عمود `logo` لشعار الحسابات البنكية
SET @has_logo := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME   = 'academic_bank_accounts'
      AND COLUMN_NAME  = 'logo'
);

SET @sql := IF(@has_logo = 0,
    'ALTER TABLE `academic_bank_accounts` ADD COLUMN `logo` VARCHAR(255) DEFAULT NULL',
    'SELECT ''bank logo column already exists'' AS bank_logo_status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;