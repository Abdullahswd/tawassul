-- Migration: Add receipt_path, transaction_ref, and processed_at to refund_requests
-- Date: 2026-11-01

ALTER TABLE `refund_requests`
ADD COLUMN `receipt_path` VARCHAR(255) NULL DEFAULT NULL AFTER `admin_note`,
ADD COLUMN `transaction_ref` VARCHAR(100) NULL DEFAULT NULL AFTER `receipt_path`,
ADD COLUMN `processed_at` DATETIME NULL DEFAULT NULL AFTER `transaction_ref`;

