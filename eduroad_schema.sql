-- ============================================================
--  Eduroad Academic Platform - Production Database Schema
--  Total Tables: 36
--  Database: acadimic
--  Charset: utf8mb4 / utf8mb4_unicode_ci
--  Verified 100% against codebase queries & strict mode rules
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. `USERS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('student','admin') NOT NULL DEFAULT 'student',
  `status` enum('active','suspended','inactive') NOT NULL DEFAULT 'active',
  `avatar_initials` varchar(4) DEFAULT NULL COMMENT 'First 2 letters of name for avatar',
  `avatar_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. `ACADEMICS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `academics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialty` varchar(150) DEFAULT NULL COMMENT 'Main academic specialty',
  `degree` enum('بكالوريوس','ماجستير','دكتوراه') DEFAULT NULL,
  `university` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_initials` varchar(10) DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `starting_price` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `total_earned` decimal(10,2) DEFAULT 0.00,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `availability` enum('available','busy','vacation') NOT NULL DEFAULT 'available',
  `avatar` varchar(255) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  KEY `idx_status` (`status`),
  KEY `idx_specialty` (`specialty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. `ACADEMIC_BANK_ACCOUNTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `academic_id` int(10) unsigned NOT NULL,
  `account_type` enum('bank','wallet') NOT NULL DEFAULT 'bank' COMMENT 'بنك أو محفظة',
  `account_name` varchar(150) NOT NULL COMMENT 'اسم البنك أو اسم المحفظة',
  `account_number` varchar(100) NOT NULL COMMENT 'رقم الحساب أو رقم المحفظة',
  `holder_name` varchar(150) DEFAULT NULL COMMENT 'اسم صاحب الحساب',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_academic` (`academic_id`),
  CONSTRAINT `fk_bank_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. `ACADEMIC_QUALIFICATIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_qualifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `academic_id` int(10) unsigned NOT NULL,
  `level` enum('بكالوريوس','ماجستير','دكتوراه') NOT NULL,
  `field` varchar(200) NOT NULL COMMENT 'Field of study',
  `university` varchar(200) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `document_file` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `college_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `specialization_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_qual_academic` (`academic_id`),
  CONSTRAINT `fk_qual_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. `ACADEMIC_SERVICES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_services` (
  `academic_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL COMMENT 'Override starting price for this service',
  PRIMARY KEY (`academic_id`,`service_id`),
  KEY `fk_as_service` (`service_id`),
  CONSTRAINT `fk_as_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_as_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. `PASSWORD_RESETS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `user_type` enum('student','academic','admin') NOT NULL DEFAULT 'student',
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. `LOGIN_OTPS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_otps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `user_type` enum('student','academic','admin') NOT NULL DEFAULT 'student',
  `user_id` int(10) unsigned DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lookup` (`email`,`user_type`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. `COLLEGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `colleges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?️',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. `DEPARTMENTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `college_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dept_college` (`college_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. `SPECIALIZATIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `specializations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_spec_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. `SERVICES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `icon` varchar(10) DEFAULT NULL COMMENT 'Emoji icon',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `level` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `order_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Cached count for performance',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `platform_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `icon_image` varchar(255) DEFAULT NULL,
  `needs_multiple_academics` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `fk_services_parent` (`parent_id`),
  CONSTRAINT `fk_services_parent` FOREIGN KEY (`parent_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. `PACKAGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1' COMMENT 'Hex color for UI',
  `features_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of feature strings',
  `max_tasks` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `delivery_days` int(11) NOT NULL DEFAULT 7,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `icon` varchar(20) NOT NULL DEFAULT '?',
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_ids` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 13. `PACKAGE_SUBSCRIPTIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `package_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `package_id` int(10) unsigned NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_package` (`package_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_ps_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. `PACKAGE_SUBSCRIPTION_USES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `package_subscription_uses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_use_sub_service` (`subscription_id`,`service_id`),
  KEY `idx_use_sub` (`subscription_id`),
  KEY `idx_use_svc` (`service_id`),
  CONSTRAINT `fk_use_sub` FOREIGN KEY (`subscription_id`) REFERENCES `package_subscriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_use_svc` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. `FEATURED_ACADEMICS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `featured_academics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'اسم الأكاديمي',
  `specialty` varchar(150) DEFAULT NULL COMMENT 'التخصص',
  `bio` text DEFAULT NULL COMMENT 'نبذة تعريفية',
  `image` varchar(255) DEFAULT NULL COMMENT 'مسار الصورة',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. `FEATURED_SERVICES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `featured_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(10) unsigned NOT NULL,
  `badge` varchar(100) DEFAULT NULL COMMENT 'شارة مميزة مثل: الأكثر طلباً',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service` (`service_id`),
  KEY `idx_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. `ORDERS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL COMMENT 'e.g. ORD-20260001',
  `student_id` int(10) unsigned NOT NULL,
  `academic_id` int(10) unsigned DEFAULT NULL COMMENT 'Assigned after acceptance',
  `service_id` int(10) unsigned DEFAULT NULL,
  `package_id` int(10) unsigned DEFAULT NULL,
  `specialty` varchar(200) DEFAULT NULL COMMENT 'Specific sub-specialty',
  `academic_level` enum('بكالوريوس','ماجستير','دكتوراه') DEFAULT NULL,
  `language` enum('العربية','الإنجليزية') DEFAULT 'العربية',
  `description` text DEFAULT NULL COMMENT 'Student notes / requirements',
  `deadline` date DEFAULT NULL,
  `academics_count` int(10) unsigned NOT NULL DEFAULT 1,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('awaiting_payment','pending_assignment','assigned','new','accepted','in_progress','delivered','revision','completed','cancelled','cancellation_requested') NOT NULL DEFAULT 'awaiting_payment',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_student` (`student_id`),
  KEY `idx_academic` (`academic_id`),
  KEY `idx_status` (`status`),
  KEY `fk_ord_service` (`service_id`),
  KEY `fk_ord_package` (`package_id`),
  CONSTRAINT `fk_ord_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. `ORDER_ASSIGNMENTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `academic_id` int(10) unsigned NOT NULL,
  `status` enum('assigned','accepted','rejected','completed','replaced') NOT NULL DEFAULT 'assigned',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `response_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `replacement_reason` varchar(100) DEFAULT NULL,
  `replacement_note` text DEFAULT NULL,
  `replaced_at` datetime DEFAULT NULL,
  `replaced_by_academic_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_academic` (`academic_id`),
  KEY `idx_order_academic` (`order_id`,`academic_id`),
  CONSTRAINT `fk_oa_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oa_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. `ORDER_ATTACHMENTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Server path relative to root',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
  `file_size` int(10) unsigned DEFAULT NULL COMMENT 'Size in bytes',
  `uploaded_by` enum('student','academic','admin') NOT NULL DEFAULT 'student',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_att_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. `ORDER_TEAM_MESSAGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_team_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `sender_id` int(10) unsigned NOT NULL,
  `sender_type` enum('academic','admin') NOT NULL DEFAULT 'academic',
  `content` text NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otm_order` (`order_id`),
  KEY `idx_otm_sender` (`sender_id`),
  CONSTRAINT `fk_otm_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. `PLATFORM_PAYMENT_METHODS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `platform_payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_type` enum('bank','wallet') NOT NULL DEFAULT 'bank' COMMENT 'بنك أو محفظة',
  `account_name` varchar(150) NOT NULL COMMENT 'اسم البنك أو اسم المحفظة',
  `account_number` varchar(100) NOT NULL COMMENT 'رقم الحساب أو رقم المحفظة',
  `holder_name` varchar(150) DEFAULT NULL COMMENT 'اسم صاحب الحساب',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. `PAYMENT_RECEIPTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned DEFAULT NULL COMMENT 'For service orders',
  `subscription_id` int(10) unsigned DEFAULT NULL COMMENT 'For package subscriptions',
  `student_id` int(10) unsigned NOT NULL,
  `method_id` int(10) unsigned DEFAULT NULL,
  `method_label` varchar(150) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt_path` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rejection_reason` text DEFAULT NULL,
  `student_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rcpt_order` (`order_id`),
  KEY `idx_rcpt_sub` (`subscription_id`),
  KEY `idx_rcpt_std` (`student_id`),
  KEY `idx_rcpt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. `PAYMENTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(25) NOT NULL COMMENT 'e.g. PAY-20260001',
  `order_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Total paid by student',
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '15% commission',
  `academic_net` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'amount - platform_fee',
  `method` enum('credit_card','bank_transfer','apple_pay','wallet') NOT NULL DEFAULT 'credit_card',
  `status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(255) DEFAULT NULL COMMENT 'Gateway reference',
  `receipt_path` varchar(255) DEFAULT NULL COMMENT '???? ????? ???????',
  `bank_account_id` int(11) DEFAULT NULL COMMENT '???? ???? ?????',
  `coupon_code` varchar(50) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_transferred_to_academic` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_number` (`payment_number`),
  UNIQUE KEY `uq_payment_number` (`payment_number`),
  KEY `idx_order` (`order_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. `REFUND_REQUESTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `refund_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processed','rejected') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `transaction_ref` varchar(100) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 25. `PAYOUTS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `payouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payout_number` varchar(25) NOT NULL COMMENT 'e.g. PAYOUT-20260001',
  `academic_id` int(10) unsigned NOT NULL,
  `bank_account_id` int(10) unsigned DEFAULT NULL COMMENT 'الحساب البنكي المستخدم',
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL COMMENT 'admin user id',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payout_number` (`payout_number`),
  UNIQUE KEY `uq_payout_number` (`payout_number`),
  KEY `idx_academic` (`academic_id`),
  KEY `idx_status` (`status`),
  KEY `fk_payout_bank` (`bank_account_id`),
  KEY `fk_payout_admin` (`created_by`),
  CONSTRAINT `fk_payout_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payout_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payout_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `academic_bank_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. `PAYOUT_MESSAGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `payout_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payout_id` int(11) NOT NULL,
  `sender_type` enum('admin','academic') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 27. `CONVERSATIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL COMMENT 'One conversation per order',
  `student_id` int(10) unsigned NOT NULL,
  `academic_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_academic` (`academic_id`),
  CONSTRAINT `fk_conv_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`),
  CONSTRAINT `fk_conv_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 28. `MESSAGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `sender_id` int(10) unsigned NOT NULL COMMENT 'ID from users or academics table',
  `sender_type` enum('student','academic','admin') NOT NULL,
  `content` text NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv` (`conversation_id`),
  KEY `idx_sender` (`sender_id`,`sender_type`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 29. `REVIEWS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL COMMENT 'One review per order',
  `student_id` int(10) unsigned NOT NULL,
  `academic_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5 COMMENT '1 to 5',
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `idx_academic` (`academic_id`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `fk_rev_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics` (`id`),
  CONSTRAINT `fk_rev_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 30. `NOTIFICATIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'ID from users OR academics table',
  `user_type` enum('student','academic','admin') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `icon` varchar(10) DEFAULT '?',
  `link` varchar(500) DEFAULT NULL COMMENT 'Optional redirect URL',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`user_type`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 31. `SETTINGS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 32. `POLICY_ITEMS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `policy_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_type` enum('student','academic') NOT NULL,
  `icon` varchar(20) DEFAULT '?',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 33. `SUPPORT_CHANNELS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_channels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('whatsapp','telegram','email','phone','instagram','twitter','website','other') NOT NULL DEFAULT 'other',
  `label` varchar(150) NOT NULL,
  `value` varchar(255) NOT NULL,
  `icon` varchar(20) NOT NULL DEFAULT '?',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 34. `SUPPORT_CONVERSATIONS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `user_type` enum('student','academic') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('open','pending','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv_user` (`user_id`,`user_type`),
  KEY `idx_conv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 35. `SUPPORT_MESSAGES`
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `sender_id` int(10) unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sm_conv` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 36. `SUPPORT_FAQS`
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `audience` enum('student','academic') NOT NULL DEFAULT 'student',
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faq_aud` (`audience`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- MASTER CONFIGURATION & SYSTEM SEED DATA
-- ============================================================

-- Seed Data for `settings` (30 rows)
INSERT IGNORE INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `created_at`, `updated_at`) VALUES
('1', 'site_name', 'Eduroad', 'general', '2026-06-09 19:05:16', '2026-08-20 00:10:53'),
('2', 'site_email', 'info@tawassul.com', 'general', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('3', 'site_phone', '+967 775113262', 'general', '2026-06-09 19:05:16', '2026-06-09 19:08:02'),
('4', 'site_url', 'https://eduroad.tawassu.com', 'general', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('5', 'site_language', 'ar', 'general', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('6', 'site_timezone', 'Asia/Riyadh', 'general', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('7', 'site_description', 'منصة تواصل الأكاديمي - نربط الطلاب بأفضل الأكاديميين لتقديم خدمات أكاديمية متميزة', 'general', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('8', 'commission_percentage', '15', 'commission', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('9', 'min_withdrawal', '200', 'commission', '2026-06-09 19:05:16', '2026-09-26 20:25:28'),
('10', 'payout_cycle', 'monthly', 'commission', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('11', 'maintenance_mode', '0', 'system', '2026-06-09 19:05:16', '2026-06-09 19:10:02'),
('12', 'allow_registration', '1', 'system', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('13', 'email_verification', '1', 'system', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('14', 'two_factor_auth', '1', 'security', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('15', 'session_logging', '1', 'security', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('16', 'notify_new_order', '1', 'notifications', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('17', 'notify_payment', '1', 'notifications', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('18', 'daily_summary_email', '1', 'notifications', '2026-06-09 19:05:16', '2026-08-19 22:55:09'),
('19', 'notify_new_academic', '1', 'notifications', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('20', 'payment_stripe_enabled', '1', 'payment', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('21', 'payment_moyasar_enabled', '1', 'payment', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('22', 'payment_paypal_enabled', '1', 'payment', '2026-06-09 19:05:16', '2026-06-09 19:10:02'),
('23', 'primary_color', '#f59e0b', 'appearance', '2026-06-09 19:05:16', '2026-09-08 21:27:19'),
('24', 'theme_mode', 'light', 'appearance', '2026-06-09 19:05:16', '2026-08-19 23:10:20'),
('25', 'smtp_host', 'smtp.gmail.com', 'email', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('26', 'smtp_port', '587', 'email', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('27', 'smtp_user', 'noreply@tawassul.com', 'email', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('28', 'smtp_password', '', 'email', '2026-06-09 19:05:16', '2026-06-09 19:05:16'),
('388', 'withdrawal_eligibility_days', '0', 'general', '2026-09-26 19:30:43', '2026-09-27 00:18:53'),
('389', 'payout_processing_time', '6', 'general', '2026-09-26 19:30:43', '2026-09-26 19:30:43');

-- Seed Data for `colleges` (6 rows)
INSERT IGNORE INTO `colleges` (`id`, `name`, `code`, `description`, `icon`, `sort_order`, `is_active`, `created_at`) VALUES
('1', 'كلية علوم الحاسب والمعلومات', 'CSIS', NULL, '💻', '1', '1', '2026-10-23 03:37:35'),
('2', 'كلية الهندسة', 'ENG', NULL, '⚙️', '2', '1', '2026-10-23 03:37:35'),
('3', 'كلية إدارة الأعمال', 'CBA', NULL, '📈', '3', '1', '2026-10-23 03:37:35'),
('4', 'كلية العلوم الطبية والصحية', 'MED', NULL, '🩺', '4', '1', '2026-10-23 03:37:35'),
('5', 'كلية العلوم', 'SCI', NULL, '🔬', '5', '1', '2026-10-23 03:37:35'),
('6', 'كلية الآداب والعلوم الإنسانية', 'ARTS', NULL, '📚', '6', '1', '2026-10-23 03:37:35');

-- Seed Data for `departments` (21 rows)
INSERT IGNORE INTO `departments` (`id`, `college_id`, `name`, `code`, `description`, `sort_order`, `is_active`, `created_at`) VALUES
('1', '1', 'علوم الحاسب', 'CS', NULL, '1', '1', '2026-10-23 03:37:35'),
('2', '1', 'نظم المعلومات', 'IS', NULL, '2', '1', '2026-10-23 03:37:35'),
('3', '1', 'تقنية المعلومات', 'IT', NULL, '3', '1', '2026-10-23 03:37:35'),
('4', '2', 'الهندسة الكهربائية', 'EE', NULL, '1', '1', '2026-10-23 03:37:35'),
('5', '2', 'الهندسة المدنية', 'CE', NULL, '2', '1', '2026-10-23 03:37:35'),
('6', '2', 'الهندسة الميكانيكية', 'ME', NULL, '3', '1', '2026-10-23 03:37:35'),
('7', '2', 'الهندسة المعمارية', 'ARCH', NULL, '4', '1', '2026-10-23 03:37:35'),
('8', '3', 'إدارة الأعمال', 'BUS', NULL, '1', '1', '2026-10-23 03:37:35'),
('9', '3', 'المحاسبة', 'ACCT', NULL, '2', '1', '2026-10-23 03:37:35'),
('10', '3', 'العلوم المالية والمصرفية', 'FIN', NULL, '3', '1', '2026-10-23 03:37:35'),
('11', '3', 'التسويق', 'MKT', NULL, '4', '1', '2026-10-23 03:37:35'),
('12', '4', 'الطب والجراحة', 'MED_SURG', NULL, '1', '1', '2026-10-23 03:37:35'),
('13', '4', 'الصيدلة', 'PHARM', NULL, '2', '1', '2026-10-23 03:37:35'),
('14', '4', 'علوم التمريض', 'NURS', NULL, '3', '1', '2026-10-23 03:37:35'),
('15', '4', 'المختبرات والتحاليل الطبية', 'LAB', NULL, '4', '1', '2026-10-23 03:37:35'),
('16', '5', 'الرياضيات والإحصاء', 'MATH', NULL, '1', '1', '2026-10-23 03:37:35'),
('17', '5', 'الفيزياء', 'PHYS', NULL, '2', '1', '2026-10-23 03:37:35'),
('18', '5', 'الكيمياء', 'CHEM', NULL, '3', '1', '2026-10-23 03:37:35'),
('19', '6', 'اللغة العربية وآدابها', 'ARAB', NULL, '1', '1', '2026-10-23 03:37:35'),
('20', '6', 'اللغات والترجمة', 'TRANS', NULL, '2', '1', '2026-10-23 03:37:35'),
('21', '6', 'الإعلام والاتصال', 'COMM', NULL, '3', '1', '2026-10-23 03:37:35');

-- Seed Data for `specializations` (71 rows)
INSERT IGNORE INTO `specializations` (`id`, `department_id`, `name`, `code`, `description`, `sort_order`, `is_active`, `created_at`) VALUES
('1', '1', 'هندسة البرمجيات', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('2', '1', 'الذكاء الاصطناعي وتعلم الآلة', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('3', '1', 'علم البيانات والتحليلات المتقدمة', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('4', '1', 'علوم الحاسب العامة', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('5', '1', 'الأنظمة الموزعة والمدمجة', NULL, NULL, '5', '1', '2026-10-23 03:37:35'),
('6', '2', 'نظم المعلومات الإدارية (MIS)', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('7', '2', 'تحليل وتصميم النظم والبيانات', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('8', '2', 'حوكمة وإدارة تقنية المعلومات', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('9', '2', 'تطوير الحلول الرقمية للأعمال', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('10', '3', 'الأمن السيبراني والتحري الرقمي', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('11', '3', 'شبكات الحاسب وإدارة البنية التحتية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('12', '3', 'الحوسبة السحابية وهندسة الويب', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('13', '3', 'إنترنت الأشياء والأنظمة الذكية', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('14', '4', 'هندسة الاتصالات والإلكترونيات', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('15', '4', 'هندسة القوى والطاقة المتجددة', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('16', '4', 'هندسة التحكم والأنظمة الذكية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('17', '5', 'الهندسة الإنشائية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('18', '5', 'هندسة الطرق والمرور والنقل', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('19', '5', 'الهندسة البيئية والموارد المائية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('20', '5', 'إدارة مشاريع التشييد والإنشاءات', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('21', '6', 'هندسة القوى والحراريات', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('22', '6', 'التصميم والتصنيع الميكانيكي', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('23', '6', 'الميكاترونكس وهندسة الروبوتات', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('24', '7', 'التصميم المعماري الحديث', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('25', '7', 'التخطيط العمراني والمدن الذكية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('26', '7', 'العمارة البيئية والمستدامة', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('27', '8', 'الإدارة العامة وإدارة العمليات', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('28', '8', 'إدارة الموارد البشرية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('29', '8', 'ريادة الأعمال وإدارة المشاريع الصغيرة', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('30', '8', 'إدارة سلاسل الإمداد واللوجستيات', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('31', '9', 'المحاسبة المالية والتقارير الدولية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('32', '9', 'المراجعة والتدقيق المالي', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('33', '9', 'المحاسبة الإدارية والتكاليف', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('34', '9', 'المحاسبة الضريبية والزكاة', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('35', '10', 'الإدارة المالية والاستثمار', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('36', '10', 'الخدمات المصرفية والمالية الإسلامية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('37', '10', 'التأمين وإدارة المخاطر المالية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('38', '11', 'التسويق الرقمي والتجارة الإلكترونية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('39', '11', 'إدارة العلامات التجارية والتواصل التسويقي', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('40', '11', 'سلوك المستهلك وبحوث السوق', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('41', '12', 'الطب الباطني العام', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('42', '12', 'الجراحة العامة وجراحة اليوم الواحد', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('43', '12', 'طب الأطفال وحديثي الولادة', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('44', '12', 'طب الأسرة والمجتمع', NULL, NULL, '4', '1', '2026-10-23 03:37:35'),
('45', '13', 'الصيدلة الإكلينيكية والسريرية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('46', '13', 'علم الأدوية والسموم', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('47', '13', 'الصيدلانيات وتصميم المركبات', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('48', '14', 'تمريض العناية المركزة والطوارئ', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('49', '14', 'تمريض الباطني والجراحي', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('50', '14', 'تمريض الصحة النفسية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('51', '15', 'الكيمياء الحيوية السريرية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('52', '15', 'علم الأحياء الدقيقة الطبية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('53', '15', 'علم أمراض الدم ونقل الدم', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('54', '16', 'الرياضيات البحتة', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('55', '16', 'الرياضيات التطبيقية والنمذجة', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('56', '16', 'الإحصاء وبحوث العمليات', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('57', '17', 'الفيزياء النظرية والذرية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('58', '17', 'الفيزياء الطبية الحيوية', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('59', '17', 'فيزياء المواد والنانوتكنولوجي', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('60', '18', 'الكيمياء العضوية', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('61', '18', 'الكيمياء التحليلية والأجهزة', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('62', '18', 'الكيمياء الحيوية والصناعية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('63', '19', 'اللغويات والنحو والصرف', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('64', '19', 'الأدب والنقد الأدبي الحديث', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('65', '19', 'البلاغة والأسلوبية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('66', '20', 'الترجمة الفورية والتحريرية (إنجليزي - عربي)', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('67', '20', 'اللغويات التطبيقية وتعليم اللغات', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('68', '20', 'الأدب المقارن والدراسات الثقافية', NULL, NULL, '3', '1', '2026-10-23 03:37:35'),
('69', '21', 'الصحافة والإعلام الرقمي الجديد', NULL, NULL, '1', '1', '2026-10-23 03:37:35'),
('70', '21', 'العلاقات العامة والاتصال المؤسسي', NULL, NULL, '2', '1', '2026-10-23 03:37:35'),
('71', '21', 'الإذاعة والتلفزيون والإنتاج المرئي', NULL, NULL, '3', '1', '2026-10-23 03:37:35');

-- Seed Data for `services` (53 rows)
INSERT IGNORE INTO `services` (`id`, `parent_id`, `name`, `icon`, `description`, `is_active`, `level`, `sort_order`, `order_count`, `created_at`, `price`, `platform_percent`, `discount_percent`, `icon_image`, `needs_multiple_academics`) VALUES
('1', NULL, 'الأبحاث والدراسات', '📚', 'إعداد الأبحاث العلمية والدراسات الأكاديمية بجميع مستوياتها.', '1', '1', '5', '0', '2026-06-09 16:48:15', '0.00', '0.00', '0.00', 'uploads/services/svc_icon_1788629429_5090.png', '0'),
('2', NULL, 'الرسائل الجامعية', '📜', 'المساعدة في كتابة وتنسيق الرسائل والأطروحات الجامعية.', '1', '1', '2', '0', '2026-06-09 16:48:15', '0.00', '0.00', '0.00', NULL, '0'),
('3', NULL, 'الترجمة الأكاديمية', '🌐', 'ترجمة الأبحاث والمستندات الأكاديمية بين العربية والإنجليزية.', '1', '1', '4', '0', '2026-06-09 16:48:15', '0.00', '0.00', '0.00', NULL, '0'),
('101', '1', 'خدمات التأسيس الأكاديمي (قبل بدء البحث)0', '🔍', NULL, '1', '2', '5', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('102', '1', 'خدمات كتابة وتطوير البحث (أثناء الكتابة)0', '✍️', NULL, '1', '2', '6', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('103', '1', 'خدمات الاستبيانات وأدوات البحث', '📊', NULL, '1', '2', '3', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('104', '1', 'خدمات المساندة والتخطيط الأكاديمي', '📋', NULL, '1', '2', '4', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('201', '2', 'خدمات التحليل الإحصائي', '📈', NULL, '1', '2', '1', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('301', '3', 'خدمات الفحص والمراجعة النهائية', '✅', NULL, '1', '2', '1', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('302', '3', 'خدمات التحكيم الأكاديمي', '⚖️', NULL, '1', '2', '2', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('303', '3', 'خدمات العرض والتقديم (المناقشة)', '🎤', NULL, '1', '2', '3', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('304', '3', 'خدمات النشر العلمي', '📰', NULL, '1', '2', '4', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('305', '3', 'خدمات استشارية خاصة ومتقدمة', '💼', NULL, '1', '2', '5', '0', '2026-08-13 20:38:26', '0.00', '0.00', '0.00', NULL, '0'),
('1001', '101', 'المساعدة في اختيار عنوان البحث', '🎯', NULL, '1', '3', '7', '0', '2026-08-13 20:38:26', '75.00', '15.00', '0.00', '', '0'),
('1002', '101', 'استشارات إعداد خطة البحث (Proposal)', '📑', NULL, '1', '3', '2', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1003', '101', 'استشارات إعداد الإطار النظري', '🧠', NULL, '1', '3', '3', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1004', '101', 'استشارات اختيار المنهجية المناسبة', '🔬', NULL, '1', '3', '4', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1005', '101', 'استشارات تحديد أدوات الدراسة', '🛠️', NULL, '0', '3', '5', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1006', '101', 'تحكيم الاستبيان', '✔️', NULL, '1', '3', '6', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1007', '102', 'استشارات إعادة الصياغة الأكاديمية', '🔄', NULL, '1', '3', '6', '0', '2026-08-13 20:38:26', '850.00', '15.00', '0.00', '', '0'),
('1008', '102', 'استشارات كتابة أجزاء محددة (مقدمة/خاتمة)', '✏️', NULL, '1', '3', '2', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1009', '102', 'استشارات الربط بين الفقرات', '🔗', NULL, '1', '3', '3', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1010', '102', 'استشارات تقوية الحجج العلمية', '⚡', NULL, '1', '3', '4', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1011', '102', 'استشارات تلخيص الدراسات السابقة', '📚', NULL, '1', '3', '5', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1012', '103', 'استشارات إعداد الاستبيان', '📋', NULL, '1', '3', '9', '0', '2026-08-13 20:38:26', '1200.00', '10.00', '0.00', NULL, '0'),
('1013', '103', 'تصميم استبيان احترافي', '🎨', NULL, '1', '3', '19', '0', '2026-08-13 20:38:26', '400.00', '20.00', '50.00', 'uploads/services/svc_icon_1788627957_4878.png', '0'),
('1014', '103', 'مراجعة الاستبيان', '🔍', NULL, '1', '3', '18', '0', '2026-08-13 20:38:26', '0.00', '20.00', '0.00', 'uploads/services/svc_icon_1788627863_5232.png', '0'),
('1015', '103', 'تحويل الاستبيان إلى Google Forms', '🌐', NULL, '1', '3', '14', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', 'uploads/services/svc_icon_1788627172_5604.png', '0'),
('1016', '103', 'تحسين جودة الأسئلة', '⭐', NULL, '1', '3', '10', '0', '2026-08-13 20:38:26', '500.00', '40.00', '0.00', NULL, '0'),
('1017', '104', 'كتابة سير ذاتية أكاديمية (CV)', '📄', NULL, '1', '3', '1', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1018', '104', 'كتابة بيان الغرض (SOP)', '🎓', NULL, '1', '3', '2', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1019', '104', 'إعداد ملفات التقديم للجامعات', '🏫', NULL, '1', '3', '3', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1020', '104', 'استشارات أكاديمية عامة', '💡', NULL, '1', '3', '4', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('1021', '104', 'تخطيط المسار البحثي', '🗺️', NULL, '1', '3', '5', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('2001', '201', 'إدخال البيانات', '⌨️', NULL, '1', '3', '5', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', '', '1'),
('2002', '201', 'تحليل SPSS / Excel / SmartPLS / Stata / R', '📊', NULL, '1', '3', '6', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', '', '1'),
('2003', '201', 'تفسير النتائج', '📖', NULL, '1', '3', '7', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', '', '1'),
('2004', '201', 'إعداد الجداول والرسوم البيانية', '📈', NULL, '1', '3', '4', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('3001', '303', 'إعداد عرض PowerPoint (عربي/إنجليزي)', '🖥️', NULL, '1', '3', '1', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('3002', '303', 'تلخيص البحث للعرض', '📝', NULL, '1', '3', '2', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('3003', '303', 'كتابة سيناريو العرض (Script)', '🏛️', NULL, '1', '3', '6', '0', '2026-08-13 20:38:26', '490.00', '9.90', '0.00', NULL, '0'),
('3004', '303', 'تدريب على المناقشة النهائية', '🎯', NULL, '1', '3', '4', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('3005', '303', 'إعداد الأسئلة المتوقعة مع الإجابات', '❓', NULL, '1', '3', '5', '0', '2026-08-13 20:38:26', '0.00', '15.00', '0.00', NULL, '0'),
('3006', NULL, 'التأسيس والبناء الأكاديمي', '📚', NULL, '1', '1', '3', '0', '2026-08-13 20:52:37', '0.00', '0.00', '0.00', NULL, '0'),
('3007', '3006', 'خدمات التأسيس الأكاديمي (قبل بدء البحث)', '✏️', NULL, '1', '2', '1', '0', '2026-08-13 20:53:30', '0.00', '0.00', '0.00', NULL, '0'),
('3009', '3007', 'المساعدة في اختيار عنوان البحث', '🚀', NULL, '1', '3', '2', '0', '2026-08-13 20:55:28', '57.00', '15.00', '0.00', '', '0'),
('3010', '3006', 'الابحاث', '⚙️', NULL, '1', '2', '2', '0', '2026-08-15 16:49:50', '0.00', '0.00', '0.00', NULL, '0'),
('3011', '3010', 'ابحاثي', '📚', NULL, '1', '3', '1', '0', '2026-08-15 16:50:15', '0.00', '15.00', '0.00', NULL, '0'),
('3013', NULL, 'البرمجه', '🎯', NULL, '1', '1', '1', '0', '2026-08-20 11:26:06', '0.00', '0.00', '0.00', NULL, '0'),
('3014', '3013', 'الموبايل', '🎓', NULL, '1', '2', '1', '0', '2026-08-20 11:26:24', '0.00', '0.00', '0.00', NULL, '0'),
('3015', '3014', 'flutter', '💡', NULL, '1', '3', '5', '0', '2026-08-20 11:26:42', '150.00', '15.00', '0.00', '', '1'),
('3016', '3014', 'ويب', '📚', NULL, '1', '3', '3', '0', '2026-09-28 03:44:57', '450.00', '30.00', '0.00', '', '0'),
('3017', '3014', 'الاستضافه', '📚', NULL, '1', '3', '4', '0', '2026-09-28 03:45:22', '720.00', '20.00', '0.00', '', '0');

-- Seed Data for `packages` (5 rows)
INSERT IGNORE INTO `packages` (`id`, `name`, `price`, `color`, `features_json`, `max_tasks`, `delivery_days`, `is_active`, `created_at`, `icon`, `original_price`, `service_ids`) VALUES
('2', 'البدايه', '100.00', '#3b82f6', '[\"3 مهام\",\"مراجعة متقدمة\",\"تسليم خلال 5 أيام\",\"دعم فني\"]', '3', '5', '1', '2026-06-09 16:48:15', '📦', '150.00', '[1012,1013,1014,1015,1016,1017,1018,1019,1020,1021,1001,1002,1003,1004,1006,1007,1008,1009,1010,1011,2001,2002,2003,3001,3002,3004,3005,3009,3011,3015]'),
('3', 'التحليل', '649.00', '#8b5cf6', '[\"5 مهام\", \"تحليل شامل\", \"تسليم خلال 3 أيام\", \"دعم 24/7\"]', '5', '3', '1', '2026-06-09 16:48:15', '📦', '0.00', NULL),
('4', 'التسليم', '999.00', '#f59e0b', '[\"10 مهام\",\"خدمة متميزة\",\"تسليم خلال 48 ساعة\",\"مدير خاص\"]', '10', '2', '1', '2026-06-09 16:48:15', '📦', '0.00', NULL),
('5', 'النخبة', '1999.00', '#ef4444', '[\"مهام غير محدودة\",\"خدمة VIP\",\"تسليم خلال 24 ساعة\",\"فريق متخصص\",\"ضمان النتائج\"]', NULL, '1', '1', '2026-06-09 16:48:15', '📦', '0.00', '[2004]'),
('6', 'باقه الحاسوب', '1200.00', '#6366f1', '[]', NULL, '7', '1', '2026-09-28 03:46:07', '⚡', '1320.00', '[3015,3016,3017]');

-- Seed Data for `platform_payment_methods` (1 rows)
INSERT IGNORE INTO `platform_payment_methods` (`id`, `account_type`, `account_name`, `account_number`, `holder_name`, `is_active`, `sort_order`, `created_at`, `updated_at`, `logo`) VALUES
('5', 'wallet', 'جيب', '775113262', 'عبدالله خليل', '1', '1', '2026-09-05 22:24:33', '2026-09-05 22:24:33', 'uploads/payment_methods/pm_logo_1788636269_6534.png');

-- Seed Data for `policy_items` (8 rows)
INSERT IGNORE INTO `policy_items` (`id`, `policy_type`, `icon`, `title`, `content`, `sort_order`, `created_at`) VALUES
('1', 'academic', '❤️', 'البيانات التي نجمعها', 'لضمان تقديمك كخبير موثوق أمام العملاء، نقوم بجمع البيانات المهنية، والشخصية للتواصل، والبيانات المالية.', '0', '2026-09-28 00:03:48'),
('2', 'academic', '📌', 'السرية والأمانة العلمية', 'يُمنع منعاً باتاً مشاركة، أو نشر، أو تسريب أي ملفات، بيانات، أو خطط بحثية خاصة بالعملاء لأي طرف ثالث، سواء كان فرداً أو جهة أكاديمية.', '2', '2026-09-28 00:03:48'),
('3', 'academic', '📌', 'حقوق الملكية الفكرية', 'جميع المخرجات، التقارير، والتحليلات التي يتم إنجازها وتسليمها للعميل عبر المنصة تصبح ملكية فكرية حصرية للعميل.', '1', '2026-09-28 00:03:48'),
('4', 'academic', '📌', 'المستحقات المالية ونموذج العمل', 'تُضاف الأرصدة إلى محفظتك الإلكترونية في المنصة فور الاعتماد النهائي للمشروع. يحق لك تقديم \"طلب سحب رصيد\" متى ما بلغت أرباحك الحد الأدنى (200 ريال).', '4', '2026-09-28 00:03:48'),
('5', 'student', '📌', 'سياسة الطلبات والتسليم', 'يلتزم الطالب بتوضيح جميع المتطلبات بشكل كامل ودقيق عند رفع الطلب. أي تغيير جوهري بعد الاتفاق قد يتطلب تكاليف إضافية.', '4', '2026-09-28 00:03:48'),
('6', 'student', '📌', 'الاسترجاع والتعديلات', 'يحق للطالب طلب تعديلات مجانية خلال فترة الضمان المحددة، شرط أن لا تخرج عن نطاق الطلب الأساسي.', '5', '2026-09-28 00:03:48'),
('7', 'student', '📌', 'سرية البيانات', 'نلتزم بحماية بياناتك الشخصية وتفاصيل طلباتك، ولا يتم مشاركتها إلا مع الأكاديمي المختص لإنجاز العمل.', '6', '2026-09-28 00:03:48'),
('10', 'student', '📌', '45454', '54545454512121254545121', '7', '2026-09-28 00:16:41');

-- Seed Data for `support_faqs` (3 rows)
INSERT IGNORE INTO `support_faqs` (`id`, `audience`, `question`, `answer`, `sort_order`, `is_active`, `created_at`) VALUES
('1', 'student', 'كيف أتابع حالة طلبي؟', 'من صفحة «طلباتي» يمكنك متابعة حالة كل طلب: قيد الإسناد، جارٍ التنفيذ، مكتمل.', '1', '1', '2026-09-09 01:03:03'),
('2', 'student', 'كيف أرفع إشعار الدفع؟', 'بعد التحويل إلى أحد حسابات المنصة، أرفق صورة الإشعار أثناء الاشتراك بالباقة أو إنشاء الطلب وسيتم اعتماده من الإدارة.', '2', '1', '2026-09-09 01:03:03'),
('3', 'academic', 'متى تُصرف الأرباح؟', 'تُصرف الأرباح المعتمدة وفق دورة التحويل الأسبوعية بعد اكتمال الطلب.', '1', '1', '2026-09-09 01:03:03');

-- Seed Data for `support_channels` (2 rows)
INSERT IGNORE INTO `support_channels` (`id`, `type`, `label`, `value`, `icon`, `sort_order`, `is_active`, `created_at`) VALUES
('1', 'whatsapp', 'واتساب الدعم', '+966500000000', '🟢', '1', '1', '2026-09-09 01:03:03'),
('2', 'email', 'البريد الإلكتروني', 'support@eduroad.com', '📧', '2', '1', '2026-09-09 01:03:03');

-- Seed Data for `users` (1 rows)
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `google_id`, `password`, `phone`, `role`, `status`, `avatar_initials`, `avatar_path`, `created_at`, `updated_at`) VALUES
('1', 'المدير العام', 'admin@tawassul.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '775113262', 'admin', 'active', 'مد', 'uploads/avatars/admin_1_1790553545.png', '2026-06-09 16:48:15', '2026-09-28 02:59:05');

