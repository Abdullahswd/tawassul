-- ============================================================
-- Migration: Add 'suspended' to academics.status ENUM
-- Date: 2026-11-01
-- ============================================================

ALTER TABLE `academics` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'suspended') 
NOT NULL DEFAULT 'pending';
