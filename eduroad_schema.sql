-- ============================================================
--  Eduroad Academic Platform - Database Schema
--  Database: acadimic
--  Encoding: utf8mb4_unicode_ci
--  Generated: 2026
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. USERS (Admins & Students)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `email`            VARCHAR(150) NOT NULL UNIQUE,
  `password`         VARCHAR(255) NOT NULL,
  `phone`            VARCHAR(20) DEFAULT NULL,
  `role`             ENUM('student','admin') NOT NULL DEFAULT 'student',
  `status`           ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  `avatar_initials`  VARCHAR(4) DEFAULT NULL COMMENT 'First 2 letters of name for avatar',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`),
  INDEX `idx_status`(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. ACADEMICS
-- ============================================================
CREATE TABLE IF NOT EXISTS `academics` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100) NOT NULL,
  `email`           VARCHAR(150) NOT NULL UNIQUE,
  `password`        VARCHAR(255) NOT NULL,
  `phone`           VARCHAR(20) DEFAULT NULL,
  `specialty`       VARCHAR(150) DEFAULT NULL COMMENT 'Main academic specialty',
  `degree`          ENUM('بكالوريوس','ماجستير','دكتوراه') DEFAULT NULL,
  `university`      VARCHAR(200) DEFAULT NULL,
  `bio`             TEXT DEFAULT NULL,
  `avatar_initials` VARCHAR(4) DEFAULT NULL,
  `starting_price`  DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum price per order',
  `balance`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Pending withdrawal balance',
  `total_earned`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'All time earnings',
  `rating`          DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Average rating 0-5',
  `total_reviews`   INT UNSIGNED NOT NULL DEFAULT 0,
  `total_orders`    INT UNSIGNED NOT NULL DEFAULT 0,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `availability`    ENUM('available','busy','vacation') NOT NULL DEFAULT 'available',
  `bank_name`       VARCHAR(100) DEFAULT NULL,
  `iban`            VARCHAR(50) DEFAULT NULL,
  `account_name`    VARCHAR(150) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status`  (`status`),
  INDEX `idx_specialty`(`specialty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. ACADEMIC QUALIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_qualifications` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_id`      INT UNSIGNED NOT NULL,
  `level`            ENUM('بكالوريوس','ماجستير','دكتوراه') NOT NULL,
  `field`            VARCHAR(200) NOT NULL COMMENT 'Field of study',
  `university`       VARCHAR(200) NOT NULL,
  `country`          VARCHAR(100) DEFAULT NULL,
  `graduation_year`  YEAR DEFAULT NULL,
  `verified`         TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_qual_academic` FOREIGN KEY (`academic_id`)
    REFERENCES `academics`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. SERVICES
-- ============================================================
CREATE TABLE IF NOT EXISTS `services` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `icon`        VARCHAR(10) DEFAULT NULL COMMENT 'Emoji icon',
  `description` TEXT DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `order_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cached count for performance',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. ACADEMIC ↔ SERVICES (Pivot)
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_services` (
  `academic_id`  INT UNSIGNED NOT NULL,
  `service_id`   INT UNSIGNED NOT NULL,
  `custom_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Override starting price for this service',
  PRIMARY KEY (`academic_id`, `service_id`),
  CONSTRAINT `fk_as_academic` FOREIGN KEY (`academic_id`)
    REFERENCES `academics`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_as_service`  FOREIGN KEY (`service_id`)
    REFERENCES `services`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. PACKAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `packages` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100) NOT NULL,
  `price`          DECIMAL(10,2) NOT NULL,
  `color`          VARCHAR(20) DEFAULT '#6366f1' COMMENT 'Hex color for UI',
  `features_json`  JSON DEFAULT NULL COMMENT 'Array of feature strings',
  `max_tasks`      INT DEFAULT NULL COMMENT 'NULL = unlimited',
  `delivery_days`  INT NOT NULL DEFAULT 7,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number`   VARCHAR(20) NOT NULL UNIQUE COMMENT 'e.g. ORD-20260001',
  `student_id`     INT UNSIGNED NOT NULL,
  `academic_id`    INT UNSIGNED DEFAULT NULL COMMENT 'Assigned after acceptance',
  `service_id`     INT UNSIGNED NOT NULL,
  `package_id`     INT UNSIGNED DEFAULT NULL,
  `specialty`      VARCHAR(200) DEFAULT NULL COMMENT 'Specific sub-specialty',
  `academic_level` ENUM('بكالوريوس','ماجستير','دكتوراه') DEFAULT NULL,
  `language`       ENUM('العربية','الإنجليزية') DEFAULT 'العربية',
  `description`    TEXT DEFAULT NULL COMMENT 'Student notes / requirements',
  `deadline`       DATE DEFAULT NULL,
  `amount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`         ENUM('new','accepted','in_progress','revision','completed','cancelled') NOT NULL DEFAULT 'new',
  `admin_notes`    TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  INDEX `idx_student`  (`student_id`),
  INDEX `idx_academic` (`academic_id`),
  INDEX `idx_status`   (`status`),
  CONSTRAINT `fk_ord_student`  FOREIGN KEY (`student_id`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT,
  CONSTRAINT `fk_ord_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_service`  FOREIGN KEY (`service_id`)  REFERENCES `services`(`id`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_ord_package`  FOREIGN KEY (`package_id`)  REFERENCES `packages`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7.1. ORDER ASSIGNMENTS (إسناد الطلبات للأكاديميين)
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_assignments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- ============================================================
-- 8. ORDER ATTACHMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_attachments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED NOT NULL,
  `file_name`     VARCHAR(255) NOT NULL COMMENT 'Original file name',
  `file_path`     VARCHAR(500) NOT NULL COMMENT 'Server path relative to root',
  `file_type`     VARCHAR(100) DEFAULT NULL COMMENT 'MIME type',
  `file_size`     INT UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
  `uploaded_by`   ENUM('student','academic','admin') NOT NULL DEFAULT 'student',
  `uploaded_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order` (`order_id`),
  CONSTRAINT `fk_att_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 9. CONVERSATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `conversations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED NOT NULL UNIQUE COMMENT 'One conversation per order',
  `student_id`   INT UNSIGNED NOT NULL,
  `academic_id`  INT UNSIGNED NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_student`  (`student_id`),
  INDEX `idx_academic` (`academic_id`),
  CONSTRAINT `fk_conv_order`    FOREIGN KEY (`order_id`)    REFERENCES `orders`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_conv_student`  FOREIGN KEY (`student_id`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT,
  CONSTRAINT `fk_conv_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 10. MESSAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id`  INT UNSIGNED NOT NULL,
  `sender_id`        INT UNSIGNED NOT NULL COMMENT 'ID from users or academics table',
  `sender_type`      ENUM('student','academic','admin') NOT NULL,
  `content`          TEXT NOT NULL,
  `is_read`          TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_conv`    (`conversation_id`),
  INDEX `idx_sender`  (`sender_id`, `sender_type`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`)
    REFERENCES `conversations`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 11. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number`   VARCHAR(25) NOT NULL UNIQUE COMMENT 'e.g. PAY-20260001',
  `order_id`         INT UNSIGNED NOT NULL,
  `student_id`       INT UNSIGNED NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL COMMENT 'Total paid by student',
  `platform_fee`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '15% commission',
  `academic_net`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'amount - platform_fee',
  `method`           ENUM('credit_card','bank_transfer','apple_pay','wallet') NOT NULL DEFAULT 'credit_card',
  `status`           ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref`  VARCHAR(255) DEFAULT NULL COMMENT 'Gateway reference',
  `coupon_code`      VARCHAR(50) DEFAULT NULL,
  `discount`         DECIMAL(10,2) DEFAULT 0.00,
  `paid_at`          DATETIME DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_number` (`payment_number`),
  INDEX `idx_order`    (`order_id`),
  INDEX `idx_student`  (`student_id`),
  INDEX `idx_status`   (`status`),
  CONSTRAINT `fk_pay_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 12. REVIEWS
-- ============================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED NOT NULL UNIQUE COMMENT 'One review per order',
  `student_id`   INT UNSIGNED NOT NULL,
  `academic_id`  INT UNSIGNED NOT NULL,
  `rating`       TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 to 5',
  `comment`      TEXT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_academic` (`academic_id`),
  INDEX `idx_student`  (`student_id`),
  CONSTRAINT `fk_rev_order`    FOREIGN KEY (`order_id`)    REFERENCES `orders`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_rev_student`  FOREIGN KEY (`student_id`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT,
  CONSTRAINT `fk_rev_academic` FOREIGN KEY (`academic_id`) REFERENCES `academics`(`id`) ON DELETE RESTRICT,
  CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 12b. PACKAGE SUBSCRIPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `package_subscriptions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `package_id`  INT UNSIGNED NOT NULL,
  `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME NOT NULL,
  `status`      ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_package` (`package_id`),
  INDEX `idx_status`  (`status`),
  CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ps_package` FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 13. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL COMMENT 'ID from users OR academics table',
  `user_type`  ENUM('student','academic','admin') NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `icon`       VARCHAR(10) DEFAULT '🔔',
  `link`       VARCHAR(500) DEFAULT NULL COMMENT 'Optional redirect URL',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user`    (`user_id`, `user_type`),
  INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- RE-ENABLE FOREIGN KEY CHECKS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- SEED DATA - بيانات تجريبية
-- ============================================================

-- Admin user (password: Admin@123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`, `avatar_initials`) VALUES
('المدير العام', 'admin@eduroad.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0500000000', 'admin', 'active', 'مد');

-- Sample students (password: Student@123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`, `avatar_initials`) VALUES
('أحمد محمد الزهراني', 'ahmed@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0501234567', 'student', 'active', 'أح'),
('سارة عبدالله الغامدي', 'sara@student.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0507654321', 'student', 'active', 'سع'),
('خالد ناصر العتيبي',   'khalid@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0509876543', 'student', 'active', 'خن');

-- Sample academics (password: Academic@123)
INSERT INTO `academics` (`name`, `email`, `password`, `phone`, `specialty`, `degree`, `university`, `bio`, `avatar_initials`, `starting_price`, `rating`, `total_reviews`, `total_orders`, `status`, `availability`) VALUES
('د. محمد علي السعيد',  'dr.mohammed@academic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0511111111', 'الرياضيات والإحصاء', 'دكتوراه', 'جامعة الملك سعود',    'أستاذ متميز في الرياضيات والإحصاء مع خبرة أكثر من 15 عاماً.', 'مع', 349.00, 4.90, 128, 245, 'approved', 'available'),
('أ. فاطمة يوسف',       'fatima@academic.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0522222222', 'اللغة العربية',      'ماجستير', 'جامعة الملك عبدالعزيز','متخصصة في التدقيق اللغوي والترجمة الأكاديمية.', 'في', 199.00, 4.70, 88,  157, 'approved', 'available'),
('د. عبدالرحمن خالد',   'abdulrahman@academic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0533333333', 'علوم الحاسب',        'دكتوراه', 'جامعة الملك فهد',      'خبير في البرمجة وعلوم الحاسب والذكاء الاصطناعي.',            'عخ', 499.00, 4.80, 67,  103, 'pending',  'available');

-- Services
INSERT INTO `services` (`name`, `icon`, `description`, `is_active`) VALUES
('الأبحاث والدراسات',      '🔬', 'إعداد الأبحاث العلمية والدراسات الأكاديمية بجميع مستوياتها.',   1),
('الرسائل الجامعية',       '📜', 'المساعدة في كتابة وتنسيق الرسائل والأطروحات الجامعية.',           1),
('الترجمة الأكاديمية',     '🌐', 'ترجمة الأبحاث والمستندات الأكاديمية بين العربية والإنجليزية.',    1),
('المراجعة والتدقيق',      '✏️', 'تدقيق لغوي ونحوي وأكاديمي شامل للأبحاث والمقالات.',             1),
('التحليل الإحصائي',       '📊', 'تحليل البيانات باستخدام SPSS وR وغيرها من الأدوات.',              1),
('العروض التقديمية',       '📋', 'إعداد عروض تقديمية احترافية للمناقشات والمؤتمرات.',               0),
('المناهج التعليمية',      '📚', 'تصميم مناهج وخطط تعليمية متكاملة.',                              1),
('برمجة المشاريع',         '💻', 'تطوير مشاريع برمجية وتطبيقات للدراسة والبحث.',                   1),
('التصميم الأكاديمي',      '🎨', 'تصميم إنفوجرافيك وتصورات بيانية لدعم الأبحاث.',                  1),
('الاستشارات التعليمية',   '💬', 'جلسات استشارية متخصصة في المسار الأكاديمي.',                     1),
('النشر العلمي',           '📰', 'المساعدة في نشر الأبحاث في مجلات محكمة ISI/Scopus.',            0),
('التدريب الأكاديمي',      '🎓', 'برامج تدريبية لتطوير المهارات الأكاديمية والبحثية.',               1);

-- Packages
INSERT INTO `packages` (`name`, `price`, `color`, `features_json`, `max_tasks`, `delivery_days`, `is_active`) VALUES
('البداية',  149.00, '#64748b', '["مهمة واحدة", "مراجعة بسيطة", "تسليم خلال 7 أيام"]',                                               1,  7, 1),
('التطوير',  349.00, '#3b82f6', '["3 مهام", "مراجعة متقدمة", "تسليم خلال 5 أيام", "دعم فني"]',                                       3,  5, 1),
('التحليل',  649.00, '#8b5cf6', '["5 مهام", "تحليل شامل", "تسليم خلال 3 أيام", "دعم 24/7"]',                                        5,  3, 1),
('التسليم',  999.00, '#f59e0b', '["10 مهام", "خدمة متميزة", "تسليم خلال 48 ساعة", "مدير خاص"]',                                     10, 2, 1),
('النخبة',  1999.00, '#ef4444', '["مهام غير محدودة", "خدمة VIP", "تسليم خلال 24 ساعة", "فريق متخصص", "ضمان النتائج"]',              NULL, 1, 1);

-- Academic qualifications for Dr. Mohammed
INSERT INTO `academic_qualifications` (`academic_id`, `level`, `field`, `university`, `country`, `graduation_year`, `verified`) VALUES
(1, 'بكالوريوس', 'رياضيات وإحصاء',         'جامعة الملك سعود',        'السعودية', 2005, 1),
(1, 'ماجستير',   'إحصاء تطبيقي',            'جامعة الملك عبدالعزيز',   'السعودية', 2008, 1),
(1, 'دكتوراه',   'الإحصاء الحيوي والتحليل الكمي', 'جامعة أوكلاند',    'نيوزيلندا', 2013, 1);

-- Academic services pivot
INSERT INTO `academic_services` (`academic_id`, `service_id`, `custom_price`) VALUES
(1, 1, NULL), (1, 5, NULL), (1, 2, NULL),
(2, 3, NULL), (2, 4, NULL), (2, 2, NULL),
(3, 8, NULL), (3, 1, NULL), (3, 5, NULL);

-- Sample orders
INSERT INTO `orders` (`order_number`, `student_id`, `academic_id`, `service_id`, `package_id`, `specialty`, `academic_level`, `language`, `description`, `deadline`, `amount`, `status`) VALUES
('ORD-20260001', 2, 1, 5, 2, 'إدارة الموارد البشرية', 'ماجستير',    'العربية',    'أحتاج تحليل بيانات استبيان لرسالة الماجستير باستخدام SPSS',  '2026-07-01', 349.00, 'completed'),
('ORD-20260002', 3, 2, 3, 3, 'القانون التجاري',        'دكتوراه',    'الإنجليزية', 'ترجمة ورقة بحثية من العربية للإنجليزية - 25 صفحة',          '2026-07-15', 649.00, 'in_progress'),
('ORD-20260003', 4, NULL, 1, 5, 'علوم الحاسب',         'دكتوراه',    'العربية',    'كتابة فصل نظري كامل لرسالة دكتوراه في الذكاء الاصطناعي',   '2026-08-01', 1999.00, 'new');

-- Sample payments
INSERT INTO `payments` (`payment_number`, `order_id`, `student_id`, `amount`, `platform_fee`, `academic_net`, `method`, `status`, `paid_at`) VALUES
('PAY-20260001', 1, 2, 349.00, 52.35,  296.65, 'credit_card',    'paid',    NOW()),
('PAY-20260002', 2, 3, 649.00, 97.35,  551.65, 'bank_transfer',  'paid',    NOW()),
('PAY-20260003', 3, 4, 1999.00, 299.85, 1699.15, 'credit_card',  'pending', NULL);

-- Sample conversation & messages for ORD-20260001
INSERT INTO `conversations` (`order_id`, `student_id`, `academic_id`) VALUES (1, 2, 1);

INSERT INTO `messages` (`conversation_id`, `sender_id`, `sender_type`, `content`, `is_read`) VALUES
(1, 2, 'student',  'السلام عليكم دكتور، لقد قمت برفع ملف البيانات للاستبيان وأتمنى البدء في أقرب فرصة.', 1),
(1, 1, 'academic', 'وعليكم السلام ورحمة الله وبركاته. تم استلام الملف وجاري الاطلاع عليه وتقييم العمل.', 1),
(1, 1, 'academic', 'هل تفضل تنسيق جداول المخرجات بصيغة APA؟', 1),
(1, 2, 'student',  'نعم من فضلك، صيغة APA المطلوبة في الجامعة.', 0);

-- Sample review
INSERT INTO `reviews` (`order_id`, `student_id`, `academic_id`, `rating`, `comment`) VALUES
(1, 2, 1, 5, 'عمل ممتاز ودقيق جداً، الدكتور محمد متعاون ويشرح المخرجات بوضوح. أنصح الجميع به!');
