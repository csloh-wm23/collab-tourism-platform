USE jomcommunicate;

ALTER TABLE business_interactions
  ADD COLUMN public_token CHAR(32) NULL UNIQUE AFTER question_label,
  ADD COLUMN status ENUM('new','in_progress','answered','archived') NOT NULL DEFAULT 'new' AFTER public_token,
  ADD COLUMN reply_text VARCHAR(1000) NULL AFTER status,
  ADD COLUMN answered_at DATETIME NULL AFTER reply_text,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD INDEX idx_business_inbox (business_id, status, created_at);
