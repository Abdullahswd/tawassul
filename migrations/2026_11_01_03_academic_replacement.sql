-- Migration: Add replaced status and replacement metadata to order_assignments
-- Date: 2026-11-01

ALTER TABLE `order_assignments`
MODIFY COLUMN `status` ENUM('assigned','accepted','rejected','completed','replaced') NOT NULL DEFAULT 'assigned',
ADD COLUMN `replacement_reason` VARCHAR(100) NULL DEFAULT NULL AFTER `completed_at`,
ADD COLUMN `replacement_note` TEXT NULL DEFAULT NULL AFTER `replacement_reason`,
ADD COLUMN `replaced_at` DATETIME NULL DEFAULT NULL AFTER `replacement_note`,
ADD COLUMN `replaced_by_academic_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `replaced_at`;

-- Drop unique constraint to allow historical records of replaced assignments
ALTER TABLE `order_assignments`
DROP INDEX `uq_order_academic`,
ADD INDEX `idx_order_academic` (`order_id`, `academic_id`);
