-- ============================================================
-- Migration: Create order_team_messages table for internal academics chat
-- Date: 2026-11-01
-- Description:
--   Enables a private, internal chat between academics assigned to the same order
--   (and admin). The student has NO access or visibility to this chat.
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

