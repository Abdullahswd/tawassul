-- ============================================================================
-- Migration: 2026_10_31_02_multi_academics_support.sql
-- Support multiple academics per order, live pricing multiplier, and completion check
-- ============================================================================

ALTER TABLE `orders` 
ADD COLUMN IF NOT EXISTS `academics_count` INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `deadline`;

ALTER TABLE `order_assignments` 
MODIFY COLUMN `status` ENUM('assigned','accepted','rejected','completed') NOT NULL DEFAULT 'assigned';

ALTER TABLE `order_assignments` 
ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL AFTER `response_at`;

