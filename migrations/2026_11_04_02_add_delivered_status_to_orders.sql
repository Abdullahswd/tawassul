ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('awaiting_payment','pending_assignment','assigned','new','accepted','in_progress','delivered','revision','completed','cancelled','cancellation_requested') NOT NULL DEFAULT 'awaiting_payment';

