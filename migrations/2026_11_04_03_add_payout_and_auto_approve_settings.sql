-- Add settings for auto approval and payout eligibility if they don't exist
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES 
('withdrawal_eligibility_days', '14', 'financial'),
('auto_approve_hours', '72', 'financial'),
('payout_processing_time', '3', 'financial'),
('min_withdrawal', '200', 'financial')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

