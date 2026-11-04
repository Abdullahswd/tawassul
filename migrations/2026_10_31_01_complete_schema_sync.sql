-- ============================================================
--  Eduroad - Complete Schema Synchronization Migration
--  Migration: 2026_10_31_01_complete_schema_sync.sql
--  Consolidates all runtime DDL changes into a single clean migration
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. COLLEGES TABLE
CREATE TABLE IF NOT EXISTS `colleges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT '🏛️',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DEPARTMENTS TABLE
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `college_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_dept_college` (`college_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SPECIALIZATIONS TABLE
CREATE TABLE IF NOT EXISTS `specializations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_spec_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. FEATURED SERVICES TABLE
CREATE TABLE IF NOT EXISTS `featured_services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `service_id` INT UNSIGNED NOT NULL,
    `badge` VARCHAR(100) DEFAULT NULL COMMENT 'شارة مميزة مثل: الأكثر طلباً',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_feat_srv` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. FEATURED ACADEMICS TABLE
CREATE TABLE IF NOT EXISTS `featured_academics` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `degree` VARCHAR(100) DEFAULT NULL,
    `university` VARCHAR(255) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. PAYOUT MESSAGES TABLE
CREATE TABLE IF NOT EXISTS `payout_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payout_id` INT UNSIGNED NOT NULL,
    `sender_type` ENUM('admin', 'academic') NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pm_payout` (`payout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. POLICY ITEMS TABLE
CREATE TABLE IF NOT EXISTS `policy_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `policy_type` ENUM('academic', 'student') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_policy_type` (`policy_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. PASSWORD RESETS TABLE
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `user_type` ENUM('student', 'academic', 'admin') NOT NULL DEFAULT 'student',
    `token` VARCHAR(100) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. PAYMENT RECEIPTS TABLE
CREATE TABLE IF NOT EXISTS `payment_receipts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED DEFAULT NULL COMMENT 'For service orders',
    `subscription_id` INT UNSIGNED DEFAULT NULL COMMENT 'For package subscriptions',
    `student_id` INT UNSIGNED NOT NULL,
    `method_id` INT UNSIGNED DEFAULT NULL,
    `method_label` VARCHAR(150) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `receipt_path` VARCHAR(500) NOT NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT DEFAULT NULL,
    `student_note` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rcpt_order` (`order_id`),
    INDEX `idx_rcpt_sub`   (`subscription_id`),
    INDEX `idx_rcpt_std`   (`student_id`),
    INDEX `idx_rcpt_status`(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. PLATFORM PAYMENT METHODS TABLE
CREATE TABLE IF NOT EXISTS `platform_payment_methods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL DEFAULT 'bank_transfer',
    `account_name` VARCHAR(150) NOT NULL,
    `account_number` VARCHAR(100) DEFAULT NULL,
    `iban` VARCHAR(50) DEFAULT NULL,
    `bank_name` VARCHAR(150) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `instructions` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. SUPPORT TABLES
CREATE TABLE IF NOT EXISTS `support_faqs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `audience` ENUM('student','academic') NOT NULL DEFAULT 'student',
    `question` VARCHAR(500) NOT NULL,
    `answer` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_faq_aud` (`audience`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_channels` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('whatsapp','telegram','email','phone','instagram','twitter','website','other') NOT NULL DEFAULT 'other',
    `label` VARCHAR(150) NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(20) NOT NULL DEFAULT '📞',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_conversations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `user_type` ENUM('student','academic') NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_conv_user` (`user_id`, `user_type`),
    INDEX `idx_conv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT UNSIGNED NOT NULL,
    `sender_type` ENUM('user','admin') NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `content` TEXT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_msg_conv` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

