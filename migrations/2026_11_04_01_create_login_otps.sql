-- Migration: Create login_otps table for two-factor authentication and email OTP verification
CREATE TABLE IF NOT EXISTS `login_otps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `user_type` enum('student','academic','admin') NOT NULL DEFAULT 'student',
  `user_id` int(10) unsigned DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lookup` (`email`, `user_type`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

